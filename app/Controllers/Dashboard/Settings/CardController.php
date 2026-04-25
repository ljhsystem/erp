<?php
// Path: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CardController.php'

namespace App\Controllers\Dashboard\Settings;

use App\Services\System\CardService;
use Core\DbPdo;

class CardController
{
    private CardService $service;

    public function __construct()
    {
        $this->service = new CardService(DbPdo::conn());
    }

    public function apiList(): void
    {
        $filters = [];

        if (!empty($_GET['filters'])) {
            $decoded = json_decode((string) $_GET['filters'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $filters = $decoded;
            }
        }

        try {
            $this->jsonResponse([
                'success' => true,
                'data' => $this->service->getList($filters),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 목록 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '카드 ID가 없습니다.']);
        }

        try {
            $this->jsonResponse([
                'success' => true,
                'data' => $this->service->getById($id),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 상세 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiSearchPicker(): void
    {
        $keyword = trim((string) ($_GET['q'] ?? ''));

        $this->jsonResponse([
            'success' => true,
            'data' => $this->service->searchPicker($keyword),
        ]);
    }

    public function apiSave(): void
    {
        try {
            $payload = [
                'id' => $_POST['id'] ?? null,
                'card_name' => trim((string) ($_POST['card_name'] ?? '')),
                'card_type' => $this->normalizeCardType((string) ($_POST['card_type'] ?? '')),
                'card_number' => trim((string) ($_POST['card_number'] ?? '')),
                'client_id' => $_POST['client_id'] ?? null,
                'account_id' => $_POST['account_id'] ?? null,
                'expiry_year' => trim((string) ($_POST['expiry_year'] ?? '')),
                'expiry_month' => trim((string) ($_POST['expiry_month'] ?? '')),
                'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'KRW'))),
                'limit_amount' => isset($_POST['limit_amount']) ? (float) $_POST['limit_amount'] : 0.0,
                'note' => trim((string) ($_POST['note'] ?? '')),
                'memo' => trim((string) ($_POST['memo'] ?? '')),
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
                'delete_card_file' => $_POST['delete_card_file'] ?? '0',
            ];

            if ($payload['card_name'] === '') {
                $this->jsonResponse(['success' => false, 'message' => '카드명을 입력하세요.']);
            }

            if ($payload['card_type'] === '') {
                $this->jsonResponse(['success' => false, 'message' => '카드유형을 선택하세요.']);
            }

            if ($payload['currency'] !== '' && !preg_match('/^[A-Z]{3}$/', $payload['currency'])) {
                $this->jsonResponse(['success' => false, 'message' => '통화 코드는 3자리 영문으로 입력하세요.']);
            }

            if ($payload['card_number'] !== '' && !preg_match('/^[0-9-]+$/', $payload['card_number'])) {
                $this->jsonResponse(['success' => false, 'message' => '카드번호는 숫자와 하이픈만 입력할 수 있습니다.']);
            }

            if ($payload['expiry_year'] !== '' && !preg_match('/^\d{4}$/', $payload['expiry_year'])) {
                $this->jsonResponse(['success' => false, 'message' => '유효기간 년도는 4자리 숫자로 입력하세요.']);
            }

            if ($payload['expiry_month'] !== '' && !preg_match('/^(0?[1-9]|1[0-2])$/', $payload['expiry_month'])) {
                $this->jsonResponse(['success' => false, 'message' => '유효기간 월은 1부터 12 사이로 입력하세요.']);
            }

            if ($payload['limit_amount'] < 0) {
                $this->jsonResponse(['success' => false, 'message' => '한도금액은 0 이상이어야 합니다.']);
            }

            $this->jsonResponse($this->service->save($payload, 'USER', $_FILES));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 저장 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDelete(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '카드 ID가 없습니다.']);
        }

        $this->jsonResponse($this->service->delete($id, 'USER'));
    }

    public function apiTrashList(): void
    {
        $this->jsonResponse([
            'success' => true,
            'data' => $this->service->getTrashList(),
        ]);
    }

    public function apiRestore(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '복원할 카드 ID가 없습니다.']);
        }

        try {
            $this->jsonResponse($this->service->restore($id, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 복원 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiRestoreBulk(): void
    {
        try {
            $input = json_decode((string) file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                $this->jsonResponse(['success' => false, 'message' => '복원할 카드를 선택하세요.']);
            }

            $this->jsonResponse($this->service->restoreBulk($ids, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 일괄 복원 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiRestoreAll(): void
    {
        try {
            $this->jsonResponse($this->service->restoreAll('USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '전체 카드 복원 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiPurge(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '영구삭제할 카드 ID가 없습니다.']);
        }

        try {
            $this->jsonResponse($this->service->purge($id, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 영구삭제 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiPurgeBulk(): void
    {
        try {
            $input = json_decode((string) file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                $this->jsonResponse(['success' => false, 'message' => '영구삭제할 카드를 선택하세요.']);
            }

            $this->jsonResponse($this->service->purgeBulk($ids, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '카드 일괄 영구삭제 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiPurgeAll(): void
    {
        try {
            $this->jsonResponse($this->service->purgeAll('USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '전체 카드 영구삭제 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiReorder(): void
    {
        try {
            $input = json_decode((string) file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];

            if (empty($changes) || !is_array($changes)) {
                $this->jsonResponse(['success' => false, 'message' => '정렬 변경 데이터가 없습니다.']);
            }

            $this->service->reorder($changes);

            $this->jsonResponse([
                'success' => true,
                'message' => '정렬 순서가 저장되었습니다.',
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '정렬 저장 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDownloadTemplate(): void
    {
        try {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadTemplate();
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '카드 엑셀 양식 다운로드 중 오류가 발생했습니다: ' . $e->getMessage();
            exit;
        }
    }

    public function apiSaveFromExcel(): void
    {
        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                $this->jsonResponse(['success' => false, 'message' => '업로드할 엑셀 파일을 선택하세요.']);
            }

            $fileTmp = (string) $_FILES['excel']['tmp_name'];
            $fileName = (string) ($_FILES['excel']['name'] ?? '');
            $fileSize = (int) ($_FILES['excel']['size'] ?? 0);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                $this->jsonResponse(['success' => false, 'message' => '엑셀 파일만 업로드할 수 있습니다.']);
            }

            if ($fileSize > 10 * 1024 * 1024) {
                $this->jsonResponse(['success' => false, 'message' => '엑셀 파일은 10MB 이하만 업로드할 수 있습니다.']);
            }

            $this->jsonResponse($this->service->saveFromExcelFile($fileTmp));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '엑셀 업로드 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDownload(): void
    {
        try {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadExcel();
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo '카드 엑셀 다운로드 중 오류가 발생했습니다: ' . $e->getMessage();
            exit;
        }
    }

    private function normalizeCardType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'corporate', '법인', '법인카드' => 'corporate',
            'personal', '개인', '개인카드' => 'personal',
            'virtual', '가상', '가상카드' => 'virtual',
            default => $normalized,
        };
    }

    private function jsonResponse(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
