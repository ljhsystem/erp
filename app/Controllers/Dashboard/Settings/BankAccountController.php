<?php
// 野껋럥以? PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/BankAccountController.php'

namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\System\BankAccountService;

class BankAccountController
{
    private BankAccountService $service;

    public function __construct()
    {
        $this->service = new BankAccountService(DbPdo::conn());
    }

    /* ============================================================
     API: 계좌 목록
     ============================================================ */
    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '계좌 목록 조회 중 오류가 발생했습니다.',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     API: ?④??곸꽭
     ============================================================ */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?ID ?⑥뵭'
            ]);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            echo json_encode([
                'success' => true,
                'data' => $row
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?鈺곌퀬????쎈솭',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }


    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $keyword = $_GET['q'] ?? '';

        $rows = $this->service->searchPicker($keyword);

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

        exit;
    }




    /* ============================================================
     API: ?④쑴伊?????
     ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $payload = [
                'id' => $_POST['id'] ?? null,
                'sort_no' => $_POST['sort_no'] ?? null,

                'account_name' => trim((string)($_POST['account_name'] ?? '')),
                'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
                'account_number' => trim((string)($_POST['account_number'] ?? '')),
                'account_holder' => trim((string)($_POST['account_holder'] ?? '')),
                'account_type' => trim((string)($_POST['account_type'] ?? '')),
                'currency' => trim((string)($_POST['currency'] ?? 'KRW')),
                'note' => trim((string)($_POST['note'] ?? '')),
                'memo' => trim((string)($_POST['memo'] ?? '')),
                'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,

                'delete_bank_file' => $_POST['delete_bank_file'] ?? '0',
            ];
            if ($payload['account_name'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '계좌명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['currency'] !== '' && !preg_match('/^[A-Z]{3}$/', strtoupper($payload['currency']))) {
                echo json_encode([
                    'success' => false,
                    'message' => '통화 코드는 3자리 영문으로 입력하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['account_number'] !== '' && !preg_match('/^[0-9-]+$/', $payload['account_number'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '계좌번호는 숫자와 하이픈만 입력할 수 있습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['bank_name'] !== '' && mb_strlen($payload['bank_name']) > 100) {
                echo json_encode([
                    'success' => false,
                    'message' => '은행명은 100자 이하로 입력하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['account_holder'] !== '' && mb_strlen($payload['account_holder']) > 100) {
                echo json_encode([
                    'success' => false,
                    'message' => '예금주는 100자 이하로 입력하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = $this->service->save($payload, 'USER', $_FILES);

            echo json_encode($result);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊???????쎈솭',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    /* ============================================================
     API: ????
     ============================================================ */
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID ?⑥뵭'
            ]);
            exit;
        }

        $result = $this->service->delete($id, 'USER');

        echo json_encode($result);
        exit;
    }

    /* ============================================================
     API: ?????
     ============================================================ */
    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $rows = $this->service->getTrashList();

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

        exit;
    }
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④??⑹???⑥뵭'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④?蹂듭????뙣',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $input = json_decode(file_get_contents('php://input'), true);

            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '蹂듭????④??⑹뵠?遺? ??곷뮸??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?좏깮 蹂듭????뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestoreAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $result = $this->service->restoreAll('USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?⑷ 蹂듭????뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④??⑹???⑥뵭'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?⑹읈??????쎈솭',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    public function apiPurgeBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $input = json_decode(file_get_contents('php://input'), true);

            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '??????④??⑹뵠?遺? ??곷뮸??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->purgeBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?좏깮 ?⑹읈??????쎈솭',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    public function apiPurgeAll(): void
{
    header('Content-Type: application/json; charset=UTF-8');

    try {

        $result = $this->service->purgeAll('USER');

        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => '?⑷ ?⑹읈??????쎈솭',
            'error'   => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


    /* ============================================================
     API: ?類ｌ졊
     ============================================================ */
     public function apiReorder(): void
     {
         header('Content-Type: application/json; charset=UTF-8');

         try {

             $input = json_decode(file_get_contents('php://input'), true);

             $changes = $input['changes'] ?? [];

             if (empty($changes) || !is_array($changes)) {
                 echo json_encode([
                     'success' => false,
                     'message' => '蹂??곗씠????쓬'
                 ], JSON_UNESCAPED_UNICODE);
                 exit;
             }

             $this->service->reorder($changes);

             echo json_encode([
                 'success' => true,
                 'message' => '?類젹 ?????⑥┷'
             ], JSON_UNESCAPED_UNICODE);

         } catch (\Throwable $e) {

             echo json_encode([
                 'success' => false,
                 'message' => '?類ｌ졊 ??????쎈솭',
                 'error'   => $e->getMessage()
             ], JSON_UNESCAPED_UNICODE);
         }

         exit;
     }


     public function apiDownloadTemplate(): void
    {
        try {

            // ???쒕??踰꾪???덇??(以묒??
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadTemplate();

        } catch (\Throwable $e) {

            http_response_code(500);

            header('Content-Type: text/plain; charset=UTF-8');

            echo '?? ??뵆????슫≪뮆諭???쎈솭: ' . $e->getMessage();
        }

        exit;
    }

    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '업로드할 엑셀 파일을 선택하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $fileTmp  = $_FILES['excel']['tmp_name'];
            $fileName = $_FILES['excel']['name'];
            $fileSize = $_FILES['excel']['size'];

            /* =========================================================
             * 엑셀 파일 검증
             * ========================================================= */
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일만 업로드할 수 있습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일 용량은 최대 10MB입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
             * ??뺥돩???紐텧 (??以묒?? SYSTEM ACTOR)
             * ========================================================= */
            $actor = 'SYSTEM:EXCEL_UPLOAD';

            $result = $this->service->saveFromExcelFile($fileTmp, $actor);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?臾? ??낆쨮????쎈솭',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    public function apiDownload(): void
    {
        try {

            /* =========================================================
             * ???쒕??踰꾪????굅 (?? 源⑥?諛⑹?)
             * ========================================================= */
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadExcel();

        } catch (\Throwable $e) {

            http_response_code(500);

            header('Content-Type: text/plain; charset=UTF-8');

            echo '?? ??슫≪뮆諭???쎈솭: ' . $e->getMessage();
        }

        exit;
    }


}
