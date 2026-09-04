<?php

namespace App\Services\Institution;

use App\Models\Institution\DailyEmploymentIncomeModel;
use App\Models\Institution\WorkplaceSizePeriodModel;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\System\StatutoryStandardResolver;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class DailyEmploymentIncomeService
{
    public const DOCUMENT_TYPE = 'DAILY_EMPLOYMENT_INCOME';
    private DailyEmploymentIncomeModel $model;
    private DailyEmploymentIncomeCalculationService $calculation;
    private DailyEmploymentIncomeBusinessUnitPolicyService $businessUnitPolicy;
    private DailyEmploymentIncomeFieldPolicyService $fieldPolicy;
    private DailyEmploymentIncomeCalculationSourceService $sourceHasher;
    private IncomeCalculationCodeService $incomeCodes;
    private DailyEmploymentIncomeLineContractService $lineContract;
    private StatutoryStandardResolver $statutoryStandards;
    private IncomeInsurancePremiumCalculationService $insurancePremium;
    private DailyEmploymentIncomeInsuranceEligibilityService $insuranceEligibility;
    private DailyEmploymentIncomeGroupInsurancePolicyService $groupInsurancePolicy;
    private DailyEmploymentIncomeCalculationResultService $calculationResults;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $db, private $closureFailureInjector = null)
    {
        $this->model = new DailyEmploymentIncomeModel($db);
        $this->calculation = new DailyEmploymentIncomeCalculationService($db);
        $this->businessUnitPolicy = new DailyEmploymentIncomeBusinessUnitPolicyService();
        $this->fieldPolicy = new DailyEmploymentIncomeFieldPolicyService($db);
        $this->sourceHasher = new DailyEmploymentIncomeCalculationSourceService();
        $this->incomeCodes = new IncomeCalculationCodeService($db);
        $this->lineContract = new DailyEmploymentIncomeLineContractService();
        $this->statutoryStandards = new StatutoryStandardResolver($db);
        $this->insurancePremium = new IncomeInsurancePremiumCalculationService();
        $this->insuranceEligibility = new DailyEmploymentIncomeInsuranceEligibilityService($db);
        $this->groupInsurancePolicy = new DailyEmploymentIncomeGroupInsurancePolicyService($db);
        $this->calculationResults = new DailyEmploymentIncomeCalculationResultService($db);
        $this->logger = LoggerFactory::getLogger('service-institution-daily-employment-income');
    }

    public function page(array $filter): array
    {
        $page = $this->model->page($filter);
        return [
            'success' => true,
            'data' => $page['rows'],
            'draw' => (int) ($filter['draw'] ?? 0),
            'recordsTotal' => $page['total'],
            'recordsFiltered' => $page['filtered'],
        ];
    }

    public function detail(string $id): array
    {
        $header = $this->model->find($id);
        if ($header === null) {
            throw new \RuntimeException('일용근로소득 문서를 찾을 수 없습니다.');
        }
        $header = ActorHelper::enrichActorNamesRow($header, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'approved_by_name' => 'approved_by',
            'deleted_by_name' => 'deleted_by',
        ]);
        $groups=$this->model->groups($id);
        foreach($groups as &$group){
          try {
            $group['business_unit_policy']=$this->businessUnitPolicy->fromCodeRow([
              'code'=>$group['business_unit'],'code_name'=>$group['business_unit_name'],'sort_no'=>$group['business_unit_sort_no'],'extra_data'=>$group['business_unit_extra_data'],
            ]);
          } catch (\Throwable) { $group['business_unit_policy']=null; }
          $items=$this->model->items((string)$group['id']);
          foreach($items as &$item){$item['workdays']=$this->model->workdays((string)$item['id']);$item['lines']=$this->model->lines((string)$item['id']);}
          unset($item);$group['items']=$items;
        }
        unset($group);
        return ['success'=>true,'data'=>[
            'header'=>$header,
            'groups'=>$groups,
            'calculation_revision'=>$this->calculationResults->latest($id),
        ]];
    }

    public function options(array $input = []): array
    {
        if (trim((string) ($input['option_type'] ?? '')) !== '') {
            return ['success' => true, 'data' => $this->model->groupOptionSearch($input)];
        }
        $options = $this->model->options();
        $options['business_units'] = array_map(
            fn(array $row): array => $this->businessUnitPolicy->fromCodeRow($row),
            $options['business_units']
        );
        return ['success' => true, 'data' => $options];
    }

    public function calculate(array $input, bool $requireDecisionReason = false): array
    {
        $month = trim((string) ($input['income_year_month'] ?? ''));
        $withholdingDate = $this->withholdingDate($input['withholding_date'] ?? null);
        $documentId = trim((string) ($input['id'] ?? '')) ?: null;
        $groups=is_array($input['groups']??null)?array_values($input['groups']):[];$items=[];
        foreach($groups as $groupIndex=>$group){
            $groupInsurance = $this->normalizeGroupInsurance($group, $requireDecisionReason);
            $groupItems=is_array($group['items']??null)?array_values($group['items']):[];if($groupItems===[])throw new \InvalidArgumentException(($groupIndex+1).'번째 근무그룹에 작업자를 추가해 주세요.');
            foreach($groupItems as $item){$items[]=array_merge($item,[
                'group_index'=>$groupIndex,
                'business_unit'=>$group['business_unit']??'',
                'project_id'=>$group['project_id']??null,
                'work_team_id'=>$group['work_team_id']??null,
                'daily_rate_amount'=>$item['daily_rate_amount']??0,
                'employment_insurance_application_status_code'=>$groupInsurance['employment_insurance_application_status_code'],
                'employment_insurance_decision_reason'=>$groupInsurance['employment_insurance_decision_reason'],
                'employment_insurance_decision_source_code_id'=>$groupInsurance['employment_insurance_decision_source_code_id'],
                'employment_insurance_decision_source_code'=>$groupInsurance['employment_insurance_decision_source_code'],
                'employment_insurance_set_by'=>$groupInsurance['employment_insurance_set_by'],
                'employment_insurance_set_at'=>$groupInsurance['employment_insurance_set_at'],
                'industrial_accident_application_status_code'=>$groupInsurance['industrial_accident_application_status_code'],
                'industrial_accident_decision_reason'=>$groupInsurance['industrial_accident_decision_reason'],
                'industrial_accident_decision_source_code_id'=>$groupInsurance['industrial_accident_decision_source_code_id'],
                'industrial_accident_decision_source_code'=>$groupInsurance['industrial_accident_decision_source_code'],
                'industrial_accident_set_by'=>$groupInsurance['industrial_accident_set_by'],
                'industrial_accident_set_at'=>$groupInsurance['industrial_accident_set_at'],
            ]);}
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || $items === []) {
            throw new \InvalidArgumentException('귀속연월과 일용근로자 근무내역을 확인해 주세요.');
        }
        $companyId = $this->model->companyId();
        $eligibilityAggregateCandidates = [];
        foreach ($items as $candidate) {
            $candidateWorkerId=trim((string)($candidate['worker_client_id']??''));
            $candidateProjectId=trim((string)($candidate['project_id']??''))?:null;
            $candidateBusinessUnit=strtoupper(trim((string)($candidate['business_unit']??'')));
            $candidateWorkdays=is_array($candidate['workdays']??null)?array_values($candidate['workdays']):[];
            foreach($candidateWorkdays as &$candidateDay){
                $candidateDay['gross_amount']=round((float)($candidateDay['daily_rate_amount']??0)+(float)($candidateDay['taxable_additional_amount']??0)+(float)($candidateDay['non_taxable_additional_amount']??0),2);
            }
            unset($candidateDay);
            $eligibilityAggregateCandidates[]=[
                'item_id'=>(string)($candidate['id']??$candidate['client_key']??''),
                'company_id'=>$companyId,
                'worker_client_id'=>$candidateWorkerId,
                'business_unit_code'=>$candidateBusinessUnit,
                'project_id'=>$candidateProjectId,
                'social_insurance_workplace_id'=>null,
                'workdays'=>$candidateWorkdays,
            ];
        }
        $results = [];
        $insurancePreflight = [];
        $workMinutesByWorkerDate = [];
        $workdayOccurrences = [];
        foreach ($items as $itemIndex => $item) {
            $workerId = trim((string) ($item['worker_client_id'] ?? ''));
            $businessUnit = strtoupper(trim((string) ($item['business_unit'] ?? '')));
            $policy = $this->businessUnitPolicy->fromCodeRow($this->model->businessUnitPolicy($businessUnit, $documentId));
            $projectId = trim((string) ($item['project_id'] ?? '')) ?: null;
            $teamId = trim((string) ($item['work_team_id'] ?? '')) ?: null;
            $workdays = is_array($item['workdays'] ?? null) ? array_values($item['workdays']) : [];
            $workTypeCode = strtoupper(trim((string) ($item['work_type_code'] ?? '')));
            $workDescription = trim((string) ($item['work_description'] ?? ''));
            if ($businessUnit === '') throw new \InvalidArgumentException(($itemIndex + 1) . '번째 근무그룹의 사업구분을 선택해 주세요.');
            if ($workerId === '') throw new \InvalidArgumentException(($itemIndex + 1) . '번째 일용근로자를 선택해 주세요.');
            if ($workdays === []) throw new \InvalidArgumentException(($itemIndex + 1) . '번째 일용근로자의 근무일을 입력해 주세요.');
            if ($workTypeCode === '') throw new \InvalidArgumentException(($itemIndex + 1) . '번째 작업자의 공종을 입력해 주세요.');
            if ($requireDecisionReason && $workDescription === '') throw new \InvalidArgumentException(($itemIndex + 1) . '번째 작업자의 작업내용을 입력해 주세요.');
            if (!$policy['uses_project'] && $projectId !== null) throw new \InvalidArgumentException('선택한 사업구분에는 프로젝트를 입력할 수 없습니다.');
            if ($policy['requires_project'] && $projectId === null) throw new \InvalidArgumentException('선택한 사업구분에는 프로젝트가 필요합니다.');
            if (!$policy['uses_work_team'] && $teamId !== null) throw new \InvalidArgumentException('선택한 사업구분에는 작업팀을 입력할 수 없습니다.');
            if ($policy['requires_work_team'] && $teamId === null) throw new \InvalidArgumentException('선택한 사업구분에는 작업팀이 필요합니다.');
            $eligibilityScope = $this->businessUnitPolicy->eligibilityScope($policy, $projectId);
            $daySeen = [];
            $calculatedDays = [];
            $summary = ['total_work_days' => 0.0, 'total_gross_amount' => 0.0, 'total_deduction_amount' => 0.0, 'total_net_payment_amount' => 0.0, 'total_employer_burden_amount' => 0.0];
            foreach ($workdays as $row) {
                $workDate = trim((string) ($row['work_date'] ?? ''));
                if (array_key_exists('actual_work_hours', $row)) {
                    throw new \InvalidArgumentException('근로시간은 actual_work_minutes 정수 분으로 입력해 주세요.');
                }
                $actualWorkMinutes = $row['actual_work_minutes'] ?? null;
                if ($actualWorkMinutes !== null && $actualWorkMinutes !== '') {
                    if (!is_int($actualWorkMinutes) && !preg_match('/^[0-9]+$/', (string) $actualWorkMinutes)) {
                        throw new \InvalidArgumentException('실제 근로시간은 정수 분으로 입력해 주세요.');
                    }
                    $actualWorkMinutes = (int) $actualWorkMinutes;
                    if ($actualWorkMinutes < 1 || $actualWorkMinutes > 1440) {
                        throw new \InvalidArgumentException('실제 근로시간은 1분 이상 1440분 이하로 입력해 주세요.');
                    }
                } else {
                    throw new \InvalidArgumentException($workDate . ' 실제근로시간(휴게시간 제외)을 입력해 주세요.');
                }
                if (!$this->isDate($workDate) || substr($workDate, 0, 7) !== $month || isset($daySeen[$workDate])) {
                    throw new \InvalidArgumentException('근무일은 실제 존재하는 귀속연월 날짜 안에서 중복 없이 선택해 주세요.');
                }
                $daySeen[$workDate] = true;
                $workerDateKey = $workerId . '|' . $workDate;
                $workMinutesByWorkerDate[$workerDateKey] = ($workMinutesByWorkerDate[$workerDateKey] ?? 0) + $actualWorkMinutes;
                $workdayOccurrences[$workerDateKey][] = [
                    'group_index' => (int) ($item['group_index'] ?? 0),
                    'worker_client_id' => $workerId,
                    'work_date' => $workDate,
                    'actual_work_minutes' => $actualWorkMinutes,
                ];
                if ($workMinutesByWorkerDate[$workerDateKey] > 1440) {
                    throw new \InvalidArgumentException($workDate . ' 동일 근로자의 문서 전체 실제근로시간 합계는 1,440분을 초과할 수 없습니다.');
                }
                $this->model->assertGroupReferences($companyId, $businessUnit, $projectId, $teamId, $workerId, $workDate, $documentId);
                $assignment = $this->model->resolveWorkerAssignment($companyId, $workerId, $teamId, $projectId, $workDate);
                $insuranceWorkplaceId = null;
                $calculationStatus = 'CALCULATED';
                // 보험사업장 Master는 현재 회사부담 결정에 사용하지 않는다.
                $insuranceIssues = [];
                if ($businessUnit === 'CONSTRUCTION') {
                    $employmentDecision = strtoupper(trim((string) ($item['employment_insurance_application_status_code'] ?? '')));
                    $industrialDecision = strtoupper(trim((string) ($item['industrial_accident_application_status_code'] ?? '')));
                    foreach ([
                        ['EMPLOYMENT_INSURANCE', $employmentDecision],
                        ['INDUSTRIAL_ACCIDENT_INSURANCE', $industrialDecision],
                    ] as [$insuranceType, $decision]) {
                        if ($decision !== 'CONFIRMATION_REQUIRED') continue;
                        $insuranceIssues[] = [
                            'insurance_type_code' => $insuranceType,
                            'status_code' => 'CONFIRMATION_REQUIRED',
                            'blocking_code' => $insuranceType . '_GROUP_DECISION_REQUIRED',
                            'message' => (string) $reason,
                        'required_action' => '근무그룹의 회사부담 여부를 확정해 주세요.',
                        ];
                    }
                }
                if ($insuranceIssues !== []) {
                    $calculationStatus = 'CONFIRMATION_REQUIRED';
                    foreach ($insuranceIssues as $issue) $insurancePreflight[] = $issue + [
                        'worker_client_id' => $workerId,
                        'work_date' => $workDate,
                        'business_unit' => $businessUnit,
                        'project_id' => $projectId,
                        'work_team_id' => $teamId,
                    ];
                }
                $dailyRate = max(0.0, round((float) ($row['daily_rate_amount'] ?? 0), 2));
                $taxableAdditional = round((float) ($row['taxable_additional_amount'] ?? 0), 2);
                $nonTaxableAdditional = max(0.0, round((float) ($row['non_taxable_additional_amount'] ?? 0), 2));
                $calculationNote = $this->nullableLimitedText(
                    $row['calculation_note'] ?? null,
                    500,
                    $workDate . ' 산정내역은 500자 이하로 입력해 주세요.'
                );
                $nonTaxableReason = $this->nullableLimitedText(
                    $row['non_taxable_reason'] ?? null,
                    500,
                    $workDate . ' 비과세 적용사유는 500자 이하로 입력해 주세요.'
                );
                if ($nonTaxableAdditional > 0 && $nonTaxableReason === null) {
                    throw new \InvalidArgumentException($workDate . ' 비과세증감에는 비과세 적용사유가 필요합니다.');
                }
                $basePay = $dailyRate;
                $gross = round($basePay + $taxableAdditional + $nonTaxableAdditional, 2);
                if ($gross < 0) {
                    throw new \InvalidArgumentException($workDate . ' 최종 지급액은 음수일 수 없습니다.');
                }
                $tax = $this->calculation->calculateWorkday($workDate, $gross, $nonTaxableAdditional, $withholdingDate);
                $workerInsurance = 0.0;
                $otherDeduction = 0.0;
                $deduction = max(0.0, round((float) $tax['income_tax_amount'] + (float) $tax['local_income_tax_amount'] + $workerInsurance + $otherDeduction, 2));
                $net = round($gross - $deduction, 2);
                if ($net > $gross || $net < 0) {
                    throw new \RuntimeException('공제합계와 실지급액 계산결과를 확인할 수 없습니다.');
                }
                $lines = [
                    ['line_type_code' => 'PAY', 'line_code' => 'BASE_PAY', 'line_name_snapshot' => '기본급', 'final_amount' => $basePay],
                    ['line_type_code' => 'PAY', 'line_code' => 'TAXABLE_ADDITIONAL_PAY', 'line_name_snapshot' => '과세 추가금액', 'final_amount' => $taxableAdditional],
                    ['line_type_code' => 'PAY', 'line_code' => 'NON_TAXABLE_ADDITIONAL_PAY', 'line_name_snapshot' => '비과세 추가금액', 'final_amount' => $nonTaxableAdditional],
                    ...$tax['lines'],
                ];
                $now = date('Y-m-d H:i:s');
                $actor = ActorHelper::user();
                foreach ($lines as &$line) {
                    $line['calculated_amount'] = (float) $line['final_amount'];
                    $line['adjustment_reason'] = $this->lineContract->adjustmentReason(
                        $line['adjustment_reason'] ?? null,
                        $line['calculated_amount'],
                        (float) $line['final_amount']
                    );
                    $line['statutory_calculation_source_code_id'] = isset($line['statutory_standard_id'])
                        ? $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'STATUTORY_RESOLVER')
                        : null;
                    $line['actual_application_source_code_id'] = $this->incomeCodes->id(
                        IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP,
                        'AUTO_APPLIED'
                    );
                    $line['processed_at'] = $now;
                    $line['processed_by'] = $actor;
                }
                unset($line);
                $workdayOverrides = [];
                foreach ((array) ($row['institution_line_overrides'] ?? []) as $override) {
                    $code = strtoupper(trim((string) ($override['line_code'] ?? '')));
                    if (in_array($code, ['DAILY_WORKER_INCOME_TAX', 'LOCAL_INCOME_TAX'], true)) $workdayOverrides[$code] = $override;
                }
                foreach ($lines as &$line) {
                    $override = $workdayOverrides[(string) ($line['line_code'] ?? '')] ?? null;
                    if (!$override) continue;
                    $calculated = (float) ($line['calculated_amount'] ?? 0);
                    $final = max(0.0, round((float) ($override['final_amount'] ?? 0), 2));
                    $reason = trim((string) ($override['adjustment_reason'] ?? ''));
                    $changed = abs($final - $calculated) >= 0.01;
                    if ($changed && $reason === '' && $requireDecisionReason) {
                        throw new \InvalidArgumentException($workDate . ' 자동계산세액과 다른 적용금액에는 적용사유가 필요합니다.');
                    }
                    $source = $changed ? 'HISTORICAL_ACTUAL' : 'AUTO_APPLIED';
                    $line['final_amount'] = $final;
                    $line['adjustment_amount'] = round($final - $calculated, 2);
                    $line['adjustment_reason'] = $reason !== '' ? $reason : null;
                    $line['actual_application_source_code_id'] = $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, $source);
                    $line['processed_at'] = $now;
                    $line['processed_by'] = $actor;
                }
                unset($line);
                $appliedLineAmount = static function (array $rows, string $code): float {
                    foreach ($rows as $line) if (($line['line_code'] ?? '') === $code) return (float) ($line['final_amount'] ?? 0);
                    return 0.0;
                };
                $tax['income_tax_amount'] = $appliedLineAmount($lines, 'DAILY_WORKER_INCOME_TAX');
                $tax['local_income_tax_amount'] = $appliedLineAmount($lines, 'LOCAL_INCOME_TAX');
                $deduction = max(0.0, round((float) $tax['income_tax_amount'] + (float) $tax['local_income_tax_amount'] + $workerInsurance + $otherDeduction, 2));
                $net = round($gross - $deduction, 2);
                if ($net > $gross || $net < 0) throw new \RuntimeException($workDate . ' 적용 공제액과 실지급액을 확인해 주세요.');
                $calculatedDays[] = array_merge($row, $tax, [
                    'work_quantity' => 1.0, 'actual_work_minutes' => $actualWorkMinutes,
                    'actual_work_hours_display' => $actualWorkMinutes === null ? null : $this->workMinutesDisplay($actualWorkMinutes),
                    'daily_rate_amount' => $dailyRate, 'base_pay_amount' => $basePay,
                    'allowance_amount' => $taxableAdditional,
                    'non_taxable_amount' => $nonTaxableAdditional, 'non_taxable_reason' => $nonTaxableReason,
                    'calculation_note' => $calculationNote, 'gross_amount' => $gross,
                    'worker_social_insurance_amount' => $workerInsurance, 'employer_social_insurance_amount' => 0.0,
                    'other_deduction_amount' => $otherDeduction,
                    'deduction_amount' => $deduction, 'net_payment_amount' => $net, 'lines' => $lines,
                    'work_team_assignment_id' => $assignment === null ? null : (string) $assignment['id'],
                    'social_insurance_workplace_id' => $insuranceWorkplaceId,
                    'calculation_status_code' => $calculationStatus,
                ]);
                $summary['total_work_days'] += 1;
                $summary['total_gross_amount'] += $gross;
                $summary['total_deduction_amount'] += $deduction;
                $summary['total_net_payment_amount'] += $net;
            }
            $workplaceSizePeriod = (new WorkplaceSizePeriodModel($this->db))->resolve(
                $companyId,
                WorkplaceSizePeriodService::PURPOSE_EMPLOYMENT_INSURANCE_VOCATIONAL,
                date('Y-m-t', strtotime($month . '-01'))
            );
            $itemLines = $this->calculateItemInsuranceLines(
                $item,
                $calculatedDays,
                $month,
                $insuranceWorkplaceId ?? null,
                $workplaceSizePeriod,
                $requireDecisionReason,
                [
                    'company_id' => $companyId,
                    'document_id' => $documentId,
                    'item_id' => (string)($item['id'] ?? $item['client_key'] ?? ''),
                    'worker_client_id' => $workerId,
                    'project_id' => $projectId,
                    'employment_type_code' => $eligibilityScope['employment_type_code'],
                    'business_unit_code' => $eligibilityScope['business_unit_code'],
                    'work_scope_code' => $eligibilityScope['eligibility_work_scope_code'],
                    'scope_derivation' => $eligibilityScope,
                    'document_aggregate_candidates' => $eligibilityAggregateCandidates,
                ]
            );
            $itemInsuranceIssueKeys = [];
            foreach ($itemLines as $line) {
                $applicationStatus = (string) ($line['application_status_code'] ?? '');
                if ($applicationStatus === 'CONFIRMATION_REQUIRED') {
                    if (($line['line_type_code'] ?? '') === 'EMPLOYER_BURDEN'
                        && in_array((string) ($line['line_code'] ?? ''), ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'], true)) {
                        continue;
                    }
                    $insuranceTypeCode = (string) ($line['line_code'] ?? '');
                    if (isset($itemInsuranceIssueKeys[$insuranceTypeCode])) continue;
                    $itemInsuranceIssueKeys[$insuranceTypeCode] = true;
                    $insurancePreflight[] = [
                        'insurance_type_code' => $insuranceTypeCode,
                        'status_code' => 'CONFIRMATION_REQUIRED',
                        'blocking_code' => 'INSURANCE_ELIGIBILITY_CONFIRMATION_REQUIRED',
                        'message' => (string) ($line['calculation_message'] ?? '사회보험 가입자격 확인이 필요합니다.'),
                        'worker_client_id' => $workerId,
                    ];
                    continue;
                }
                if ($applicationStatus !== 'APPLICABLE' || ($line['calculated_amount'] ?? null) !== null) continue;
                $insurancePreflight[] = [
                    'insurance_type_code' => (string) ($line['line_code'] ?? ''),
                    'status_code' => 'CONFIRMATION_REQUIRED',
                    'blocking_code' => 'INSURANCE_STATUTORY_CALCULATION_FAILED',
                    'message' => (string) ($line['calculation_message'] ?? '사회보험 법정 자동계산에 실패했습니다.'),
                    'worker_client_id' => $workerId,
                ];
            }
            foreach ($itemLines as $line) {
                if (($line['line_type_code'] ?? '') === 'DEDUCTION') {
                    $amount = max(0.0, (float) ($line['final_amount'] ?? 0));
                    $summary['total_deduction_amount'] += $amount;
                    $summary['total_net_payment_amount'] -= $amount;
                } elseif (($line['line_type_code'] ?? '') === 'EMPLOYER_BURDEN') {
                    $summary['total_employer_burden_amount'] += max(0.0, (float) ($line['final_amount'] ?? 0));
                }
            }
            if ($summary['total_net_payment_amount'] < 0) {
                throw new \InvalidArgumentException('실제 적용한 공제액이 지급액을 초과할 수 없습니다.');
            }
            $calculationSourceKey = (string) ($item['calculation_source_key'] ?? '');
            $results[] = array_merge($item, ['business_unit' => $businessUnit, 'work_scope_code' => $this->businessUnitPolicy->technicalWorkScope($policy, $projectId), 'eligibility_scope' => $eligibilityScope, 'project_id' => $projectId, 'work_team_id' => $teamId, 'workdays' => $calculatedDays, 'lines' => $itemLines, 'summary' => $summary, 'calculation_source_hash' => hash('sha256', $calculationSourceKey)]);
        }
        $calculatedGroups=[];foreach($groups as $index=>$group){$insurance=$this->normalizeGroupInsurance($group, $requireDecisionReason);$calculatedGroups[$index]=['client_key'=>$group['client_key']??null,'business_unit'=>strtoupper(trim((string)($group['business_unit']??''))),'project_id'=>trim((string)($group['project_id']??''))?:null,'work_team_id'=>trim((string)($group['work_team_id']??''))?:null,'work_description'=>trim((string)($group['work_description']??'')),'items'=>[]]+$insurance;if($requireDecisionReason&&$calculatedGroups[$index]['work_description']==='')throw new \InvalidArgumentException(($index+1).'번째 근무그룹의 작업내용을 입력해 주세요.');}
        foreach($results as $result){$groupIndex=(int)$result['group_index'];unset($result['group_index']);$calculatedGroups[$groupIndex]['items'][]=$result;}
        $worktimeWarnings = [];
        foreach ($workdayOccurrences as $occurrences) {
            if (count($occurrences) < 2) continue;
            $worktimeWarnings[] = [
                'code' => 'MULTIPLE_GROUPS_SAME_WORKER_DATE',
                'message' => '같은 근로자와 근무일이 여러 근무그룹에 포함되어 있습니다. 시간대 중복 여부를 확인해 주세요.',
                'context' => ['occurrences' => $occurrences],
            ];
        }
        return ['success' => true, 'data' => [
            'groups' => array_values($calculatedGroups),
            'worktime_warnings' => $worktimeWarnings,
            'insurance_preflight' => [
                'status_code' => $insurancePreflight === [] ? 'CALCULATED' : 'CONFIRMATION_REQUIRED',
                'issues' => $insurancePreflight,
            ],
        ]];
    }

    public function save(array $input): array
    {
        return $this->logged('DAILY_INCOME_SAVED','save',['target_id'=>trim((string)($input['id']??''))?:null],fn():array=>$this->saveInternal($input));
    }

    private function saveInternal(array $input): array
    {
        $this->fieldPolicy->validateRequiredFields($input);
        $this->assertUnsupportedWorkdayFieldsAreEmpty($input);
        $this->assertNoDuplicateGroupWorkers($input);
        $requestKey = trim((string) ($input['request_key'] ?? ''));
        if ($requestKey === '') {
            throw new \InvalidArgumentException('저장 요청 식별값이 필요합니다.');
        }
        $payloadHash = hash('sha256', json_encode($this->normalizedPayload($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existingCommand = $this->model->findCommand($requestKey);
        if ($existingCommand) {
            if (!hash_equals((string) $existingCommand['payload_hash'], $payloadHash)) {
                throw new \RuntimeException('동일한 요청 식별값에 다른 저장내용을 사용할 수 없습니다.');
            }
            if ($existingCommand['command_status'] === 'COMPLETED') {
                return ['success' => true, 'data' => ['id' => $existingCommand['daily_employment_income_id']], 'message' => '이미 저장된 요청입니다.'];
            }
            throw new \RuntimeException('동일한 저장 요청이 처리 중입니다.');
        }
        $id = trim((string) ($input['id'] ?? '')) ?: UuidHelper::generate();
        $input['id'] = $id;
        $calculationResult = $this->calculate($input, true)['data'];
        $calculatedGroups = $calculationResult['groups'];
        $calculated = array_merge(...array_map(static fn(array $group): array => $group['items'], $calculatedGroups));
        $month = trim((string) ($input['income_year_month'] ?? ''));
        $title = trim((string) ($input['document_title'] ?? ''));
        $withholdingDate = $this->withholdingDate($input['withholding_date'] ?? null);
        if ($title === '') {
            throw new \InvalidArgumentException('제목을 확인해 주세요.');
        }
        $actor = ActorHelper::user();
        $companyId = $this->model->companyId();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $existing = $this->model->find($id, true);
            if ($existing && !in_array((string) $existing['status_code'], ['DRAFT', 'REJECTED', 'WITHDRAWN'], true)) {
                throw new \RuntimeException('현재 문서상태에서는 수정할 수 없습니다.');
            }
            try {
                $this->model->insertCommand([
                    'id' => UuidHelper::generate(), 'request_key' => $requestKey,
                    'command_type' => $existing ? 'UPDATE' : 'SAVE', 'daily_employment_income_id' => $id,
                    'payload_hash' => $payloadHash, 'command_status' => 'PROCESSING',
                    'processed_by' => $actor, 'started_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') throw $exception;
                $concurrentCommand = $this->model->findCommand($requestKey, true);
                if ($concurrentCommand === null) throw $exception;
                if (!hash_equals((string) $concurrentCommand['payload_hash'], $payloadHash)) {
                    throw new \RuntimeException('동일한 요청 식별값에 다른 저장내용을 사용할 수 없습니다.');
                }
                if ((string) $concurrentCommand['command_status'] !== 'COMPLETED') {
                    throw new \RuntimeException('동일한 저장 요청이 처리 중입니다.');
                }
                if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
                return [
                    'success' => true,
                    'data' => ['id' => (string) $concurrentCommand['daily_employment_income_id']],
                    'message' => '이미 저장된 요청입니다.',
                ];
            }
            $totals = ['total_work_days' => 0.0, 'total_gross_amount' => 0.0, 'total_deduction_amount' => 0.0, 'total_net_payment_amount' => 0.0, 'total_employer_burden_amount' => 0.0];
            foreach ($calculated as $item) foreach ($totals as $key => $_) $totals[$key] += (float) ($item['summary'][$key] ?? 0);
            $workerIds = array_unique(array_map(static fn(array $item): string => (string) $item['worker_client_id'], $calculated));
            $workTeamIds = array_unique(array_filter(array_map(static fn(array $item): string => (string) ($item['work_team_id'] ?? ''), $calculated)));
            $sortNo = $existing ? (int) $existing['sort_no'] : $this->model->nextSortNo();
            $header = ['company_id' => $companyId, 'income_year_month' => $month, 'withholding_date' => $withholdingDate, 'document_title' => $title, 'description' => $this->nullableText($input['description'] ?? null), 'memo' => $this->nullableText($input['memo'] ?? null), 'worker_count' => count($workerIds), 'work_team_count' => count($workTeamIds)] + $totals + ['updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor];
            if ($existing) $this->model->updateHeader($id, $header);
            else $this->model->insertHeader(['id' => $id, 'sort_no' => $sortNo, 'status_code' => 'DRAFT', 'created_by' => $actor] + $header);
            $persistedIds = $this->model->replaceAggregate($id,$calculatedGroups,$actor);
            $calculationSourceGroups = $calculatedGroups;
            foreach ($calculationSourceGroups as $groupIndex => &$sourceGroup) {
                $sourceGroup['id'] = $persistedIds['groups'][$groupIndex] ?? null;
                foreach ($sourceGroup['items'] as $itemIndex => &$sourceItem) {
                    $sourceItem['id'] = $persistedIds['items'][$groupIndex][$itemIndex] ?? null;
                }
                unset($sourceItem);
            }
            unset($sourceGroup);
            $calculationSourceHash = $this->sourceHasher->hash([
                'daily_employment_income_id' => $id,
                'income_year_month' => $month,
                'withholding_date' => $withholdingDate,
                'calculation_policy_version' => DailyEmploymentIncomeCalculationResultService::SNAPSHOT_SCHEMA_VERSION,
                'groups' => $calculationSourceGroups,
            ]);
            $this->calculationResults->persist(
                $id,
                $month,
                $calculatedGroups,
                $persistedIds,
                $calculationSourceHash,
                $actor
            );
            $this->model->completeCommand($requestKey, 1, $id);
            if ($ownsTransaction) $this->db->commit();
            return ['success' => true, 'data' => ['id' => $id], 'message' => '저장했습니다.'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function assertUnsupportedWorkdayFieldsAreEmpty(array $input): void
    {
        $blockedFields = [];
        $groups = is_array($input['groups'] ?? null) ? $input['groups'] : [];
        foreach ($groups as $group) {
            $items = is_array($group['items'] ?? null) ? $group['items'] : [];
            foreach ($items as $item) {
                $workdays = is_array($item['workdays'] ?? null) ? $item['workdays'] : [];
                foreach ($workdays as $workday) {
                    $hasNonTaxableEvidence = trim((string) ($workday['non_taxable_evidence'] ?? '')) !== '';
                    if ($hasNonTaxableEvidence) $blockedFields['non_taxable_evidence'] = '비과세 근거자료';
                }
            }
        }
        if ($blockedFields !== []) {
            throw new \InvalidArgumentException(
                '공식 저장계약이 아직 적용되지 않아 다음 항목을 저장할 수 없습니다: '
                . implode(', ', array_values($blockedFields))
                . '. 입력한 자료는 저장되지 않았습니다.'
            );
        }
    }

    private function assertNoDuplicateGroupWorkers(array $input): void
    {
        foreach (array_values(is_array($input['groups'] ?? null) ? $input['groups'] : []) as $groupIndex => $group) {
            $seen = [];
            foreach (array_values(is_array($group['items'] ?? null) ? $group['items'] : []) as $item) {
                $workerId = trim((string) ($item['worker_client_id'] ?? ''));
                if ($workerId === '') continue;
                if (isset($seen[$workerId])) {
                    throw new \InvalidArgumentException(($groupIndex + 1) . '번째 근무그룹에 동일한 일용근로자가 중복되어 저장할 수 없습니다.');
                }
                $seen[$workerId] = true;
            }
        }
    }

    private function nullableLimitedText(mixed $value, int $maximumLength, string $message): ?string
    {
        $text = trim((string) $value);
        if ($text === '') return null;
        if (mb_strlen($text) > $maximumLength) throw new \InvalidArgumentException($message);
        return $text;
    }

    private function workMinutesDisplay(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        if ($hours === 0) return $remainingMinutes . '분';
        if ($remainingMinutes === 0) return $hours . '시간';
        return $hours . '시간 ' . $remainingMinutes . '분';
    }

    private function calculateItemInsuranceLines(
        array $item,
        array $workdays,
        string $month,
        ?string $workplaceId,
        ?array $workplaceSizePeriod,
        bool $requireActualAmount,
        array $eligibilityContext
    ): array
    {
        $gross = round(array_sum(array_map(static fn(array $day): float => (float) ($day['gross_amount'] ?? 0), $workdays)), 2);
        $overrides = [];
        foreach (is_array($item['institution_line_overrides'] ?? null) ? $item['institution_line_overrides'] : [] as $override) {
            $code = strtoupper(trim((string) ($override['line_code'] ?? '')));
            $type = strtoupper(trim((string) ($override['line_type_code'] ?? 'DEDUCTION')));
            if ($code !== '') {
                $overrides[$type . ':' . $code] = $override;
                if ($type === 'DEDUCTION') $overrides[$code] = $override;
            }
        }
        $eligibility = $this->insuranceEligibility->resolveItem($eligibilityContext + [
            'workdays' => $workdays,
            'social_insurance_workplace_id' => $workplaceId,
        ]);
        $lines = $this->calculateEligibilityInsuranceLines(
            $eligibility,
            $month,
            $workplaceId
        );

        $manualEmploymentEligibility = $this->groupInsurancePolicy->companyBurdenResult($item, 'employment_insurance');
        $employmentEligibility = $this->componentEligibilityResult(
            $manualEmploymentEligibility,
            'UNEMPLOYMENT_BENEFIT'
        );
        $vocationalEligibility = $this->componentEligibilityResult(
            $manualEmploymentEligibility,
            'EMPLOYMENT_STABILITY_VOCATIONAL'
        );
        $industrialEligibility = $this->groupInsurancePolicy->companyBurdenResult($item, 'industrial_accident');
        $employmentStatus = $this->lineEligibilityStatus($employmentEligibility);
        $vocationalStatus = $this->lineEligibilityStatus($vocationalEligibility);
        $industrialStatus = $this->lineEligibilityStatus($industrialEligibility);

        $standard = $employmentStatus === 'APPLICABLE'
            ? $this->statutoryStandards->resolveOptional('EMPLOYMENT_INSURANCE', $month . '-01')
            : null;
        if ($standard !== null) {
            $employmentEligibility['premium_revision_id'] = (string) $standard['id'];
            $vocationalEligibility['premium_revision_id'] = (string) $standard['id'];
        }
        if ($employmentStatus === 'EXCLUDED') {
            $lines[] = $this->excludedInsuranceLine(
                'DEDUCTION',
                'EMPLOYMENT_INSURANCE',
                '고용보험',
                (string) ($employmentEligibility['reason_name'] ?? '고용보험 적용 제외'),
                $item['employment_insurance_decision_source_code_id'] ?? null
            );
        } elseif ($employmentStatus === 'CONFIRMATION_REQUIRED') {
            $lines[] = $this->unresolvedInsuranceLine('EMPLOYMENT_INSURANCE', '고용보험', $overrides['EMPLOYMENT_INSURANCE'] ?? [], (string) ($employmentEligibility['reason_detail'] ?? $employmentEligibility['reason_name'] ?? '고용보험 적용 여부를 확인해 주세요.'));
        } elseif ($standard === null) {
            $lines[] = $this->unresolvedInsuranceLine('EMPLOYMENT_INSURANCE', '고용보험', $overrides['EMPLOYMENT_INSURANCE'] ?? [], '해당 귀속월의 고용보험 법정기준이 없습니다.');
        } else {
            $value = (array) ($standard['value_data'] ?? []);
            $policy = (array) ($value['calculation_policy'] ?? []);
            $rate = isset($value['employee_rate']) && is_numeric($value['employee_rate']) ? (float) $value['employee_rate'] : null;
            if ($rate === null || $rate < 0 || empty($policy['method']) || !isset($policy['discard_below_unit'])) {
                $lines[] = $this->unresolvedInsuranceLine('EMPLOYMENT_INSURANCE', '고용보험', $overrides['EMPLOYMENT_INSURANCE'] ?? [], '고용보험 근로자부담 계산정책이 완전하지 않습니다.');
            } else {
                $trace = $this->insurancePremium->calculate($gross, $rate, $standard);
                $before = (float) $trace['calculation_before_rounding'];
                $unit = (float) $trace['rounding_unit'];
                $calculated = (float) $trace['calculated_amount'];
                $override = $overrides['EMPLOYMENT_INSURANCE'] ?? [];
                $final = array_key_exists('final_amount', $override) && $override['final_amount'] !== null
                    ? round((float) $override['final_amount'], 2) : round($calculated, 2);
                if ($final < 0) throw new \InvalidArgumentException('고용보험 실제 적용액은 음수일 수 없습니다.');
                $reasonInput = trim((string) ($override['adjustment_reason'] ?? ''));
                $reason = $requireActualAmount
                    ? $this->lineContract->adjustmentReason($reasonInput, $calculated, $final)
                    : ($reasonInput !== '' ? $reasonInput : null);
                $actualSource = trim((string) ($override['actual_application_source_code'] ?? ''));
                if ($actualSource === '') {
                    $actualSource = isset($override['final_amount']) || $month < date('Y-m')
                        ? 'HISTORICAL_ACTUAL'
                        : 'AUTO_APPLIED';
                }
                $lines[] = [
                    'line_type_code' => 'DEDUCTION', 'line_code' => 'EMPLOYMENT_INSURANCE', 'line_name_snapshot' => '고용보험',
                    'application_status_code' => 'APPLICABLE', 'calculation_basis_amount' => $gross,
                    'calculation_rate' => $rate, 'calculation_before_rounding' => round($before, 4),
                    'rounding_method_code' => (string) $trace['rounding_method_code'], 'rounding_unit' => $unit,
                    'statutory_standard_id' => (string) $standard['id'], 'coverage_id' => null,
                    'social_insurance_workplace_id' => $workplaceId, 'calculated_amount' => round($calculated, 2),
                    'final_amount' => $final, 'adjustment_reason' => $reason,
                    'statutory_calculation_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'STATUTORY_RESOLVER'),
                    'actual_application_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, $actualSource),
                    'actual_application_source_code' => $actualSource,
                    'processed_at' => date('Y-m-d H:i:s'), 'processed_by' => ActorHelper::user(),
                    'calculation_status_code' => 'CALCULATED', 'calculation_message' => null,
                    'standard_effective_from' => $standard['effective_from'] ?? ($month . '-01'),
                    'standard_effective_to' => $standard['effective_to'] ?? null,
                    'eligibility_result' => $employmentEligibility,
                ];
            }
        }
        foreach ([
            ['EMPLOYMENT_INSURANCE', '고용보험 사용자부담', $employmentStatus, $employmentEligibility],
            ['EMPLOYMENT_INSURANCE_VOCATIONAL', '고용안정·직업능력개발 부담', $vocationalStatus, $vocationalEligibility],
            ['INDUSTRIAL_ACCIDENT_INSURANCE', '산재보험 사용자부담', $industrialStatus, $industrialEligibility],
        ] as [$code, $name, $status, $lineEligibility]) {
            if ($status === 'EXCLUDED') {
                $lines[] = $this->excludedInsuranceLine(
                    'EMPLOYER_BURDEN',
                    $code,
                    $name,
                    (string) ($lineEligibility['reason_name'] ?? $name . ' 적용 제외'),
                    $code === 'INDUSTRIAL_ACCIDENT_INSURANCE'
                        ? ($item['industrial_accident_decision_source_code_id'] ?? null)
                        : ($item['employment_insurance_decision_source_code_id'] ?? null)
                );
                $lines[array_key_last($lines)]['eligibility_result'] = $lineEligibility;
                continue;
            }
            if ($status === 'CONFIRMATION_REQUIRED') {
                $lines[] = $this->unresolvedEmployerInsuranceLine(
                    $code,
                    $name,
                    $gross,
                    $status,
                    null,
                    (string) ($lineEligibility['reason_detail'] ?? $lineEligibility['reason_name'] ?? '가입자격 확인이 필요합니다.')
                );
                $lines[array_key_last($lines)]['eligibility_result'] = $lineEligibility;
                continue;
            }
            $standardForLine = $code === 'INDUSTRIAL_ACCIDENT_INSURANCE'
                ? $this->statutoryStandards->resolveOptional('INDUSTRIAL_ACCIDENT', $month . '-01')
                : $standard;
            if ($standardForLine !== null) {
                $lineEligibility['premium_revision_id'] = (string) $standardForLine['id'];
            }
            $rate = null;
            $sizePeriodId = null;
            if ($code === 'EMPLOYMENT_INSURANCE') {
                $rate = $standardForLine['value_data']['employer_rate'] ?? null;
            } elseif ($code === 'EMPLOYMENT_INSURANCE_VOCATIONAL' && $standardForLine && $workplaceSizePeriod) {
                try {
                    $rate = (new WorkplaceSizeRateResolver())->resolveAdditionalEmployerRate($workplaceSizePeriod, (array) $standardForLine['value_data']);
                    $sizePeriodId = (string) $workplaceSizePeriod['id'];
                } catch (\Throwable) {
                    $rate = null;
                }
            } elseif ($code === 'INDUSTRIAL_ACCIDENT_INSURANCE' && $standardForLine) {
                $rates = array_values((array) ($standardForLine['value_data']['industry_rates'] ?? []));
                if (count($rates) === 1 && is_numeric($rates[0]['employer_rate'] ?? null)) $rate = (float) $rates[0]['employer_rate'];
            }
            if (!$standardForLine || !is_numeric($rate)) {
                $lines[] = $this->unresolvedEmployerInsuranceLine($code, $name, $gross, $status, $standardForLine,
                    $code === 'EMPLOYMENT_INSURANCE_VOCATIONAL' && !$workplaceSizePeriod
                        ? '확정된 회사규모 기간자료가 없습니다.'
                        : ($code === 'INDUSTRIAL_ACCIDENT_INSURANCE' ? '산재보험 공식 사업종류 요율 Scope를 하나로 확정할 수 없습니다.' : '공식 사용자부담 요율이 없습니다.'));
                $lines[array_key_last($lines)]['eligibility_result'] = $lineEligibility;
                continue;
            }
            try {
                $trace = $this->insurancePremium->calculate($gross, (float) $rate, $standardForLine);
                $lines[] = $this->calculatedEmployerInsuranceLine($code, $name, $trace, $standardForLine, $workplaceId, $sizePeriodId);
                $lines[array_key_last($lines)]['eligibility_result'] = $lineEligibility;
            } catch (\Throwable $exception) {
                $lines[] = $this->unresolvedEmployerInsuranceLine($code, $name, $gross, $status, $standardForLine, $exception->getMessage());
                $lines[array_key_last($lines)]['eligibility_result'] = $lineEligibility;
            }
        }
        $eligibilityByLineCode = [
            'EMPLOYMENT_INSURANCE' => $employmentEligibility,
            'EMPLOYMENT_INSURANCE_VOCATIONAL' => $vocationalEligibility,
            'INDUSTRIAL_ACCIDENT_INSURANCE' => $industrialEligibility,
        ];
        foreach ($lines as &$line) {
            $lineCode = (string) ($line['line_code'] ?? '');
            if (!isset($line['eligibility_result']) && isset($eligibilityByLineCode[$lineCode])) {
                $line['eligibility_result'] = $eligibilityByLineCode[$lineCode];
            }
            $key = strtoupper((string) ($line['line_type_code'] ?? '')) . ':' . strtoupper((string) ($line['line_code'] ?? ''));
            $override = $overrides[$key] ?? null;
            if (!$override) continue;
            if (($line['application_status_code'] ?? null) === 'CONFIRMATION_REQUIRED') continue;
            $calculated = $line['calculated_amount'] ?? null;
            $final = max(0.0, round((float) ($override['final_amount'] ?? 0), 2));
            $reason = trim((string) ($override['adjustment_reason'] ?? ''));
            $changed = $calculated === null ? abs($final) >= 0.01 : abs($final - (float) $calculated) >= 0.01;
            if ($changed && $reason === '' && $requireActualAmount) throw new \InvalidArgumentException('자동계산액과 다른 적용금액에는 적용사유가 필요합니다.');
            $source = strtoupper(trim((string) ($override['actual_application_source_code'] ?? '')));
            if ($source === '') $source = $changed ? 'HISTORICAL_ACTUAL' : 'AUTO_APPLIED';
            $line['final_amount'] = $final;
            $line['adjustment_amount'] = $calculated === null ? 0.0 : round($final - (float) $calculated, 2);
            $line['adjustment_reason'] = $reason !== '' ? $reason : null;
            $line['actual_application_source_code_id'] = $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, $source);
            $line['actual_application_source_code'] = $source;
            $line['processed_at'] = date('Y-m-d H:i:s');
            $line['processed_by'] = ActorHelper::user();
        }
        unset($line);
        if ($requireActualAmount) {
            foreach ($lines as $line) {
                if (($line['application_status_code'] ?? null) !== 'APPLICABLE') continue;
                if (!in_array((string) ($line['line_code'] ?? ''), ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'], true)) continue;
                if (($line['final_amount'] ?? null) === null) {
                    throw new \InvalidArgumentException(($line['line_name_snapshot'] ?? '사회보험') . ' 실제 적용액을 입력해 주세요.');
                }
            }
        }
        return $lines;
    }

    private function componentEligibilityResult(array $result, string $componentCode): array
    {
        foreach ((array) ($result['component_results'] ?? []) as $component) {
            if (!is_array($component) || (string) ($component['component_code'] ?? '') !== $componentCode) continue;
            $companyBurdenSource = in_array((string) ($result['decision_source_code'] ?? ''), [
                'GROUP_MANUAL_SETTING',
                DailyEmploymentIncomeGroupInsurancePolicyService::MANUAL_SOURCE_CODE,
                DailyEmploymentIncomeGroupInsurancePolicyService::BUSINESS_POLICY_SOURCE_CODE,
            ], true);
            $status = match ((string) ($component['status_code'] ?? '')) {
                'APPLICABLE' => $companyBurdenSource ? 'APPLICABLE' : 'ELIGIBLE',
                'EXCLUDED' => $companyBurdenSource ? 'EXCLUDED' : 'NOT_ELIGIBLE',
                default => 'CONFIRMATION_REQUIRED',
            };
            return array_merge($result, [
                'status' => $status,
                'result_code' => $status,
                'reason_code' => $component['reason_code'] ?? ($result['reason_code'] ?? null),
                'reason_name' => $component['reason_name'] ?? ($result['reason_name'] ?? null),
                'reason_detail' => $component['reason_detail'] ?? ($result['reason_detail'] ?? null),
                'decision_basis_code' => in_array($status, ['ELIGIBLE', 'APPLICABLE'], true) ? ($component['reason_code'] ?? $result['decision_basis_code'] ?? null) : null,
                'decision_basis_name' => in_array($status, ['ELIGIBLE', 'APPLICABLE'], true) ? ($component['reason_name'] ?? null) : null,
                'decision_basis_detail' => in_array($status, ['ELIGIBLE', 'APPLICABLE'], true) ? ($component['reason_detail'] ?? null) : null,
                'passed_conditions' => in_array($status, ['ELIGIBLE', 'APPLICABLE'], true) ? (array) ($component['evaluated_rules'] ?? []) : [],
                'component_results' => [$component],
            ]);
        }
        return $result;
    }

    private function lineEligibilityStatus(array $result): string
    {
        return match ((string) ($result['status'] ?? $result['result_code'] ?? 'CONFIRMATION_REQUIRED')) {
            'ELIGIBLE', 'APPLICABLE' => 'APPLICABLE',
            'NOT_ELIGIBLE', 'EXCLUDED' => 'EXCLUDED',
            default => 'CONFIRMATION_REQUIRED',
        };
    }

    private function calculateEligibilityInsuranceLines(array $eligibility, string $month, ?string $workplaceId): array
    {
        $lines = [];
        $healthAmounts = ['DEDUCTION' => null, 'EMPLOYER_BURDEN' => null];
        foreach ([
            ['NATIONAL_PENSION', '국민연금'],
            ['HEALTH_INSURANCE', '건강보험'],
            ['LONG_TERM_CARE', '장기요양보험'],
        ] as [$code, $name]) {
            $result = (array)($eligibility[$code] ?? []);
            $status = (string)($result['status'] ?? 'CONFIRMATION_REQUIRED');
            $standard = null;
            if ($status === 'ELIGIBLE') {
                $standard = $this->statutoryStandards->resolveOptional($code, $month . '-01');
            }
            foreach (['DEDUCTION' => $name, 'EMPLOYER_BURDEN' => $name . ' 회사부담'] as $lineType => $lineName) {
                if ($status === 'CONFIRMATION_REQUIRED') {
                    $missing = implode(', ', array_map(
                        static fn(array $row): string => (string)($row['field'] ?? ''),
                        (array)($result['missing_inputs'] ?? [])
                    ));
                    $line = $this->eligibilityConfirmationLine(
                        $lineType,
                        $code,
                        $lineName,
                        $standard,
                        $workplaceId,
                        $missing === '' ? '가입자격 확인이 필요합니다.' : '가입자격 필수 입력이 없습니다: ' . $missing
                    );
                } elseif ($status === 'NOT_ELIGIBLE') {
                    $line = $this->excludedInsuranceLine(
                        $lineType,
                        $code,
                        $lineName,
                        (string)($result['reason_code'] ?? '가입자격 적용 제외'),
                        null
                    );
                } elseif ($standard === null) {
                    $line = $this->eligibilityConfirmationLine($lineType, $code, $lineName, null, $workplaceId, '보험료 Revision이 없습니다.');
                } else {
                    $values = (array)($standard['value_data'] ?? []);
                    $rate = $code === 'LONG_TERM_CARE'
                        ? ($values['rate_value'] ?? null)
                        : ($lineType === 'DEDUCTION' ? ($values['employee_rate'] ?? null) : ($values['employer_rate'] ?? null));
                    $basis = $code === 'LONG_TERM_CARE'
                        ? $healthAmounts[$lineType]
                        : ($result['evaluated_income_amount'] ?? null);
                    if (!is_numeric($rate) || !is_numeric($basis)) {
                        $line = $this->eligibilityConfirmationLine($lineType, $code, $lineName, $standard, $workplaceId, '보험료 계산기초 또는 요율이 없습니다.');
                    } else {
                        $trace = $this->insurancePremium->calculate((float)$basis, (float)$rate, $standard);
                        $line = $this->calculatedInsuranceLine($lineType, $code, $lineName, $trace, $standard, $workplaceId);
                    }
                }
                $line['eligibility_result'] = $result;
                $lines[] = $line;
                if ($code === 'HEALTH_INSURANCE' && $status === 'ELIGIBLE') {
                    $healthAmounts[$lineType] = $line['calculated_amount'] ?? null;
                }
            }
        }
        return $lines;
    }

    private function eligibilityConfirmationLine(
        string $lineType,
        string $code,
        string $name,
        ?array $standard,
        ?string $workplaceId,
        string $message
    ): array {
        $values = (array) ($standard['value_data'] ?? []);
        $rate = $code === 'LONG_TERM_CARE'
            ? ($values['rate_value'] ?? null)
            : ($lineType === 'DEDUCTION' ? ($values['employee_rate'] ?? null) : ($values['employer_rate'] ?? null));
        return [
            'line_type_code' => $lineType,
            'line_code' => $code,
            'line_name_snapshot' => $name,
            'application_status_code' => 'CONFIRMATION_REQUIRED',
            'calculation_basis_amount' => null,
            'calculation_rate' => is_numeric($rate) ? (float) $rate : null,
            'calculation_before_rounding' => null,
            'rounding_method_code' => null,
            'rounding_unit' => null,
            'statutory_standard_id' => $standard['id'] ?? null,
            'coverage_id' => null,
            'social_insurance_workplace_id' => $workplaceId,
            'calculated_amount' => null,
            'final_amount' => null,
            'adjustment_amount' => null,
            'adjustment_reason' => null,
            'statutory_calculation_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'UNRESOLVED'),
            'actual_application_source_code_id' => null,
            'actual_application_source_code' => null,
            'processed_at' => null,
            'processed_by' => null,
            'calculation_status_code' => 'NEEDS_CONFIRMATION',
            'calculation_message' => $message,
            'standard_effective_from' => $standard['effective_from'] ?? null,
            'standard_effective_to' => $standard['effective_to'] ?? null,
        ];
    }

    private function calculatedInsuranceLine(
        string $lineType,
        string $code,
        string $name,
        array $trace,
        array $standard,
        ?string $workplaceId
    ): array {
        return [
            'line_type_code' => $lineType,
            'line_code' => $code,
            'line_name_snapshot' => $name,
            'application_status_code' => 'APPLICABLE',
            'calculation_basis_amount' => $trace['calculation_basis_amount'],
            'calculation_rate' => $trace['calculation_rate'],
            'calculation_before_rounding' => $trace['calculation_before_rounding'],
            'rounding_method_code' => $trace['rounding_method_code'],
            'rounding_unit' => $trace['rounding_unit'],
            'statutory_standard_id' => (string) $standard['id'],
            'coverage_id' => null,
            'social_insurance_workplace_id' => $workplaceId,
            'calculated_amount' => $trace['calculated_amount'],
            'final_amount' => $trace['calculated_amount'],
            'adjustment_reason' => null,
            'statutory_calculation_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'STATUTORY_RESOLVER'),
            'actual_application_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, 'AUTO_APPLIED'),
            'actual_application_source_code' => 'AUTO_APPLIED',
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_by' => ActorHelper::user(),
            'calculation_status_code' => 'CALCULATED',
            'calculation_message' => null,
            'standard_effective_from' => $standard['effective_from'] ?? null,
            'standard_effective_to' => $standard['effective_to'] ?? null,
        ];
    }

    private function unresolvedApplicableInsuranceLine(string $code, string $name, string $message): array
    {
        $line = $this->unresolvedInsuranceLine($code, $name, [], $message);
        $line['application_status_code'] = 'APPLICABLE';
        return $line;
    }

    private function roundInsuranceValue(float $value, array $policy): float
    {
        $unit = max(1, (int) ($policy['discard_below_unit'] ?? 1));
        return match ((string) ($policy['method'] ?? '')) {
            'TRUNCATE', 'FLOOR' => floor($value / $unit) * $unit,
            'ROUND' => round($value / $unit) * $unit,
            'ROUND_UP', 'CEIL' => ceil($value / $unit) * $unit,
            default => throw new \RuntimeException('공식 끝수처리 정책이 없습니다.'),
        };
    }

    private function insuranceResultLimitsReady(array $values, array $policy): bool
    {
        $hasMinimum = isset($values['minimum_result_amount']) && $values['minimum_result_amount'] !== '';
        $hasMaximum = isset($values['maximum_result_amount']) && $values['maximum_result_amount'] !== '';
        if (!$hasMinimum && !$hasMaximum) return true;
        $stage = (string) ($values['result_limit_application_stage'] ?? '');
        if (in_array($stage, ['AFTER_PREMIUM_CALCULATION', 'AFTER_ROUNDING'], true)) return true;
        if ($stage !== '' || ($policy['method'] ?? '') !== 'TRUNCATE') return false;
        $unit = max(1, (int) ($policy['discard_below_unit'] ?? 1));
        return (!$hasMinimum || (int) $values['minimum_result_amount'] % $unit === 0)
            && (!$hasMaximum || (int) $values['maximum_result_amount'] % $unit === 0);
    }

    private function calculatedEmployerInsuranceLine(string $code, string $name, array $trace, array $standard, ?string $workplaceId, ?string $sizePeriodId): array
    {
        $amount = (float) $trace['calculated_amount'];
        return [
            'line_type_code' => 'EMPLOYER_BURDEN', 'line_code' => $code, 'line_name_snapshot' => $name,
            'application_status_code' => 'APPLICABLE', 'statutory_standard_id' => (string) $standard['id'],
            'social_insurance_workplace_id' => $workplaceId, 'workplace_size_period_id' => $sizePeriodId,
            'calculation_basis_amount' => $trace['calculation_basis_amount'], 'calculation_rate' => $trace['calculation_rate'],
            'calculation_before_rounding' => $trace['calculation_before_rounding'], 'rounding_method_code' => $trace['rounding_method_code'],
            'rounding_unit' => $trace['rounding_unit'], 'calculated_amount' => $amount, 'final_amount' => $amount,
            'adjustment_reason' => null, 'calculation_status_code' => 'CALCULATED', 'calculation_message' => null,
            'statutory_calculation_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'STATUTORY_RESOLVER'),
            'actual_application_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, 'AUTO_APPLIED'),
            'actual_application_source_code' => 'AUTO_APPLIED', 'processed_at' => date('Y-m-d H:i:s'), 'processed_by' => ActorHelper::user(),
            'standard_effective_from' => $standard['effective_from'] ?? null, 'standard_effective_to' => $standard['effective_to'] ?? null,
        ];
    }

    private function unresolvedEmployerInsuranceLine(string $code, string $name, float $gross, string $status, ?array $standard, string $message): array
    {
        $applicationStatus = $status === 'APPLICABLE' ? 'CONFIRMATION_REQUIRED' : $status;
        return [
            'line_type_code' => 'EMPLOYER_BURDEN', 'line_code' => $code, 'line_name_snapshot' => $name,
            'application_status_code' => $applicationStatus,
            'calculation_basis_amount' => $gross, 'statutory_standard_id' => $standard['id'] ?? null,
            'calculated_amount' => null, 'final_amount' => null, 'adjustment_reason' => null,
            'calculation_status_code' => 'NEEDS_CONFIRMATION', 'calculation_message' => $message,
            'standard_effective_from' => $standard['effective_from'] ?? null, 'standard_effective_to' => $standard['effective_to'] ?? null,
        ];
    }

    private function excludedInsuranceLine(
        string $lineType,
        string $code,
        string $name,
        string $reason,
        mixed $sourceCodeId
    ): array {
        return [
            'line_type_code' => $lineType,
            'line_code' => $code,
            'line_name_snapshot' => $name,
            'application_status_code' => 'EXCLUDED',
            'calculated_amount' => 0.0,
            'final_amount' => 0.0,
            'adjustment_amount' => 0.0,
            'adjustment_reason' => trim($reason),
            'statutory_calculation_source_code_id' => null,
            'actual_application_source_code_id' => $sourceCodeId,
            'actual_application_source_code' => 'MANUAL_INTERIM_GROUP',
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_by' => ActorHelper::user(),
            'calculation_status_code' => 'EXCLUDED',
            'calculation_message' => trim($reason),
        ];
    }

    private function unresolvedInsuranceLine(string $code, string $name, array $override, string $message): array
    {
        return [
            'line_type_code' => 'DEDUCTION', 'line_code' => $code, 'line_name_snapshot' => $name,
            'application_status_code' => 'CONFIRMATION_REQUIRED', 'calculated_amount' => null, 'final_amount' => null,
            'adjustment_reason' => trim((string) ($override['adjustment_reason'] ?? '')) ?: null,
            'statutory_calculation_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP, 'UNRESOLVED'),
            'actual_application_source_code_id' => $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, 'HISTORICAL_ACTUAL'),
            'actual_application_source_code' => 'HISTORICAL_ACTUAL',
            'processed_at' => date('Y-m-d H:i:s'), 'processed_by' => ActorHelper::user(),
            'calculation_status_code' => 'NEEDS_CONFIRMATION', 'calculation_message' => $message,
        ];
    }

    public function submissionPreflight(string $id): array
    {
        $blockingErrors = [];
        $warnings = [];
        $addError = static function (array &$target, string $code, string $message, array $context = []): void {
            $target[] = ['code' => $code, 'message' => $message, 'context' => $context];
        };
        $addWarning = static function (array &$target, string $code, string $message, array $context = []): void {
            $target[] = ['code' => $code, 'message' => $message, 'context' => $context];
        };

        $header = $this->model->find($id);
        if ($header === null) {
            $addError($blockingErrors, 'DOCUMENT_NOT_FOUND', '일용근로소득 문서를 찾을 수 없습니다.');
            return ['success' => true, 'data' => $this->preflightResult($blockingErrors, $warnings, 'BLOCKED', 'BLOCKED', 'NOT_APPLICABLE', null)];
        }
        if (!in_array((string) $header['status_code'], ['DRAFT', 'REJECTED', 'WITHDRAWN'], true)) {
            $addError($blockingErrors, 'DOCUMENT_STATUS_NOT_SUBMITTABLE', '현재 문서상태에서는 결재요청할 수 없습니다.', ['status_code' => $header['status_code']]);
        }
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $header['income_year_month'])) {
            $addError($blockingErrors, 'INVALID_DOCUMENT_PERIOD', '귀속연월을 확인해 주세요.');
        }

        $detail = $this->detail($id)['data'];
        $groups = $detail['groups'] ?? [];
        if ($groups === []) {
            $addError($blockingErrors, 'GROUP_REQUIRED', '근무그룹을 한 건 이상 입력해 주세요.');
        }
        $inputGroups = [];
        $storedScopeKeys = [];
        $storedSourceGroups = [];
        foreach ($groups as $groupIndex => $group) {
            if (strtoupper((string) ($group['business_unit'] ?? '')) === 'CONSTRUCTION') {
                foreach (['employment_insurance'=>'고용보험','industrial_accident'=>'산재보험'] as $prefix=>$label) {
                    $status = (string) ($group[$prefix . '_application_status_code'] ?? '');
                    if (!in_array($status, ['APPLICABLE', 'EXCLUDED'], true)) {
                        $addError($blockingErrors, strtoupper($prefix) . '_GROUP_DECISION_REQUIRED',
                            ($groupIndex + 1) . '번째 Group의 ' . $label . ' 회사부담 여부를 저장해 주세요.',
                            ['group_id'=>$group['id'] ?? null,'insurance_type'=>$prefix]);
                    }
                }
            }
            $items = is_array($group['items'] ?? null) ? $group['items'] : [];
            if ($items === []) {
                $addError($blockingErrors, 'ITEM_REQUIRED', ($groupIndex + 1) . '번째 근무그룹에 작업자를 추가해 주세요.');
            }
            $inputItems = [];
            $sourceItems = [];
            foreach ($items as $itemIndex => $item) {
                $workdays = is_array($item['workdays'] ?? null) ? $item['workdays'] : [];
                if ($workdays === []) {
                    $addError($blockingErrors, 'WORKDAY_REQUIRED', '작업자별 근무일을 한 건 이상 입력해 주세요.', ['item_id' => $item['id'] ?? null]);
                }
                $inputWorkdays = [];
                $sourceWorkdays = [];
                $lineByWorkday = [];
                foreach (($item['lines'] ?? []) as $line) {
                    $lineByWorkday[(string) ($line['daily_employment_income_workday_id'] ?? '')][] = $line;
                }
                foreach ($workdays as $workday) {
                    $workDate = (string) ($workday['work_date'] ?? '');
                    $scopeKey = implode('|', [
                        (string) ($item['worker_client_id'] ?? ''), $workDate,
                        strtoupper((string) ($group['business_unit'] ?? '')),
                        (string) ($group['project_id'] ?? ''), (string) ($group['work_team_id'] ?? ''),
                    ]);
                    if (isset($storedScopeKeys[$scopeKey])) {
                        $addError($blockingErrors, 'DUPLICATE_WORKDAY_SCOPE', '동일 작업자·근무일·지급원천이 문서 안에서 중복되었습니다.', ['work_date' => $workDate]);
                    }
                    $storedScopeKeys[$scopeKey] = true;
                    foreach ($this->model->duplicateWorkdayDocuments(
                        $id,
                        (string) $header['company_id'],
                        (string) $item['worker_client_id'],
                        $workDate,
                        strtoupper((string) $group['business_unit']),
                        $this->nullableText($group['project_id'] ?? null),
                        $this->nullableText($group['work_team_id'] ?? null)
                    ) as $duplicate) {
                        $addError($blockingErrors, 'DUPLICATE_ACTIVE_WORKDAY', '다른 활성 문서에 동일 작업자·근무일·지급원천이 존재합니다.', [
                            'work_date' => $workDate, 'duplicate_document_id' => $duplicate['id'],
                        ]);
                    }
                    if ((float) ($workday['non_taxable_amount'] ?? 0) !== 0.0
                        && trim((string) ($workday['non_taxable_reason'] ?? '')) === '') {
                        $addError($blockingErrors, 'NON_TAX_REASON_REQUIRED', '비과세증감에는 비과세 적용사유가 필요합니다.', ['work_date' => $workDate]);
                    }
                    $inputWorkdays[] = [
                        'work_date' => $workDate,
                        'actual_work_minutes' => $workday['actual_work_minutes'] ?? null,
                        'daily_rate_amount' => (float) ($workday['daily_rate_amount'] ?? 0),
                        'taxable_additional_amount' => (float) ($workday['allowance_amount'] ?? 0),
                        'non_taxable_additional_amount' => (float) ($workday['non_taxable_amount'] ?? 0),
                        'non_taxable_reason' => $workday['non_taxable_reason'] ?? null,
                    ];
                    $sourceWorkdays[] = $workday + ['lines' => $lineByWorkday[(string) ($workday['id'] ?? '')] ?? []];
                }
                $inputItems[] = [
                    'worker_client_id' => $item['worker_client_id'] ?? '',
                    'work_type_code' => $item['work_type_code'] ?? '',
                    'work_description' => $item['work_description'] ?? '',
                    'institution_line_overrides' => array_values(array_map(
                        static fn(array $line): array => [
                            'line_type_code' => $line['line_type_code'],
                            'line_code' => $line['line_code'],
                            'final_amount' => $line['final_amount'],
                            'adjustment_reason' => $line['adjustment_reason'] ?? null,
                            'actual_application_source_code' => $line['calculated_amount'] === null
                                ? 'HISTORICAL_ACTUAL'
                                : ((float) $line['calculated_amount'] === (float) $line['final_amount'] ? 'AUTO_APPLIED' : 'MANUAL_OVERRIDE'),
                        ],
                        array_filter($item['lines'] ?? [], static fn(array $line): bool =>
                            ($line['daily_employment_income_workday_id'] ?? null) === null
                            && in_array(($line['line_type_code'] ?? ''), ['DEDUCTION', 'EMPLOYER_BURDEN'], true)
                        )
                    )),
                    'workdays' => $inputWorkdays,
                ];
                $sourceItems[] = $item + ['workdays' => $sourceWorkdays];
            }
            $inputGroups[] = [
                'business_unit' => $group['business_unit'] ?? '',
                'project_id' => $group['project_id'] ?? null,
                'work_team_id' => $group['work_team_id'] ?? null,
                'work_description' => $group['work_description'] ?? '',
                'employment_insurance_application_status_code' => $group['employment_insurance_application_status_code'] ?? null,
                'employment_insurance_decision_reason' => $group['employment_insurance_decision_reason'] ?? null,
                'employment_insurance_decision_source_code_id' => $group['employment_insurance_decision_source_code_id'] ?? null,
                'employment_insurance_set_by' => $group['updated_by'] ?? null,
                'employment_insurance_set_at' => $group['updated_at'] ?? null,
                'industrial_accident_application_status_code' => $group['industrial_accident_application_status_code'] ?? null,
                'industrial_accident_decision_reason' => $group['industrial_accident_decision_reason'] ?? null,
                'industrial_accident_decision_source_code_id' => $group['industrial_accident_decision_source_code_id'] ?? null,
                'industrial_accident_set_by' => $group['updated_by'] ?? null,
                'industrial_accident_set_at' => $group['updated_at'] ?? null,
                'items' => $inputItems,
            ];
            $storedSourceGroups[] = $group + ['items' => $sourceItems];
        }

        $calculationStatus = 'CALCULATED';
        $insuranceStatus = 'CALCULATED';
        $currentSourceGroups = [];
        try {
            $recalculated = $this->calculate([
                'id' => $id,
                'income_year_month' => $header['income_year_month'],
                'withholding_date' => $header['withholding_date'],
                'groups' => $inputGroups,
            ])['data'];
            if (($recalculated['insurance_preflight']['status_code'] ?? '') !== 'CALCULATED') {
                $insuranceStatus = 'CONFIRMATION_REQUIRED';
                foreach ($recalculated['insurance_preflight']['issues'] ?? [] as $issue) {
                    $addError($blockingErrors, (string) ($issue['blocking_code'] ?? 'INSURANCE_CONFIRMATION_REQUIRED'), (string) ($issue['message'] ?? '사회보험 확인이 필요합니다.'), $issue);
                }
            }
            foreach ($recalculated['worktime_warnings'] ?? [] as $warning) {
                $addWarning($warnings, (string) ($warning['code'] ?? 'MULTIPLE_GROUPS_SAME_WORKER_DATE'), (string) ($warning['message'] ?? '같은 근무일의 복수 근무그룹을 확인해 주세요.'), (array) ($warning['context'] ?? []));
            }
            $this->compareStoredCalculation($groups, $recalculated['groups'] ?? [], $blockingErrors);
            $currentSourceGroups = $recalculated['groups'] ?? [];
            foreach ($currentSourceGroups as $groupIndex => &$sourceGroup) {
                $sourceGroup['id'] = $groups[$groupIndex]['id'] ?? null;
                foreach ((array) ($sourceGroup['items'] ?? []) as $itemIndex => &$sourceItem) {
                    $sourceItem['id'] = $groups[$groupIndex]['items'][$itemIndex]['id'] ?? null;
                }
                unset($sourceItem);
            }
            unset($sourceGroup);
        } catch (\Throwable) {
            $calculationStatus = 'BLOCKED';
            $addError($blockingErrors, 'CALCULATION_REPRODUCTION_FAILED', '저장된 계산결과를 재현할 수 없습니다.');
        }

        $sourceHash = $this->sourceHasher->hash([
            'daily_employment_income_id' => $id,
            'income_year_month' => $header['income_year_month'],
            'withholding_date' => $header['withholding_date'],
            'calculation_policy_version' => DailyEmploymentIncomeCalculationResultService::SNAPSHOT_SCHEMA_VERSION,
            'groups' => $currentSourceGroups !== [] ? $currentSourceGroups : $storedSourceGroups,
        ]);
        $latestRevision = $this->calculationResults->latest($id);
        $requiredResultTypes = [
            'NATIONAL_PENSION',
            'HEALTH_INSURANCE',
            'LONG_TERM_CARE_INSURANCE',
            'EMPLOYMENT_INSURANCE',
            'INDUSTRIAL_ACCIDENT_INSURANCE',
        ];
        $storedResultTypes = [];
        if (!is_array($latestRevision)
            || (string) ($latestRevision['status_code'] ?? '') !== 'CALCULATED'
            || !hash_equals((string) ($latestRevision['source_hash'] ?? ''), $sourceHash)) {
            $calculationStatus = 'RECALCULATION_REQUIRED';
            $addError(
                $blockingErrors,
                'RECALCULATION_REQUIRED',
                '입력자료 또는 계산정책이 변경되었습니다. 최신 계산결과를 저장한 후 결재요청해 주세요.',
                [
                    'current_source_hash' => $sourceHash,
                    'calculation_revision_id' => $latestRevision['id'] ?? null,
                    'revision_source_hash' => $latestRevision['source_hash'] ?? null,
                ]
            );
        } else {
            foreach ((array) ($latestRevision['results'] ?? []) as $result) {
                $storedResultTypes[(string) ($result['result_type_code'] ?? '')] = true;
                $snapshot = is_array($result['eligibility_snapshot'] ?? null)
                    ? $result['eligibility_snapshot']
                    : [];
                $itemId = (string) ($result['daily_employment_income_item_id'] ?? '');
                if ($itemId === '' || $itemId !== (string) ($snapshot['source_item_id'] ?? '')) {
                    $addError($blockingErrors, 'CALCULATION_RESULT_ITEM_MISMATCH', '최신 계산결과의 작업자 원천 연결이 일치하지 않습니다.', ['result_id' => $result['id'] ?? null]);
                }
            }
            $missingTypes = array_values(array_diff($requiredResultTypes, array_keys($storedResultTypes)));
            if ($missingTypes !== []) {
                $calculationStatus = 'RECALCULATION_REQUIRED';
                $addError($blockingErrors, 'CALCULATION_RESULT_INCOMPLETE', '최신 계산결과에 보험 5종 Snapshot이 모두 저장되어 있지 않습니다.', ['missing_result_types' => $missingTypes]);
            }
        }
        if ($this->model->latestCompletedCommand($id) === null) {
            $addError($blockingErrors, 'COMPLETED_SAVE_COMMAND_REQUIRED', '완료된 저장 명령을 확인할 수 없습니다.');
        }
        $approvalReadiness = $this->model->approvalTemplateReadiness(self::DOCUMENT_TYPE);
        if (empty($approvalReadiness['ready'])) {
            $addError(
                $blockingErrors,
                'APPROVAL_TEMPLATE_REQUIRED',
                '일용근로소득 결재선이 준비되지 않았습니다.',
                $approvalReadiness
            );
        }
        $addWarning(
            $warnings,
            'REGULAR_DAILY_IDENTITY_UNVERIFIED',
            '직원과 일용 작업자를 연결하는 공식 개인 식별 SSOT가 없어 상용·일용 중복귀속은 자동 판정하지 않았습니다.'
        );
        if (!in_array($calculationStatus, ['BLOCKED', 'RECALCULATION_REQUIRED'], true) && $blockingErrors !== []) $calculationStatus = 'CONFIRMATION_REQUIRED';
        return ['success' => true, 'data' => $this->preflightResult(
            $blockingErrors,
            $warnings,
            $calculationStatus,
            $insuranceStatus,
            $this->hasStoredNonTax($groups) ? 'CONFIRMATION_REQUIRED' : 'NOT_APPLICABLE',
            $sourceHash
        )];
    }

    public function submit(string $id): array
    {
        return $this->logged('DAILY_INCOME_SUBMITTED','submit',['target_id'=>$id],fn():array=>$this->submitInternal($id));
    }

    private function submitInternal(string $id): array
    {
        return $this->transaction(function () use ($id): array {
            $activeRequest = $this->model->activeApprovalRequest(self::DOCUMENT_TYPE, $id, true);
            if ($activeRequest !== null) {
                return [
                    'success' => true,
                    'data' => ['request_id' => (string) $activeRequest['id'], 'current_step' => (int) $activeRequest['current_step']],
                    'message' => '이미 진행 중인 결재요청입니다.',
                ];
            }
            $preflight = $this->submissionPreflight($id)['data'];
            if (empty($preflight['can_submit'])) {
                throw new \RuntimeException((string) ($preflight['blocking_errors'][0]['message'] ?? '결재요청 사전검증을 통과하지 못했습니다.'));
            }
            [$userId, $actor] = $this->identity();
            $result = (new ApprovalWorkflowService($this->db))->submit(self::DOCUMENT_TYPE, $id, $userId, $actor);
            $this->model->updateWorkflow($id, 'PENDING', (string) $result['request_id'], $actor);
            return ['success' => true, 'data' => $result, 'message' => '결재를 요청했습니다.'];
        });
    }

    public function withdraw(string $requestId): array
    {
        return $this->logged('DAILY_INCOME_WITHDRAWN','withdraw',['request_id'=>$requestId],fn():array=>$this->withdrawInternal($requestId));
    }

    private function withdrawInternal(string $requestId): array
    {
        return $this->transaction(function () use ($requestId): array {
            [$userId, $actor] = $this->identity();
            $request = (new ApprovalWorkflowService($this->db))->withdraw($requestId, self::DOCUMENT_TYPE, $userId, $actor);
            $this->model->updateWorkflow((string) $request['document_id'], 'WITHDRAWN', $requestId, $actor);
            return ['success' => true, 'message' => '기안을 회수했습니다.'];
        });
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        return $this->logged('DAILY_INCOME_APPROVAL_ACTED','act',['step_id'=>$stepId,'decision'=>strtoupper(trim($decision))],fn():array=>$this->actInternal($stepId,$decision,$comment));
    }

    private function actInternal(string $stepId, string $decision, ?string $comment): array
    {
        return $this->transaction(function () use ($stepId, $decision, $comment): array {
            [$userId, $actor] = $this->identity();
            $generation = new DailyEmploymentIncomeAccountingGenerationService($this->db, $this->closureFailureInjector);
            if (strtolower(trim($decision)) === 'approved') {
                $replayed = $generation->replayCompletedFinalStep($stepId, $userId);
                if ($replayed !== null) {
                    return ['success' => true, 'data' => $replayed, 'message' => '이미 완료된 승인 결과를 재사용했습니다.'];
                }
            }
            $preflight = strtolower(trim($decision)) === 'approved'
                ? $generation->preflightFinalStep($stepId, true)
                : ['is_final' => false];
            $result = (new ApprovalWorkflowService($this->db))->act($stepId, self::DOCUMENT_TYPE, $decision, $comment, $userId, $actor);
            $documentId = (string) $result['request']['document_id'];
            if ($result['state'] === 'APPROVED') {
                if (empty($preflight['is_final']) || !isset($preflight['plan'])) throw new \RuntimeException('최종 결재단계 사전검증 결과가 없습니다.');
                $accounting = $generation->materialize($preflight['plan'], $actor);
                $this->model->updateWorkflow($documentId, 'APPROVED', (string) $result['request']['id'], $actor, true);
                return ['success' => true, 'data' => $accounting, 'message' => '최종 승인과 일용근로소득 증빙·지급거래 생성이 완료되었습니다.'];
            }
            $headerStatus = $result['state'] === 'IN_PROGRESS' ? 'PENDING' : $result['state'];
            $this->model->updateWorkflow($documentId, $headerStatus, (string) $result['request']['id'], $actor);
            return ['success' => true, 'message' => $result['state'] === 'REJECTED' ? '반려했습니다.' : '승인했습니다.'];
        });
    }

    public function approvalDetail(array $request): array
    {
        $detail = $this->detail((string) $request['document_id'])['data'];
        $items = [];
        $workerNames = [];
        foreach ($detail['groups'] as $group) foreach ($group['items'] as $item) {
            $workerName = trim((string) ($item['worker_name_snapshot'] ?? ''));
            if ($workerName !== '') $workerNames[] = $workerName;
            $items[] = $item + [
                'group_id' => $group['id'],
                'business_unit' => $group['business_unit'],
                'business_unit_code' => $group['business_unit'],
                'business_division_code' => $group['business_unit'],
                'business_division_name' => $group['business_unit_name'] ?? $group['business_unit'],
                'project_id' => $group['project_id'],
                'worker_id' => $item['worker_client_id'] ?? null,
                'worker_name' => $item['worker_name_snapshot'] ?? null,
            ];
        }
        $workerNames = array_values(array_unique($workerNames));
        $applicantName = $workerNames === [] ? null : ($workerNames[0] . (count($workerNames) > 1 ? ' 외 ' . (count($workerNames) - 1) . '명' : ''));
        $header = $detail['header'];
        $requestId = (string) ($request['request_id'] ?? $request['id'] ?? '');
        $documentNo = $request['document_no'] ?? $request['sort_no'] ?? $header['sort_no'] ?? null;
        $requestedAt = $request['requested_at'] ?? null;
        $header = array_merge($request, $header, [
            'request_id' => $requestId,
            'request_number' => $documentNo,
            'document_no' => $documentNo,
            'document_id' => $request['document_id'] ?? $header['id'] ?? null,
            'document_type' => self::DOCUMENT_TYPE,
            'title' => $header['document_title'] ?? null,
            'applicant_id' => count($items) === 1 ? ($items[0]['worker_id'] ?? null) : null,
            'applicant_name' => $applicantName,
            'applicant_department_name' => $request['department_name'] ?? null,
            'application_date' => $requestedAt,
            'requested_at' => $requestedAt,
            'status' => $request['status'] ?? null,
            'status_code' => $request['status'] ?? $header['status_code'] ?? null,
        ]);
        return [
            'type' => self::DOCUMENT_TYPE, 'type_name' => '일용근로소득',
            'header' => $header, 'items' => $items,
            'totals' => [
                'total_gross_amount' => $header['total_gross_amount'],
                'total_deduction_amount' => $header['total_deduction_amount'],
                'total_net_payment_amount' => $header['total_net_payment_amount'],
                'total_employer_burden_amount' => $header['total_employer_burden_amount'],
                'total_amount' => $header['total_gross_amount'],
                'deduction_amount' => $header['total_deduction_amount'],
                'net_payment_amount' => $header['total_net_payment_amount'],
                'employer_burden_amount' => $header['total_employer_burden_amount'],
            ],
            'detail_supported' => true,
        ];
    }

    private function compareStoredCalculation(array $storedGroups, array $calculatedGroups, array &$errors): void
    {
        $add = static function (array &$target, string $code, string $message, array $context = []): void {
            $target[] = ['code' => $code, 'message' => $message, 'context' => $context];
        };
        $headerTotals = ['total_work_days' => 0.0, 'total_gross_amount' => 0.0, 'total_deduction_amount' => 0.0, 'total_net_payment_amount' => 0.0, 'total_employer_burden_amount' => 0.0];
        foreach ($storedGroups as $groupIndex => $storedGroup) {
            $calculatedGroup = $calculatedGroups[$groupIndex] ?? null;
            foreach (($storedGroup['items'] ?? []) as $itemIndex => $storedItem) {
                $calculatedItem = $calculatedGroup['items'][$itemIndex] ?? null;
                if (!is_array($calculatedItem)) {
                    $add($errors, 'CALCULATION_ITEM_MISMATCH', '저장된 작업자 계산단위를 재현할 수 없습니다.', ['item_id' => $storedItem['id'] ?? null]);
                    continue;
                }
                foreach ($headerTotals as $key => $_) {
                    $stored = round((float) ($storedItem[$key] ?? 0), 2);
                    $calculated = round((float) ($calculatedItem['summary'][$key] ?? 0), 2);
                    $headerTotals[$key] += $stored;
                    if (abs($stored - $calculated) > 0.009) {
                        $add($errors, 'ITEM_TOTAL_MISMATCH', '작업자 저장합계와 서버 재계산 합계가 일치하지 않습니다.', ['item_id' => $storedItem['id'] ?? null, 'field' => $key]);
                    }
                }
                $storedDays = $storedItem['workdays'] ?? [];
                foreach ($storedDays as $dayIndex => $storedDay) {
                    $calculatedDay = $calculatedItem['workdays'][$dayIndex] ?? null;
                    if (!is_array($calculatedDay)) {
                        $add($errors, 'WORKDAY_RESULT_MISSING', '근무일 계산결과를 재현할 수 없습니다.', ['workday_id' => $storedDay['id'] ?? null]);
                        continue;
                    }
                    foreach (['taxable_amount','income_tax_amount','local_income_tax_amount','net_payment_amount'] as $key) {
                        if (abs(round((float) ($storedDay[$key] ?? 0), 2) - round((float) ($calculatedDay[$key] ?? 0), 2)) > 0.009) {
                            $add($errors, 'WORKDAY_AMOUNT_MISMATCH', '근무일 저장금액과 서버 재계산 금액이 일치하지 않습니다.', ['workday_id' => $storedDay['id'] ?? null, 'field' => $key]);
                        }
                    }
                    $storedLines = array_values(array_filter($storedItem['lines'] ?? [], static fn(array $line): bool => (string) ($line['daily_employment_income_workday_id'] ?? '') === (string) ($storedDay['id'] ?? '')));
                    $storedByCode = array_column($storedLines, null, 'line_code');
                    foreach (['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'] as $lineCode) {
                        $storedLine = $storedByCode[$lineCode] ?? null;
                        $calculatedLine = current(array_filter($calculatedDay['lines'] ?? [], static fn(array $line): bool => ($line['line_code'] ?? '') === $lineCode)) ?: null;
                        if (!is_array($storedLine) || !is_array($calculatedLine) || trim((string) ($storedLine['statutory_standard_id'] ?? '')) === '') {
                            $add($errors, 'STATUTORY_TRACE_REQUIRED', '세금 계산의 법정기준 추적정보가 없습니다.', ['workday_id' => $storedDay['id'] ?? null, 'line_code' => $lineCode]);
                            continue;
                        }
                        foreach (['final_amount','calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_unit'] as $field) {
                            if (abs(round((float) ($storedLine[$field] ?? 0), 6) - round((float) ($calculatedLine[$field] ?? 0), 6)) > 0.000001) {
                                $add($errors, 'TAX_LINE_MISMATCH', '저장된 세금 Line과 서버 재계산 결과가 일치하지 않습니다.', ['workday_id' => $storedDay['id'] ?? null, 'line_code' => $lineCode, 'field' => $field]);
                            }
                        }
                        if ((string) $storedLine['statutory_standard_id'] !== (string) ($calculatedLine['statutory_standard_id'] ?? '')) {
                            $add($errors, 'STATUTORY_REVISION_CHANGED', '저장 시 사용한 법정기준과 현재 적용 법정기준이 다릅니다.', ['workday_id' => $storedDay['id'] ?? null, 'line_code' => $lineCode]);
                        }
                    }
                }
                $storedItemLines = array_values(array_filter(
                    $storedItem['lines'] ?? [],
                    static fn(array $line): bool => ($line['daily_employment_income_workday_id'] ?? null) === null
                ));
                $storedItemByKey = [];
                foreach ($storedItemLines as $line) {
                    $storedItemByKey[(string) ($line['line_type_code'] ?? '') . ':' . (string) ($line['line_code'] ?? '')] = $line;
                }
                foreach ($calculatedItem['lines'] ?? [] as $calculatedLine) {
                    $key = (string) ($calculatedLine['line_type_code'] ?? '') . ':' . (string) ($calculatedLine['line_code'] ?? '');
                    $storedLine = $storedItemByKey[$key] ?? null;
                    if (!is_array($storedLine)) {
                        $add($errors, 'INSURANCE_LINE_MISSING', '저장된 사회보험 Item Line이 없습니다.', ['item_id' => $storedItem['id'] ?? null, 'line_key' => $key]);
                        continue;
                    }
                    if (($calculatedLine['application_status_code'] ?? null) === 'APPLICABLE') {
                        foreach ([
                            'calculation_basis_amount', 'calculation_rate', 'calculation_before_rounding',
                            'rounding_method_code', 'rounding_unit', 'statutory_standard_id',
                            'calculated_amount', 'final_amount', 'statutory_calculation_source_code_id',
                            'actual_application_source_code_id', 'processed_at', 'processed_by',
                        ] as $field) {
                            if (($storedLine[$field] ?? null) === null || ($storedLine[$field] ?? '') === '') {
                                $add($errors, 'INSURANCE_LINE_TRACE_REQUIRED', '사회보험 Item Line의 계산·적용 추적정보가 없습니다.', ['item_id' => $storedItem['id'] ?? null, 'line_key' => $key, 'field' => $field]);
                            }
                        }
                    }
                    foreach (['calculation_basis_amount', 'calculation_rate', 'calculation_before_rounding', 'rounding_unit', 'calculated_amount', 'final_amount'] as $field) {
                        if (abs(round((float) ($storedLine[$field] ?? 0), 6) - round((float) ($calculatedLine[$field] ?? 0), 6)) > 0.000001) {
                            $add($errors, 'INSURANCE_LINE_MISMATCH', '저장된 사회보험 Item Line과 서버 재계산 결과가 일치하지 않습니다.', ['item_id' => $storedItem['id'] ?? null, 'line_key' => $key, 'field' => $field]);
                        }
                    }
                    if ((string) ($storedLine['statutory_standard_id'] ?? '') !== (string) ($calculatedLine['statutory_standard_id'] ?? '')) {
                        $add($errors, 'INSURANCE_STATUTORY_REVISION_CHANGED', '저장 시 사용한 사회보험 법정기준과 현재 적용 법정기준이 다릅니다.', ['item_id' => $storedItem['id'] ?? null, 'line_key' => $key]);
                    }
                }
            }
        }
        $header = $this->model->find((string) ($storedGroups[0]['daily_employment_income_id'] ?? ''));
        if ($header !== null) {
            foreach ($headerTotals as $key => $value) {
                if (abs(round((float) $header[$key], 2) - round($value, 2)) > 0.009) {
                    $add($errors, 'DOCUMENT_TOTAL_MISMATCH', '문서합계와 작업자 합계가 일치하지 않습니다.', ['field' => $key]);
                }
            }
        }
    }

    private function hasStoredNonTax(array $groups): bool
    {
        foreach ($groups as $group) foreach (($group['items'] ?? []) as $item) foreach (($item['workdays'] ?? []) as $workday) {
            if (round((float) ($workday['non_taxable_amount'] ?? 0), 2) !== 0.0) return true;
        }
        return false;
    }

    private function normalizeGroupInsurance(array $group, bool $requireDecisionReason = true): array
    {
        $businessUnit = strtoupper(trim((string) ($group['business_unit'] ?? '')));
        $policyRow = $this->model->businessUnitPolicy($businessUnit);
        $this->businessUnitPolicy->fromCodeRow($policyRow);
        return $this->groupInsurancePolicy->normalize($group, $requireDecisionReason);
    }

    private function preflightResult(array $errors, array $warnings, string $calculationStatus, string $insuranceStatus, string $nonTaxStatus, ?string $sourceHash): array
    {
        return [
            'can_submit' => $errors === [],
            'blocking_errors' => $errors,
            'warnings' => $warnings,
            'calculation_status' => $calculationStatus,
            'insurance_status' => $insuranceStatus,
            'non_tax_status' => $nonTaxStatus,
            'source_hash' => $sourceHash,
            'checked_at' => date(DATE_ATOM),
        ];
    }

    public function delete(string $id): array
    {
        return $this->logged('DAILY_INCOME_DELETED','delete',['target_id'=>$id],fn():array=>$this->deleteInternal($id));
    }

    private function deleteInternal(string $id): array
    {
        if ($id === '' || !$this->model->softDelete($id, ActorHelper::user())) {
            throw new \RuntimeException('현재 문서상태에서는 삭제할 수 없습니다.');
        }
        return ['success' => true, 'message' => '삭제했습니다.'];
    }

    public function trash(): array { return ['success' => true, 'data' => $this->model->trash()]; }
    public function restore(string $id): array { return $this->restoreMany([$id]); }
    public function restoreMany(array $ids): array
    {
        return $this->logged('DAILY_INCOME_RESTORED','restore',['requested_count'=>count($ids)],fn():array=>$this->restoreManyInternal($ids));
    }

    private function restoreManyInternal(array $ids): array
    {
        return $this->transaction(function () use ($ids): array {
            $count=0;$actor=ActorHelper::user();foreach($this->ids($ids) as $id)if($this->model->restore($id,$actor))$count++;
            return ['success'=>true,'data'=>['restored_count'=>$count],'message'=>$count>0?'복원했습니다.':'복원된 문서가 없습니다.'];
        });
    }
    public function restoreAll(): array { return $this->restoreMany($this->model->trashIds()); }
    public function purge(string $id): array { return $this->purgeMany([$id]); }
    public function purgeMany(array $ids): array
    {
        return $this->logged('DAILY_INCOME_PURGED','purge',['requested_count'=>count($ids)],fn():array=>$this->purgeManyInternal($ids),true);
    }

    private function purgeManyInternal(array $ids): array
    {
        return $this->transaction(function () use ($ids): array {
            $count=0;$skipped=0;foreach($this->ids($ids) as $id){if($this->model->purge($id))$count++;else$skipped++;}
            return ['success'=>true,'data'=>['deleted_count'=>$count,'skipped_count'=>$skipped],'message'=>$count>0?'완전삭제했습니다.':'완전삭제 가능한 문서가 없습니다.'];
        });
    }
    public function purgeAll(): array { return $this->purgeMany($this->model->trashIds()); }

    private function normalizedPayload(array $input): array
    {
        return [
            'id' => trim((string) ($input['id'] ?? '')),
            'income_year_month' => trim((string) ($input['income_year_month'] ?? '')),
            'withholding_date' => trim((string) ($input['withholding_date'] ?? '')),
            'document_title' => trim((string) ($input['document_title'] ?? '')),
            'description' => $this->nullableText($input['description'] ?? null),
            'memo' => $this->nullableText($input['memo'] ?? null),
            'groups' => is_array($input['groups'] ?? null) ? array_values($input['groups']) : [],
        ];
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function withholdingDate(mixed $value): string
    {
        $date = trim((string) $value);
        if (!$this->isDate($date)) throw new \InvalidArgumentException('원천징수일을 확인해 주세요.');
        return $date;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids), static fn(string $id): bool => $id !== '')));
    }

    private function transaction(callable $callback): array
    {
        $owned=!$this->db->inTransaction();if($owned)$this->db->beginTransaction();try{$result=$callback();if($owned)$this->db->commit();return$result;}catch(\Throwable $exception){if($owned&&$this->db->inTransaction())$this->db->rollBack();throw$exception;}
    }

    private function identity(): array
    {
        $parsed = ActorHelper::parse(ActorHelper::user());
        $userId = trim((string) ($parsed['id'] ?? ''));
        if ($userId === '') throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');
        return [$userId, ActorHelper::user()];
    }

    private function logged(string $eventCode,string $action,array $context,callable $operation,bool $warningOnSuccess=false):array
    {
        $started=microtime(true);$base=['event_code'=>$eventCode,'service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user()]+$context;
        try{$result=$operation();$level=$warningOnSuccess?'warning':'info';$this->logger->{$level}('일용근로소득 업무 처리가 완료되었습니다.',$base+['result'=>'SUCCESS','duration_ms'=>(int)round((microtime(true)-$started)*1000)]);return$result;}
        catch(\InvalidArgumentException|\DomainException $e){$this->logger->warning('일용근로소득 업무 처리가 차단되었습니다.',$base+['result'=>'BLOCKED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]);throw$e;}
        catch(\Throwable $e){$this->logger->error('일용근로소득 업무 처리에 실패했습니다.',$base+['result'=>'FAILED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]);throw$e;}
    }
}
