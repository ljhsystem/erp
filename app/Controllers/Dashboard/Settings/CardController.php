<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CardController.php'

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
                'message' => '移대뱶 紐⑸줉??遺덈윭?ㅼ? 紐삵뻽?듬땲??',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ID媛 ?꾩슂?⑸땲??',
            ]);
        }

        try {
            $this->jsonResponse([
                'success' => true,
                'data' => $this->service->getById($id),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ?뺣낫瑜?遺덈윭?ㅼ? 紐삵뻽?듬땲??',
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
                'sort_no' => $_POST['sort_no'] ?? null,
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
                $this->jsonResponse(['success' => false, 'message' => '移대뱶紐낆? ?꾩닔 ?낅젰?낅땲??']);
            }

            if ($payload['card_type'] === '') {
                $this->jsonResponse(['success' => false, 'message' => '移대뱶?좏삎? ?꾩닔 ?낅젰?낅땲??']);
            }

            if ($payload['currency'] !== '' && !preg_match('/^[A-Z]{3}$/', $payload['currency'])) {
                $this->jsonResponse(['success' => false, 'message' => '?듯솕 肄붾뱶??3?먮━ ?곷Ц?쇰줈 ?낅젰?댁＜?몄슂.']);
            }

            if ($payload['card_number'] !== '' && !preg_match('/^[0-9-]+$/', $payload['card_number'])) {
                $this->jsonResponse(['success' => false, 'message' => '移대뱶踰덊샇???レ옄? ?섏씠?덈쭔 ?낅젰?????덉뒿?덈떎.']);
            }

            if ($payload['expiry_year'] !== '' && !preg_match('/^\d{4}$/', $payload['expiry_year'])) {
                $this->jsonResponse(['success' => false, 'message' => '?좏슚湲곌컙(??? 4?먮━ ?レ옄濡??낅젰?댁＜?몄슂.']);
            }

            if ($payload['expiry_month'] !== '' && !preg_match('/^(0?[1-9]|1[0-2])$/', $payload['expiry_month'])) {
                $this->jsonResponse(['success' => false, 'message' => '?좏슚湲곌컙(??? 1遺??12 ?ъ씠 ?レ옄濡??낅젰?댁＜?몄슂.']);
            }

            if ($payload['limit_amount'] < 0) {
                $this->jsonResponse(['success' => false, 'message' => '?쒕룄湲덉븸? 0 ?댁긽?댁뼱???⑸땲??']);
            }

            $this->jsonResponse($this->service->save($payload, 'USER', $_FILES));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ???以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiDelete(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '移대뱶 ID媛 ?꾩슂?⑸땲??']);
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
            $this->jsonResponse(['success' => false, 'message' => '蹂듦뎄??移대뱶 ID媛 ?꾩슂?⑸땲??']);
        }

        try {
            $this->jsonResponse($this->service->restore($id, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 蹂듦뎄 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
                $this->jsonResponse(['success' => false, 'message' => '蹂듦뎄??移대뱶媛 ?놁뒿?덈떎.']);
            }

            $this->jsonResponse($this->service->restoreBulk($ids, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ?쇨큵 蹂듦뎄 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
                'message' => '?꾩껜 移대뱶 蹂듦뎄 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function apiPurge(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));

        if ($id === '') {
            $this->jsonResponse(['success' => false, 'message' => '?꾩쟾 ??젣??移대뱶 ID媛 ?꾩슂?⑸땲??']);
        }

        try {
            $this->jsonResponse($this->service->purge($id, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ?꾩쟾 ??젣 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
                $this->jsonResponse(['success' => false, 'message' => '?꾩쟾 ??젣??移대뱶媛 ?놁뒿?덈떎.']);
            }

            $this->jsonResponse($this->service->purgeBulk($ids, 'USER'));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '移대뱶 ?쇨큵 ?꾩쟾 ??젣 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
                'message' => '?꾩껜 移대뱶 ?꾩쟾 ??젣 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
                $this->jsonResponse(['success' => false, 'message' => '?뺣젹 蹂寃??곗씠?곌? ?놁뒿?덈떎.']);
            }

            $this->service->reorder($changes);

            $this->jsonResponse([
                'success' => true,
                'message' => '?뺣젹????λ릺?덉뒿?덈떎.',
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '?뺣젹 ???以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
            echo '移대뱶 ?쒗뵆由??ㅼ슫濡쒕뱶 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎: ' . $e->getMessage();
            exit;
        }
    }

    public function apiSaveFromExcel(): void
    {
        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                $this->jsonResponse(['success' => false, 'message' => '?낅줈?쒗븷 ?묒? ?뚯씪???좏깮?댁＜?몄슂.']);
            }

            $fileTmp = (string) $_FILES['excel']['tmp_name'];
            $fileName = (string) ($_FILES['excel']['name'] ?? '');
            $fileSize = (int) ($_FILES['excel']['size'] ?? 0);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                $this->jsonResponse(['success' => false, 'message' => '?묒? ?뚯씪留??낅줈?쒗븷 ???덉뒿?덈떎.']);
            }

            if ($fileSize > 10 * 1024 * 1024) {
                $this->jsonResponse(['success' => false, 'message' => '?묒? ?뚯씪? 理쒕? 10MB源뚯? ?낅줈?쒗븷 ???덉뒿?덈떎.']);
            }

            $this->jsonResponse($this->service->saveFromExcelFile($fileTmp));
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '?묒? ?낅줈??以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.',
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
            echo '移대뱶 ?묒? ?ㅼ슫濡쒕뱶 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎: ' . $e->getMessage();
            exit;
        }
    }

    private function normalizeCardType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'corporate', '법인' => 'corporate',
            'personal', '개인' => 'personal',
            'virtual', '가상' => 'virtual',
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
