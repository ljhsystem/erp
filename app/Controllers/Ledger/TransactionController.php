<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Models\Ledger\TransactionFileModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherModel;
use App\Services\File\FileService;
use App\Services\Ledger\JournalLearningService;
use App\Services\Ledger\JournalRecommendationService;
use App\Services\Ledger\TransactionCrudService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class TransactionController
{
    private PDO $pdo;
    private TransactionCrudService $service;
    private TransactionModel $transactionModel;
    private TransactionFileModel $transactionFileModel;
    private TransactionLinkModel $transactionLinkModel;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private FileService $fileService;
    private JournalLearningService $journalLearningService;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new TransactionCrudService($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
        $this->transactionFileModel = new TransactionFileModel($this->pdo);
        $this->transactionLinkModel = new TransactionLinkModel($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($this->pdo);
        $this->journalLearningService = new JournalLearningService($this->pdo);
        $this->fileService = new FileService($this->pdo);
        $this->layout = new LayoutController($this->pdo);
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '거래관리',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webTransaction(): void
    {
        $this->redirectToLedgerTransaction();
    }

    public function webCreate(): void
    {
        $this->redirectToLedgerTransaction();
    }

    public function webLedgerTransaction(): void
    {
        $this->renderTransactionCreatePage([
            'pageTitle' => '거래입력',
            'pageSubtitle' => '거래 입력, 목록, 전표 연결을 한 화면에서 관리합니다.',
        ]);
    }

    public function webLedgerCreate(): void
    {
        $this->redirectToLedgerTransaction();
    }

    private function renderTransactionCreatePage(array $params): void
    {
        $this->renderPage('/app/views/ledger/transaction/index.php', $params);
    }

    private function redirectToLedgerTransaction(): void
    {
        header('Location: /ledger/transactions/input', true, 302);
        exit;
    }

    public function apiList(): void
    {
        $this->json(function (): array {
            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode((string) $_GET['filters'], true);
                if (is_array($decoded)) {
                    $filters = $decoded;
                }
            } else {
                $filters = $_GET;
            }

            return [
                'success' => true,
                'data' => $this->service->getList($filters),
            ];
        });
    }

    public function apiReorder(): void
    {
        $this->json(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $changes = $input['changes'] ?? [];

            if ($changes === []) {
                return [
                    'success' => false,
                    'message' => '정렬 데이터가 없습니다.',
                ];
            }

            $this->service->reorder($changes);

            return [
                'success' => true,
                'message' => '정렬 저장 완료',
            ];
        });
    }

    public function apiDetail(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            $row = $this->service->getById($id);
            if (!$row) {
                http_response_code(404);
                return [
                    'success' => false,
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ];
            }

            return [
                'success' => true,
                'data' => $this->withLinkedVouchers($row),
            ];
        });
    }

    public function apiFile(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            http_response_code(400);
            exit('Missing file id');
        }

        $file = $this->transactionFileModel->getById($id);
        if (!$file || empty($file['file_path'])) {
            http_response_code(404);
            exit('File not found');
        }

        $abs = \Core\storage_resolve_abs((string) $file['file_path']);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            exit('File not found');
        }

        $fileName = (string) ($file['file_name'] ?: basename($abs));
        $mime = mime_content_type($abs) ?: 'application/octet-stream';
        $disposition = in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ], true) ? 'inline' : 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header(
            'Content-Disposition: ' . $disposition
            . '; filename="' . addcslashes($fileName, "\\\"") . '"'
            . "; filename*=UTF-8''" . rawurlencode($fileName)
        );
        readfile($abs);
        exit;
    }

    public function apiSave(): void
    {
        $this->json(function (): array {
            $payload = $_POST;
            $rawBody = file_get_contents('php://input');

            if ($rawBody !== false && trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $payload = array_replace_recursive($payload, $decoded);
                }
            }

            return $this->service->save($payload, $_FILES);
        });
    }

    public function apiCreateVoucher(): void
    {
        $this->json(function (): array {
            $payload = $this->requestPayload();
            $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            return $this->createDraftVoucherFromTransaction($transactionId, $payload);
        });
    }

    public function apiRecommendVoucher(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_GET['transaction_id'] ?? $_POST['transaction_id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('嫄곕옒 ID媛 ?꾩슂?⑸땲??');
            }

            $transaction = $this->service->getById($transactionId);
            if (!$transaction || !empty($transaction['deleted_at'])) {
                throw new \InvalidArgumentException('嫄곕옒瑜?李얠쓣 ???놁뒿?덈떎.');
            }

            if ($this->fetchLinkedVouchers($transactionId) !== []) {
                throw new \RuntimeException('?대? ?곌껐???꾪몴媛 ?덉뒿?덈떎.');
            }

            return [
                'success' => true,
                'transaction' => $this->voucherWizardTransaction($transaction),
                'recommendation' => (new JournalRecommendationService($this->pdo))->recommendForTransaction($transactionId),
            ];
        });
    }

    public function apiLinkVoucher(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));
            $voucherId = trim((string) ($_POST['voucher_id'] ?? ''));

            if ($transactionId === '' || $voucherId === '') {
                throw new \InvalidArgumentException('거래와 전표를 선택해 주세요.');
            }

            $transaction = $this->service->getById($transactionId);
            if (!$transaction || !empty($transaction['deleted_at'])) {
                throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
            }

            $voucher = $this->voucherModel->getById($voucherId);
            if (!$voucher || !empty($voucher['deleted_at'])) {
                throw new \InvalidArgumentException('전표를 찾을 수 없습니다.');
            }

            $this->assertVoucherLinkEditable($voucher);

            $actor = ActorHelper::user();

            $this->pdo->beginTransaction();
            if (!$this->transactionLinkModel->insertOrRestore(
                $transactionId,
                $voucherId,
                $transaction['total_amount'] ?? null,
                'MANUAL',
                $actor
            )) {
                throw new \RuntimeException('전표 연결 저장에 실패했습니다.');
            }

            $this->service->recalculateMatchStatus($transactionId, $actor);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전표가 연결되었습니다.',
                'data' => $this->withLinkedVouchers($this->service->getById($transactionId) ?? []),
            ];
        });
    }

    public function apiUnlinkVoucher(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));
            $voucherId = trim((string) ($_POST['voucher_id'] ?? ''));

            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래를 선택해 주세요.');
            }

            $links = $this->transactionLinkModel->getByTransactionId($transactionId);
            foreach ($links as $link) {
                if ($voucherId !== '' && (string) ($link['voucher_id'] ?? '') !== $voucherId) {
                    continue;
                }

                $voucher = $this->voucherModel->getById((string) ($link['voucher_id'] ?? ''));
                if ($voucher) {
                    $this->assertVoucherLinkEditable($voucher);
                }
            }

            $actor = ActorHelper::user();
            $this->pdo->beginTransaction();
            foreach ($links as $link) {
                $linkedVoucherId = (string) ($link['voucher_id'] ?? '');
                if ($linkedVoucherId === '' || ($voucherId !== '' && $linkedVoucherId !== $voucherId)) {
                    continue;
                }

                $this->transactionLinkModel->softDeleteByTransactionAndVoucher($transactionId, $linkedVoucherId, $actor);
            }
            $this->service->recalculateMatchStatus($transactionId, $actor);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전표 연결이 해제되었습니다.',
                'data' => $this->withLinkedVouchers($this->service->getById($transactionId) ?? []),
            ];
        });
    }

    public function apiDelete(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_POST['transaction_id'] ?? $_POST['id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래를 선택해 주세요.');
            }

            return $this->service->softDelete($transactionId);
        });
    }

    public function apiTrashList(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("
                SELECT
                    t.*,
                    COALESCE(sc.client_name, '') AS client_name,
                    COALESCE(sp.project_name, '') AS project_name
                FROM ledger_transactions t
                LEFT JOIN system_clients sc
                    ON t.client_id = sc.id
                LEFT JOIN system_projects sp
                    ON t.project_id = sp.id
                WHERE t.deleted_at IS NOT NULL
                ORDER BY t.deleted_at DESC, t.transaction_date DESC
            ");

            return [
                'success' => true,
                'data' => array_map(static function (array $row): array {
                    unset($row['tax_type']);
                    return $row;
                }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []),
            ];
        });
    }

    public function apiRestore(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('복원할 거래를 선택해 주세요.');
            }

            $this->restoreTransactions([$id]);

            return [
                'success' => true,
                'message' => '거래가 복원되었습니다.',
            ];
        });
    }

    public function apiRestoreBulk(): void
    {
        $this->json(function (): array {
            $ids = $this->idsFromJsonBody();
            if ($ids === []) {
                throw new \InvalidArgumentException('복원할 거래를 선택해 주세요.');
            }

            $this->restoreTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 복원되었습니다.',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("SELECT id FROM ledger_transactions WHERE deleted_at IS NOT NULL");
            $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $this->restoreTransactions($ids);

            return [
                'success' => true,
                'message' => '전체 거래가 복원되었습니다.',
            ];
        });
    }

    public function apiPurge(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('삭제할 거래를 선택해 주세요.');
            }

            $this->purgeTransactions([$id]);

            return [
                'success' => true,
                'message' => '거래가 영구 삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeBulk(): void
    {
        $this->json(function (): array {
            $ids = $this->idsFromJsonBody();
            if ($ids === []) {
                throw new \InvalidArgumentException('삭제할 거래를 선택해 주세요.');
            }

            $this->purgeTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 영구 삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("SELECT id FROM ledger_transactions WHERE deleted_at IS NOT NULL");
            $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $this->purgeTransactions($ids);

            return [
                'success' => true,
                'message' => '전체 거래가 영구 삭제되었습니다.',
            ];
        });
    }

    private function json(callable $callback): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $result = $callback();
            http_response_code(!empty($result['success']) ? 200 : 400);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function idsFromJsonBody(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload) || !isset($payload['ids']) || !is_array($payload['ids'])) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $payload['ids'])));
    }

    private function requestPayload(): array
    {
        $payload = $_POST;
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_replace_recursive($payload, $decoded);
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function restoreTransactions(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $actor = ActorHelper::user();
        $actorId = is_array($actor) ? ($actor['id'] ?? null) : $actor;

        try {
            $this->pdo->beginTransaction();

            foreach ($ids as $id) {
                $this->transactionModel->update($id, [
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actorId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function purgeTransactions(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $filePaths = [];

        try {
            $this->pdo->beginTransaction();

            $deleteItems = $this->pdo->prepare("DELETE FROM ledger_transaction_lines WHERE transaction_id = :id");
            $softDeleteLinks = $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE transaction_id = :id
                  AND is_active = 1
                  AND deleted_at IS NULL
            ");
            $actor = ActorHelper::user();

            foreach ($ids as $id) {
                foreach ($this->transactionFileModel->getByTransactionId($id) as $file) {
                    if (!empty($file['file_path'])) {
                        $filePaths[] = (string) $file['file_path'];
                    }
                }

                $deleteItems->execute([':id' => $id]);
                $softDeleteLinks->execute([
                    ':id' => $id,
                    ':deleted_by' => $actor,
                    ':updated_by' => $actor,
                ]);
                $this->transactionModel->hardDelete($id);
            }

            $this->pdo->commit();

            foreach (array_unique($filePaths) as $filePath) {
                $this->fileService->delete($filePath);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function withLinkedVouchers(array $transaction): array
    {
        if ($transaction === [] || empty($transaction['id'])) {
            return $transaction;
        }

        $transaction['linked_vouchers'] = $this->fetchLinkedVouchers((string) $transaction['id']);

        return $transaction;
    }

    private function fetchLinkedVouchers(string $transactionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                l.id AS link_id,
                l.link_type,
                v.id,
                v.sort_no,
                v.voucher_no,
                v.voucher_date,
                v.status,
                v.summary_text
            FROM ledger_transaction_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.voucher_id
            WHERE l.transaction_id = :transaction_id
              AND l.deleted_at IS NULL
              AND l.is_active = 1
              AND v.deleted_at IS NULL
            ORDER BY v.voucher_date DESC, v.sort_no DESC
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function createDraftVoucherFromTransaction(string $transactionId, array $payload = []): array
    {
        $transaction = $this->service->getById($transactionId);
        if (!$transaction || !empty($transaction['deleted_at'])) {
            throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
        }

        if ($this->fetchLinkedVouchers($transactionId) !== []) {
            throw new \RuntimeException('이미 연결된 전표가 있습니다.');
        }

        $lines = $this->normalizeWizardLines($payload['lines'] ?? []);
        $this->assertBalancedVoucherLines($lines);

        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $voucherId = UuidHelper::generate();
        $voucherDate = trim((string) ($header['transaction_date'] ?? $transaction['transaction_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $voucherNo = $this->nextVoucherNo($voucherDate);
        $items = is_array($transaction['items'] ?? null) ? $transaction['items'] : [];
        $firstItemName = trim((string) ($items[0]['item_name'] ?? ''));
        $totalAmount = array_reduce($items, static function (float $sum, array $item): float {
            return $sum + (float) ($item['total_amount'] ?? 0);
        }, 0.0);

        $this->pdo->beginTransaction();

        if (!$this->voucherModel->insert([
            'id' => $voucherId,
            'sort_no' => SequenceHelper::next('ledger_vouchers', 'sort_no'),
            'voucher_no' => $voucherNo,
            'voucher_date' => $voucherDate,
            'source_type' => 'TRANSACTION',
            'source_id' => $transactionId,
            'status' => 'draft',
            'summary_text' => trim((string) ($header['description'] ?? $transaction['description'] ?? '')) ?: ($firstItemName ?: null),
            'note' => $transaction['note'] ?? null,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ])) {
            throw new \RuntimeException('전표 생성에 실패했습니다.');
        }

        if (!$this->transactionLinkModel->insertOrRestore(
            $transactionId,
            $voucherId,
            $transaction['total_amount'] ?? $totalAmount,
            'AUTO',
            $actor
        )) {
            throw new \RuntimeException('전표 연결 저장에 실패했습니다.');
        }

        $lineNo = 1;
        $learningLines = [];
        foreach ($lines as $line) {
            $lineId = UuidHelper::generate();
            if (!$this->voucherLineModel->insert([
                'id' => $lineId,
                'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                'voucher_id' => $voucherId,
                'line_no' => $lineNo++,
                'account_id' => (string) $line['account_id'],
                'debit' => $line['line_type'] === 'DEBIT' ? number_format((float) $line['amount'], 2, '.', '') : '0.00',
                'credit' => $line['line_type'] === 'CREDIT' ? number_format((float) $line['amount'], 2, '.', '') : '0.00',
                'line_summary' => $line['line_summary'] ?? ($transaction['description'] ?? null),
                'recommend_source' => $line['source'] ?? null,
                'recommend_confidence' => $line['confidence'] ?? null,
                'journal_rule_id' => $line['journal_rule_id'] ?? null,
                'recommend_reason' => $line['reason'] ?? null,
                'is_user_modified' => !empty($line['is_user_modified']) ? 1 : 0,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('추천 전표라인 저장에 실패했습니다.');
            }

            $refs = [];
            if (!empty($line['client_id'])) {
                $refs[] = ['ref_type' => 'CLIENT', 'ref_id' => $line['client_id'], 'is_primary' => 1];
            }
            if (!empty($line['project_id'])) {
                $refs[] = ['ref_type' => 'PROJECT', 'ref_id' => $line['project_id'], 'is_primary' => 0];
            }
            if ($refs !== []) {
                $this->voucherLineRefModel->bulkInsert($lineId, $refs, $actor, $timestamp);
            }

            $line['voucher_line_id'] = $lineId;
            $line['line_no'] = $lineNo - 1;
            $learningLines[] = $line;
        }

        $this->journalLearningService->recordVoucherDraft($transaction, $voucherId, $learningLines, $actor);
        $this->service->recalculateMatchStatus($transactionId, $actor);
        $this->pdo->commit();

        return [
            'success' => true,
            'message' => '전표가 생성되었습니다.',
            'voucher_id' => $voucherId,
            'voucher_no' => $voucherNo,
            'data' => $this->withLinkedVouchers($this->service->getById($transactionId) ?? []),
        ];
    }

    private function voucherWizardTransaction(array $transaction): array
    {
        return [
            'id' => (string) ($transaction['id'] ?? ''),
            'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
            'client_id' => (string) ($transaction['client_id'] ?? ''),
            'client_name' => (string) ($transaction['client_name'] ?? ''),
            'project_id' => (string) ($transaction['project_id'] ?? ''),
            'project_name' => (string) ($transaction['project_name'] ?? ''),
            'business_unit' => (string) ($transaction['business_unit'] ?? ''),
            'transaction_type' => (string) ($transaction['transaction_type'] ?? ''),
            'transaction_direction' => (string) ($transaction['transaction_direction'] ?? ''),
            'import_type' => (string) ($transaction['import_type'] ?? ''),
            'supply_amount' => (string) ($transaction['supply_amount'] ?? '0'),
            'vat_amount' => (string) ($transaction['vat_amount'] ?? '0'),
            'total_amount' => (string) ($transaction['total_amount'] ?? '0'),
            'description' => (string) ($transaction['description'] ?? ''),
        ];
    }

    private function normalizeWizardLines(mixed $lines): array
    {
        if (!is_array($lines)) {
            throw new \InvalidArgumentException('遺꾧컻?쇱씤???낅젰?섏꽭??');
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineType = strtoupper(trim((string) ($line['line_type'] ?? '')));
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $amount = round((float) str_replace(',', '', (string) ($line['amount'] ?? 0)), 2);

            if ($lineType === '' && isset($line['debit'], $line['credit'])) {
                $lineType = (float) $line['debit'] > 0 ? 'DEBIT' : 'CREDIT';
                $amount = max((float) $line['debit'], (float) $line['credit']);
            }

            if (!in_array($lineType, ['DEBIT', 'CREDIT'], true) || $accountId === '' || $amount <= 0) {
                continue;
            }

            $normalized[] = [
                'line_type' => $lineType,
                'account_id' => $accountId,
                'amount' => $amount,
                'line_summary' => trim((string) ($line['line_summary'] ?? '')) ?: null,
                'client_id' => trim((string) ($line['client_id'] ?? '')) ?: null,
                'project_id' => trim((string) ($line['project_id'] ?? '')) ?: null,
                'source' => trim((string) ($line['source'] ?? '')) ?: null,
                'confidence' => isset($line['confidence']) ? (int) $line['confidence'] : null,
                'journal_rule_id' => trim((string) ($line['journal_rule_id'] ?? '')) ?: null,
                'reason' => trim((string) ($line['reason'] ?? '')) ?: null,
                'recommended_line_type' => strtoupper(trim((string) ($line['recommended_line_type'] ?? $lineType))) ?: $lineType,
                'recommended_account_id' => trim((string) ($line['recommended_account_id'] ?? $accountId)) ?: $accountId,
                'recommended_amount' => round((float) str_replace(',', '', (string) ($line['recommended_amount'] ?? $amount)), 2),
                'is_user_modified' => !empty($line['is_user_modified'])
                    || strtoupper(trim((string) ($line['recommended_line_type'] ?? $lineType))) !== $lineType
                    || trim((string) ($line['recommended_account_id'] ?? $accountId)) !== $accountId
                    || round((float) str_replace(',', '', (string) ($line['recommended_amount'] ?? $amount)), 2) !== $amount,
            ];
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('저장할 분개라인이 없습니다.');
        }

        return $normalized;
    }

    private function assertBalancedVoucherLines(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            if (($line['line_type'] ?? '') === 'DEBIT') {
                $debit += (float) $line['amount'];
            } elseif (($line['line_type'] ?? '') === 'CREDIT') {
                $credit += (float) $line['amount'];
            }
        }

        if ($debit <= 0 || $credit <= 0 || round($debit, 2) !== round($credit, 2)) {
            throw new \InvalidArgumentException('차변합과 대변합이 일치해야 전표를 저장할 수 있습니다.');
        }
    }

    private function assertVoucherLinkEditable(array $voucher): void
    {
        if (($voucher['status'] ?? '') === 'posted') {
            throw new \RuntimeException('posted 상태의 전표는 연결을 변경할 수 없습니다.');
        }
    }

    private function nextVoucherNo(string $voucherDate): string
    {
        $prefix = preg_replace('/[^0-9]/', '', $voucherDate);
        if ($prefix === '') {
            $prefix = date('Ymd');
        }

        $stmt = $this->pdo->prepare("
            SELECT voucher_no
            FROM ledger_vouchers
            WHERE voucher_no LIKE :prefix
            ORDER BY voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);

        $last = (string) ($stmt->fetchColumn() ?: '');
        $next = 1;
        if (preg_match('/-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
