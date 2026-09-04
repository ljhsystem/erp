<?php

namespace App\Services\Approval;

use App\Models\Approval\PersonalExpenseClassificationCorrectionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class PersonalExpenseClassificationCorrectionService
{
    private PersonalExpenseClassificationCorrectionModel $model;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new PersonalExpenseClassificationCorrectionModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval-personal-expense-classification-correction');
    }

    public function correctBatch(array $payload): array
    {
        $documentId = trim((string) ($payload['personal_expense_id'] ?? ''));
        $approvalRequestId = trim((string) ($payload['approval_request_id'] ?? ''));
        $batchKey = trim((string) ($payload['correction_batch_key'] ?? ''));
        $reason = trim((string) ($payload['correction_reason'] ?? ''));
        $items = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];
        if ($documentId === '' || $approvalRequestId === '' || $batchKey === '' || $reason === '' || $items === []) {
            throw new \InvalidArgumentException('문서, 승인요청, 일괄 정정키, 정정 사유와 정정 항목은 필수입니다.');
        }
        if (count($items) !== count(array_unique(array_map(static fn (array $row): string => trim((string) ($row['personal_expense_item_id'] ?? '')), $items)))) {
            throw new \InvalidArgumentException('같은 개인경비 항목을 한 번에 중복 정정할 수 없습니다.');
        }
        if (count($items) !== count(array_unique(array_map(static fn (array $row): string => trim((string) ($row['request_key'] ?? '')), $items)))) {
            throw new \InvalidArgumentException('같은 정정 요청키를 한 번에 중복 사용할 수 없습니다.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $companyId = $this->model->resolveCompanyId();
            $document = $this->model->lockDocument($documentId);
            if ($document === null || strtoupper((string) ($document['document_status'] ?? '')) !== 'APPROVED') {
                throw new \RuntimeException('최종승인된 개인경비 문서만 회계분류를 정정할 수 있습니다.');
            }
            if ((string) ($document['current_approval_request_id'] ?? '') !== $approvalRequestId) {
                throw new \RuntimeException('개인경비 문서와 승인요청이 일치하지 않습니다.');
            }
            $request = $this->model->approvalRequest($approvalRequestId);
            if ($request === null || strtolower((string) ($request['status'] ?? '')) !== 'approved') {
                throw new \RuntimeException('완료된 최종승인 요청을 확인할 수 없습니다.');
            }

            $prepared = [];
            foreach ($items as $input) {
                $prepared[] = $this->prepareItem($companyId, $documentId, $approvalRequestId, $batchKey, $reason, $input);
            }

            $actor = ActorHelper::user();
            $processedAt = date('Y-m-d H:i:s');
            $results = [];
            foreach ($prepared as $row) {
                if (!empty($row['_existing'])) {
                    unset($row['_existing']);
                    $results[] = $row;
                    continue;
                }
                $row['id'] = UuidHelper::generate();
                $row['processed_at'] = $processedAt;
                $row['processed_by'] = $actor;
                $this->model->insert($row);
                $results[] = $row;
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            $this->logger->info('개인경비 회계분류 일괄 정정을 완료했습니다.', [
                'event_code' => 'PERSONAL_EXPENSE_CLASSIFICATION_CORRECTION_COMPLETED',
                'result' => 'SUCCESS',
                'document_id' => $documentId,
                'approval_request_id' => $approvalRequestId,
                'corrected_count' => count($results),
            ]);
            return [
                'success' => true,
                'message' => count($results) . '건의 회계분류를 정정했습니다.',
                'data' => ['correction_batch_key' => $batchKey, 'corrections' => $results],
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $level = $exception instanceof \PDOException ? 'error' : 'warning';
            $this->logger->{$level}('개인경비 회계분류 일괄 정정이 완료되지 않았습니다.', [
                'event_code' => 'PERSONAL_EXPENSE_CLASSIFICATION_CORRECTION_FAILED',
                'result' => $level === 'error' ? 'FAILED' : 'BLOCKED',
                'document_id' => $documentId,
                'approval_request_id' => $approvalRequestId,
                'requested_count' => count($items),
                'error_code' => get_class($exception),
                'error' => $exception,
            ]);
            throw $exception;
        }
    }

    private function prepareItem(string $companyId, string $documentId, string $approvalRequestId, string $batchKey, string $reason, array $input): array
    {
        $itemId = trim((string) ($input['personal_expense_item_id'] ?? ''));
        $evidenceId = trim((string) ($input['evidence_id'] ?? ''));
        $correctedCategory = strtoupper(trim((string) ($input['corrected_category'] ?? '')));
        $requestKey = trim((string) ($input['request_key'] ?? ''));
        if ($itemId === '' || $evidenceId === '' || $correctedCategory === '' || $requestKey === '') {
            throw new \InvalidArgumentException('Item, Evidence, 정정 분류와 요청키는 필수입니다.');
        }

        $item = $this->model->lockItem($documentId, $itemId);
        if ($item === null) {
            throw new \RuntimeException('정정할 승인 개인경비 Item을 찾을 수 없습니다.');
        }
        $evidence = $this->model->lockEvidence($evidenceId, $itemId);
        if ($evidence === null) {
            throw new \RuntimeException('승인 Item과 연결된 Evidence가 일치하지 않습니다.');
        }
        if (!$this->model->activeCategoryExists($correctedCategory)) {
            throw new \InvalidArgumentException('사용할 수 없는 개인경비 경비구분입니다.');
        }

        $original = strtoupper(trim((string) ($item['expense_category'] ?? '')));
        $latest = $this->model->latestForItem($itemId, true);
        $previousEffective = $latest !== null ? (string) $latest['corrected_category'] : $original;
        $amount = number_format((float) ($item['item_total_amount'] ?? 0), 2, '.', '');
        if ((float) $amount <= 0) {
            throw new \RuntimeException('정정 Item의 금액 Snapshot이 올바르지 않습니다.');
        }

        $existing = $this->model->findByRequestKey($companyId, $requestKey);
        if ($existing !== null) {
            $expected = [
                'personal_expense_id' => $documentId,
                'personal_expense_item_id' => $itemId,
                'approval_request_id' => $approvalRequestId,
                'evidence_id' => $evidenceId,
                'original_category' => $original,
                'corrected_category' => $correctedCategory,
                'amount_snapshot' => $amount,
                'correction_reason' => $reason,
                'correction_batch_key' => $batchKey,
            ];
            foreach ($expected as $field => $value) {
                if ((string) ($existing[$field] ?? '') !== (string) $value) {
                    throw new \RuntimeException('동일 요청키가 다른 정정 내용으로 재사용되었습니다.');
                }
            }
            $existing['_existing'] = true;
            return $existing;
        }
        if ($original === '' || $correctedCategory === $original || $correctedCategory === $previousEffective) {
            throw new \InvalidArgumentException('현재 유효분류와 다른 분류만 정정할 수 있습니다.');
        }

        $row = [
            'company_id' => $companyId,
            'personal_expense_id' => $documentId,
            'personal_expense_item_id' => $itemId,
            'approval_request_id' => $approvalRequestId,
            'evidence_id' => $evidenceId,
            'revision_no' => (int) ($latest['revision_no'] ?? 0) + 1,
            'previous_correction_id' => $latest['id'] ?? null,
            'original_category' => $original,
            'previous_effective_category' => $previousEffective,
            'corrected_category' => $correctedCategory,
            'amount_snapshot' => $amount,
            'correction_reason' => $reason,
            'correction_batch_key' => $batchKey,
            'request_key' => $requestKey,
        ];

        return $row;
    }
}
