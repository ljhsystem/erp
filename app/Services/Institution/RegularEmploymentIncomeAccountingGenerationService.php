<?php

namespace App\Services\Institution;

use App\Models\Institution\RegularEmploymentIncomeAccountingGenerationModel;
use App\Models\Institution\RegularEmploymentIncomeModel;
use App\Models\Ledger\SalaryReportEvidenceModel;
use App\Services\Ledger\EvidenceExternalKeyService;
use App\Services\Ledger\EvidenceWorkflowPolicyService;
use App\Services\Ledger\TransactionCrudService;
use Core\Helpers\UuidHelper;
use PDO;

final class RegularEmploymentIncomeAccountingGenerationService
{
    public const ROLE_EMPLOYEE_PAYROLL = 'EMPLOYEE_PAYROLL';
    public const ROLE_PAYROLL_REPORT_EVIDENCE = 'PAYROLL_REPORT_EVIDENCE';

    private RegularEmploymentIncomeAccountingGenerationModel $model;
    private RegularEmploymentIncomeModel $incomeModel;

    public function __construct(private readonly PDO $db, private $failureInjector = null)
    {
        $this->model = new RegularEmploymentIncomeAccountingGenerationModel($db);
        $this->incomeModel = new RegularEmploymentIncomeModel($db);
    }

    public function preflightFinalStep(string $stepId, bool $lock = false): array
    {
        $context = $this->model->approvalContextByStep($stepId, $lock);
        if (!$context || ($context['document_type'] ?? '') !== 'REGULAR_EMPLOYMENT_INCOME') {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '상용근로소득 결재요청을 찾을 수 없습니다.');
        }
        if (strtoupper((string) ($context['step_type'] ?? '')) !== 'FINAL_APPROVAL') {
            return ['is_final' => false, 'context' => $context];
        }
        if (!in_array((string) ($context['request_status'] ?? ''), ['pending', 'in_progress'], true)
            || ($context['status'] ?? '') !== 'pending'
            || (int) $context['current_step'] !== (int) $context['sort_no']) {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '현재 처리할 최종 결재단계가 아닙니다.');
        }
        return ['is_final' => true, 'context' => $context, 'plan' => $this->preflight(
            (string) $context['document_id'],
            (string) $context['approval_request_id'],
            $lock
        )];
    }

    public function preflight(string $documentId, string $approvalRequestId, bool $lock = false): array
    {
        $approval = $this->model->approvalContextByRequest($approvalRequestId, $lock);
        if (!$approval
            || ($approval['document_type'] ?? '') !== 'REGULAR_EMPLOYMENT_INCOME'
            || (string) ($approval['document_id'] ?? '') !== $documentId
            || !in_array((string) ($approval['status'] ?? ''), ['pending', 'in_progress'], true)) {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '급여문서와 결재요청의 연결을 확인해 주세요.');
        }
        $header = $this->incomeModel->find($documentId, $lock);
        if (!$header || ($header['document_status'] ?? '') !== 'PENDING'
            || (string) ($header['current_approval_request_id'] ?? '') !== $approvalRequestId) {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '결재 대기 중인 상용근로소득 원본을 확인해 주세요.');
        }
        $month = (string) $header['income_year_month'];
        $recognitionDate = $month . '-' . date('t', strtotime($month . '-01'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || !$this->isDate($recognitionDate)) {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '귀속월을 확인해 주세요.');
        }
        $items = $this->incomeModel->items($documentId, $lock);
        if ($items === []) $this->fail('PAYROLL_SOURCE_INCOMPLETE', '직원별 급여 원천이 없습니다.');
        if ($this->model->registriesForDocument($documentId, $lock) !== []
            || (new SalaryReportEvidenceModel($this->db))->findBySource($documentId, $lock) !== []) {
            $this->fail('ACCOUNTING_PARTIAL_GENERATION', '기존 생성자료가 감지됐습니다. 자동 재처리할 수 없습니다.');
        }

        $planned = [];
        $totals = ['gross' => 0.0, 'deduction' => 0.0, 'net' => 0.0, 'employer_burden' => 0.0];
        foreach ($items as $item) {
            $lines = $this->incomeModel->lineItems((string) $item['id']);
            if ($lines === []) $this->fail('PAYROLL_SOURCE_INCOMPLETE', (string) $item['employee_name_snapshot'] . ' 직원의 급여 Line이 없습니다.');
            foreach ($lines as $line) $this->validateStoredLineSnapshot($line);
            $planned[] = $item + ['line_items' => $lines];
            $totals['gross'] += (float) $item['gross_amount'];
            $totals['deduction'] += (float) $item['deduction_amount'];
            $totals['net'] += (float) $item['net_payment_amount'];
            $itemEmployerBurden = 0.0;
            foreach ($lines as $line) {
                if (($line['item_type_code'] ?? '') === 'EMPLOYER_BURDEN') {
                    $itemEmployerBurden += (float) $line['final_amount'];
                }
            }
            if (abs($itemEmployerBurden - (float) ($item['employer_burden_amount'] ?? 0)) >= .01) {
                $this->fail('PAYROLL_SOURCE_INCOMPLETE', (string) $item['employee_name_snapshot'] . ' 직원의 사용자부담 합계가 일치하지 않습니다.');
            }
            $totals['employer_burden'] += $itemEmployerBurden;
        }
        foreach ($totals as &$amount) $amount = round($amount, 2);
        unset($amount);
        if (abs($totals['gross'] - (float) $header['gross_amount']) >= .01
            || abs($totals['deduction'] - (float) $header['deduction_amount']) >= .01
            || abs($totals['net'] - (float) $header['net_payment_amount']) >= .01) {
            $this->fail('PAYROLL_SOURCE_INCOMPLETE', '급여 Header와 직원별 합계가 일치하지 않습니다.');
        }
        return compact('header', 'approvalRequestId', 'month', 'recognitionDate')
            + ['items' => $planned, 'approval_request_id' => $approvalRequestId, 'attribution_month' => $month,
                'recognition_date' => $recognitionDate, 'totals' => $totals];
    }

    public function materialize(array $plan, string $actor): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('급여 증빙과 거래 생성은 바깥 Transaction 안에서만 실행해야 합니다.');
        $header = $plan['header'];
        $documentId = (string) $header['id'];
        $approvalId = (string) $plan['approval_request_id'];
        $month = (string) $plan['attribution_month'];
        $recognition = (string) $plan['recognition_date'];
        $evidenceModel = new SalaryReportEvidenceModel($this->db);
        $transactionService = new TransactionCrudService($this->db, $this->failureInjector);
        $evidenceIds = $transactionIds = $registryIds = [];

        foreach ($plan['items'] as $index => $item) {
            $ordinal = $index + 1;
            $this->checkpoint('before_evidence_' . $ordinal);
            $itemId = (string) $item['id'];
            $evidenceId = UuidHelper::generate();
            $employerBurden = 0.0;
            foreach ($item['line_items'] as $line) {
                if (($line['item_type_code'] ?? '') === 'EMPLOYER_BURDEN') $employerBurden += (float) $line['final_amount'];
            }
            $evidenceModel->insert($this->evidenceRow($plan, $item, $evidenceId, round($employerBurden, 2), $actor));
            foreach ($item['line_items'] as $line) {
                $evidenceModel->insertLine([
                    'id' => UuidHelper::generate(), 'evidence_id' => $evidenceId, 'source_line_id' => $line['id'],
                    'sort_no' => $line['sort_no'], 'raw_item_type_code' => $line['item_type_code'],
                    'raw_item_code' => $line['item_code'], 'raw_item_name' => $line['item_name_snapshot'],
                    'raw_application_status_code' => $line['application_status_code'],
                    'raw_calculation_basis_amount' => $line['calculation_basis_amount'], 'raw_calculation_rate' => $line['calculation_rate'],
                    'raw_calculation_before_rounding' => $line['calculation_before_rounding'], 'raw_calculated_amount' => $line['calculated_amount'],
                    'raw_adjustment_amount' => $line['adjustment_amount'], 'raw_final_amount' => $line['final_amount'],
                    'raw_rounding_method_code' => $line['rounding_method_code'], 'raw_rounding_unit' => $line['rounding_unit'],
                    'raw_statutory_standard_id' => $line['statutory_standard_id'],
                    'source_hash' => hash('sha256', json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)),
                    'created_by' => $actor,
                ]);
            }
            $evidenceIds[] = $evidenceId;
            $this->checkpoint('after_evidence_' . $ordinal);
            $registryIds[] = (string) $this->register([
                'regular_employment_income_id' => $documentId,
                'regular_employment_income_item_id' => $itemId,
                'generation_role' => self::ROLE_PAYROLL_REPORT_EVIDENCE,
                'aggregation_key' => 'EMPLOYEE|' . $itemId . '|EVIDENCE',
                'approval_request_id' => $approvalId,
                'evidence_id' => $evidenceId,
                'transaction_id' => null,
                'payment_schedule_id' => null,
                'attribution_month' => $month,
                'recognition_date' => null,
            ], $actor)['row']['id'];
            $saved = $transactionService->save($this->employeeTransactionPayload($header, $item, $evidenceId, $recognition));
            if (empty($saved['success'])) $this->fail('PAYROLL_SOURCE_INCOMPLETE', '직원 급여 거래 생성에 실패했습니다: ' . (string) ($saved['message'] ?? '원인 없음'));
            $transactionId = (string) $saved['id'];
            $transactionIds[] = $transactionId;
            $this->checkpoint('after_employee_transaction_' . $ordinal);
            $registryIds[] = (string) $this->register([
                'regular_employment_income_id' => $documentId,
                'regular_employment_income_item_id' => $itemId,
                'generation_role' => self::ROLE_EMPLOYEE_PAYROLL,
                'aggregation_key' => 'EMPLOYEE|' . $itemId . '|TRANSACTION',
                'approval_request_id' => $approvalId,
                'evidence_id' => $evidenceId,
                'transaction_id' => $transactionId,
                'payment_schedule_id' => null,
                'attribution_month' => $month,
                'recognition_date' => $recognition,
            ], $actor)['row']['id'];
            $this->checkpoint('after_evidence_link_' . $ordinal);
        }
        $this->checkpoint('after_registry');
        $this->checkpoint('before_audit');
        $this->incomeModel->insertAudit([
            'id' => UuidHelper::generate(), 'regular_employment_income_id' => $documentId,
            'regular_employment_income_item_id' => null, 'action_code' => 'ACCOUNTING_MATERIALIZE',
            'reason' => '최종 승인 직원별 급여 증빙과 거래 생성', 'before_value' => null,
            'after_value' => json_encode(compact('evidenceIds', 'transactionIds', 'registryIds'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_key' => $this->requestKey($documentId, self::ROLE_PAYROLL_REPORT_EVIDENCE, $approvalId . '|AUDIT'),
            'acted_by' => $actor,
        ]);
        return ['evidence_ids' => $evidenceIds, 'transaction_ids' => $transactionIds, 'registry_ids' => $registryIds,
            'payment_schedule_ids' => [], 'evidence_count' => count($evidenceIds), 'transaction_count' => count($transactionIds),
            'payment_schedule_count' => 0, 'duplicate_prevented' => false];
    }

    public function validateStoredLineSnapshot(array $line): void
    {
        $type = strtoupper((string) ($line['item_type_code'] ?? ''));
        $status = strtoupper((string) ($line['application_status_code'] ?? ''));
        $amount = round((float) ($line['final_amount'] ?? 0), 2);
        if (!in_array($type, ['PAY', 'DEDUCTION', 'EMPLOYER_BURDEN'], true)) $this->fail('PAYROLL_SOURCE_INCOMPLETE', '급여 Line 유형을 확인해 주세요.');
        if ($type === 'PAY') return;
        if ($type === 'EMPLOYER_BURDEN') {
            (new RegularEmploymentIncomeLineSnapshotValidationService())->validateEmployerBurden($line);
            return;
        }
        if (!in_array($status, ['APPLICABLE', 'EXCLUDED', 'NOT_APPLICABLE'], true)) $this->fail('PAYROLL_SOURCE_INCOMPLETE', '급여 Line 적용상태를 확인해 주세요.');
        if ($status !== 'APPLICABLE' && $amount !== 0.0) $this->fail('PAYROLL_SOURCE_INCOMPLETE', '적용되지 않는 급여 Line 금액은 0원이어야 합니다.');
        if ($type !== 'PAY' && $status === 'APPLICABLE' && empty($line['statutory_standard_id'])) $this->fail('STATUTORY_STANDARD_MISSING', '법정 급여 Line의 법정기준이 없습니다.');
    }

    public function requestKey(string $documentId, string $role, string $aggregationKey): string
    {
        $this->assertRole($role);
        return 'regular-income:' . hash('sha256', $documentId . '|' . $role . '|' . $aggregationKey);
    }

    public function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function register(array $payload, string $actor): array
    {
        $normalized = $this->normalizeRegistry($payload);
        $requestKey = $this->requestKey($normalized['regular_employment_income_id'], $normalized['generation_role'], $normalized['aggregation_key']);
        $hash = $this->payloadHash($normalized);
        $existing = $this->model->findByRequestKey($requestKey, true);
        if ($existing) {
            if (!hash_equals((string) $existing['payload_hash'], $hash)) throw new \RuntimeException('동일 생성요청에 서로 다른 Payload가 감지됐습니다.');
            return ['row' => $existing, 'duplicate_prevented' => true];
        }
        $row = ['id' => UuidHelper::generate()] + $normalized + ['request_key' => $requestKey, 'payload_hash' => $hash, 'created_by' => $actor];
        $this->model->insertRegistry($row);
        return ['row' => $row, 'duplicate_prevented' => false];
    }

    private function evidenceRow(array $plan, array $item, string $id, float $employerBurden, string $actor): array
    {
        $header = $plan['header'];
        $itemId = (string) $item['id'];
        $snapshot = [
            'source_regular_employment_income_id' => (string) $header['id'],
            'regular_employment_income_item_id' => $itemId,
            'approval_request_id' => (string) $plan['approval_request_id'],
            'raw_gross_payment_amount' => round((float) $item['gross_amount'], 2),
            'raw_worker_deduction_amount' => round((float) $item['deduction_amount'], 2),
            'raw_net_payment_amount' => round((float) $item['net_payment_amount'], 2),
            'raw_employer_burden_amount' => round($employerBurden, 2),
            'calculation_version' => (string) ($header['calculation_version'] ?? ''),
            'line_items' => array_map(static fn(array $line): array => [
                'source_line_id' => (string) $line['id'],
                'sort_no' => (int) $line['sort_no'],
                'item_type_code' => (string) $line['item_type_code'],
                'item_code' => (string) $line['item_code'],
                'item_name' => (string) $line['item_name_snapshot'],
                'application_status_code' => $line['application_status_code'],
                'calculation_basis_amount' => $line['calculation_basis_amount'],
                'calculation_rate' => $line['calculation_rate'],
                'calculation_before_rounding' => $line['calculation_before_rounding'],
                'calculated_amount' => $line['calculated_amount'],
                'adjustment_amount' => $line['adjustment_amount'],
                'final_amount' => $line['final_amount'],
                'rounding_method_code' => $line['rounding_method_code'],
                'rounding_unit' => $line['rounding_unit'],
                'statutory_standard_id' => $line['statutory_standard_id'],
            ], $item['line_items']),
        ];
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return [
            'id' => $id, 'sort_no' => (new SalaryReportEvidenceModel($this->db))->nextSortNo(),
            'external_key' => (new EvidenceExternalKeyService())->key(['source_regular_employment_income_id' => $header['id'], 'regular_employment_income_item_id' => $itemId], 'PAYROLL_REPORT'),
            'source_type' => 'INTERNAL_APPROVAL', 'import_type' => 'PAYROLL_REPORT', 'source_regular_employment_income_id' => $header['id'],
            'source_document_id' => $header['id'], 'source_item_id' => $itemId,
            'approval_request_id' => $plan['approval_request_id'], 'regular_employment_income_item_id' => $itemId,
            'business_key_hash' => hash('sha256', 'PAYROLL_REPORT|' . $header['id'] . '|' . $itemId),
            'employee_id' => $item['employee_id'], 'business_unit' => 'HQ', 'transaction_direction' => 'EXPENSE', 'operation_type' => 'PAYROLL',
            'work_team_id' => null, 'team_id' => null,
            'raw_income_year_month' => $plan['attribution_month'],
            'raw_withholding_date' => $header['withholding_date'],
            'raw_gross_amount' => $item['gross_amount'], 'raw_taxable_amount' => $item['taxable_amount'], 'raw_non_taxable_amount' => $item['non_taxable_amount'],
            'raw_income_tax_amount' => $item['income_tax_amount'], 'raw_local_income_tax_amount' => $item['local_income_tax_amount'],
            'raw_national_pension_amount' => $item['national_pension_amount'], 'raw_health_insurance_amount' => $item['health_insurance_amount'],
            'raw_long_term_care_amount' => $item['long_term_care_amount'], 'raw_employment_insurance_amount' => $item['employment_insurance_amount'],
            'raw_other_deduction_amount' => $item['other_deduction_amount'], 'raw_deduction_amount' => $item['deduction_amount'],
            'raw_gross_payment_amount' => $item['gross_amount'], 'raw_worker_deduction_amount' => $item['deduction_amount'],
            'raw_net_payment_amount' => $item['net_payment_amount'], 'raw_employer_burden_amount' => $employerBurden,
            'raw_description' => $header['description'], 'snapshot_json' => $snapshotJson, 'snapshot_version' => 1,
            'snapshot_origin_code' => 'APPROVAL_CAPTURED', 'source_hash' => hash('sha256', $snapshotJson),
            'calculation_version' => $snapshot['calculation_version'],
            'evidence_status' => EvidenceWorkflowPolicyService::COMPLETED,
            'approved_at' => date('Y-m-d H:i:s'), 'approved_by' => $actor, 'created_by' => $actor, 'updated_by' => $actor,
        ];
    }

    private function employeeTransactionPayload(array $header, array $item, string $evidenceId, string $recognitionDate): array
    {
        $pay = $settlements = [];
        $deductionPolicy = new RegularEmploymentIncomeDeductionLineService();
        foreach ($item['line_items'] as $line) {
            $amount = round((float) $line['final_amount'], 2);
            if ($amount === 0.0 || ($line['item_type_code'] !== 'PAY' && ($line['application_status_code'] ?? '') !== 'APPLICABLE')) continue;
            $trace = ['regular_employment_income_line_item_id' => $line['id'], 'statutory_standard_revision_id' => $line['statutory_standard_id'] ?? null, 'calculation_basis_id' => null];
            if ($line['item_type_code'] === 'PAY') {
                $pay[] = $trace + ['item_date' => $recognitionDate, 'item_name' => $line['item_name_snapshot'], 'item_quantity' => 1,
                    'item_unit_name' => '건', 'item_unit_price' => $amount, 'item_supply_amount' => $amount,
                    'item_description' => $header['income_year_month'] . ' 귀속 ' . $item['employee_name_snapshot'] . ' ' . $line['item_code']];
            } elseif ($line['item_type_code'] === 'DEDUCTION') {
                $signed = $deductionPolicy->signedAmount($line);
                $settlements[] = $trace + ['settlement_type' => $deductionPolicy->projectionType($line), 'amount_sign' => $signed < 0 ? 'PLUS' : 'MINUS',
                    'amount' => $amount, 'currency' => 'KRW', 'settlement_description' => $line['item_name_snapshot'],
                    'meta_json' => ['burden_subject' => 'EMPLOYEE', 'item_code' => $line['item_code'], 'attribution_month' => $header['income_year_month']]];
            }
        }
        if (count($pay) !== 4) $this->fail('PAYROLL_SOURCE_INCOMPLETE', (string) $item['employee_name_snapshot'] . ' 직원의 거래 지급항목은 4건이어야 합니다.');
        $from = $header['income_year_month'] . '-01';
        return ['business_unit' => 'HQ', 'transaction_direction' => 'EXPENSE', 'operation_type' => 'PAYROLL', 'employee_id' => $item['employee_id'],
            'transaction_date' => $recognitionDate, 'transaction_supply_amount' => $item['gross_amount'], 'transaction_final_amount' => $item['net_payment_amount'],
            'transaction_description' => $header['title'] . ' - ' . $item['employee_name_snapshot'], 'transaction_note' => $header['income_year_month'] . ' 귀속 직원별 급여',
            'status' => 'completed', 'reference_validation_context' => ['employee_policy' => 'REGULAR_EMPLOYMENT_INCOME_EFFECTIVE_SNAPSHOT',
                'source_document_id' => $header['id'], 'source_item_id' => $item['id'], 'employment_contract_id' => $item['employment_contract_id'],
                'period_from' => $from, 'period_to' => date('Y-m-t', strtotime($from))],
            'items' => $pay, 'settlements' => $settlements,
            'linked_evidences' => [['import_type' => 'PAYROLL_REPORT', 'evidence_id' => $evidenceId, 'link_purpose' => EvidenceWorkflowPolicyService::LINK_SOURCE_TRACE]]];
    }

    private function normalizeRegistry(array $payload): array
    {
        $role = strtoupper(trim((string) ($payload['generation_role'] ?? '')));
        $this->assertRole($role);
        $row = ['regular_employment_income_id' => trim((string) ($payload['regular_employment_income_id'] ?? '')),
            'regular_employment_income_item_id' => $this->nullable($payload['regular_employment_income_item_id'] ?? null),
            'generation_role' => $role, 'aggregation_key' => trim((string) ($payload['aggregation_key'] ?? '')),
            'approval_request_id' => trim((string) ($payload['approval_request_id'] ?? '')), 'evidence_id' => $this->nullable($payload['evidence_id'] ?? null),
            'transaction_id' => $this->nullable($payload['transaction_id'] ?? null), 'payment_schedule_id' => $this->nullable($payload['payment_schedule_id'] ?? null),
            'attribution_month' => trim((string) ($payload['attribution_month'] ?? '')), 'recognition_date' => $this->nullable($payload['recognition_date'] ?? null)];
        if ($row['regular_employment_income_id'] === '' || $row['aggregation_key'] === '' || $row['approval_request_id'] === ''
            || $row['regular_employment_income_item_id'] === null || $row['evidence_id'] === null) throw new \InvalidArgumentException('급여 Registry 필수값을 확인해 주세요.');
        if ($role === self::ROLE_PAYROLL_REPORT_EVIDENCE && ($row['transaction_id'] !== null || $row['payment_schedule_id'] !== null || $row['recognition_date'] !== null)) throw new \InvalidArgumentException('급여 증빙 역할의 연결값을 확인해 주세요.');
        if ($role === self::ROLE_EMPLOYEE_PAYROLL && ($row['transaction_id'] === null || $row['payment_schedule_id'] !== null || $row['recognition_date'] === null)) throw new \InvalidArgumentException('직원 급여 역할의 연결값을 확인해 주세요.');
        return $row;
    }

    private function assertRole(string $role): void
    {
        if (!in_array($role, [self::ROLE_EMPLOYEE_PAYROLL, self::ROLE_PAYROLL_REPORT_EVIDENCE], true)) throw new \InvalidArgumentException('급여 생성 역할을 확인해 주세요.');
    }

    private function checkpoint(string $name): void
    {
        if ($this->failureInjector !== null) ($this->failureInjector)('closure.' . $name);
    }

    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) if (is_array($item)) $item = $this->canonicalize($item);
        unset($item);
        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function fail(string $code, string $message, ?\Throwable $previous = null): never
    {
        throw new RegularEmploymentIncomeAccountingException($code, $message, null, $previous);
    }
}
