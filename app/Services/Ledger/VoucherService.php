<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\EvidenceReferenceModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\JournalRuleModel;
use App\Models\Ledger\SubAccountPolicyModel;
use App\Models\Ledger\VoucherModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Services\Funds\PaymentObligationService;
use App\Services\Auth\AuthSessionService;
use App\Services\System\NotificationService;
use Core\Helpers\ActorHelper;
use Core\Helpers\RefTypeHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class VoucherService
{
    private const SOURCE_TYPE_VALUES = ['TAX', 'HOMETAX', 'CARD', 'CARD_COMPANY', 'BANK', 'SHOPPING', 'TRADE', 'IMPORT', 'MANUAL', 'SYSTEM'];

    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private VoucherLineRefService $voucherLineRefService;
    private NotificationService $notificationService;
    private EvidenceLinkModel $evidenceLinkModel;
    private EvidenceSourceRepository $evidenceSourceRepository;
    private EvidenceReferenceModel $evidenceReferenceModel;
    private EvidenceSchemaModel $evidenceSchemaModel;
    private ChartAccountModel $chartAccountModel;
    private SubAccountPolicyModel $subAccountPolicyModel;
    private PaymentObligationService $paymentObligationService;
    private JournalRuleModel $journalRuleModel;
    private JournalLearningFeedbackService $learningFeedbackService;
    private VoucherPostingValidationService $postingValidationService;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
        $this->voucherLineRefService = new VoucherLineRefService($pdo);
        $this->notificationService = new NotificationService($pdo);
        $this->evidenceLinkModel = new EvidenceLinkModel($pdo);
        $this->evidenceSourceRepository = new EvidenceSourceRepository($pdo);
        $this->evidenceReferenceModel = new EvidenceReferenceModel($pdo);
        $this->evidenceSchemaModel = new EvidenceSchemaModel($pdo);
        $this->chartAccountModel = new ChartAccountModel($pdo);
        $this->subAccountPolicyModel = new SubAccountPolicyModel($pdo);
        $this->paymentObligationService = new PaymentObligationService($pdo);
        $this->journalRuleModel = new JournalRuleModel($pdo);
        $this->learningFeedbackService = new JournalLearningFeedbackService($pdo);
        $this->postingValidationService = new VoucherPostingValidationService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.VoucherService');
    }

    public function save(array $data): array
    {
        $actor = ActorHelper::user();
        $voucherId = trim((string) ($data['id'] ?? ''));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        $status = VoucherStatus::DRAFT;
        $sourceType = strtoupper(trim((string) ($data['source_type'] ?? 'MANUAL')));
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $linkedEvidences = $this->normalizeLinkedEvidences($data['linked_evidences'] ?? []);
        $existingVoucher = $voucherId !== '' ? $this->voucherModel->getById($voucherId) : null;
        $previousEvidences = $voucherId !== '' ? $this->evidenceLinkModel->getVoucherEvidences($voucherId) : [];
        $this->traceVoucherPayload('save.input', [
            'voucher_id' => $voucherId,
            'voucher_date' => $voucherDate,
            'source_type' => $sourceType,
            'lines' => $lines,
        ]);
        $validation = $this->validateVoucher(
            $voucherId,
            $voucherDate,
            $status,
            $sourceType,
            $lines
        );
        $normalizedLines = $validation['lines'];
        $timestamp = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            if ($voucherId === '') {
                $voucherId = UuidHelper::generate();
                $voucherNo = $this->resolveVoucherNo($data, $voucherDate);

                $headerPayload = [
                    'id' => $voucherId,
                    'sort_no' => SequenceHelper::next('ledger_vouchers', 'sort_no'),
                    'voucher_no' => $voucherNo,
                    'voucher_date' => $voucherDate,
                    'status' => $status,
                    'summary' => null,
                    'reject_reason' => null,
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];
                $saved = $this->voucherModel->insert($headerPayload);
            } else {
                $existing = $existingVoucher;
                if (!$existing) {
                    throw new \RuntimeException("\u{C804}\u{D45C}\u{B97C} \u{CC3E}\u{C744} \u{C218} \u{C5C6}\u{C2B5}\u{B2C8}\u{B2E4}.");
                }

                $this->assertVoucherEditable($existing);

                $payload = [
                    'voucher_date' => $voucherDate,
                    'status' => $status,
                    'reject_reason' => null,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (trim((string) ($existing['voucher_no'] ?? '')) === '') {
                    $payload['voucher_no'] = $this->resolveVoucherNo($data, $voucherDate);
                }
                $saved = $this->voucherModel->update($voucherId, $payload);
            }

            if (!$saved) {
                throw new \RuntimeException("\u{C804}\u{D45C} \u{C800}\u{C7A5}\u{C5D0} \u{C2E4}\u{D328}\u{D588}\u{C2B5}\u{B2C8}\u{B2E4}.");
            }

            $this->deleteVoucherChildren($voucherId);
            $savedLines = [];
            foreach ($normalizedLines as $line) {
                $lineId = UuidHelper::generate();
                $ok = $this->voucherLineModel->insert([
                    'id' => $lineId,
                    'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                    'line_no' => $line['line_no'],
                    'voucher_id' => $voucherId,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'journal_rule_id' => $line['journal_rule_id'] ?? null,
                    'is_user_modified' => !empty($line['is_user_modified']) ? 1 : 0,
                    'recommended_account_id' => $line['recommended_account_id'] ?? null,
                    'recommended_line_type' => $line['recommended_line_type'] ?? null,
                    'recommended_amount' => $line['recommended_amount'] ?? null,
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('전표 헤더를 저장하지 못했습니다.');
                }

                $savedLines[] = [
                    'id' => $lineId,
                    'refs' => $line['refs'] ?? [],
                ];
            }

            $this->voucherLineRefService->replaceForVoucherLines($savedLines, $actor, $timestamp);
            $this->evidenceLinkModel->replaceVoucherEvidences($voucherId, $linkedEvidences);
            $this->recordVoucherEvidenceActions($voucherId, $previousEvidences, $linkedEvidences);
            $this->tracePersistedVoucherLines('save.persisted', $voucherId);
            $this->refreshVoucherHeaderSummary($voucherId, $actor, $timestamp);

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $voucherId,
                'voucher_id' => $voucherId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function recordVoucherEvidenceActions(string $voucherId, array $previous, array $current): void
    {
        $previousMap = [];
        foreach ($previous as $evidence) {
            $previousMap[strtoupper((string) ($evidence['import_type'] ?? '')) . ':' . (string) ($evidence['evidence_id'] ?? '')] = $evidence;
        }
        $currentMap = [];
        foreach ($current as $evidence) {
            $key = strtoupper((string) ($evidence['import_type'] ?? '')) . ':' . (string) ($evidence['evidence_id'] ?? '');
            $currentMap[$key] = $evidence;
            if (isset($previousMap[$key])) continue;
        }
        foreach ($previousMap as $key => $evidence) {
            if (isset($currentMap[$key])) continue;
        }
    }

    private function normalizeLinkedEvidences(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            throw new VoucherValidationException('연결 증빙 형식이 올바르지 않습니다.', 'evidence');
        }

        $normalized = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new VoucherValidationException('연결 증빙 형식이 올바르지 않습니다.', 'evidence');
            }
            $importType = strtoupper(trim((string) ($row['import_type'] ?? '')));
            $evidenceId = trim((string) ($row['evidence_id'] ?? ''));
            if ($importType === '' || $evidenceId === '') {
                throw new VoucherValidationException('연결 증빙의 자료유형과 증빙 ID가 필요합니다.', 'evidence');
            }
            $key = $importType . "\0" . $evidenceId;
            if (isset($normalized[$key])) {
                continue;
            }
            $source = $this->evidenceSourceRepository->find($importType, $evidenceId);
            if (!$source) {
                throw new VoucherValidationException('존재하지 않거나 삭제된 증빙은 연결할 수 없습니다.', 'evidence');
            }
            $policyType = strtoupper(trim((string) ($source['evidence_type'] ?? '')));
            if (!in_array($policyType, ['DATA', 'FUND', 'BOTH'], true)) {
                throw new VoucherValidationException('증빙정책에서 전표 연결이 허용되지 않은 증빙입니다.', 'evidence');
            }
            $normalized[$key] = [
                'import_type' => $importType,
                'evidence_id' => $evidenceId,
            ];
        }

        return array_values($normalized);
    }

    public function reorder(array $changes): bool
    {
        if ($changes === []) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \RuntimeException('정렬 변경 데이터가 올바르지 않습니다.');
                }
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function deleteVoucher(string $voucherId): void
    {
        $voucherId = trim($voucherId);
        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher) {
            throw new \RuntimeException('삭제할 전표를 찾을 수 없습니다.');
        }

        if (!VoucherStatus::isDraft($voucher['status'] ?? null)) {
            throw new \RuntimeException('임시저장(draft) 상태의 전표만 삭제할 수 있습니다.');
        }

        $actor = ActorHelper::user();

        try {
            $this->pdo->beginTransaction();

            if (!$this->voucherModel->softDelete($voucherId, $actor)) {
                throw new \RuntimeException('전표를 삭제하지 못했습니다.');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function restoreVoucher(string $voucherId): void
    {
        $voucherId = trim($voucherId);
        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        $actor = ActorHelper::user();

        try {
            $this->pdo->beginTransaction();

            if (!$this->voucherModel->restore($voucherId, $actor)) {
                throw new \RuntimeException('전표를 복원하지 못했습니다.');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function searchSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = $this->normalizeSummaryText($keyword) ?? '';
        if (mb_strlen($keyword, 'UTF-8') < 2) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'summary' => (string) ($row['summary'] ?? ''),
                'summary_text' => (string) ($row['summary'] ?? ''),
                'used_count' => (int) ($row['used_count'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
            ];
        }, $this->voucherModel->searchSummaryTexts($keyword, $limit));
    }

    public function searchLineSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = $this->normalizeSummaryText($keyword) ?? '';
        if (mb_strlen($keyword, 'UTF-8') < 2) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'summary' => (string) ($row['summary'] ?? ''),
                'used_count' => (int) ($row['used_count'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
            ];
        }, $this->voucherLineModel->searchLineSummaryTexts($keyword, $limit));
    }
    public function updateStatus(string $voucherId, string $nextStatus): array
    {
        $voucherId = trim($voucherId);
        $nextStatus = VoucherStatus::normalize($nextStatus, '');
        $this->traceVoucherPayload('status.request', [
            'voucher_id' => $voucherId,
            'next_status' => $nextStatus,
        ]);

        if ($voucherId === '' || $nextStatus === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
        $voucher = $this->voucherModel->getByIdForUpdate($voucherId);
        if (!$voucher || !empty($voucher['deleted_at'])) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        $currentStatus = VoucherStatus::normalize($voucher['status'] ?? null, '');

        $allowedNext = [
            VoucherStatus::DRAFT => [VoucherStatus::REVIEW_REQUESTED],
            VoucherStatus::REVIEW_REQUESTED => [VoucherStatus::DRAFT, VoucherStatus::REVIEWED],
            VoucherStatus::REVIEWED => [VoucherStatus::REVIEW_REQUESTED, VoucherStatus::POSTED],
        ];

        if (!in_array($nextStatus, $allowedNext[$currentStatus] ?? [], true)) {
            throw new \RuntimeException('현재 상태에서는 요청한 상태로 변경할 수 없습니다.');
        }

        $lines = $this->getPersistedVoucherLinesForValidation($voucherId);
        $journalStatus = $this->calculateJournalStatus($lines, $nextStatus);

        if (
            in_array($nextStatus, [VoucherStatus::REVIEW_REQUESTED, VoucherStatus::REVIEWED], true)
            && $journalStatus !== 'READY'
        ) {
            $this->validationError(
                '분개 상태가 READY가 아니므로 전표 상태를 변경할 수 없습니다.',
                'journal_status'
            );
        }

        if (
            $nextStatus === VoucherStatus::POSTED
            && !in_array($journalStatus, ['READY', 'POSTED'], true)
        ) {
            $this->validationError(
                '분개 상태가 READY 또는 POSTED가 아니므로 전표를 승인할 수 없습니다.',
                'journal_status'
            );
        }

        $this->validateVoucherBalance($lines);
        $this->validateVoucherSubAccountPolicies($lines);
        if ($nextStatus === VoucherStatus::POSTED) {
            $this->postingValidationService->validate($voucherId, $lines);
        }

        $payload = [
            'status'     => $nextStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => ActorHelper::user(),
        ];

            $updated = $this->voucherModel->update($voucherId, $payload);
            if (!$updated) {
                throw new \RuntimeException('전표 상태 변경에 실패했습니다.');
            }

            $paymentObligations = null;
            if ($currentStatus === VoucherStatus::REVIEWED && $nextStatus === VoucherStatus::POSTED) {
                $postedVoucher = $this->voucherModel->getById($voucherId) ?: $voucher;
                $paymentObligations = $this->paymentObligationService->synchronizeOnFirstPosting(
                    $postedVoucher,
                    (string) $payload['updated_by']
                );
                $feedbackEvents = $this->learningFeedbackService->recordPostedEvents(
                    $voucherId,
                    (string) $payload['updated_by']
                );
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            $ruleUsageUpdated = 0;
            $ruleUsageUpdateFailed = false;
            $feedbackProjection = null;
            $feedbackProjectionFailed = false;
            if ($currentStatus === VoucherStatus::REVIEWED && $nextStatus === VoucherStatus::POSTED) {
                try {
                    $feedbackProjection = $this->learningFeedbackService->synchronizeProjections();
                } catch (\Throwable $e) {
                    $feedbackProjectionFailed = true;
                    $this->logger->error('전표 승인 후 학습 Projection 반영에 실패했습니다.', [
                        'voucher_id' => $voucherId,
                        'event_ids' => $feedbackEvents['event_ids'] ?? [],
                        'projection_type' => 'RECENT_AND_CLIENT',
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }
                $ruleIds = [];
                try {
                    $ruleIds = $this->journalRuleModel->confirmedUsageRuleIds($voucherId);
                    $ruleUsageUpdated = $this->journalRuleModel->recordConfirmedUsage(
                        $voucherId,
                        (string) $payload['updated_by']
                    );
                    if ($ruleUsageUpdated !== count($ruleIds)) {
                        $ruleUsageUpdateFailed = true;
                        $this->logger->warning('전표 승인 후 분개규칙 사용량 반영 건수가 일치하지 않습니다.', [
                            'voucher_id' => $voucherId,
                            'rule_ids' => $ruleIds,
                            'updated_count' => $ruleUsageUpdated,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $ruleUsageUpdateFailed = true;
                    $this->logger->error('전표 승인 후 분개규칙 사용량 반영에 실패했습니다.', [
                        'voucher_id' => $voucherId,
                        'rule_ids' => $ruleIds,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'id' => $voucherId,
                'status' => $nextStatus,
                'payment_obligations' => $paymentObligations,
                'rule_usage_updated' => $ruleUsageUpdated,
                'rule_usage_update_failed' => $ruleUsageUpdateFailed,
                'feedback_event_count' => count($feedbackEvents['created_ids'] ?? []),
                'feedback_projection' => $feedbackProjection,
                'feedback_projection_failed' => $feedbackProjectionFailed,
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    public function confirm(string $voucherId): array
    {
        $voucherId = trim($voucherId);

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        return $this->updateStatus($voucherId, VoucherStatus::REVIEW_REQUESTED);
    }

    public function requestReview(string $voucherId): array
    {
        return $this->confirm($voucherId);
    }

    public function cancelReview(string $voucherId): array
    {
        $voucherId = trim($voucherId);

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        return $this->updateStatus($voucherId, VoucherStatus::DRAFT);
    }

    public function cancelReviewRequest(string $voucherId): array
    {
        return $this->cancelReview($voucherId);
    }
    public function completeReview(string $voucherId): array
    {
        $voucherId = trim($voucherId);

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        return $this->updateStatus($voucherId, VoucherStatus::REVIEWED);
    }

    public function cancelCompleteReview(string $voucherId): array
    {
        $voucherId = trim($voucherId);

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        return $this->updateStatus($voucherId, VoucherStatus::REVIEW_REQUESTED);
    }

    public function post(string $voucherId): array
    {
        $result = $this->updateStatus($voucherId, VoucherStatus::POSTED);
        $this->createVoucherNotification($voucherId, 'post', '전표 전기완료', '전표가 전기완료되었습니다.');

        return $result;
    }

    public function createReversalVoucher(string $voucherId, string $actorId): array
    {
        $voucherId = trim($voucherId);
        $actor = trim($actorId) !== '' ? trim($actorId) : ActorHelper::user();

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        $original = $this->voucherModel->getById($voucherId);
        if (!$original || !empty($original['deleted_at'])) {
            throw new \RuntimeException('원본 전표를 찾을 수 없습니다.');
        }

        if (!VoucherStatus::isPosted($original['status'] ?? null)) {
            throw new \RuntimeException('전표승인 상태의 전표만 취소전표를 생성할 수 있습니다.');
        }

        if ((int) ($original['is_reversal'] ?? 0) === 1) {
            throw new \RuntimeException('역분개 전표에서는 다시 역분개 전표를 생성할 수 없습니다.');
        }

        if ($this->voucherModel->findActiveReversalOf($voucherId)) {
            throw new \RuntimeException('이미 취소전표가 생성되어 있습니다.');
        }

        $lines = $this->voucherLineModel->getByVoucherId($voucherId);
        if ($lines === []) {
            throw new \RuntimeException('원본 전표의 분개라인을 찾을 수 없습니다.');
        }

        $timestamp = date('Y-m-d H:i:s');
        $voucherDate = date('Y-m-d');
        $newVoucherId = UuidHelper::generate();
        $newVoucherNo = $this->nextVoucherNo($voucherDate);
        $originalNo = trim((string) ($original['voucher_no'] ?? ''));

        try {
            $this->pdo->beginTransaction();

            $original = $this->voucherModel->getByIdForUpdate($voucherId);
            if (!$original || !empty($original['deleted_at'])) {
                throw new \RuntimeException('원본 전표를 찾을 수 없습니다.');
            }
            if (!VoucherStatus::isPosted($original['status'] ?? null)) {
                throw new \RuntimeException('승인된 전표만 취소전표를 생성할 수 있습니다.');
            }
            if ((int) ($original['is_reversal'] ?? 0) === 1) {
                throw new \RuntimeException('취소전표에서는 다시 취소전표를 생성할 수 없습니다.');
            }
            if ($this->voucherModel->findActiveReversalOf($voucherId)) {
                throw new \RuntimeException('이미 취소전표가 생성되어 있습니다.');
            }

            $lines = $this->voucherLineRefService->hydrateVoucherLines(
                $this->voucherLineModel->getByVoucherId($voucherId)
            );
            if ($lines === []) {
                throw new \RuntimeException('원본 전표의 분개라인을 찾을 수 없습니다.');
            }

            $saved = $this->voucherModel->insert([
                'id' => $newVoucherId,
                'sort_no' => SequenceHelper::next('ledger_vouchers', 'sort_no'),
                'voucher_no' => $newVoucherNo,
                'voucher_date' => $voucherDate,
                'status' => VoucherStatus::DRAFT,
                'summary' => '역분개 전표' . ($originalNo !== '' ? " ({$originalNo})" : ''),
                'reject_reason' => null,
                'is_reversal' => 1,
                'reversal_of' => $voucherId,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ]);

            if (!$saved) {
                throw new \RuntimeException('역분개 전표를 저장하지 못했습니다.');
            }

            $reversalLines = [];
            foreach ($lines as $index => $line) {
                $newLineId = UuidHelper::generate();
                $refs = is_array($line['refs'] ?? null) ? array_values($line['refs']) : [];
                if ($refs === []) {
                    $legacyRefTarget = trim((string) ($line['ref_target'] ?? ''));
                    $legacyRefId = trim((string) ($line['ref_id'] ?? ''));
                    if ($legacyRefTarget !== '' && $legacyRefId !== '') {
                        $refs[] = ['ref_target' => $legacyRefTarget, 'ref_id' => $legacyRefId];
                    }
                }

                $ok = $this->voucherLineModel->insert([
                    'id' => $newLineId,
                    'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                    'line_no' => $index + 1,
                    'voucher_id' => $newVoucherId,
                    'account_id' => (string) ($line['account_id'] ?? ''),
                    'debit' => number_format((float) ($line['credit'] ?? 0), 2, '.', ''),
                    'credit' => number_format((float) ($line['debit'] ?? 0), 2, '.', ''),
                    'line_summary' => (string) ($line['line_summary'] ?? ''),
                    'journal_rule_id' => $this->nullableString($line['journal_rule_id'] ?? null),
                    'is_user_modified' => (int) ($line['is_user_modified'] ?? 0),
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('역분개 전표의 분개라인을 저장하지 못했습니다.');
                }

                $reversalLines[] = ['id' => $newLineId, 'refs' => $refs];
            }

            $this->voucherLineRefService->replaceForVoucherLines($reversalLines, $actor, $timestamp);
            $this->refreshVoucherHeaderSummary($newVoucherId, $actor, $timestamp);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        $this->createVoucherNotification(
            $newVoucherId,
            'reverse',
            '역분개 전표 생성',
            '역분개 전표가 생성되었습니다.'
        );

        return [
            'success' => true,
            'id' => $newVoucherId,
            'voucher_id' => $newVoucherId,
            'voucher_no' => $newVoucherNo,
            'status' => VoucherStatus::DRAFT,
            'is_reversal' => 1,
            'reversal_of' => $voucherId,
        ];
    }

    public function reject(string $voucherId, string $reason): array
    {
        $voucherId = trim($voucherId);
        $reason = trim($reason);

        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 입력되지 않았습니다.');
        }

        if ($reason === '') {
            throw new \RuntimeException('반려 사유를 입력해 주세요.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
        $voucher = $this->voucherModel->getByIdForUpdate($voucherId);
        if (!$voucher || !empty($voucher['deleted_at'])) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        $currentStatus = VoucherStatus::normalize($voucher['status'] ?? null, '');

        if ($currentStatus !== VoucherStatus::REVIEW_REQUESTED) {
            throw new \RuntimeException('검토요청 상태의 전표만 반려할 수 있습니다.');
        }

        $updated = $this->voucherModel->update($voucherId, [
            'status' => VoucherStatus::DRAFT,
            'reject_reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => ActorHelper::user(),
        ]);

        if (!$updated) {
            throw new \RuntimeException('전표 반려 처리에 실패했습니다.');
        }

        if ($ownsTransaction) {
            $this->pdo->commit();
        }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->createVoucherNotification(
            $voucherId,
            'reject',
            '전표 반려',
            '전표가 반려되었습니다.'
        );

        return [
            'success' => true,
            'id' => $voucherId,
            'status' => VoucherStatus::DRAFT,
        ];
    }
    private function validateVoucher(
        string $voucherId,
        string $voucherDate,
        string $status,
        string $sourceType,
        array $lines
    ): array {
        if ($voucherDate === '') {
            $this->validationError('전표일자를 입력해 주세요.', 'voucher_date');
        }

        if (!in_array($status, VoucherStatus::values(), true)) {
            $this->validationError('올바른 전표 상태를 선택해 주세요.', 'status');
        }

        if (VoucherStatus::isDeleted($status)) {
            $this->validationError('삭제된 상태의 전표는 저장할 수 없습니다.', 'voucher_status');
        }

        if (!in_array($sourceType, self::SOURCE_TYPE_VALUES, true)) {
            $this->validationError('잘못된 자료출처입니다.', 'source_type');
        }

        if ($voucherId !== '') {
            $existing = $this->voucherModel->getById($voucherId);

            if (!$existing) {
                $this->validationError('전표를 찾을 수 없습니다.', 'voucher_status');
            }

            if (!VoucherStatus::isDraft($existing['status'] ?? null) || !empty($existing['deleted_at'])) {
                $this->validationError('임시저장(draft) 상태의 전표만 수정할 수 있습니다.', 'voucher_status');
            }
        }

        $normalizedLines = $this->normalizeVoucherLines($lines, false);
        $totals = $this->voucherLineTotals($normalizedLines);
        $journalStatus = $this->calculateJournalStatus($normalizedLines, $status);

        if ($normalizedLines !== []) {
            $this->validateVoucherSubAccountPolicies($normalizedLines);
        }

        return [
            'lines' => $normalizedLines,
            'debit_total' => number_format($totals['debit_sum'], 2, '.', ''),
            'credit_total' => number_format($totals['credit_sum'], 2, '.', ''),
            'journal_status' => $journalStatus,
        ];
    }

    private function validateVoucherBalance(array $lines): array
    {
        $totals = $this->voucherLineTotals($lines);

        if (
            $totals['debit_sum'] <= 0 ||
            $totals['credit_sum'] <= 0 ||
            round($totals['debit_sum'], 2) !== round($totals['credit_sum'], 2)
        ) {
            $this->validationError(
                '차변과 대변의 합계금액이 일치해야 합니다.',
                'balance'
            );
        }

        return $totals;
    }

    private function voucherLineTotals(array $lines): array
    {
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $line) {
            $debitSum += (float) $line['debit'];
            $creditSum += (float) $line['credit'];
        }

        return [
            'debit_sum' => $debitSum,
            'credit_sum' => $creditSum,
        ];
    }

    private function calculateJournalStatus(array $lines, string $voucherStatus = VoucherStatus::DRAFT): string
    {
        if (VoucherStatus::isPostedOrClosed($voucherStatus)) {
            return 'POSTED';
        }
        if ($lines === []) {
            return 'EMPTY';
        }

        $totals = $this->voucherLineTotals($lines);
        if ($totals['debit_sum'] <= 0 || $totals['credit_sum'] <= 0 || round($totals['debit_sum'], 2) !== round($totals['credit_sum'], 2)) {
            return 'UNBALANCED';
        }

        return 'READY';
    }
    private function normalizeVoucherLines(array $lines, bool $requireLine = true): array
    {
        $normalized = [];
        $lineNo = 1;

        foreach ($lines as $line) {
            $accountValue = trim((string) ($line['account_id'] ?? $line['account_code'] ?? ''));
            $refs = $this->normalizeLineRefs($line);
            $debit = round($this->parseAmount($line['debit'] ?? 0), 2);
            $credit = round($this->parseAmount($line['credit'] ?? 0), 2);
            $lineSummary = $this->nullableString($line['line_summary'] ?? null);

            if ($accountValue === '' && $refs === [] && $debit === 0.0 && $credit === 0.0 && $lineSummary === null) {
                continue;
            }

            if ($accountValue === '') {
                $this->validationError('계정과목을 선택해 주세요.', 'line_account');
            }

            $account = $this->resolveAccount($accountValue);
            if ($account === null) {
                $this->validationError('선택한 계정과목을 찾을 수 없습니다.', 'line_account');
            }

            foreach ($refs as $ref) {
                $this->validateRefTarget($ref['ref_target'], $ref['ref_id']);
            }

            if (!(($debit > 0 && $credit == 0.0) || ($debit == 0.0 && $credit > 0))) {
                $this->validationError(
                    '차변 또는 대변 중 하나에만 금액을 입력해야 합니다.',
                    'line_amount'
                );
            }

            if (false && count($refs) > 1) {
                $this->validationError(
                    '하나의 분개라인에는 하나의 보조계정만 연결할 수 있습니다.',
                    'line_ref'
                );
            }

            $primaryRef = $refs[0] ?? ['ref_target' => null, 'ref_id' => null];

            $normalized[] = [
                'line_no' => $lineNo,
                'account_id' => (string) $account['id'],
                'account_name' => (string) $account['account_name'],
                'refs' => $refs,
                'ref_target' => $primaryRef['ref_target'] ?? null,
                'ref_id' => $primaryRef['ref_id'] ?? null,
                'debit' => number_format(max($debit, 0), 2, '.', ''),
                'credit' => number_format(max($credit, 0), 2, '.', ''),
                'line_summary' => $lineSummary,
                'journal_rule_id' => trim((string) ($line['journal_rule_id'] ?? '')) ?: null,
                'is_user_modified' => !empty($line['is_user_modified']) ? 1 : 0,
                'recommended_account_id' => trim((string) ($line['recommended_account_id'] ?? '')) ?: null,
                'recommended_line_type' => in_array(strtoupper(trim((string) ($line['recommended_line_type'] ?? ''))), ['DEBIT', 'CREDIT'], true)
                    ? strtoupper(trim((string) $line['recommended_line_type'])) : null,
                'recommended_amount' => ($line['recommended_amount'] ?? null) !== null
                    ? number_format($this->parseAmount($line['recommended_amount']), 2, '.', '') : null,
            ];
            $lineNo++;
        }

        if ($requireLine && $normalized === []) {
            $this->validationError('분개라인을 1건 이상 입력해 주세요.', 'line');
        }

        return $normalized;
    }
    private function normalizeLineRefs(array $line): array
    {
        $rawRefs = is_array($line['refs'] ?? null) ? $line['refs'] : [];

        if ($rawRefs === [] && (trim((string) ($line['ref_target'] ?? '')) !== '' || trim((string) ($line['ref_id'] ?? '')) !== '')) {
            $rawRefs[] = [
                'ref_target' => $line['ref_target'] ?? '',
                'ref_id' => $line['ref_id'] ?? '',
            ];
        }

        $refs = [];
        $seenRefs = [];

        foreach ($rawRefs as $ref) {
            $refType = $this->normalizeRefTypeAlias((string) ($ref['ref_target'] ?? ''));
            $refId = trim((string) ($ref['ref_id'] ?? ''));

            if ($refType === '' && $refId === '') {
                continue;
            }

            if ($refType === '') {
                $this->validationError('보조계정 대상(ref_target)을 입력해 주세요.', 'line_ref');
            }

            if ($refId === '') {
                $this->validationError('보조계정 ID(ref_id)를 입력해 주세요.', 'line_ref');
            }

            $refKey = $refType . ':' . $refId;
            if (isset($seenRefs[$refKey])) {
                $this->validationError('동일한 보조계정을 중복하여 입력할 수 없습니다.', 'line_ref_duplicate');
            }

            $seenRefs[$refKey] = true;
            $refs[] = [
                'ref_target' => $refType,
                'ref_id' => $refId,
                'is_primary' => (int) ($ref['is_primary'] ?? ($refs === [] ? 1 : 0)),
            ];
        }

        return $refs;
    }

    private function validateVoucherSubAccountPolicies(array $lines): void
    {
        $policiesByAccount = $this->subAccountPolicyModel->getGroupedByAccountIds(array_column($lines, 'account_id'));
        foreach ($lines as $line) {
            $accountId = (string) $line['account_id'];
            $accountName = trim((string) ($line['account_name'] ?? $accountId));
            $refs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $policies = $policiesByAccount[$accountId] ?? [];

            $requiredTypes = [];
            foreach ($policies as $policy) {
                $policyRefType = $this->normalizeRefTypeAlias($this->resolveSubAccountPolicyRefType($policy));
                if ($policyRefType === '') {
                    continue;
                }

                if ((int) ($policy['is_required'] ?? 0) === 1) {
                    $requiredTypes[] = $policyRefType;
                }
            }

            if ($requiredTypes === []) {
                continue;
            }

            $selectedMap = [];
            foreach ($refs as $ref) {
                $refType = $this->normalizeRefTypeAlias((string) ($ref['ref_target'] ?? ''));
                $refId = trim((string) ($ref['ref_id'] ?? ''));
                if ($refType !== '' && $refId !== '') {
                    $selectedMap[$refType] = true;
                }
            }

            $requiredTypes = array_values(array_unique(array_filter($requiredTypes)));
            $missingTypes = [];
            foreach ($requiredTypes as $requiredType) {
                if (empty($selectedMap[$requiredType])) {
                    $missingTypes[] = $requiredType;
                }
            }

            $this->traceVoucherPayload('status.required_refs', [
                'account_id' => $accountId,
                'account_name' => $accountName,
                'required_ref_targets' => $requiredTypes,
                'selected_ref_targets' => array_keys($selectedMap),
                'missing_ref_targets' => $missingTypes,
                'refs' => $refs,
            ]);

            if ($missingTypes === []) {
                continue;
            }

            if (count($requiredTypes) > 1) {
                $this->validationError(
                    $accountName . ' 계정은 ' . $this->joinRefTypeLabels($requiredTypes) . ' 보조계정을 모두 선택해야 합니다.',
                    'required_ref'
                );
            }

            $this->validationError(
                '필수 보조계정이 선택되지 않았습니다. (계정: ' . $accountName . ', 대상: ' . $this->joinRefTypeLabels($missingTypes) . ')',
                'required_ref'
            );
        }
    }

    private function resolveSubAccountPolicyRefType(array $policy): string
    {
        $refType = $this->normalizeRefTypeAlias((string) ($policy['ref_target'] ?? ''));
        $subCode = $this->normalizeRefTypeAlias((string) ($policy['sub_code'] ?? ''));

        if ($refType === 'REF_TARGET') {
            return $subCode;
        }

        return $refType !== '' ? $refType : $subCode;
    }

    private function normalizeRefTypeAlias(string $refType): string
    {
        return match (strtoupper(trim($refType))) {
            'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY', 'PARTNER' => 'CLIENT',
            'PROJECT' => 'PROJECT',
            'BANK', 'BANK_ACCOUNT', 'ACCOUNT' => 'ACCOUNT',
            'EMPLOYEE', 'USER' => 'EMPLOYEE',
            'CARD' => 'CARD',
            default => strtoupper(trim($refType)),
        };
    }

    private function joinRefTypeLabels(array $refTypes): string
    {
        $labels = array_values(array_unique(array_map(
            static fn(string $refType): string => RefTypeHelper::label($refType),
            $refTypes
        )));

        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }

        if (count($labels) === 2) {
            return $labels[0] . ' 및 ' . $labels[1];
        }

        $last = array_pop($labels);
        return implode(', ', $labels) . ' 및 ' . $last;
    }

    private function validateRefTarget(string $refType, string $refId): void
    {
        $table = match ($refType) {
            'ACCOUNT' => 'system_bank_accounts',
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'EMPLOYEE' => 'user_employees',
            'CARD' => 'system_cards',
            'BANK', 'BANK_ACCOUNT' => 'system_bank_accounts',
            'VOUCHER' => 'ledger_vouchers',
            'CONTRACT' => null,
            'ORDER' => null,
            default => false,
        };

        if ($table === false) {
            $this->validationError(
                '지원하지 않는 보조계정 대상입니다.',
                'ref_target'
            );
        }

        if ($table === null) {
            if ($refId === '') {
                $this->validationError(
                    '보조계정 ID를 입력해 주세요.',
                    'ref_target'
                );
            }
            return;
        }

        if (!$this->existsById($table, $refId)) {
            $this->validationError(
                '선택한 보조계정을 찾을 수 없습니다.',
                'ref_target'
            );
        }
    }
    private function assertExists(string $table, string $id, string $message): void
    {
        if (!$this->existsById($table, $id)) {
            throw new \RuntimeException($message);
        }
    }

    private function existsById(string $table, string $id): bool
    {
        return match ($table) {
            'ledger_accounts' => $this->chartAccountModel->getById($id) !== null,
            'ledger_vouchers' => $this->voucherModel->getById($id) !== null,
            'system_clients' => $this->evidenceReferenceModel->resolveId('CLIENT', $id) === $id,
            'system_projects' => $this->evidenceReferenceModel->resolveId('PROJECT', $id) === $id,
            'user_employees' => $this->evidenceReferenceModel->resolveId('EMPLOYEE', $id) === $id,
            'system_bank_accounts' => $this->evidenceReferenceModel->resolveId('ACCOUNT', $id) === $id,
            'system_cards' => $this->evidenceReferenceModel->resolveId('CARD', $id) === $id,
            default => throw new \InvalidArgumentException('Invalid table name.'),
        };
    }

    private function validationError(string $message, string $validationType): never
    {
        throw new VoucherValidationException($message, $validationType);
    }
    private function assertVoucherEditable(array $voucher): void
    {
        $status = (string) ($voucher['status'] ?? '');

        if (!VoucherStatus::isEditable($status)) {
            $messages = [
                VoucherStatus::REVIEW_REQUESTED => '검토요청 상태의 전표는 수정할 수 없습니다. 검토요청을 취소한 후 수정해 주세요.',
                VoucherStatus::REVIEWED => '검토완료(reviewed) 상태의 전표는 수정할 수 없습니다.',
                VoucherStatus::POSTED => '전표승인 상태의 전표는 수정할 수 없습니다.',
                VoucherStatus::CLOSED => '마감(closed) 상태의 전표는 수정할 수 없습니다.',
                VoucherStatus::DELETED => '삭제된 전표는 수정할 수 없습니다.',
            ];

            $this->validationError(
                $messages[$status] ?? '현재 상태의 전표는 수정할 수 없습니다.',
                'voucher_status'
            );
        }

        if (!empty($voucher['deleted_at'])) {
            $this->validationError(
                '삭제된 전표는 수정할 수 없습니다.',
                'voucher_status'
            );
        }
    }

    private function deleteVoucherChildren(string $voucherId): void
    {
        $this->voucherLineModel->purgeByVoucherId($voucherId);
    }

    private function refreshVoucherHeaderSummary(string $voucherId, string $actor, string $timestamp): void
    {
        $lines = $this->voucherLineRefService->hydrateVoucherLines(
            $this->voucherLineModel->getByVoucherId($voucherId)
        );

        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $summaryAccountId = null;
        $summaryLineSummary = null;
        $summaryRefIds = [
            'summary_client_id' => null,
            'summary_project_id' => null,
            'summary_bank_account_id' => null,
            'summary_card_id' => null,
            'summary_employee_id' => null,
        ];

        foreach ($lines as $line) {
            $debitTotal += (float) ($line['debit'] ?? 0);
            $creditTotal += (float) ($line['credit'] ?? 0);

            if ($summaryAccountId === null) {
                $accountId = trim((string) ($line['account_id'] ?? ''));
                $summaryAccountId = $accountId !== '' ? $accountId : null;
            }

            if ($summaryLineSummary === null) {
                $lineSummary = trim((string) ($line['line_summary'] ?? ''));
                $summaryLineSummary = $lineSummary !== '' ? $lineSummary : null;
            }

            foreach (is_array($line['refs'] ?? null) ? $line['refs'] : [] as $ref) {
                $refTarget = strtoupper(trim((string) ($ref['ref_target'] ?? '')));
                $refId = trim((string) ($ref['ref_id'] ?? ''));
                if ($refId === '') {
                    continue;
                }

                $summaryField = match ($refTarget) {
                    'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY', 'PARTNER' => 'summary_client_id',
                    'PROJECT' => 'summary_project_id',
                    'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => 'summary_bank_account_id',
                    'CARD' => 'summary_card_id',
                    'EMPLOYEE', 'USER' => 'summary_employee_id',
                    default => null,
                };

                if ($summaryField !== null && $summaryRefIds[$summaryField] === null) {
                    $summaryRefIds[$summaryField] = $refId;
                }
            }
        }

        if (round($debitTotal, 2) !== round($creditTotal, 2)) {
            throw new \RuntimeException('차변 합계와 대변 합계가 일치하지 않습니다.');
        }

        $summary = $this->buildPersistedVoucherSummary($lines);
        $updated = $this->voucherModel->update($voucherId, [
            'summary' => $summary,
            'debit_total' => number_format($debitTotal, 2, '.', ''),
            'credit_total' => number_format($creditTotal, 2, '.', ''),
            'line_count' => count($lines),
            'summary_account_id' => $summaryAccountId,
            'summary_client_id' => $summaryRefIds['summary_client_id'],
            'summary_project_id' => $summaryRefIds['summary_project_id'],
            'summary_bank_account_id' => $summaryRefIds['summary_bank_account_id'],
            'summary_card_id' => $summaryRefIds['summary_card_id'],
            'summary_employee_id' => $summaryRefIds['summary_employee_id'],
            'summary_line_summary' => $summaryLineSummary,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);

        if (!$updated) {
            throw new \RuntimeException('전표 대표정보 저장 중 오류가 발생했습니다.');
        }
    }

    private function buildPersistedVoucherSummary(array $lines): ?string
    {
        $firstLine = $lines[0] ?? null;
        if (!is_array($firstLine)) {
            return null;
        }

        $accountName = trim((string) ($firstLine['account_name'] ?? $firstLine['account_id'] ?? ''));
        if ($accountName === '') {
            return null;
        }

        $extraCount = max(count($lines) - 1, 0);

        return $extraCount > 0
            ? $accountName . ' 외 ' . $extraCount . '건'
            : $accountName;
    }

    private function getPersistedVoucherLinesForValidation(string $voucherId): array
    {
        $lines = $this->voucherLineModel->getByVoucherId($voucherId);
        $hydratedLines = $this->voucherLineRefService->hydrateVoucherLines($lines);
        $validationLines = $this->voucherLineRefService->buildValidationLines($hydratedLines);
        $this->traceVoucherPayload('status.validation_lines', [
            'voucher_id' => $voucherId,
            'db_lines' => $this->compactVoucherLines($lines),
            'hydrated_lines' => $this->compactVoucherLines($hydratedLines),
            'validation_lines' => $this->compactVoucherLines($validationLines),
        ]);

        return $validationLines;
    }

    private function tracePersistedVoucherLines(string $stage, string $voucherId): void
    {
        if ($voucherId === '') {
            return;
        }

        $lines = $this->voucherLineModel->getByVoucherId($voucherId);
        $hydratedLines = $this->voucherLineRefService->hydrateVoucherLines($lines);
        $validationLines = $this->voucherLineRefService->buildValidationLines($hydratedLines);

        $this->traceVoucherPayload($stage, [
            'voucher_id' => $voucherId,
            'db_lines' => $this->compactVoucherLines($lines),
            'hydrated_lines' => $this->compactVoucherLines($hydratedLines),
            'validation_lines' => $this->compactVoucherLines($validationLines),
        ]);
    }

    private function compactVoucherLines(array $lines): array
    {
        return array_map(static function (array $line): array {
            $refs = [];
            foreach (is_array($line['refs'] ?? null) ? $line['refs'] : [] as $ref) {
                $refs[] = [
                    'ref_target' => (string) ($ref['ref_target'] ?? ''),
                    'ref_id' => (string) ($ref['ref_id'] ?? ''),
                ];
            }

            return [
                'id' => (string) ($line['id'] ?? ''),
                'account_id' => (string) ($line['account_id'] ?? ''),
                'debit' => (string) ($line['debit'] ?? '0'),
                'credit' => (string) ($line['credit'] ?? '0'),
                'line_summary' => (string) ($line['line_summary'] ?? ''),
                'refs' => $refs,
            ];
        }, $lines);
    }

    private function traceVoucherPayload(string $stage, array $payload): void
    {
        error_log('[VoucherService] ' . $stage . '=' . json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function resolveVoucherNo(array $data, string $voucherDate): string
    {
        $voucherNo = trim((string) ($data['voucher_no'] ?? ''));
        if ($voucherNo !== '') {
            return $voucherNo;
        }

        return $this->nextVoucherNo($voucherDate);
    }

    private function nextVoucherNo(string $voucherDate): string
    {
        return $this->voucherModel->nextVoucherNo($voucherDate);
    }

    private function createVoucherNotification(
        string $voucherId,
        string $actionType,
        string $title,
        string $actionLabel
    ): void {
        try {
            $authSession = new AuthSessionService();
            $currentUser = $authSession->getCurrentUser() ?? [];
            $actorUserId = (string) ($currentUser['id'] ?? '');
            $actorName = trim((string) ($currentUser['employee_name'] ?? ''))
                ?: trim((string) ($currentUser['username'] ?? ''))
                ?: '시스템';

            $recipientIds = $this->notificationService->getAdminUserIds();
            if ($recipientIds === [] && $actorUserId !== '') {
                $recipientIds = [$actorUserId];
            }

            $voucher = $this->voucherModel->getById($voucherId) ?: [];
            $voucherNo = trim((string) ($voucher['voucher_no'] ?? ''));
            $targetText = $voucherNo !== '' ? " {$voucherNo}" : '';

            $message = $actionType === 'reverse'
                ? "역분개 전표{$targetText}가 생성되었습니다."
                : "{$actorName}님이 전표{$targetText}를 {$actionLabel}했습니다.";

            foreach (array_values(array_unique($recipientIds)) as $recipientUserId) {
                $this->notificationService->createNotification([
                    'recipient_user_id' => $recipientUserId,
                    'actor_user_id' => $actorUserId !== '' ? $actorUserId : null,
                    'action_type' => $actionType,
                    'ref_table' => 'ledger_vouchers',
                    'ref_id' => $voucherId,
                    'title' => $title,
                    'message' => $message,
                ]);
            }
        } catch (\Throwable) {
            // 알림 생성 실패가 전표 처리에 영향을 주지 않도록 예외는 무시한다.
        }
    }


    private function resolveAccount(string $accountValue): ?array
    {
        return $this->chartAccountModel->resolveByIdOrCode($accountValue);
    }
    private function parseAmount(mixed $value): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) ($value ?? '')));

        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') {
            return 0.0;
        }

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private function normalizeSummaryText(mixed $value): ?string
    {
        $string = preg_replace('/\s+/u', ' ', trim((string) ($value ?? '')));

        return $string === '' ? null : $string;
    }
}
