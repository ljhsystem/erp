<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use App\Services\Ledger\VoucherService;
use App\Services\Ledger\VoucherValidationException;
use Core\DbPdo;
use PDO;

class VoucherController
{
    private PDO $pdo;
    private VoucherService $service;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private VoucherPaymentModel $voucherPaymentModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->service = new VoucherService($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($this->pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($this->pdo);
        $this->transactionLinkModel = new TransactionLinkModel($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
    }

    public function apiList(): void
    {
        $this->jsonResponse(function (): array {
            $filters = [];
            if (!empty($_GET['filters'])) {
                $filters = json_decode((string) $_GET['filters'], true) ?? [];
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $this->voucherModel->getList($filters),
            ];
        });
    }

    public function apiReorder(): void
    {
        $this->jsonResponse(function (): array {
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
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $voucher = $this->voucherModel->getById($id);
            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => '전표를 찾을 수 없습니다.',
                ];
            }

            $voucher['lines'] = $this->voucherLineModel->getByVoucherId($id);
            $lineRefs = $this->voucherLineRefModel->getGroupedByVoucherLineIds(array_column($voucher['lines'], 'id'));
            foreach ($voucher['lines'] as &$line) {
                $line['refs'] = array_map(static fn(array $ref): array => [
                    'ref_type' => $ref['ref_type'] ?? '',
                    'ref_id' => $ref['ref_id'] ?? '',
                    'is_primary' => (int) ($ref['is_primary'] ?? 0),
                ], $lineRefs[$line['id']] ?? []);
            }
            unset($line);
            $voucher['payments'] = $this->voucherPaymentModel->getByVoucherId($id);
            $voucher['linked_transaction'] = null;

            foreach ($this->transactionLinkModel->getByVoucherId($id) as $link) {
                if (($link['link_type'] ?? '') !== 'MANUAL') {
                    continue;
                }

                $transactionId = trim((string) ($link['transaction_id'] ?? ''));
                if ($transactionId !== '') {
                    $voucher['linked_transaction'] = $this->transactionModel->getById($transactionId);
                }
                break;
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $voucher,
            ];
        });
    }

    public function apiTransactionSearch(): void
    {
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));
            $rows = $this->transactionModel->getList([]);

            if ($query !== '') {
                $rows = array_values(array_filter($rows, static function (array $row) use ($query): bool {
                    $haystack = implode(' ', [
                        $row['sort_no'] ?? '',
                        $row['transaction_date'] ?? '',
                        $row['client_name'] ?? '',
                        $row['project_name'] ?? '',
                        $row['item_summary'] ?? '',
                        $row['description'] ?? '',
                        $row['total_amount'] ?? '',
                    ]);

                    return stripos($haystack, $query) !== false;
                }));
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => array_slice($rows, 0, 50),
            ];
        });
    }

    public function apiCreateTransaction(): void
    {
        $this->jsonResponse(function (): array {
            http_response_code(400);

            return [
                'success' => false,
                'message' => '전표에서 거래를 생성할 수 없습니다. 거래입력 화면에서 전표를 생성하거나 연결해 주세요.',
            ];
        });
    }

    public function apiSummarySearch(): void
    {
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));

            return [
                'success' => true,
                'items' => $this->service->searchSummaryTexts($query, 10),
            ];
        });
    }

    public function apiSave(): void
    {
        $this->jsonResponse(function (): array {
            $payload = $_POST;
            $payload['lines'] = json_decode((string) ($_POST['lines'] ?? '[]'), true) ?? [];
            $payload['payments'] = json_decode((string) ($_POST['payments'] ?? '[]'), true) ?? [];

            $result = $this->service->save($payload);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? '저장 완료' : ($result['message'] ?? '저장 실패'),
                'data' => $result,
            ];
        });
    }

    public function apiUpdateStatus(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? ''));

            $result = $this->service->updateStatus($id, $status);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '상태 변경 완료',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiDelete(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->service->deleteVoucher($id);

            return [
                'success' => true,
                'message' => '삭제 완료',
            ];
        });
    }

    public function apiTrashList(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT *
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, sort_no DESC
            ");

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ];
        });
    }

    public function apiRestore(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->restoreVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '복원 완료',
            ];
        });
    }

    public function apiRestoreBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '복원할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 복원 완료',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 복원 완료',
            ];
        });
    }

    public function apiPurge(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->purgeVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '완전 삭제할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 완전 삭제 완료',
            ];
        });
    }

    private function jsonResponse(callable $callback): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode($callback(), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            if ($e instanceof VoucherValidationException) {
                $payload['validation_type'] = $e->getValidationType();
            }

            http_response_code(400);
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function restoreVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->pdo->prepare("
            UPDATE ledger_transaction_links
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL
            WHERE voucher_id = :voucher_id
        ")->execute([':voucher_id' => $id]);

        $this->voucherModel->restore($id, null);
    }

    private function purgeVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
        ")->execute([':voucher_id' => $id]);

        if (!$this->voucherModel->hardDelete($id)) {
            throw new \RuntimeException('전표 완전 삭제에 실패했습니다.');
        }
    }

}
