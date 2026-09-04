<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentContractModel;
use App\Models\Institution\RegularEmploymentIncomeModel;
use App\Models\Institution\WorkplaceSizePeriodModel;
use App\Services\System\StatutoryStandardResolver;
use PDO;

class RegularEmploymentIncomeCalculationService
{
    public const VERSION = 'REGULAR_INCOME_V4_CARDS';
    public const TRACE_COLUMNS = [
        'application_status_code',
        'calculation_basis_amount',
        'calculation_rate',
        'calculation_before_rounding',
        'rounding_method_code',
        'rounding_unit',
        'statutory_standard_id',
        'social_insurance_coverage_id',
        'workplace_size_period_id',
    ];

    private StatutoryStandardResolver $standards;
    private RegularEmploymentIncomeModel $incomeModel;
    private IncomeInsurancePremiumCalculationService $insurancePremium;

    public function __construct(private readonly PDO $db)
    {
        $this->standards = new StatutoryStandardResolver($db);
        $this->incomeModel = new RegularEmploymentIncomeModel($db);
        $this->insurancePremium = new IncomeInsurancePremiumCalculationService();
    }

    public function preview(string $month, string $taxReferenceDate, array $employeeInputs, string $actor): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $taxReferenceDate)) {
            throw new \InvalidArgumentException('귀속연월과 세액 기준일을 확인해 주세요.');
        }

        [$employeeIds, $dependentCounts, $snapshotInputs, $manualPayInputs, $manualDeductionInputs, $insuranceOverrideInputs, $institutionOverrideInputs]
            = $this->normalizeInputs($employeeInputs);
        if ($employeeIds === []) {
            throw new \InvalidArgumentException('계산할 직원을 선택해 주세요.');
        }

        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $this->hydrateDependentCounts($employeeIds, $month, $dependentCounts);
        foreach ($employeeIds as $employeeId) {
            if (!isset($dependentCounts[$employeeId])) {
                $dependentCounts[$employeeId] = 1;
            }
        }
        $priorInsuranceSettings=$this->incomeModel->latestInsuranceOverrideLines($employeeIds,$month);
        [$validByEmployee, $contracts] = $this->contractContext($employeeIds, $from, $to);

        $insurance = (new SocialInsuranceService($this->db))->batchForMonth($employeeIds, $month);
        $coverages = [];
        foreach ($insurance['coverages'] as $row) {
            $coverages[$row['employee_id']][$row['insurance_type_code']][] = $row;
        }
        $bases = [];
        foreach ($insurance['bases'] as $row) {
            $bases[$row['coverage_id']][$row['basis_type_code']][] = $row;
        }

        [$standards, $standardErrors] = $this->resolveStandards($to, $taxReferenceDate);
        $companyId = (string) $this->db->query('SELECT id FROM system_company ORDER BY created_at, id LIMIT 1')->fetchColumn();
        $workplaceSizePeriod = $companyId !== ''
            ? (new WorkplaceSizePeriodModel($this->db))->resolve($companyId, WorkplaceSizePeriodService::PURPOSE_EMPLOYMENT_INSURANCE_VOCATIONAL, $to)
            : null;
        $supportedDependentCounts = [];
        foreach ((array) ($standards['EMPLOYMENT_INCOME_TAX_TABLE']['value_data']['table']['dependent_counts'] ?? []) as $dependentCount) {
            if (filter_var($dependentCount, FILTER_VALIDATE_INT) !== false && (int) $dependentCount >= 1) {
                $supportedDependentCounts[] = (int) $dependentCount;
            }
        }
        $supportedDependentCounts = array_values(array_unique($supportedDependentCounts));
        sort($supportedDependentCounts, SORT_NUMERIC);
        $results = [];
        $days = (int) date('t', strtotime($from));
        $payPolicy = new RegularEmploymentIncomePayLineService(new PayComponentService($this->db));

        foreach ($employeeIds as $employeeId) {
            $messages = [];
            $notices = [];
            $lines = [];
            $references = [];

            foreach ($contracts[$employeeId] ?? [] as $contract) {
                $start = max(strtotime($from), strtotime($contract['contract_start_date']));
                $end = min(strtotime($to), strtotime($contract['contract_end_date'] ?: $to));
                $coveredDays = max(0, (int) (($end - $start) / 86400) + 1);
                $amount = round((float) $contract['amount'] * $coveredDays / $days, 2);
                $key = 'PAY:' . $contract['component_code'];
                if (!isset($lines[$key])) {
                    $lines[$key] = $payPolicy->contractLine(
                        $this->line('PAY', $contract['component_code'], $contract['component_name'], 0, strtoupper($contract['tax_type']) === 'TAXABLE' ? 1 : 0),
                        (string) $contract['contract_id']
                    );
                }
                $lines[$key]['calculated_amount'] += $amount;
                $lines[$key]['final_amount'] += $amount;
                $references[] = $this->reference(
                    'EMPLOYMENT_CONTRACT',
                    'institution_employment_contracts',
                    $contract['contract_id'],
                    null,
                    date('Y-m-d', $start),
                    date('Y-m-d', $end),
                    $contract['component_code'],
                    $coveredDays . '일 적용'
                );
            }
            foreach ($manualPayInputs[$employeeId] ?? [] as $index => $manual) {
                $normalized = $payPolicy->normalizeManualLine($manual, $actor, $taxReferenceDate);
                $lines['PAY:MANUAL:' . $index . ':' . $normalized['item_code']] = $normalized;
            }

            if (count($validByEmployee[$employeeId] ?? []) > 1) {
                $messages[] = '귀속월에 적용할 유효 근로계약 후보가 여러 건입니다.';
            } elseif (!isset($contracts[$employeeId])) {
                $messages[] = '귀속월에 적용할 승인 근로계약이 없습니다.';
            }

            $payPolicy->finalPayComposition(array_values($lines));
            $payTotals = $payPolicy->totals(array_values($lines));
            $gross = $payTotals['gross_amount'];
            $taxable = $payTotals['taxable_amount'];
            $deduction = 0.0;
            $burden = 0.0;
            $healthPremium = null;
            $resolvedSnapshots = $snapshotInputs[$employeeId] ?? [];
            $basisResolutions = [
                'taxable_monthly_salary_amount' => [
                    'amount' => round($taxable, 2),
                    'status_code' => 'AUTO_CALCULATED',
                    'source_code' => 'PAY_ITEM_TAX_CLASSIFICATION',
                    'message' => '증액·감액을 반영한 지급항목의 과세·비과세 구분과 최종금액으로 자동 산출',
                ],
            ];

            $contractPolicy = count($validByEmployee[$employeeId] ?? []) === 1
                ? $validByEmployee[$employeeId][0]
                : null;
            $employmentPolicy = $contractPolicy['employment_insurance_application_status_code'] ?? null;
            $industrialPolicy = $contractPolicy['industrial_accident_application_status_code'] ?? null;
            if ($contractPolicy !== null && $employmentPolicy === 'EXCLUDED') {
                $coverages[$employeeId]['EMPLOYMENT_INSURANCE'] = [[
                    'id'=>'','employee_id'=>$employeeId,
                    'insurance_type_code'=>'EMPLOYMENT_INSURANCE','coverage_status_code'=>'EXCLUDED',
                    'effective_from'=>$contractPolicy['contract_start_date'],'effective_to'=>$contractPolicy['contract_end_date'],
                    'confirmed_at'=>$contractPolicy['approved_at'] ?? $contractPolicy['updated_at'] ?? $from,
                    'exclusion_reason'=>$contractPolicy['employment_insurance_exclusion_reason'] ?? '근로계약상 적용 제외',
                ]];
            } elseif ($contractPolicy !== null && $employmentPolicy === null) {
                $messages[] = '유효한 승인 근로계약의 고용보험 적용정보를 확인해 주세요.';
            }
            if ($contractPolicy !== null && $industrialPolicy === null) {
                $messages[] = '유효한 승인 근로계약의 산재보험 적용정보를 확인해 주세요.';
            }

            foreach (['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'EMPLOYMENT_INSURANCE'] as $insuranceType) {
                $premium = $this->calculateInsurance(
                    $insuranceType,
                    $employeeId,
                    $month,
                    $from,
                    $to,
                    $gross,
                    $taxable,
                    $coverages,
                    $bases,
                    $snapshotInputs,
                    $standards,
                    $standardErrors,
                    $workplaceSizePeriod,
                    $lines,
                    $messages,
                    $notices,
                    $references,
                    $basisResolutions,
                    $resolvedSnapshots,
                    $deduction,
                    $burden
                );
                if ($insuranceType === 'HEALTH_INSURANCE') {
                    $healthPremium = $premium;
                }
            }

            $this->calculateLongTermCare(
                $employeeId,
                $coverages,
                $standards,
                $standardErrors,
                $healthPremium,
                $lines,
                $messages,
                $notices,
                $references,
                $deduction,
                $burden
            );
            $this->calculateTaxes(
                $month,
                $taxable,
                $dependentCounts[$employeeId] ?? null,
                $standards,
                $standardErrors,
                $lines,
                $messages,
                $references,
                $deduction
            );

            foreach(['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'] as$insuranceCode){
                $lineKey='DEDUCTION:'.$insuranceCode;if(!isset($lines[$lineKey]))continue;
                if(($lines[$lineKey]['application_status_code']??null)==='EXCLUDED')continue;
                $explicitSetting=$insuranceOverrideInputs[$employeeId][$insuranceCode]??null;
                $setting=$explicitSetting??($priorInsuranceSettings[$employeeId.':'.$insuranceCode]??null);
                if(!$setting)continue;
                $isReset=str_starts_with((string)($setting['source_key']??''),'INSURANCE_OVERRIDE_RESET|');
                if($isReset){if($explicitSetting){$lines[$lineKey]['adjustment_reason']=trim((string)($setting['adjustment_reason']??''))?:null;$lines[$lineKey]['calculation_source_code']='CALCULATED';$lines[$lineKey]['business_source_code']='MANUAL';$lines[$lineKey]['source_reference_id']=$setting['id']??($setting['source_reference_id']??null);$lines[$lineKey]['source_key']='INSURANCE_OVERRIDE_RESET|'.$insuranceCode.'|'.$month;$lines[$lineKey]['processed_at']=date('Y-m-d H:i:s');$lines[$lineKey]['processed_by']=$actor;}continue;}
                $automatic=(float)$lines[$lineKey]['calculated_amount'];$applied=round((float)($setting['final_amount']??$automatic),2);
                $lines[$lineKey]['final_amount']=$applied;$lines[$lineKey]['adjustment_amount']=round($applied-$automatic,2);
                $lines[$lineKey]['adjustment_reason']=trim((string)($setting['adjustment_reason']??''))?:null;
                $lines[$lineKey]['calculation_source_code']='MANUAL';$lines[$lineKey]['business_source_code']='MANUAL';
                $lines[$lineKey]['source_reference_id']=$setting['id']??($setting['source_reference_id']??null);
                $originMonth=(string)($setting['income_year_month']??'');
                $lines[$lineKey]['source_key']='INSURANCE_OVERRIDE|'.$insuranceCode.'|'.($originMonth?:$month);
                $lines[$lineKey]['processed_at']=date('Y-m-d H:i:s');$lines[$lineKey]['processed_by']=$actor;
                if($originMonth!==''){$lines[$lineKey]['override_inherited']=true;$lines[$lineKey]['override_origin_month']=$originMonth;}
                $deduction+=round($applied-$automatic,2);
            }

            foreach ($manualDeductionInputs[$employeeId] ?? [] as $manualDeduction) {
                $normalized = (new RegularEmploymentIncomeDeductionLineService())->normalizeSettlement($manualDeduction, $actor);
                $lineKey = 'DEDUCTION:' . $normalized['item_code'];
                if (!isset($lines[$lineKey])) {
                    $lines[$lineKey] = $normalized;
                    $deduction += (new RegularEmploymentIncomeDeductionLineService())->signedAmount($normalized);
                }
            }
            if (!isset($lines['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']) && $industrialPolicy === 'EXCLUDED') {
                $lines['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE'] = $this->excludedLine(
                    'EMPLOYER_BURDEN','INDUSTRIAL_ACCIDENT_INSURANCE','산재보험 사용자부담',
                    null,
                    (string) ($contractPolicy['industrial_accident_exclusion_reason'] ?? '근로계약상 적용 제외')
                );
            } elseif (!isset($lines['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']) && $industrialPolicy === 'APPLICABLE') {
                $this->calculateIndustrialAccident(
                    $taxable,
                    $standards['INDUSTRIAL_ACCIDENT'] ?? null,
                    $standardErrors['INDUSTRIAL_ACCIDENT'] ?? null,
                    $lines,
                    $messages,
                    $references,
                    $burden
                );
            } elseif (!isset($lines['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE'])) {
                $lines['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE'] = $this->blockedLine(
                    'EMPLOYER_BURDEN',
                    'INDUSTRIAL_ACCIDENT_INSURANCE',
                    '산재보험 사용자부담',
                    '공식 업종·보험관계가 연결되지 않아 법정기준 확인이 필요합니다.'
                );
                $notices[] = '산재보험 사용자부담은 공식 업종·보험관계가 연결될 때까지 미확정입니다.';
            }
            foreach ($institutionOverrideInputs[$employeeId] ?? [] as $lineKey => $setting) {
                if (!isset($lines[$lineKey])) continue;
                $automatic = $lines[$lineKey]['calculated_amount'] ?? null;
                $applied = round((float) ($setting['final_amount'] ?? 0), 2);
                if ($applied < 0) throw new \InvalidArgumentException('기관별 계산 적용금액은 0원 이상이어야 합니다.');
                $reason = trim((string) ($setting['adjustment_reason'] ?? ''));
                $changed = $automatic === null ? abs($applied) >= 0.01 : abs($applied - (float) $automatic) >= 0.01;
                if ($changed && $reason === '') throw new \InvalidArgumentException('자동계산액과 다른 적용금액에는 적용사유가 필요합니다.');
                $previous = (float) ($lines[$lineKey]['final_amount'] ?? 0);
                $lines[$lineKey]['final_amount'] = $applied;
                $lines[$lineKey]['adjustment_amount'] = $automatic === null ? 0.0 : round($applied - (float) $automatic, 2);
                $lines[$lineKey]['adjustment_reason'] = $reason !== '' ? $reason : null;
                $lines[$lineKey]['calculation_source_code'] = $changed ? 'MANUAL' : 'CALCULATED';
                $lines[$lineKey]['business_source_code'] = 'MANUAL';
                $lines[$lineKey]['source_key'] = 'INSTITUTION_OVERRIDE|' . $lineKey . '|' . $month;
                $lines[$lineKey]['processed_at'] = date('Y-m-d H:i:s');
                $lines[$lineKey]['processed_by'] = $actor;
                if (str_starts_with($lineKey, 'DEDUCTION:')) $deduction += round($applied - $previous, 2);
                if (str_starts_with($lineKey, 'EMPLOYER_BURDEN:')) $burden += round($applied - $previous, 2);
            }
            $processedAt = date('Y-m-d H:i:s');
            foreach ($lines as &$line) {
                if (($line['item_type_code'] ?? '') !== 'EMPLOYER_BURDEN'
                    || ($line['application_status_code'] ?? null) !== 'APPLICABLE') {
                    continue;
                }
                $line['business_source_code'] ??= (string) ($line['calculation_source_code'] ?? 'CALCULATED');
                $line['source_reference_id'] ??= $line['statutory_standard_id'] ?? null;
                $line['processed_at'] ??= $processedAt;
                $line['processed_by'] ??= $actor;
            }
            unset($line);
            $coverageRows = [];
            foreach ((array) ($coverages[$employeeId] ?? []) as $rows) {
                foreach ((array) $rows as $coverageRow) $coverageRows[] = $coverageRow;
            }
            $lines = (new RegularEmploymentIncomeInsuranceProjectionService())->project(
                array_values($lines),
                $contractPolicy,
                $coverageRows,
                date('Y-m-d H:i:s')
            );
            $unresolved = count(array_filter(
                $lines,
                fn (array $line): bool => $line['item_type_code'] === 'DEDUCTION'
                    && ($line['calculation_status_code'] ?? 'CALCULATED') !== 'CALCULATED'
            ));
            $deductionAmounts = [];
            foreach ($lines as $line) {
                if (($line['item_type_code'] ?? '') !== 'DEDUCTION') continue;
                $deductionAmounts[(string) ($line['item_code'] ?? '')] = round((float) ($line['final_amount'] ?? 0), 2);
            }
            $results[] = ['employee_id' => $employeeId] + $resolvedSnapshots + [
                'dependent_count_snapshot' => $dependentCounts[$employeeId] ?? null,
                'supported_dependent_counts' => $supportedDependentCounts,
                'contract_amount' => $payTotals['contract_amount'],
                'increase_amount' => $payTotals['increase_amount'],
                'decrease_amount' => $payTotals['decrease_amount'],
                'basis_resolutions' => $basisResolutions,
                'calculation_status_code' => $messages === [] ? 'CALCULATED' : 'NEEDS_CONFIRMATION',
                'calculation_message' => implode(' ', array_unique($messages)),
                'calculation_notice' => implode(' ', array_unique($notices)),
                'calculation_version' => self::VERSION,
                'gross_amount' => round($gross, 2),
                'taxable_amount' => round($taxable, 2),
                'non_taxable_amount' => round($gross - $taxable, 2),
                'national_pension_amount' => $deductionAmounts['NATIONAL_PENSION'] ?? 0.0,
                'health_insurance_amount' => $deductionAmounts['HEALTH_INSURANCE'] ?? 0.0,
                'long_term_care_amount' => $deductionAmounts['LONG_TERM_CARE'] ?? ($deductionAmounts['LONG_TERM_CARE_INSURANCE'] ?? 0.0),
                'employment_insurance_amount' => $deductionAmounts['EMPLOYMENT_INSURANCE'] ?? 0.0,
                'income_tax_amount' => $deductionAmounts['EMPLOYMENT_INCOME_TAX'] ?? 0.0,
                'local_income_tax_amount' => $deductionAmounts['LOCAL_INCOME_TAX'] ?? 0.0,
                'other_deduction_amount' => $deductionAmounts['OTHER_DEDUCTION'] ?? 0.0,
                'confirmed_deduction_amount' => round($deduction, 2),
                'unresolved_deduction_count' => $unresolved,
                'deduction_amount' => $unresolved ? null : round($deduction, 2),
                'net_payment_amount' => $unresolved ? null : round($gross - $deduction, 2),
                'employer_burden_amount' => round($burden, 2),
                'line_items' => array_values($lines),
                'calculation_bases' => $references,
            ];
        }

        return [
            'results' => $results,
            'readiness' => array_filter($results, fn (array $row): bool => $row['calculation_status_code'] !== 'CALCULATED') ? 'BLOCKED' : 'READY',
            'calculation_version' => self::VERSION,
        ];
    }

    private function normalizeInputs(array $inputs): array
    {
        $ids = [];
        $dependents = [];
        $snapshots = [];
        $manual = [];
        $manualDeductions = [];$insuranceOverrides=[];$institutionOverrides=[];
        foreach ($inputs as $input) {
            $employeeId = is_array($input) ? trim((string) ($input['employee_id'] ?? '')) : trim((string) $input);
            if ($employeeId === '') {
                continue;
            }
            $ids[] = $employeeId;
            if (!is_array($input)) {
                continue;
            }
            if (array_key_exists('dependent_count_snapshot', $input)
                && $input['dependent_count_snapshot'] !== ''
                && $input['dependent_count_snapshot'] !== null) {
                $dependents[$employeeId] = (int) $input['dependent_count_snapshot'];
            }
            $manual[$employeeId] = array_values(array_filter(
                (array) ($input['pay_line_items'] ?? []),
                fn (array $line): bool => in_array(strtoupper((string) ($line['pay_effect_code'] ?? '')), ['INCREASE', 'DECREASE'], true)
            ));
            $manualDeductions[$employeeId] = array_values(array_filter(
                (array) ($input['deduction_line_items'] ?? []),
                fn (array $line): bool => ($line['item_type_code'] ?? '') === 'DEDUCTION'
                    && (str_starts_with((string) ($line['source_key'] ?? ''), 'SETTLEMENT|') || isset($line['settlement_parent_code']))
            ));
            foreach((array)($input['insurance_override_line_items']??[])as$line){$code=(string)($line['item_code']??'');if(in_array($code,['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'],true))$insuranceOverrides[$employeeId][$code]=$line;}
            foreach ((array) ($input['institution_override_line_items'] ?? []) as $line) {
                $type = strtoupper(trim((string) ($line['item_type_code'] ?? '')));
                $code = strtoupper(trim((string) ($line['item_code'] ?? '')));
                if (in_array($type, ['DEDUCTION', 'EMPLOYER_BURDEN'], true) && $code !== '') {
                    $institutionOverrides[$employeeId][$type . ':' . $code] = $line;
                }
            }
            foreach (['national_pension_basis_snapshot', 'health_insurance_basis_snapshot', 'employment_insurance_basis_snapshot'] as $field) {
                if (array_key_exists($field, $input) && $input[$field] !== '' && $input[$field] !== null) {
                    $snapshots[$employeeId][$field] = round((float) str_replace(',', '', (string) $input[$field]), 2);
                }
            }
        }
        return [array_values(array_unique($ids)), $dependents, $snapshots, $manual, $manualDeductions, $insuranceOverrides, $institutionOverrides];
    }

    private function calculateInsurance(
        string $type,
        string $employeeId,
        string $month,
        string $from,
        string $to,
        float $gross,
        float $taxable,
        array $coverages,
        array $bases,
        array $snapshotInputs,
        array $standards,
        array $standardErrors,
        ?array $workplaceSizePeriod,
        array &$lines,
        array &$messages,
        array &$notices,
        array &$references,
        array &$basisResolutions,
        array &$resolvedSnapshots,
        float &$deduction,
        float &$burden
    ): ?float {
        $name = $this->name($type);
        $snapshotField = [
            'NATIONAL_PENSION' => 'national_pension_basis_snapshot',
            'HEALTH_INSURANCE' => 'health_insurance_basis_snapshot',
            'EMPLOYMENT_INSURANCE' => 'employment_insurance_basis_snapshot',
        ][$type];
        $coverageRows = $coverages[$employeeId][$type] ?? [];
        $basisType = [
            'NATIONAL_PENSION' => 'STANDARD_MONTHLY_INCOME',
            'HEALTH_INSURANCE' => 'MONTHLY_REMUNERATION',
            'EMPLOYMENT_INSURANCE' => 'INSURABLE_REMUNERATION',
        ][$type];

        $base = $snapshotInputs[$employeeId][$snapshotField] ?? null;
        $sourceCode = null;
        if ($base !== null) {
            $base = (float) $base;
            $sourceCode = 'PAYROLL_SNAPSHOT';
        }
        $coverage = count($coverageRows) === 1 ? $coverageRows[0] : null;
        if ($coverage && $coverage['confirmed_at'] && $coverage['coverage_status_code'] === 'EXCLUDED') {
            $reason = trim((string) ($coverage['exclusion_reason'] ?? '')) ?: '확정 Coverage 적용제외';
            $lines['DEDUCTION:' . $type] = $this->excludedLine('DEDUCTION', $type, $name, (string) $coverage['id'], $reason);
            if ($type === 'EMPLOYMENT_INSURANCE') {
                $lines['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE'] = $this->excludedLine(
                    'EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE', '고용보험 회사부담', (string) $coverage['id'], $reason
                );
                $lines['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL'] = $this->excludedLine(
                    'EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE_VOCATIONAL', '고용안정·직업능력개발 부담', (string) $coverage['id'], $reason
                );
            }
            return 0.0;
        }
        if ($base === null && $coverage && $coverage['confirmed_at'] && $coverage['coverage_status_code'] === 'ACQUIRED') {
            $basisRows = $bases[$coverage['id']][$basisType] ?? [];
            if (count($basisRows) === 1 && $basisRows[0]['confirmation_status_code'] === 'CONFIRMED') {
                $base = (float) $basisRows[0]['basis_amount'];
                $sourceCode = 'INSURANCE_ASSESSMENT_BASE';
                $references[] = $this->reference('INSURANCE_COVERAGE', 'institution_social_insurance_coverages', $coverage['id'], null, $coverage['effective_from'], $coverage['effective_to'], $type, $coverage['coverage_status_code']);
                $references[] = $this->reference('INSURANCE_ASSESSMENT_BASE', 'institution_social_insurance_assessment_bases', $basisRows[0]['id'], $basisRows[0]['confirmation_status_code'], $basisRows[0]['effective_from'], $basisRows[0]['effective_to'], $basisType, number_format($base) . '원');
            }
        }

        $standard = $standards[$type] ?? null;
        $policy = is_array($standard) ? ($standard['value_data']['calculation_policy'] ?? []) : [];
        $policyBaseCode = (string) ($policy['base_value_code'] ?? 'PAY_ITEM_FINAL_AMOUNT');
        $fallbackBaseCode = (string) ($policy['automatic_fallback_base_value_code'] ?? 'PAY_ITEM_FINAL_AMOUNT');
        if ($base === null) {
            if ($policyBaseCode === 'INSURABLE_REMUNERATION'
                || $fallbackBaseCode === 'TAXABLE_PAY_ITEM_FINAL_AMOUNT') {
                $base = $taxable;
                $sourceCode = 'TAXABLE_PAY_ITEM_FINAL_AMOUNT';
                $notices[] = $name . ' Coverage/Basis가 없어 법정기준의 지급항목 포함·제외 정책으로 계산기초를 자동 산출했습니다.';
            } else {
                $base = $gross;
                $sourceCode = 'PAY_ITEM_FINAL_AMOUNT';
                $notices[] = $name . ' Coverage/Basis가 없어 지급항목 최종금액으로 계산기초를 자동 산출했습니다.';
            }
        } elseif ($sourceCode === 'PAYROLL_SNAPSHOT') {
            $notices[] = $name . ' 계산기초는 사용자가 확인한 급여 Snapshot을 사용했습니다.';
        }
        $resolvedSnapshots[$snapshotField] = $base;
        $basisResolutions[$snapshotField] = [
            'amount' => $base,
            'status_code' => $sourceCode === 'INSURANCE_ASSESSMENT_BASE' ? 'REFERENCE_CONFIRMED' : ($sourceCode === 'PAYROLL_SNAPSHOT' ? 'USER_CONFIRMED' : 'AUTO_CALCULATED'),
            'source_code' => $sourceCode,
            'message' => $sourceCode === 'INSURANCE_ASSESSMENT_BASE' ? '확정 Coverage/Basis 자동 제안' : ($sourceCode === 'PAYROLL_SNAPSHOT' ? '상용근로소득 Snapshot 사용' : ($sourceCode === 'TAXABLE_PAY_ITEM_FINAL_AMOUNT' ? '비과세 지급항목 제외 보수로 자동 산출' : '지급항목 최종금액으로 자동 산출')),
        ];

        if (isset($standardErrors[$type])) {
            $this->blockDeduction($lines, $messages, $type, $name, $standardErrors[$type]);
            return null;
        }
        $standard = $standards[$type];
        $employeeRate = $standard['value_data']['employee_rate'] ?? null;
        $employerRate = $standard['value_data']['employer_rate'] ?? null;
        if ($employeeRate === null || ($type !== 'EMPLOYMENT_INSURANCE' && $employerRate === null)) {
            $this->blockDeduction($lines, $messages, $type, $name, $name . ' 공식 계산정책이 없습니다.');
            return null;
        }
        if ($type === 'HEALTH_INSURANCE' && !$this->resultLimitsReady($standard['value_data'], $policy)) {
            $this->blockDeduction($lines, $messages, $type, $name, '건강보험 결과한도 적용단계를 확정할 수 없습니다.');
            return null;
        }

        if (($policy['stage'] ?? '') === 'ASSESSMENT_BASE') {
            $base = $this->round($base, $policy);
        }
        if (isset($standard['value_data']['minimum_base_amount']) && $standard['value_data']['minimum_base_amount'] !== '') {
            $base = max($base, (float) $standard['value_data']['minimum_base_amount']);
        }
        if (isset($standard['value_data']['maximum_base_amount']) && $standard['value_data']['maximum_base_amount'] !== '') {
            $base = min($base, (float) $standard['value_data']['maximum_base_amount']);
        }
        $resolvedSnapshots[$snapshotField] = $base;
        $basisResolutions[$snapshotField] = [
            'amount' => $base,
            'status_code' => $sourceCode === 'INSURANCE_ASSESSMENT_BASE' ? 'REFERENCE_CONFIRMED' : ($sourceCode === 'PAYROLL_SNAPSHOT' ? 'USER_CONFIRMED' : 'AUTO_CALCULATED'),
            'source_code' => $sourceCode,
            'message' => match ($sourceCode) {
                'INSURANCE_ASSESSMENT_BASE' => '확정 Coverage/Basis 자동 제안',
                'PAYROLL_SNAPSHOT' => '상용근로소득 Snapshot 사용',
                'TAXABLE_PAY_ITEM_FINAL_AMOUNT' => '비과세 지급항목 제외 보수로 자동 산출',
                default => '지급항목 최종금액으로 자동 산출',
            },
        ];

        if (empty($policy['method'])) {
            $reason = $name . ' 공식 계산정책이 없습니다.';
            $this->blockDeduction($lines, $messages, $type, $name, $reason);
            $lines['DEDUCTION:' . $type]['calculation_rate'] = (float) $employeeRate;
            $lines['DEDUCTION:' . $type]['calculation_basis_amount'] = $base;
            $lines['DEDUCTION:' . $type]['calculation_before_rounding'] = round($base * (float) $employeeRate, 4);
            $lines['DEDUCTION:' . $type]['rounding_method_code'] = null;
            $lines['DEDUCTION:' . $type]['rounding_unit'] = null;
            $lines['DEDUCTION:' . $type]['standard_effective_from'] = $standard['effective_from'];
            $lines['DEDUCTION:' . $type]['standard_effective_to'] = $standard['effective_to'];
            return null;
        }

        $beforeRounding = $base * (float) $employeeRate;
        $employeePremium = $this->finalizePremium($beforeRounding, $policy, $standard['value_data']);
        $lines['DEDUCTION:' . $type] = $this->line('DEDUCTION', $type, $name, $employeePremium);
        $lines['DEDUCTION:' . $type]['calculation_rate'] = (float) $employeeRate;
        $lines['DEDUCTION:' . $type]['application_status_code'] = 'APPLICABLE';
        $lines['DEDUCTION:' . $type]['calculation_basis_amount'] = $base;
        $lines['DEDUCTION:' . $type]['calculation_before_rounding'] = round($beforeRounding, 4);
        $lines['DEDUCTION:' . $type]['rounding_method_code'] = $policy['method'];
        $lines['DEDUCTION:' . $type]['rounding_unit'] = (int) ($policy['discard_below_unit'] ?? 1);
        $lines['DEDUCTION:' . $type]['statutory_standard_id'] = $standard['id'];
        $lines['DEDUCTION:' . $type]['social_insurance_coverage_id'] = $coverage['id'] ?? null;
        $lines['DEDUCTION:' . $type]['minimum_base_amount'] = isset($standard['value_data']['minimum_base_amount']) ? (float) $standard['value_data']['minimum_base_amount'] : null;
        $lines['DEDUCTION:' . $type]['maximum_base_amount'] = isset($standard['value_data']['maximum_base_amount']) ? (float) $standard['value_data']['maximum_base_amount'] : null;
        $lines['DEDUCTION:' . $type]['standard_effective_from'] = $standard['effective_from'];
        $lines['DEDUCTION:' . $type]['standard_effective_to'] = $standard['effective_to'];
        $deduction += $employeePremium;
        if ($type !== 'EMPLOYMENT_INSURANCE') {
            $companyBefore = $base * (float) $employerRate;
            $companyPremium = $this->finalizePremium($companyBefore, $policy, $standard['value_data']);
            $lines['EMPLOYER_BURDEN:' . $type] = $this->line('EMPLOYER_BURDEN', $type, $name . ' 회사부담', $companyPremium);
            $lines['EMPLOYER_BURDEN:' . $type] += [
                'application_status_code'=>'APPLICABLE','calculation_basis_amount'=>$base,
                'calculation_rate'=>(float)$employerRate,'calculation_before_rounding'=>round($companyBefore,4),
                'rounding_method_code'=>$policy['method'],'rounding_unit'=>(int)($policy['discard_below_unit']??1),
                'statutory_standard_id'=>$standard['id'],'social_insurance_coverage_id'=>$coverage['id']??null,
                'workplace_size_period_id'=>null,
            ];
            $burden += $companyPremium;
        } else {
            if (!$workplaceSizePeriod) {
                $messages[] = '확정된 회사규모 기간자료가 없습니다.';
                $lines['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL'] = $this->blockedLine('EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE_VOCATIONAL', '고용안정·직업능력개발 부담', '확정된 회사규모 기간자료가 없습니다.');
                return $employeePremium;
            }
            $employerBefore = $base * (float) $employerRate;
            $employerPremium = $this->finalizePremium($employerBefore, $policy, $standard['value_data']);
            $lines['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE'] = $this->line('EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE', '고용보험 회사부담', $employerPremium) + [
                'application_status_code' => 'APPLICABLE', 'calculation_basis_amount' => $base,
                'calculation_rate' => (float) $employerRate, 'calculation_before_rounding' => round($employerBefore, 4),
                'rounding_method_code' => $policy['method'], 'rounding_unit' => (int) ($policy['discard_below_unit'] ?? 1),
                'statutory_standard_id' => $standard['id'], 'social_insurance_coverage_id' => $coverage['id'] ?? null,
                'workplace_size_period_id' => null,
            ];
            $vocationalRate = (new WorkplaceSizeRateResolver())->resolveAdditionalEmployerRate($workplaceSizePeriod, $standard['value_data']);
            $vocationalBefore = $base * $vocationalRate;
            $vocationalPremium = $this->finalizePremium($vocationalBefore, $policy, $standard['value_data']);
            $lines['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL'] = $this->line('EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE_VOCATIONAL', '고용안정·직업능력개발 부담', $vocationalPremium) + [
                'application_status_code' => 'APPLICABLE', 'calculation_basis_amount' => $base,
                'calculation_rate' => $vocationalRate, 'calculation_before_rounding' => round($vocationalBefore, 4),
                'rounding_method_code' => $policy['method'], 'rounding_unit' => (int) ($policy['discard_below_unit'] ?? 1),
                'statutory_standard_id' => $standard['id'], 'social_insurance_coverage_id' => $coverage['id'] ?? null,
                'workplace_size_period_id' => $workplaceSizePeriod['id'],
            ];
            $burden += $employerPremium + $vocationalPremium;
            $references[] = $this->reference('STATUTORY_STANDARD', 'institution_workplace_size_periods', $workplaceSizePeriod['id'], (string)$workplaceSizePeriod['revision_no'], $workplaceSizePeriod['effective_from'], $workplaceSizePeriod['effective_to'], $workplaceSizePeriod['business_size_code'], '계산목적 '.$workplaceSizePeriod['calculation_purpose_code'].' · 상시근로자 '.$workplaceSizePeriod['regular_worker_count'].'명');
        }
        $references[] = $this->reference('STATUTORY_STANDARD', 'system_statutory_standards', $standard['id'], isset($standard['version'])?(string)$standard['version']:null, $standard['effective_from'], $standard['effective_to'], $type, '산정대상 '.number_format($base,2,'.','').'원 · 근로자요율 '.number_format((float)$employeeRate,8,'.','').' · '.$sourceCode);
        return $employeePremium;
    }

    private function calculateLongTermCare(
        string $employeeId,
        array $coverages,
        array $standards,
        array $standardErrors,
        ?float $healthPremium,
        array &$lines,
        array &$messages,
        array &$notices,
        array &$references,
        float &$deduction,
        float &$burden
    ): void {
        $overrides = $coverages[$employeeId]['LONG_TERM_CARE'] ?? [];
        $excluded = count($overrides) === 1
            && $overrides[0]['confirmed_at']
            && $overrides[0]['coverage_status_code'] === 'EXCLUDED';
        if ($excluded) {
            $lines['DEDUCTION:LONG_TERM_CARE'] = $this->line('DEDUCTION', 'LONG_TERM_CARE', '장기요양보험', 0);
            return;
        }
        if ($healthPremium === null) {
            $this->blockDeduction($lines, $messages, 'LONG_TERM_CARE', '장기요양보험', '기초가 되는 건강보험료가 미확정입니다.');
            return;
        }
        $notices[] = '장기요양보험은 확정 건강보험료를 기초로 계산했습니다.';
        if (isset($standardErrors['LONG_TERM_CARE'])) {
            $this->blockDeduction($lines, $messages, 'LONG_TERM_CARE', '장기요양보험', $standardErrors['LONG_TERM_CARE']);
            return;
        }
        $standard = $standards['LONG_TERM_CARE'];
        $policy = $standard['value_data']['calculation_policy'] ?? [];
        $rate = $standard['value_data']['rate_value'] ?? null;
        if ($rate === null || empty($policy['method'])) {
            $this->blockDeduction($lines, $messages, 'LONG_TERM_CARE', '장기요양보험', '장기요양 공식 계산정책이 없습니다.');
            return;
        }
        if (($policy['stage'] ?? '') !== 'AFTER_HEALTH_INSURANCE_PREMIUM' || ($policy['base_value_code'] ?? '') !== 'HEALTH_INSURANCE_PREMIUM') {
            $this->blockDeduction($lines, $messages, 'LONG_TERM_CARE', '장기요양보험', '장기요양 계산기초 단계를 확정할 수 없습니다.');
            return;
        }
        $beforeRounding = $healthPremium * (float) $rate;
        $premium = $this->finalizePremium($beforeRounding, $policy, $standard['value_data']);
        $lines['DEDUCTION:LONG_TERM_CARE'] = $this->line('DEDUCTION', 'LONG_TERM_CARE', '장기요양보험', $premium);
        $lines['DEDUCTION:LONG_TERM_CARE']['calculation_rate'] = (float) $rate;
        $lines['DEDUCTION:LONG_TERM_CARE']['application_status_code'] = 'APPLICABLE';
        $lines['DEDUCTION:LONG_TERM_CARE']['calculation_basis_amount'] = $healthPremium;
        $lines['DEDUCTION:LONG_TERM_CARE']['calculation_before_rounding'] = round($beforeRounding, 4);
        $lines['DEDUCTION:LONG_TERM_CARE']['rounding_method_code'] = $policy['method'];
        $lines['DEDUCTION:LONG_TERM_CARE']['rounding_unit'] = (int) ($policy['discard_below_unit'] ?? 1);
        $lines['DEDUCTION:LONG_TERM_CARE']['statutory_standard_id'] = $standard['id'];
        $lines['DEDUCTION:LONG_TERM_CARE']['social_insurance_coverage_id'] = null;
        $lines['DEDUCTION:LONG_TERM_CARE']['workplace_size_period_id'] = null;
        $lines['DEDUCTION:LONG_TERM_CARE']['standard_effective_from'] = $standard['effective_from'];
        $lines['DEDUCTION:LONG_TERM_CARE']['standard_effective_to'] = $standard['effective_to'];
        $lines['EMPLOYER_BURDEN:LONG_TERM_CARE'] = $this->line('EMPLOYER_BURDEN', 'LONG_TERM_CARE', '장기요양보험 회사부담', $premium);
        $lines['EMPLOYER_BURDEN:LONG_TERM_CARE'] += array_intersect_key(
            $lines['DEDUCTION:LONG_TERM_CARE'],
            array_flip(self::TRACE_COLUMNS)
        );
        $deduction += $premium;
        $burden += $premium;
        $references[] = $this->reference('STATUTORY_STANDARD', 'system_statutory_standards', $standard['id'], isset($standard['version'])?(string)$standard['version']:null, $standard['effective_from'], $standard['effective_to'], 'LONG_TERM_CARE', '산정대상 '.number_format($healthPremium,2,'.','').'원 · 요율 '.number_format((float)$rate,8,'.','').' · 확정 건강보험료 기반');
    }

    private function calculateTaxes(string $month, float $taxable, ?int $dependents, array $standards, array $errors, array &$lines, array &$messages, array &$references, float &$deduction): void
    {
        $incomeTax = null;
        if (isset($errors['EMPLOYMENT_INCOME_TAX_TABLE'])) {
            $this->blockDeduction($lines, $messages, 'EMPLOYMENT_INCOME_TAX', '근로소득세', $errors['EMPLOYMENT_INCOME_TAX_TABLE']);
        } elseif ($dependents === null) {
            $this->blockDeduction($lines, $messages, 'EMPLOYMENT_INCOME_TAX', '근로소득세', substr($month, 0, 4) . '년 ' . substr($month, 5, 2) . '월 공제대상 가족수 Snapshot을 입력해 주세요.');
            $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['calculation_basis_amount'] = $taxable;
            $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['statutory_standard_id'] = $standards['EMPLOYMENT_INCOME_TAX_TABLE']['id'];
            $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['standard_effective_from'] = $standards['EMPLOYMENT_INCOME_TAX_TABLE']['effective_from'];
            $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['standard_effective_to'] = $standards['EMPLOYMENT_INCOME_TAX_TABLE']['effective_to'];
        } else {
            try {
                $taxStandard = $standards['EMPLOYMENT_INCOME_TAX_TABLE'];
                $taxResult = (new EmploymentIncomeTaxTableService())->calculate($taxable, $dependents, $taxStandard['value_data']);
                $incomeTax = $taxResult['tax_amount'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX'] = $this->line('DEDUCTION', 'EMPLOYMENT_INCOME_TAX', '근로소득세', $incomeTax);
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['application_status_code'] = 'APPLICABLE';
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['calculation_basis_amount'] = $taxResult['taxable_salary_amount'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['statutory_standard_id'] = $taxStandard['id'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['social_insurance_coverage_id'] = null;
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['workplace_size_period_id'] = null;
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['tax_table_effective_from'] = $taxStandard['effective_from'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['tax_table_effective_to'] = $taxStandard['effective_to'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['tax_table_salary_from'] = $taxResult['salary_from'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['tax_table_salary_to'] = $taxResult['salary_to'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['dependent_count'] = $taxResult['dependent_count'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['dependent_column_key'] = $taxResult['dependent_column_key'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['table_tax_amount'] = $taxResult['table_tax_amount'];
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['threshold_applied'] = $taxResult['threshold_applied'];
                $deduction += $incomeTax;
            } catch (\Throwable $exception) {
                $this->blockDeduction($lines, $messages, 'EMPLOYMENT_INCOME_TAX', '근로소득세', $exception->getMessage());
            }
        }
        if ($incomeTax === null) {
            $this->blockDeduction($lines, $messages, 'LOCAL_INCOME_TAX', '지방소득세', '근로소득세가 미확정되어 지방소득세를 계산할 수 없습니다.');
            if (!isset($errors['LOCAL_INCOME_TAX_WITHHOLDING'])) {
                $local = $standards['LOCAL_INCOME_TAX_WITHHOLDING'];
                $policy = $local['value_data']['calculation_policy'] ?? [];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['calculation_rate'] = (float) ($local['value_data']['rate_value'] ?? 0);
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['rounding_method_code'] = $policy['method'] ?? null;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['rounding_unit'] = isset($policy['discard_below_unit']) ? (int) $policy['discard_below_unit'] : null;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['statutory_standard_id'] = $local['id'];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['standard_effective_from'] = $local['effective_from'];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['standard_effective_to'] = $local['effective_to'];
            }
        } elseif (isset($errors['LOCAL_INCOME_TAX_WITHHOLDING'])) {
            $this->blockDeduction($lines, $messages, 'LOCAL_INCOME_TAX', '지방소득세', $errors['LOCAL_INCOME_TAX_WITHHOLDING']);
        } else {
            $local = $standards['LOCAL_INCOME_TAX_WITHHOLDING'];
            $policy = $local['value_data']['calculation_policy'] ?? [];
            $rate = $local['value_data']['rate_value'] ?? null;
            if ($rate === null || empty($policy['method'])) {
                $this->blockDeduction($lines, $messages, 'LOCAL_INCOME_TAX', '지방소득세', '지방소득세 공식 계산정책이 없습니다.');
            } else {
                $localTax = $this->round($incomeTax * (float) $rate, $policy);
                $lines['DEDUCTION:LOCAL_INCOME_TAX'] = $this->line('DEDUCTION', 'LOCAL_INCOME_TAX', '지방소득세', $localTax);
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['application_status_code'] = 'APPLICABLE';
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['calculation_basis_amount'] = $incomeTax;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['calculation_rate'] = (float) $rate;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['calculation_before_rounding'] = round($incomeTax * (float) $rate, 4);
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['rounding_method_code'] = $policy['method'];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['rounding_unit'] = (int) ($policy['discard_below_unit'] ?? 1);
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['statutory_standard_id'] = $local['id'];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['social_insurance_coverage_id'] = null;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['workplace_size_period_id'] = null;
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['standard_effective_from'] = $local['effective_from'];
                $lines['DEDUCTION:LOCAL_INCOME_TAX']['standard_effective_to'] = $local['effective_to'];
                $deduction += $localTax;
            }
        }
        if (isset($lines['DEDUCTION:EMPLOYMENT_INCOME_TAX'])) {
            $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX']['calculation_mode_projection'] = IncomeCalculationModeProjectionService::automatic(
                $lines['DEDUCTION:EMPLOYMENT_INCOME_TAX'],
                [
                    'calculation_basis_name' => '귀속연월 과세급여와 공제대상 가족수에 해당하는 근로소득 간이세액표 적용',
                    'detail' => '과세급여 구간과 공제대상 가족수 열에서 확정된 세액을 적용합니다.',
                ]
            );
        }
        if (isset($lines['DEDUCTION:LOCAL_INCOME_TAX'])) {
            $lines['DEDUCTION:LOCAL_INCOME_TAX']['calculation_mode_projection'] = IncomeCalculationModeProjectionService::automatic(
                $lines['DEDUCTION:LOCAL_INCOME_TAX'],
                [
                    'calculation_basis_name' => '확정된 근로소득세를 기준으로 지방소득세율 적용',
                    'detail' => '근로소득세에 법정 지방소득세율을 적용한 뒤 끝수처리합니다.',
                ]
            );
        }
        foreach (['EMPLOYMENT_INCOME_TAX_TABLE', 'LOCAL_INCOME_TAX_WITHHOLDING'] as $type) {
            if (!isset($errors[$type])) {
                $standard = $standards[$type];
                $references[] = $this->reference('STATUTORY_STANDARD', 'system_statutory_standards', $standard['id'], null, $standard['effective_from'], $standard['effective_to'], $type, $type);
            }
        }
    }

    private function hydrateDependentCounts(array $employeeIds, string $month, array &$counts): void
    {
        if (count($counts) >= count($employeeIds)) {
            return;
        }
        $in = implode(',', array_fill(0, count($employeeIds), '?'));
        $statement = $this->db->prepare("SELECT i.employee_id,i.dependent_count_snapshot FROM institution_regular_employment_income_items i JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id WHERE i.employee_id IN($in) AND h.income_year_month=? AND i.deleted_at IS NULL AND h.deleted_at IS NULL");
        $statement->execute([...$employeeIds, $month]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($row['dependent_count_snapshot'] !== null) {
                $counts[(string) $row['employee_id']] = (int) $row['dependent_count_snapshot'];
            }
        }
    }

    private function contractContext(array $employeeIds, string $from, string $to): array
    {
        $validContracts = (new EmploymentContractValidityService(new EmploymentContractModel($this->db)))->effectiveContractsForPeriod($from, $to);
        $validByEmployee = [];
        foreach ($validContracts as $contract) {
            if (in_array((string) $contract['employee_id'], $employeeIds, true)) {
                $validByEmployee[(string) $contract['employee_id']][] = $contract;
            }
        }
        $canonical = [];
        foreach ($validByEmployee as $rows) {
            if (count($rows) === 1) {
                $canonical[(string) $rows[0]['id']] = true;
            }
        }
        $in = implode(',', array_fill(0, count($employeeIds), '?'));
        $statement = $this->db->prepare("SELECT c.id contract_id,c.employee_id,c.contract_start_date,c.contract_end_date,cc.component_code,cc.component_name,cc.amount,cc.tax_type FROM institution_employment_contracts c JOIN institution_employment_contracts_components cc ON cc.contract_id=c.id AND cc.deleted_at IS NULL WHERE c.employee_id IN($in) AND c.deleted_at IS NULL AND c.contract_status='APPROVED' AND c.contract_start_date<=? AND(c.contract_end_date IS NULL OR c.contract_end_date>=?) ORDER BY c.employee_id,cc.sort_no");
        $statement->execute([...$employeeIds, $to, $from]);
        $contracts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (isset($canonical[(string) $row['contract_id']])) {
                $contracts[(string) $row['employee_id']][] = $row;
            }
        }
        return [$validByEmployee, $contracts];
    }

    private function resolveStandards(string $insuranceDate, string $taxReferenceDate): array
    {
        $types = ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT', 'EMPLOYMENT_INCOME_TAX_TABLE', 'LOCAL_INCOME_TAX_WITHHOLDING'];
        $insuranceTypes = ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT'];
        $standards = [];
        $errors = [];
        foreach ($types as $type) {
            $date = in_array($type, $insuranceTypes, true) ? $insuranceDate : $taxReferenceDate;
            try {
                $standards[$type] = $this->standards->resolve($type, $date);
            } catch (\Throwable) {
                $errors[$type] = $type . ' 법정기준 누락(' . $date . ')';
            }
        }
        return [$standards, $errors];
    }

    private function calculateIndustrialAccident(
        float $base,
        ?array $standard,
        ?string $standardError,
        array &$lines,
        array &$messages,
        array &$references,
        float &$burden
    ): void {
        $code = 'INDUSTRIAL_ACCIDENT_INSURANCE';
        $name = '산재보험 사용자부담';
        $reason = $standardError ?: '산재보험 법정기준을 확인할 수 없습니다.';
        $rates = is_array($standard) ? array_values((array) ($standard['value_data']['industry_rates'] ?? [])) : [];
        if ($standard && count($rates) !== 1) {
            $reason = '산재보험 적용은 확정됐지만 공식 사업종류 요율 Scope를 하나로 확정할 수 없습니다.';
        }

        $line = $this->blockedLine('EMPLOYER_BURDEN', $code, $name, $reason);
        $line['application_status_code'] = 'APPLICABLE';
        $line['calculation_basis_amount'] = $base;
        if (!$standard || count($rates) !== 1) {
            $lines['EMPLOYER_BURDEN:' . $code] = $line;
            $messages[] = $reason;
            return;
        }

        $rate = isset($rates[0]['employer_rate']) ? (float) $rates[0]['employer_rate'] : null;
        $industryName = trim((string) ($rates[0]['industry_name'] ?? ''));
        $line['calculation_rate'] = $rate;
        $line['calculation_before_rounding'] = $rate === null ? null : round($base * $rate, 4);
        $line['statutory_standard_id'] = $standard['id'];
        $line['standard_effective_from'] = $standard['effective_from'];
        $line['standard_effective_to'] = $standard['effective_to'];
        $policy = (array) ($standard['value_data']['calculation_policy'] ?? []);
        if ($rate === null || empty($policy['method']) || empty($policy['discard_below_unit'])) {
            $reason = '산재보험 적용 및 ' . ($industryName ?: '사업종류') . ' 법정요율은 확인됐지만 공식 월별 끝수처리 기준이 미등록입니다.';
            $line['calculation_message'] = $reason;
            $lines['EMPLOYER_BURDEN:' . $code] = $line;
            $messages[] = $reason;
            $references[] = $this->reference('STATUTORY_STANDARD', 'system_statutory_standards', $standard['id'], isset($standard['version']) ? (string) $standard['version'] : null, $standard['effective_from'], $standard['effective_to'], 'INDUSTRIAL_ACCIDENT', $industryName . ' · ' . number_format((float) $rate * 100, 4) . '%');
            return;
        }

        $amount = $this->finalizePremium((float) $line['calculation_before_rounding'], $policy, $standard['value_data']);
        $line['calculated_amount'] = $amount;
        $line['adjustment_amount'] = 0.0;
        $line['final_amount'] = $amount;
        $line['calculation_status_code'] = 'CALCULATED';
        $line['calculation_message'] = null;
        $line['rounding_method_code'] = $policy['method'];
        $line['rounding_unit'] = (int) $policy['discard_below_unit'];
        $lines['EMPLOYER_BURDEN:' . $code] = $line;
        $burden += $amount;
        $references[] = $this->reference('STATUTORY_STANDARD', 'system_statutory_standards', $standard['id'], isset($standard['version']) ? (string) $standard['version'] : null, $standard['effective_from'], $standard['effective_to'], 'INDUSTRIAL_ACCIDENT', $industryName . ' · ' . number_format((float) $rate * 100, 4) . '%');
    }

    private function line(string $type, string $code, string $name, float $amount, ?int $taxable = null): array
    {
        return ['item_type_code' => $type, 'item_code' => $code, 'item_name_snapshot' => $name, 'taxable_flag' => $taxable, 'calculated_amount' => round($amount, 2), 'adjustment_amount' => 0.0, 'final_amount' => round($amount, 2), 'adjustment_reason' => null, 'calculation_source_code' => 'CALCULATED', 'calculation_status_code' => 'CALCULATED', 'calculation_message' => null];
    }

    private function excludedLine(string $type, string $code, string $name, ?string $coverageId, ?string $reason = null): array
    {
        return ['item_type_code'=>$type,'item_code'=>$code,'item_name_snapshot'=>$name,'taxable_flag'=>null,'calculated_amount'=>null,'adjustment_amount'=>null,'final_amount'=>0.0,'adjustment_reason'=>null,'calculation_source_code'=>'CALCULATED','calculation_status_code'=>'CALCULATED','calculation_message'=>$reason,'application_status_code'=>'EXCLUDED','calculation_basis_amount'=>null,'calculation_rate'=>null,'calculation_before_rounding'=>null,'rounding_method_code'=>null,'rounding_unit'=>null,'statutory_standard_id'=>null,'social_insurance_coverage_id'=>($coverageId !== null && trim($coverageId) !== '' ? $coverageId : null),'workplace_size_period_id'=>null,'business_reason'=>$reason];
    }

    private function blockedLine(string $type, string $code, string $name, string $reason): array
    {
        return ['item_type_code'=>$type,'item_code'=>$code,'item_name_snapshot'=>$name,'taxable_flag'=>null,'calculated_amount'=>null,'adjustment_amount'=>null,'final_amount'=>0.0,'adjustment_reason'=>null,'calculation_source_code'=>'CALCULATED','calculation_status_code'=>'NEEDS_CONFIRMATION','calculation_message'=>$reason,'application_status_code'=>null];
    }

    private function blockDeduction(array &$lines, array &$messages, string $code, string $name, string $reason): void
    {
        $messages[] = $reason;
        $lines['DEDUCTION:' . $code] = ['item_type_code' => 'DEDUCTION', 'item_code' => $code, 'item_name_snapshot' => $name, 'taxable_flag' => null, 'calculated_amount' => null, 'adjustment_amount' => null, 'final_amount' => null, 'adjustment_reason' => null, 'calculation_source_code' => 'CALCULATED', 'calculation_status_code' => 'NEEDS_CONFIRMATION', 'calculation_message' => $reason];
    }

    private function reference(string $type, string $table, string $id, ?string $revision, ?string $from, ?string $to, string $code, string $summary): array
    {
        return ['basis_type_code' => $type, 'source_table' => $table, 'source_id' => $id, 'source_revision' => $revision, 'effective_from' => $from, 'effective_to' => $to, 'basis_code' => $code, 'basis_summary' => $summary];
    }

    private function round(float $value, array $policy): float
    {
        $unit = max(1, (int) ($policy['discard_below_unit'] ?? 1));
        return match ($policy['method'] ?? '') {
            'TRUNCATE' => floor($value / $unit) * $unit,
            'ROUND' => round($value / $unit) * $unit,
            'ROUND_UP', 'CEIL' => ceil($value / $unit) * $unit,
            default => throw new \RuntimeException('공식 끝수처리 정책이 없습니다.'),
        };
    }

    private function finalizePremium(float $value, array $policy, array $values): float
    {
        return $this->insurancePremium->finalize($value, $policy, $values);
    }

    private function clampResult(float $value, array $values): float
    {
        if (isset($values['minimum_result_amount']) && $values['minimum_result_amount'] !== '') {
            $value = max($value, (float) $values['minimum_result_amount']);
        }
        if (isset($values['maximum_result_amount']) && $values['maximum_result_amount'] !== '') {
            $value = min($value, (float) $values['maximum_result_amount']);
        }
        return $value;
    }

    private function resultLimitsReady(array $values, array $policy): bool
    {
        $hasMin = isset($values['minimum_result_amount']) && $values['minimum_result_amount'] !== '';
        $hasMax = isset($values['maximum_result_amount']) && $values['maximum_result_amount'] !== '';
        if (!$hasMin && !$hasMax) {
            return true;
        }
        $stage = (string) ($values['result_limit_application_stage'] ?? '');
        if (in_array($stage, ['AFTER_PREMIUM_CALCULATION', 'AFTER_ROUNDING'], true)) {
            return true;
        }
        if ($stage !== '' || ($policy['method'] ?? '') !== 'TRUNCATE') {
            return false;
        }
        $unit = max(1, (int) ($policy['discard_below_unit'] ?? 1));
        return (!$hasMin || (int) $values['minimum_result_amount'] % $unit === 0)
            && (!$hasMax || (int) $values['maximum_result_amount'] % $unit === 0);
    }

    private function name(string $type): string
    {
        return ['NATIONAL_PENSION' => '국민연금', 'HEALTH_INSURANCE' => '건강보험', 'EMPLOYMENT_INSURANCE' => '고용보험'][$type] ?? $type;
    }
}
