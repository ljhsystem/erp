<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\DailyEmploymentIncomeAccountingGenerationModel;
use App\Models\Institution\DailyEmploymentIncomeModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Services\Ledger\TransactionCrudService;
use Core\Helpers\UuidHelper;
use PDO;

final class DailyEmploymentIncomeAccountingGenerationService
{
    public const EVIDENCE_TYPE = 'DAILY_EMPLOYMENT_INCOME';

    private DailyEmploymentIncomeAccountingGenerationModel $model;
    private DailyEmploymentIncomeModel $incomeModel;
    private DailyEmploymentIncomeCalculationResultService $results;

    public function __construct(private readonly PDO $db, private $failureInjector = null)
    {
        $this->model = new DailyEmploymentIncomeAccountingGenerationModel($db);
        $this->incomeModel = new DailyEmploymentIncomeModel($db);
        $this->results = new DailyEmploymentIncomeCalculationResultService($db);
    }

    public function preflightFinalStep(string $stepId, bool $lock = false): array
    {
        $context = $this->model->approvalContextByStep($stepId, $lock);
        if (!$context || (string) ($context['document_type'] ?? '') !== DailyEmploymentIncomeService::DOCUMENT_TYPE) {
            throw new \RuntimeException('일용근로소득 결재요청을 찾을 수 없습니다.');
        }
        if (strtoupper((string) ($context['step_type'] ?? '')) !== 'FINAL_APPROVAL') {
            return ['is_final' => false, 'context' => $context];
        }
        if (!in_array((string) ($context['request_status'] ?? ''), ['pending', 'in_progress'], true)
            || (string) ($context['status'] ?? '') !== 'pending'
            || (int) ($context['current_step'] ?? 0) !== (int) ($context['sort_no'] ?? -1)) {
            throw new \RuntimeException('현재 처리할 최종 결재단계가 아닙니다.');
        }
        return ['is_final' => true, 'context' => $context, 'plan' => $this->preflight(
            (string) $context['document_id'],
            (string) $context['approval_request_id'],
            $lock
        )];
    }

    public function preflight(string $documentId, string $approvalId, bool $lock = false): array
    {
        $header = $this->incomeModel->find($documentId, $lock);
        if (!$header || (string) ($header['status_code'] ?? '') !== 'PENDING'
            || (string) ($header['approval_request_id'] ?? '') !== $approvalId) {
            throw new \RuntimeException('결재 대기 중인 일용근로소득 원본을 확인해 주세요.');
        }
        $revision = $this->results->latest($documentId);
        if (!$revision || (string) ($revision['status_code'] ?? '') !== 'CALCULATED') {
            throw new \RuntimeException('최신 공식 계산 Revision을 확인해 주세요.');
        }
        $types = array_values(array_unique(array_map(
            static fn(array $result): string => (string) ($result['result_type_code'] ?? ''),
            (array) ($revision['results'] ?? [])
        )));
        $required = ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT_INSURANCE'];
        if (array_diff($required, $types) !== []) throw new \RuntimeException('보험 5종 공식 계산결과가 완전하지 않습니다.');

        $groups = $this->incomeModel->groups($documentId);
        $items = [];
        foreach ($groups as $group) {
            foreach ($this->incomeModel->items((string) $group['id']) as $item) {
                $item['workdays'] = $this->incomeModel->workdays((string) $item['id']);
                $item['lines'] = $this->incomeModel->lines((string) $item['id']);
                $items[] = ['group' => $group, 'item' => $item];
            }
        }
        if ($items === []) throw new \RuntimeException('승인할 Group×근로자 지급자료가 없습니다.');
        return compact('header', 'revision', 'items') + ['approval_request_id' => $approvalId];
    }

    public function materialize(array $plan, string $actor): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('일용근로소득 최종승인 물리화는 바깥 Transaction 안에서만 실행해야 합니다.');
        $header = $plan['header'];
        $revision = $plan['revision'];
        $documentId = (string) $header['id'];
        $approvalId = (string) $plan['approval_request_id'];
        $payload = ['document_id' => $documentId, 'approval_request_id' => $approvalId, 'calculation_revision_id' => $revision['id'], 'source_hash' => $revision['source_hash']];
        $payloadHash = $this->hash($payload);
        $existing = $this->model->closure($documentId, $approvalId, true);
        if ($existing) {
            if (!hash_equals((string) $existing['payload_hash'], $payloadHash)) throw new \RuntimeException('동일 승인에 서로 다른 Closure Payload가 감지됐습니다.');
            if ((string) $existing['status_code'] === 'COMPLETED') {
                return $this->completedResponse($existing, $this->assertCompletedArtifacts($existing, $plan), true);
            }
            throw new \RuntimeException('동일 승인 Closure가 처리 중입니다.');
        }
        $closureId = UuidHelper::generate();
        $this->model->insertClosure([
            'id' => $closureId, 'daily_employment_income_id' => $documentId,
            'approval_request_id' => $approvalId, 'calculation_revision_id' => $revision['id'],
            'source_hash' => $revision['source_hash'], 'status_code' => 'PROCESSING',
            'payload_hash' => $payloadHash, 'created_by' => $actor, 'updated_by' => $actor,
        ]);

        $evidenceIds = $transactionIds = [];
        foreach ($plan['items'] as $index => $source) {
            $group = $source['group'];
            $item = $source['item'];
            $this->assertEvidenceAmounts($header, $group, $item);
            $grain = [self::EVIDENCE_TYPE, $documentId, $group['id'], $item['id'], $item['worker_client_id']];
            $businessKeyHash = hash('sha256', implode('|', $grain));
            $snapshot = $this->snapshot($plan, $group, $item);
            $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            $this->checkpoint('before_evidence_' . ($index + 1));
            $evidenceId = UuidHelper::generate();
            $this->model->insertEvidence([
                'id' => $evidenceId, 'source_daily_employment_income_id' => $documentId,
                'daily_employment_income_item_id' => $item['id'], 'daily_employment_income_group_id' => $group['id'],
                'approval_request_id' => $approvalId, 'calculation_revision_id' => $revision['id'],
                'source_hash' => $revision['source_hash'], 'worker_client_id' => $item['worker_client_id'],
                'business_unit' => $group['business_unit'], 'transaction_direction' => 'EXPENSE',
                'operation_type' => 'DAILY_WORKER',
                'work_scope_code' => $group['project_id'] ? 'PROJECT' : 'HEAD_OFFICE',
                'sort_no' => (int) ($item['sort_no'] ?? 0),
                'external_key' => 'DEI:' . hash('sha256', implode('|', [$documentId, $group['id'], $item['id']])),
                'source_type' => 'INTERNAL_APPROVAL', 'import_type' => 'DAILY_EMPLOYMENT_INCOME',
                'client_id' => $item['worker_client_id'], 'employee_id' => null,
                'bank_account_id' => null, 'card_id' => null,
                'project_id' => $group['project_id'] ?: null, 'work_team_id' => $group['work_team_id'] ?: null,
                'raw_business_unit' => $group['business_unit'],
                'raw_project_id' => $group['project_id'] ?: null,
                'raw_work_team_id' => $group['work_team_id'] ?: null,
                'income_year_month' => $header['income_year_month'],
                'raw_income_year_month' => $header['income_year_month'],
                'raw_withholding_date' => $header['withholding_date'],
                'total_work_days' => $item['total_work_days'], 'total_gross_amount' => $item['total_gross_amount'],
                'total_deduction_amount' => $item['total_deduction_amount'], 'total_net_payment_amount' => $item['total_net_payment_amount'],
                'total_employer_burden_amount' => $item['total_employer_burden_amount'],
                'raw_work_day_count' => $item['total_work_days'],
                'raw_gross_payment_amount' => $item['total_gross_amount'],
                'raw_worker_deduction_amount' => $item['total_deduction_amount'],
                'raw_net_payment_amount' => $item['total_net_payment_amount'],
                'raw_employer_burden_amount' => $item['total_employer_burden_amount'],
                'snapshot_json' => $snapshotJson, 'business_key_hash' => $businessKeyHash,
                'evidence_status_code' => 'COMPLETED', 'evidence_status' => 'COMPLETED',
                'approved_by' => $actor,
                'approved_at' => date('Y-m-d H:i:s'), 'created_by' => $actor, 'updated_by' => $actor,
            ]);
            foreach ((array) ($item['lines'] ?? []) as $lineIndex => $line) {
                $this->model->insertEvidenceRawLine($this->rawLineRow(
                    $evidenceId,
                    (string) $revision['id'],
                    $line,
                    $lineIndex + 1,
                    $actor
                ));
            }
            $this->checkpoint('after_evidence_raw_lines_' . ($index + 1));
            $evidenceIds[] = $evidenceId;
            $this->model->insertAccountingLink($this->linkRow($closureId, $documentId, $group, $item, 'EVIDENCE', $businessKeyHash, $snapshot, $evidenceId, null, $actor));
            $this->checkpoint('after_evidence_' . ($index + 1));

            $transaction = (new TransactionCrudService($this->db, $this->failureInjector))->save(
                $this->workerPaymentPayload($plan, $group, $item)
            );
            if (empty($transaction['success'])) throw new \RuntimeException((string) ($transaction['message'] ?? '일용근로소득 지급거래 생성에 실패했습니다.'));
            $transactionId = (string) $transaction['id'];
            $this->checkpoint('before_link_' . ($index + 1));
            (new EvidenceLinkModel($this->db))->upsertAutoTransactionEvidence(self::EVIDENCE_TYPE, $evidenceId, $transactionId);
            $this->checkpoint('after_link_' . ($index + 1));
            $transactionIds[] = $transactionId;
            $paymentKeyHash = hash('sha256', implode('|', array_merge($grain, ['WORKER_PAYMENT'])));
            $this->model->insertAccountingLink($this->linkRow($closureId, $documentId, $group, $item, 'WORKER_PAYMENT', $paymentKeyHash, $snapshot, $evidenceId, $transactionId, $actor));
            $this->checkpoint('after_registry_' . ($index + 1));
            $this->checkpoint('after_transaction_' . ($index + 1));
        }
        $this->checkpoint('before_closure_complete');
        $this->model->completeClosure($closureId, $actor);
        $completed = $this->model->closure($documentId, $approvalId, true);
        if (!$completed) throw new \RuntimeException('완료된 일용근로소득 Closure를 다시 조회하지 못했습니다.');
        return $this->completedResponse($completed, $this->assertCompletedArtifacts($completed, $plan), false);
    }

    public function replayCompletedFinalStep(string $stepId, string $userId): ?array
    {
        $context = $this->model->approvalContextByStep($stepId, true);
        if (!$context || (string) ($context['document_type'] ?? '') !== DailyEmploymentIncomeService::DOCUMENT_TYPE
            || strtoupper((string) ($context['step_type'] ?? '')) !== 'FINAL_APPROVAL') {
            return null;
        }
        if ((string) ($context['request_status'] ?? '') !== 'approved' || (string) ($context['status'] ?? '') !== 'approved') {
            return null;
        }
        if ((string) ($context['acted_by'] ?? '') !== $userId) {
            throw new \RuntimeException('기존 최종승인 처리자만 동일 Callback 결과를 재조회할 수 있습니다.');
        }
        $documentId = (string) $context['document_id'];
        $approvalId = (string) $context['approval_request_id'];
        $closure = $this->model->closure($documentId, $approvalId, true);
        if (!$closure || (string) ($closure['status_code'] ?? '') !== 'COMPLETED') {
            throw new \RuntimeException('승인 완료 상태와 Closure 원장이 일치하지 않습니다.');
        }
        $revision = $this->results->latest($documentId);
        if (!$revision || !hash_equals((string) $closure['calculation_revision_id'], (string) $revision['id'])
            || !hash_equals((string) $closure['source_hash'], (string) $revision['source_hash'])) {
            throw new \RuntimeException('동일 승인 Callback의 Source Hash 또는 Calculation Revision이 변경되었습니다.');
        }
        $header = $this->incomeModel->find($documentId, true);
        if (!$header || (string) ($header['approval_request_id'] ?? '') !== $approvalId) {
            throw new \RuntimeException('승인 원본과 Closure 원장의 업무키가 일치하지 않습니다.');
        }
        $items = [];
        foreach ($this->incomeModel->groups($documentId) as $group) {
            foreach ($this->incomeModel->items((string) $group['id']) as $item) {
                $item['workdays'] = $this->incomeModel->workdays((string) $item['id']);
                $item['lines'] = $this->incomeModel->lines((string) $item['id']);
                $items[] = ['group' => $group, 'item' => $item];
            }
        }
        return $this->completedResponse($closure, $this->assertCompletedArtifacts(
            $closure,
            compact('header', 'revision', 'items') + ['approval_request_id' => $approvalId]
        ), true);
    }

    private function assertCompletedArtifacts(array $closure, array $plan): array
    {
        $expected = [];
        $expectedAmounts = [];
        foreach ($plan['items'] as $source) {
            $group = $source['group'];
            $item = $source['item'];
            $grain = [self::EVIDENCE_TYPE, $closure['daily_employment_income_id'], $group['id'], $item['id'], $item['worker_client_id']];
            $expected['EVIDENCE|' . $item['id']] = hash('sha256', implode('|', $grain));
            $expected['WORKER_PAYMENT|' . $item['id']] = hash('sha256', implode('|', array_merge($grain, ['WORKER_PAYMENT'])));
            $expectedAmounts[(string) $item['id']] = [
                'gross' => round((float) $item['total_gross_amount'], 2),
                'deduction' => round((float) $item['total_deduction_amount'], 2),
                'net' => round((float) $item['total_net_payment_amount'], 2),
                'settlements' => $this->expectedSettlementTrace($plan, $group, $item),
                'raw_lines' => $this->expectedRawLines((string) $plan['revision']['id'], $item),
            ];
        }
        $rows = $this->model->accountingLinks((string) $closure['id'], true);
        $seen = [];
        foreach ($rows as $row) {
            $key = (string) $row['artifact_role'] . '|' . (string) $row['daily_employment_income_item_id'];
            if (isset($seen[$key]) || !isset($expected[$key])
                || !hash_equals($expected[$key], (string) $row['business_key_hash'])) {
                throw new \RuntimeException('동일 승인 Callback의 Group×근로자 업무키가 변경되었거나 중복되었습니다.');
            }
            if (!hash_equals((string) $closure['source_hash'], (string) $row['evidence_source_hash'])
                || !hash_equals((string) $closure['calculation_revision_id'], (string) $row['evidence_calculation_revision_id'])
                || !hash_equals((string) $closure['approval_request_id'], (string) $row['evidence_approval_request_id'])) {
                throw new \RuntimeException('Closure와 Evidence의 Source Hash 또는 Calculation Revision이 일치하지 않습니다.');
            }
            if ((string) $row['artifact_role'] === 'EVIDENCE') {
                $amounts = $expectedAmounts[(string) $row['daily_employment_income_item_id']] ?? null;
                if (!$amounts) throw new \RuntimeException('일용 Evidence Raw Line 검증기준이 없습니다.');
                $this->assertRawLines(
                    $this->model->evidenceRawLines((string) $row['evidence_id'], true),
                    $amounts['raw_lines']
                );
            }
            if ((string) $row['artifact_role'] === 'WORKER_PAYMENT' && trim((string) ($row['evidence_link_id'] ?? '')) === '') {
                throw new \RuntimeException('근로자 지급 Transaction의 Evidence Link를 확인할 수 없습니다.');
            }
            if ((string) $row['artifact_role'] === 'WORKER_PAYMENT') {
                $composition = $this->model->transactionComposition((string) $row['transaction_id']);
                $amounts = $expectedAmounts[(string) $row['daily_employment_income_item_id']] ?? null;
                if (!$composition || !$amounts
                    || (string) $composition['operation_type'] !== 'DAILY_WORKER'
                    || (string) $composition['status'] !== 'completed'
                    || (int) $composition['evidence_link_count'] !== 1
                    || (int) $composition['daily_evidence_link_count'] !== 1
                    || (int) $composition['item_count'] !== 1
                    || round((float) $row['evidence_gross_payment_amount'], 2) !== $amounts['gross']
                    || round((float) $row['evidence_worker_deduction_amount'], 2) !== $amounts['deduction']
                    || round((float) $row['evidence_net_payment_amount'], 2) !== $amounts['net']
                    || round((float) $composition['item_total'], 2) !== $amounts['gross']
                    || round((float) $composition['transaction_supply_amount'], 2) !== $amounts['gross']
                    || round((float) $composition['settlement_total'], 2) !== -$amounts['deduction']
                    || round((float) $composition['transaction_settlement_amount'], 2) !== -$amounts['deduction']
                    || round((float) $composition['transaction_final_amount'], 2) !== $amounts['net']) {
                    throw new \RuntimeException('일용 지급 Transaction의 세전 지급액·공제 정산·실지급액 구성을 확인해 주세요.');
                }
                $this->assertSettlementTrace(
                    $this->model->transactionSettlements((string) $row['transaction_id']),
                    $amounts['settlements']
                );
            }
            $seen[$key] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new \RuntimeException('일용근로소득 Closure 산출물 Registry가 완전하지 않습니다.');
        }
        return $rows;
    }

    private function completedResponse(array $closure, array $rows, bool $reused): array
    {
        $evidence = array_values(array_unique(array_column($rows, 'evidence_id')));
        $transactions = array_values(array_unique(array_filter(array_column($rows, 'transaction_id'))));
        $evidenceLinks = array_values(array_unique(array_filter(array_column($rows, 'evidence_link_id'))));
        $hashRows = array_map(static fn(array $row): array => [
            'role' => $row['artifact_role'], 'business_key_hash' => $row['business_key_hash'],
            'evidence_id' => $row['evidence_id'], 'transaction_id' => $row['transaction_id'],
        ], $rows);
        $resultHash = $this->hash(['closure_id' => $closure['id'], 'source_hash' => $closure['source_hash'], 'artifacts' => $hashRows]);
        return [
            'status' => $reused ? 'ALREADY_PROCESSED' : 'PROCESSED',
            'created' => $reused ? 0 : ['evidence' => count($evidence), 'transactions' => count($transactions), 'evidence_links' => count($evidenceLinks)],
            'reused' => $reused ? ['evidence' => count($evidence), 'transactions' => count($transactions), 'evidence_links' => count($evidenceLinks)] : ['evidence' => 0, 'transactions' => 0, 'evidence_links' => 0],
            'closure_id' => $closure['id'], 'result_hash' => $resultHash,
            'evidence_ids' => $evidence, 'transaction_ids' => $transactions,
        ];
    }

    private function snapshot(array $plan, array $group, array $item): array
    {
        return [
            'source_document_id' => $plan['header']['id'], 'source_group_id' => $group['id'],
            'source_item_id' => $item['id'], 'worker_client_id' => $item['worker_client_id'],
            'worker_name' => $item['worker_name_snapshot'], 'business_unit' => $group['business_unit'],
            'project_id' => $group['project_id'], 'work_team_id' => $group['work_team_id'],
            'income_year_month' => $plan['header']['income_year_month'],
            'workdays' => $item['workdays'], 'lines' => $item['lines'],
            'total_work_days' => $item['total_work_days'], 'total_gross_amount' => $item['total_gross_amount'],
            'total_deduction_amount' => $item['total_deduction_amount'], 'total_net_payment_amount' => $item['total_net_payment_amount'],
            'total_employer_burden_amount' => $item['total_employer_burden_amount'],
            'calculation_revision_id' => $plan['revision']['id'], 'source_hash' => $plan['revision']['source_hash'],
            'approval_request_id' => $plan['approval_request_id'],
        ];
    }

    private function workerPaymentPayload(array $plan, array $group, array $item): array
    {
        $header = $plan['header'];
        $workDates = array_values(array_filter(array_column((array) ($item['workdays'] ?? []), 'work_date')));
        $transactionDate = $workDates === [] ? null : max($workDates);
        if (!is_string($transactionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
            throw new \RuntimeException('일용근로소득 거래일을 확정할 근무일이 없습니다.');
        }
        $gross = round((float) $item['total_gross_amount'], 2);
        $deduction = round((float) $item['total_deduction_amount'], 2);
        $net = round((float) $item['total_net_payment_amount'], 2);
        $settlements = [];
        foreach ((array) ($item['lines'] ?? []) as $line) {
            if (strtoupper((string) ($line['line_type_code'] ?? '')) !== 'DEDUCTION') continue;
            $amount = round((float) ($line['final_amount'] ?? 0), 2);
            $component = [
                'daily_employment_income_line_id' => (string) ($line['id'] ?? ''),
                'line_code' => (string) ($line['line_code'] ?? ''),
                'line_name' => (string) ($line['line_name_snapshot'] ?? ''),
                'application_status_code' => (string) ($line['application_status_code'] ?? ''),
                'amount' => $amount,
                'statutory_standard_id' => $line['statutory_standard_id'] ?? null,
            ];
            if ($amount <= 0 || strtoupper($component['application_status_code']) !== 'APPLICABLE') continue;
            $settlements[] = [
                'settlement_type' => strtoupper($component['line_code']) . '_CURRENT',
                'amount_sign' => 'MINUS',
                'amount' => $amount,
                'currency' => 'KRW',
                'settlement_description' => $component['line_name'] ?: '일용근로소득 원천징수',
                'meta_json' => [
                    'burden_subject' => 'EMPLOYEE',
                    'source_document_id' => (string) $header['id'],
                    'source_group_id' => (string) $group['id'],
                    'source_item_id' => (string) $item['id'],
                    'source_line_id' => $component['daily_employment_income_line_id'],
                    'line_code' => $component['line_code'],
                    'application_status_code' => $component['application_status_code'],
                    'statutory_standard_id' => $component['statutory_standard_id'],
                    'calculation_revision_id' => (string) $plan['revision']['id'],
                    'source_hash' => (string) $plan['revision']['source_hash'],
                    'attribution_month' => (string) $header['income_year_month'],
                ],
            ];
        }
        $settlementTotal = round(array_sum(array_map(static fn(array $row): float => (float) $row['amount'], $settlements)), 2);
        if ($settlementTotal !== $deduction || round($gross - $deduction, 2) !== $net) {
            throw new \RuntimeException('일용근로소득 지급액·공제액·실지급액 구성을 확인해 주세요.');
        }
        return [
            'business_unit' => $group['business_unit'],
            'transaction_direction' => 'EXPENSE',
            'operation_type' => 'DAILY_WORKER',
            'client_id' => $item['worker_client_id'],
            'project_id' => $group['project_id'] ?: null,
            'team_id' => $group['work_team_id'] ?: null,
            'transaction_date' => $transactionDate,
            'transaction_supply_amount' => $gross,
            'transaction_final_amount' => $net,
            'transaction_description' => $header['document_title'] . ' - ' . $item['worker_name_snapshot'],
            'transaction_note' => $header['income_year_month'] . ' 귀속 일용근로소득 지급',
            'status' => 'completed',
            'items' => [[
                'item_date' => $transactionDate,
                'item_name' => $header['income_year_month'] . ' 귀속 일용근로소득',
                'item_quantity' => 1,
                'item_unit_name' => '건',
                'item_unit_price' => $gross,
                'item_supply_amount' => $gross,
                'item_description' => $group['work_description'],
            ]],
            'settlements' => $settlements,
        ];
    }

    private function rawLineRow(string $evidenceId, string $revisionId, array $line, int $fallbackSortNo, string $actor): array
    {
        $lineId = trim((string) ($line['id'] ?? ''));
        if ($lineId === '') throw new \RuntimeException('일용 Evidence Raw Line의 원 계산 Line ID가 없습니다.');
        $type = strtoupper(trim((string) ($line['line_type_code'] ?? '')));
        if (!in_array($type, ['PAY', 'DEDUCTION', 'EMPLOYER_BURDEN'], true)) {
            throw new \RuntimeException('일용 Evidence Raw Line 유형이 올바르지 않습니다.');
        }
        return [
            'id' => UuidHelper::generate(), 'evidence_id' => $evidenceId,
            'sort_no' => (int) ($line['sort_no'] ?? $fallbackSortNo),
            'source_calculation_line_id' => $lineId, 'calculation_revision_id' => $revisionId,
            'line_type_code' => $type, 'line_code' => strtoupper(trim((string) ($line['line_code'] ?? ''))),
            'line_name_snapshot' => (string) ($line['line_name_snapshot'] ?? ''),
            'burden_subject_code' => $type === 'EMPLOYER_BURDEN' ? 'EMPLOYER' : 'EMPLOYEE',
            'application_status_code' => $line['application_status_code'] ?? null,
            'taxability_code' => $line['taxability_code'] ?? null,
            'raw_calculation_basis_amount' => $line['calculation_basis_amount'] ?? null,
            'raw_calculation_rate' => $line['calculation_rate'] ?? null,
            'raw_calculation_before_rounding' => $line['calculation_before_rounding'] ?? null,
            'raw_calculated_amount' => $line['calculated_amount'] ?? null,
            'raw_adjustment_amount' => $line['adjustment_amount'] ?? null,
            'raw_final_amount' => $line['final_amount'] ?? null,
            'rounding_method_code' => $line['rounding_method_code'] ?? null,
            'rounding_unit' => $line['rounding_unit'] ?? null,
            'statutory_standard_id' => $line['statutory_standard_id'] ?? null,
            'coverage_id' => $line['coverage_id'] ?? null,
            'social_insurance_workplace_id' => $line['social_insurance_workplace_id'] ?? null,
            'created_by' => $actor, 'updated_by' => $actor,
        ];
    }

    private function expectedRawLines(string $revisionId, array $item): array
    {
        $expected = [];
        foreach ((array) ($item['lines'] ?? []) as $index => $line) {
            $id = (string) ($line['id'] ?? '');
            $expected[$id] = [
                'source_calculation_line_id' => $id,
                'calculation_revision_id' => $revisionId,
                'line_type_code' => strtoupper((string) ($line['line_type_code'] ?? '')),
                'line_code' => strtoupper((string) ($line['line_code'] ?? '')),
                'burden_subject_code' => strtoupper((string) ($line['line_type_code'] ?? '')) === 'EMPLOYER_BURDEN' ? 'EMPLOYER' : 'EMPLOYEE',
                'application_status_code' => $line['application_status_code'] ?? null,
                'raw_final_amount' => round((float) ($line['final_amount'] ?? 0), 2),
            ];
        }
        ksort($expected);
        return $expected;
    }

    private function assertRawLines(array $actualRows, array $expected): void
    {
        $actual = [];
        foreach ($actualRows as $row) {
            $id = (string) ($row['source_calculation_line_id'] ?? '');
            if ($id === '' || isset($actual[$id])) throw new \RuntimeException('일용 Evidence Raw Line Grain이 중복되었거나 없습니다.');
            $actual[$id] = [
                'source_calculation_line_id' => $id,
                'calculation_revision_id' => (string) ($row['calculation_revision_id'] ?? ''),
                'line_type_code' => strtoupper((string) ($row['line_type_code'] ?? '')),
                'line_code' => strtoupper((string) ($row['line_code'] ?? '')),
                'burden_subject_code' => strtoupper((string) ($row['burden_subject_code'] ?? '')),
                'application_status_code' => $row['application_status_code'] ?? null,
                'raw_final_amount' => round((float) ($row['raw_final_amount'] ?? 0), 2),
            ];
        }
        ksort($actual);
        if ($actual !== $expected) throw new \RuntimeException('일용 Evidence Raw Line이 승인 계산 Line과 일치하지 않습니다.');
    }

    private function expectedSettlementTrace(array $plan, array $group, array $item): array
    {
        $expected = [];
        foreach ((array) ($item['lines'] ?? []) as $line) {
            $amount = round((float) ($line['final_amount'] ?? 0), 2);
            if (strtoupper((string) ($line['line_type_code'] ?? '')) !== 'DEDUCTION'
                || strtoupper((string) ($line['application_status_code'] ?? '')) !== 'APPLICABLE'
                || $amount <= 0) {
                continue;
            }
            $lineId = (string) ($line['id'] ?? '');
            $expected[$lineId] = [
                'amount' => $amount,
                'source_document_id' => (string) $plan['header']['id'],
                'source_group_id' => (string) $group['id'],
                'source_item_id' => (string) $item['id'],
                'source_line_id' => $lineId,
                'line_code' => (string) ($line['line_code'] ?? ''),
                'calculation_revision_id' => (string) $plan['revision']['id'],
                'source_hash' => (string) $plan['revision']['source_hash'],
                'attribution_month' => (string) $plan['header']['income_year_month'],
            ];
        }
        ksort($expected);
        return $expected;
    }

    private function assertEvidenceAmounts(array $header, array $group, array $item): void
    {
        $workDays = round((float) ($item['total_work_days'] ?? 0), 2);
        $gross = round((float) ($item['total_gross_amount'] ?? 0), 2);
        $deduction = round((float) ($item['total_deduction_amount'] ?? 0), 2);
        $net = round((float) ($item['total_net_payment_amount'] ?? 0), 2);
        $employerBurden = round((float) ($item['total_employer_burden_amount'] ?? 0), 2);
        if ($workDays < 0 || $gross < 0 || $deduction < 0 || $net < 0 || $employerBurden < 0) {
            throw new \RuntimeException('일용근로소득 Evidence 금액과 근무일수는 음수일 수 없습니다.');
        }
        if (round($gross - $deduction, 2) !== $net) {
            throw new \RuntimeException('일용근로소득 공제 전 지급액, 근로자 공제액과 실지급액이 일치하지 않습니다.');
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($header['income_year_month'] ?? ''))) {
            throw new \RuntimeException('일용근로소득 귀속연월 형식이 올바르지 않습니다.');
        }
        if (trim((string) ($group['business_unit'] ?? '')) === '') {
            throw new \RuntimeException('일용근로소득 근무그룹의 사업구분이 없습니다.');
        }
    }

    private function assertSettlementTrace(array $settlements, array $expected): void
    {
        $actual = [];
        foreach ($settlements as $settlement) {
            if ((string) ($settlement['amount_sign'] ?? '') !== 'MINUS') {
                throw new \RuntimeException('일용근로소득 공제 Settlement 부호가 올바르지 않습니다.');
            }
            $meta = json_decode((string) ($settlement['meta_json'] ?? ''), true);
            if (!is_array($meta) || (string) ($meta['burden_subject'] ?? '') !== 'EMPLOYEE') {
                throw new \RuntimeException('일용근로소득 지급거래에 근로자 공제가 아닌 Settlement가 포함되어 있습니다.');
            }
            $lineId = (string) ($meta['source_line_id'] ?? '');
            if ($lineId === '' || isset($actual[$lineId])) {
                throw new \RuntimeException('일용근로소득 공제 Settlement 원 계산 Line 추적정보가 중복되었거나 없습니다.');
            }
            $actual[$lineId] = ['amount' => round((float) $settlement['amount'], 2)] + array_intersect_key(
                $meta,
                array_flip([
                    'source_document_id','source_group_id','source_item_id','source_line_id',
                    'line_code','calculation_revision_id','source_hash','attribution_month',
                ])
            );
        }
        ksort($actual);
        if ($actual !== $expected) {
            throw new \RuntimeException('일용근로소득 공제 Settlement 추적정보가 승인 계산결과와 일치하지 않습니다.');
        }
    }

    private function linkRow(string $closureId, string $documentId, array $group, array $item, string $role, string $businessHash, array $snapshot, string $evidenceId, ?string $transactionId, string $actor): array
    {
        return [
            'id' => UuidHelper::generate(), 'closure_id' => $closureId,
            'daily_employment_income_id' => $documentId, 'daily_employment_income_group_id' => $group['id'],
            'daily_employment_income_item_id' => $item['id'], 'worker_client_id' => $item['worker_client_id'],
            'artifact_role' => $role, 'business_key_hash' => $businessHash,
            'payload_hash' => $this->hash($snapshot + ['artifact_role' => $role]),
            'evidence_id' => $evidenceId, 'transaction_id' => $transactionId, 'created_by' => $actor,
        ];
    }

    private function hash(array $payload): string
    {
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function checkpoint(string $name): void
    {
        if ($this->failureInjector !== null) ($this->failureInjector)('daily_closure.' . $name);
    }
}
