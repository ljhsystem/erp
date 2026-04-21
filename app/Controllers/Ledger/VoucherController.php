<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use PDO;

class VoucherController
{
    private PDO $pdo;
    private VoucherService $service;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherPaymentModel $voucherPaymentModel;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->service = new VoucherService($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($this->pdo);
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode([
                'success' => true,
                'message' => '조회 완료',
                'data' => $this->voucherModel->getList(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $voucher = $this->voucherModel->getById($id);
            if (!$voucher) {
                echo json_encode([
                    'success' => false,
                    'message' => '전표를 찾을 수 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $voucher['lines'] = $this->voucherLineModel->getByVoucherId($id);
            $voucher['payments'] = $this->voucherPaymentModel->getByVoucherId($id);

            echo json_encode([
                'success' => true,
                'message' => '조회 완료',
                'data' => $voucher,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = $_POST;
            $payload['lines'] = json_decode((string) ($_POST['lines'] ?? '[]'), true) ?? [];
            $payload['payments'] = json_decode((string) ($_POST['payments'] ?? '[]'), true) ?? [];

            $result = $this->service->save($payload);

            echo json_encode([
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? '저장 완료' : ($result['message'] ?? '저장 실패'),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->pdo->beginTransaction();

            $this->voucherLineModel->softDeleteByVoucherId($id, null);
            foreach ($this->voucherPaymentModel->getByVoucherId($id) as $payment) {
                $paymentId = trim((string) ($payment['id'] ?? ''));
                if ($paymentId !== '') {
                    $this->voucherPaymentModel->softDelete($paymentId, null);
                }
            }
            $success = $this->voucherModel->softDelete($id, null);

            if (!$success) {
                throw new \RuntimeException('삭제 처리에 실패했습니다.');
            }

            $this->pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => '삭제 완료',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $stmt = $this->pdo->query("
                SELECT *
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, code DESC
            ");

            echo json_encode([
                'success' => true,
                'message' => '조회 완료',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->pdo->beginTransaction();

            $this->voucherModel->restore($id, null);
            $this->voucherLineModel->restoreByVoucherId($id, null);
            foreach ($this->voucherPaymentModel->getByVoucherId($id) as $payment) {
                $paymentId = trim((string) ($payment['id'] ?? ''));
                if ($paymentId !== '') {
                    $this->voucherPaymentModel->restore($paymentId);
                }
            }

            $this->pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => '복구 완료',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->pdo->beginTransaction();

            foreach ($this->voucherPaymentModel->getByVoucherId($id) as $payment) {
                $paymentId = trim((string) ($payment['id'] ?? ''));
                if ($paymentId !== '') {
                    $this->voucherPaymentModel->hardDelete($paymentId);
                }
            }

            foreach ($this->voucherLineModel->getByVoucherId($id) as $line) {
                $lineId = trim((string) ($line['id'] ?? ''));
                if ($lineId !== '') {
                    $this->voucherLineModel->hardDelete($lineId);
                }
            }

            $success = $this->voucherModel->hardDelete($id);
            if (!$success) {
                throw new \RuntimeException('영구 삭제에 실패했습니다.');
            }

            $this->pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => '삭제 완료',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}
