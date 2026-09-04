<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\DailyEmploymentIncomeCalculationResultModel;
use Core\Helpers\UuidHelper;
use PDO;

final class DailyEmploymentIncomeCalculationResultService
{
    public const SNAPSHOT_SCHEMA_VERSION = 'DAILY_INSURANCE_CALCULATION_RESULT_V1';
    private DailyEmploymentIncomeCalculationResultModel $model;
    private ?InsuranceEligibilityReasonProjectionService $reasonProjection = null;

    public function __construct(PDO $db)
    {
        $this->model = new DailyEmploymentIncomeCalculationResultModel($db);
        $this->reasonProjection = new InsuranceEligibilityReasonProjectionService($db);
    }

    public function persist(
        string $documentId,
        string $month,
        array $groups,
        array $persistedIds,
        string $sourceHash,
        string $actor
    ): string {
        $latest = $this->model->latestRevision($documentId, true);
        if ($latest !== null && hash_equals((string)$latest['source_hash'], $sourceHash)) {
            return (string)$latest['id'];
        }
        if ($latest !== null) {
            if ((string)($latest['status_code'] ?? '') !== 'CALCULATED') {
                throw new \RuntimeException('확정되거나 처리 중인 계산 Revision은 재계산할 수 없습니다.');
            }
            if ($this->model->markStale((string)$latest['id'], $actor) !== 1) {
                throw new \RuntimeException('최신 계산 Revision 상태가 변경되어 재계산할 수 없습니다.');
            }
        }
        $revisionId = UuidHelper::generate();
        $now = date('Y-m-d H:i:s');
        $this->model->insertRevision([
            'id' => $revisionId,
            'daily_employment_income_id' => $documentId,
            'revision_no' => (int)($latest['revision_no'] ?? 0) + 1,
            'calculation_policy_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'source_hash' => $sourceHash,
            'status_code' => 'CALCULATED',
            'supersedes_revision_id' => $latest['id'] ?? null,
            'calculated_by' => $actor,
            'calculated_at' => $now,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'failed_at' => null,
            'failure_code' => null,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);
        foreach (array_values($groups) as $groupIndex => $group) {
            foreach (array_values((array)($group['items'] ?? [])) as $itemIndex => $item) {
                $itemId = $persistedIds['items'][$groupIndex][$itemIndex] ?? null;
                if (!is_string($itemId) || $itemId === '') throw new \RuntimeException('계산 Result의 작업자 Item ID를 확인할 수 없습니다.');
                $this->persistItem($revisionId, $itemId, $month, $item, [
                    'group_id'=>$persistedIds['groups'][$groupIndex] ?? null,
                    'workday_ids'=>$persistedIds['workdays'][$groupIndex][$itemIndex] ?? [],
                    'lines'=>$persistedIds['lines'][$groupIndex][$itemIndex] ?? [],
                ], $sourceHash, $actor);
            }
        }
        return $revisionId;
    }

    public function latest(string $documentId): ?array
    {
        if (!$this->model->available()) return null;
        $revision = $this->model->latestWithResults($documentId);
        if ($revision === null) return null;
        foreach ($revision['results'] as &$result) {
            foreach (['missing_inputs','calculation_basis_snapshot','eligibility_snapshot'] as $field) {
                if (!isset($result[$field]) || !is_string($result[$field])) continue;
                $decoded = json_decode($result[$field], true);
                if (is_array($decoded)) $result[$field] = $decoded;
            }
            $this->appendEligibilityDisplayProjection($result);
        }
        unset($result);
        return $revision;
    }

    private function persistItem(
        string $revisionId,
        string $itemId,
        string $month,
        array $item,
        array $sourceIds,
        string $sourceHash,
        string $actor
    ): void {
        $byInsurance = [];
        foreach ((array)($item['lines'] ?? []) as $line) {
            $code = (string)($line['line_code'] ?? '');
            if ($code === 'EMPLOYMENT_INSURANCE_VOCATIONAL') {
                $byInsurance['EMPLOYMENT_INSURANCE']['VOCATIONAL'] = $line;
                continue;
            }
            if (!in_array($code, [
                'NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE',
                'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT_INSURANCE',
            ], true)) continue;
            $byInsurance[$code][(string)($line['line_type_code'] ?? '')] = $line;
        }
        foreach ($byInsurance as $code => $lines) {
            $employee = (array)($lines['DEDUCTION'] ?? []);
            $employer = (array)($lines['EMPLOYER_BURDEN'] ?? []);
            $primary = $employee !== [] ? $employee : $employer;
            $eligibility = (array)($employee['eligibility_result'] ?? $employer['eligibility_result'] ?? []);
            if (isset($lines['VOCATIONAL']['eligibility_result'])) {
                $vocational = (array) $lines['VOCATIONAL']['eligibility_result'];
                $eligibility['component_results'] = array_values(array_merge(
                    (array) ($eligibility['component_results'] ?? []),
                    (array) ($vocational['component_results'] ?? [])
                ));
            }
            if ($code === 'EMPLOYMENT_INSURANCE') {
                $eligibility['component_results'] = array_values(array_merge(
                    (array) ($eligibility['component_results'] ?? []),
                    array_filter([
                        $this->premiumComponent('UNEMPLOYMENT_EMPLOYEE', $employee, 'EMPLOYEE'),
                        $this->premiumComponent('UNEMPLOYMENT_EMPLOYER', $employer, 'EMPLOYER'),
                        $this->premiumComponent('VOCATIONAL_EMPLOYER', (array) ($lines['VOCATIONAL'] ?? []), 'EMPLOYER'),
                    ])
                ));
            }
            $eligibility['source_group_id']=$sourceIds['group_id'] ?? null;
            $eligibility['source_item_id']=$itemId;
            $eligibility['source_workday_ids']=array_values(array_filter((array)($sourceIds['workday_ids'] ?? []),'is_string'));
            $eligibility['source_payment_line_ids']=array_values(array_map(
                static fn(array $line):string=>(string)$line['id'],
                array_filter((array)($sourceIds['lines'] ?? []),static fn(array $line):bool=>($line['line_type_code']??'')==='PAY')
            ));
            $status = (string)($eligibility['status'] ?? 'CONFIRMATION_REQUIRED');
            $manualSetting = in_array((string) ($eligibility['decision_source_code'] ?? ''), ['GROUP_MANUAL_SETTING', 'DAILY_GROUP_MANUAL_SETTING', 'BUSINESS_DIVISION_POLICY'], true);
            $snapshot = $this->orderedSnapshot($code, $item, $eligibility, $employee, $employer, $sourceHash);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            if (!is_string($snapshotJson)) throw new \RuntimeException('보험 계산 Snapshot을 생성할 수 없습니다.');
            $confirmation = $status === 'CONFIRMATION_REQUIRED';
            $this->model->insertResult([
                'id' => UuidHelper::generate(),
                'calculation_revision_id' => $revisionId,
                'result_type_code' => $code === 'LONG_TERM_CARE' ? 'LONG_TERM_CARE_INSURANCE' : $code,
                'worker_client_id' => $item['worker_client_id'],
                'daily_employment_income_item_id' => $itemId,
                'social_insurance_workplace_id' => $primary['social_insurance_workplace_id'] ?? null,
                'work_date' => null,
                'application_from' => $month . '-01',
                'application_to' => date('Y-m-t', strtotime($month . '-01')),
                'payment_sequence' => 1,
                'calculation_basis_amount' => $confirmation ? null : ($primary['calculation_basis_amount'] ?? 0),
                'automatic_employee_amount' => $confirmation ? null : ($employee['calculated_amount'] ?? 0),
                'automatic_employer_amount' => $confirmation ? null : ($employer['calculated_amount'] ?? 0),
                'confirmed_employee_amount' => $confirmation ? null : ($employee['final_amount'] ?? 0),
                'confirmed_employer_amount' => $confirmation ? null : ($employer['final_amount'] ?? 0),
                'statutory_standard_id' => $primary['statutory_standard_id'] ?? null,
                'status_code' => in_array($status, ['NOT_ELIGIBLE', 'EXCLUDED'], true) ? 'EXCLUDED' : 'CALCULATED',
                'eligibility_status_code' => $manualSetting ? null : $status,
                'eligibility_reason_code' => $manualSetting ? null : ($eligibility['reason_code'] ?? null),
                'missing_inputs' => json_encode($eligibility['missing_inputs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'exception_reason' => null,
                'calculation_basis_snapshot' => $snapshotJson,
                'eligibility_revision_id' => $eligibility['eligibility_revision_id'] ?? null,
                'eligibility_snapshot' => $snapshotJson,
                'snapshot_schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
                'workplace_scope_key' => $primary['social_insurance_workplace_id'] ?? 'NONE',
                'workday_scope_key' => '1000-01-01',
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
        }
    }

    private function orderedSnapshot(string $code, array $item, array $eligibility, array $employee, array $employer, string $sourceHash): array
    {
        $primary = $employee !== [] ? $employee : $employer;
        return [
            'snapshot_schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'source_hash' => $sourceHash,
            'calculated_at' => date('Y-m-d H:i:s'),
            'insurance_type_code' => $code,
            'employment_type_code' => $eligibility['employment_type_code'] ?? 'DAILY',
            'business_unit_code' => $eligibility['business_unit_code'] ?? ($item['business_unit'] ?? null),
            'eligibility_work_scope_code' => $eligibility['eligibility_work_scope_code'] ?? null,
            'scope_derivation_snapshot' => $eligibility['scope_derivation_snapshot'] ?? ($item['eligibility_scope'] ?? null),
            'eligibility_status_code' => ($eligibility['decision_source_code'] ?? null) === 'GROUP_MANUAL_SETTING'
                ? null : ($eligibility['status'] ?? null),
            'manual_application_status_code' => $eligibility['manual_application_status_code'] ?? null,
            'eligibility_reason_code' => ($eligibility['decision_source_code'] ?? null) === 'GROUP_MANUAL_SETTING'
                ? null : ($eligibility['reason_code'] ?? null),
            'eligibility_reason_name' => $eligibility['reason_name'] ?? null,
            'eligibility_reason_detail' => $eligibility['reason_detail'] ?? null,
            'decision_basis_code' => $eligibility['decision_basis_code'] ?? null,
            'decision_basis_name' => $eligibility['decision_basis_name'] ?? null,
            'decision_basis_detail' => $eligibility['decision_basis_detail'] ?? null,
            'decision_source_code' => $eligibility['decision_source_code'] ?? null,
            'decision_source_name' => $eligibility['decision_source_name'] ?? null,
            'manual_setting_reason' => $eligibility['manual_setting_reason'] ?? null,
            'company_burden_status_code' => $eligibility['company_burden_status_code'] ?? null,
            'company_burden_name' => $eligibility['company_burden_name'] ?? null,
            'burden_source_code' => $eligibility['burden_source_code'] ?? null,
            'burden_source_name' => $eligibility['burden_source_name'] ?? null,
            'set_by' => $eligibility['set_by'] ?? null,
            'set_by_name' => $eligibility['set_by_name'] ?? null,
            'set_at' => $eligibility['set_at'] ?? null,
            'passed_conditions' => $eligibility['passed_conditions'] ?? [],
            'missing_inputs' => $eligibility['missing_inputs'] ?? [],
            'failed_conditions' => $eligibility['failed_conditions'] ?? [],
            'component_results' => $eligibility['component_results'] ?? [],
            'evaluated_conditions' => $eligibility['evaluated_conditions'] ?? [],
            'eligibility_revision_id' => $eligibility['eligibility_revision_id'] ?? null,
            'premium_revision_id' => $primary['statutory_standard_id'] ?? null,
            'employment_analysis' => $eligibility['employment_analysis'] ?? null,
            'project_analysis' => $eligibility['project_analysis'] ?? null,
            'workplace_analysis' => $eligibility['workplace_analysis'] ?? null,
            'source_document_id' => $eligibility['source_document_id'] ?? null,
            'source_group_id' => $eligibility['source_group_id'] ?? null,
            'source_item_id' => $eligibility['source_item_id'] ?? null,
            'source_workday_ids' => $eligibility['source_workday_ids'] ?? [],
            'source_payment_line_ids' => $eligibility['source_payment_line_ids'] ?? [],
            'aggregation_scope_code' => $eligibility['aggregation_scope_code'] ?? 'ITEM',
            'aggregation_project_ids' => $eligibility['aggregation_project_ids'] ?? array_values(array_filter([$item['project_id'] ?? null])),
            'aggregation_workplace_ids' => $eligibility['aggregation_workplace_ids'] ?? array_values(array_filter([$primary['social_insurance_workplace_id'] ?? null])),
            'aggregation_item_ids' => $eligibility['aggregation_item_ids'] ?? [],
            'evaluated_work_days' => $eligibility['evaluated_work_days'] ?? null,
            'evaluated_work_minutes' => $eligibility['evaluated_work_minutes'] ?? null,
            'evaluated_income_amount' => $eligibility['evaluated_income_amount'] ?? null,
            'premium_calculation_basis' => $primary['calculation_basis_amount'] ?? null,
            'premium_rate' => $primary['calculation_rate'] ?? null,
            'rounding_before_amount' => $primary['calculation_before_rounding'] ?? null,
            'rounding_policy' => $primary['rounding_policy'] ?? null,
            'employee_automatic_amount' => $employee['calculated_amount'] ?? null,
            'employee_amount' => $employee['final_amount'] ?? null,
            'employee_adjustment_amount' => $employee['adjustment_amount'] ?? null,
            'employee_adjustment_reason' => $employee['adjustment_reason'] ?? null,
            'employer_automatic_amount' => $employer['calculated_amount'] ?? null,
            'employer_amount' => $employer['final_amount'] ?? null,
            'employer_adjustment_amount' => $employer['adjustment_amount'] ?? null,
            'employer_adjustment_reason' => $employer['adjustment_reason'] ?? null,
        ];
    }

    private function appendEligibilityDisplayProjection(array &$result): void
    {
        $snapshot = is_array($result['eligibility_snapshot'] ?? null) ? $result['eligibility_snapshot'] : [];
        $manualSetting = in_array((string) ($snapshot['decision_source_code'] ?? ''), ['GROUP_MANUAL_SETTING', 'DAILY_GROUP_MANUAL_SETTING', 'BUSINESS_DIVISION_POLICY'], true);
        $status = (string)($result['eligibility_status_code'] ?? ($manualSetting ? ($snapshot['manual_application_status_code'] ?? '') : ''));
        if ($manualSetting) $result['application_status_code'] = $status;
        $result['eligibility_status_name'] = match ($status) {
            'ELIGIBLE', 'APPLICABLE' => '적용',
            'PARTIALLY_ELIGIBLE', 'PARTIALLY_APPLICABLE' => '일부 적용',
            'NOT_ELIGIBLE', 'EXCLUDED' => '적용 제외',
            'CALCULATION_ERROR' => '계산 오류',
            default => '확인 필요',
        };
        $reasonCode = (string)($result['eligibility_reason_code'] ?? $snapshot['eligibility_reason_code'] ?? $snapshot['reason_code'] ?? '');
        $policy = json_decode((string)($result['eligibility_policy_value_data'] ?? ''), true);
        $this->reasonProjection ??= new InsuranceEligibilityReasonProjectionService();
        $projection = $this->reasonProjection->enrich([
            'result_id' => $result['id'] ?? null,
            'status' => $status,
            'reason_code' => $reasonCode,
            'reason_name' => $snapshot['eligibility_reason_name'] ?? $snapshot['reason_name'] ?? null,
            'reason_detail' => $snapshot['eligibility_reason_detail'] ?? $snapshot['reason_detail'] ?? null,
            'missing_inputs' => $snapshot['missing_inputs'] ?? $result['missing_inputs'] ?? [],
            'evaluated_conditions' => $snapshot['evaluated_conditions'] ?? [],
            'component_results' => $snapshot['component_results'] ?? [],
            'decision_basis_code' => $snapshot['decision_basis_code'] ?? null,
            'decision_basis_name' => $snapshot['decision_basis_name'] ?? null,
            'decision_basis_detail' => $snapshot['decision_basis_detail'] ?? null,
            'passed_conditions' => $snapshot['passed_conditions'] ?? [],
        ], is_array($policy) ? $policy : []);
        $reasonName = trim((string)($projection['reason_name'] ?? ''));
        $reasonDetail = trim((string)($projection['reason_detail'] ?? ''));
        $fallback = in_array($status, ['ELIGIBLE', 'APPLICABLE'], true)
            ? '적용 판정근거 확인 필요'
            : ($status === 'CONFIRMATION_REQUIRED' ? '확인 필요자료를 확인할 수 없습니다.' : '판정 사유 확인 필요');
        $result['eligibility_reason_name'] = $reasonName !== '' ? $reasonName : $fallback;
        $result['eligibility_reason_detail'] = $reasonDetail !== '' ? $reasonDetail : null;
        $result['decision_basis_code'] = $projection['decision_basis_code'] ?? null;
        $result['decision_basis_name'] = $projection['decision_basis_name'] ?? null;
        $result['decision_basis_detail'] = $projection['decision_basis_detail'] ?? null;
        $result['passed_conditions'] = $projection['passed_conditions'] ?? [];
        $result['failed_conditions'] = $projection['failed_conditions'];
        $result['missing_facts'] = $projection['missing_facts'];
        $result['component_results'] = $projection['component_results'];
        unset($result['eligibility_policy_value_data']);
    }

    private function premiumComponent(string $componentCode, array $line, string $burdenSubject): ?array
    {
        if ($line === []) return null;
        return [
            'component_code' => $componentCode,
            'burden_subject_code' => $burdenSubject,
            'application_status_code' => $line['application_status_code'] ?? null,
            'premium_revision_id' => $line['statutory_standard_id'] ?? null,
            'calculation_basis_amount' => $line['calculation_basis_amount'] ?? null,
            'calculation_rate' => $line['calculation_rate'] ?? null,
            'calculation_before_rounding' => $line['calculation_before_rounding'] ?? null,
            'automatic_amount' => $line['calculated_amount'] ?? null,
            'applied_amount' => $line['final_amount'] ?? null,
            'adjustment_amount' => $line['adjustment_amount'] ?? null,
            'adjustment_reason' => $line['adjustment_reason'] ?? null,
        ];
    }
}
