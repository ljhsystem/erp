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
     API: ?④쑴伊?筌뤴뫖以?
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
                'message' => '?④쑴伊?筌뤴뫖以?鈺곌퀬????쎈솭',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     API: ?④쑴伊??怨멸쉭
     ============================================================ */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?ID ?袁⑥뵭'
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
                    'message' => '怨꾩쥖紐낆? ?꾩닔?낅땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['currency'] !== '' && !preg_match('/^[A-Z]{3}$/', strtoupper($payload['currency']))) {
                echo json_encode([
                    'success' => false,
                    'message' => '?듯솕 肄붾뱶??3?먮━ ?곷Ц?쇰줈 ?낅젰?댁＜?몄슂.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['account_number'] !== '' && !preg_match('/^[0-9-]+$/', $payload['account_number'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '怨꾩쥖踰덊샇???レ옄? ?섏씠?덈쭔 ?낅젰?????덉뒿?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['bank_name'] !== '' && mb_strlen($payload['bank_name']) > 100) {
                echo json_encode([
                    'success' => false,
                    'message' => '??됰챸? 100???댄븯濡??낅젰?댁＜?몄슂.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['account_holder'] !== '' && mb_strlen($payload['account_holder']) > 100) {
                echo json_encode([
                    'success' => false,
                    'message' => '?덇툑二쇰뒗 100???댄븯濡??낅젰?댁＜?몄슂.'
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
                'message' => 'ID ?袁⑥뵭'
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
                'message' => '?④쑴伊??袁⑹뵠???袁⑥뵭'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?癰귣벊????쎈솭',
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
                    'message' => '癰귣벊????④쑴伊??袁⑹뵠?遺? ??곷뮸??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?醫뤾문 癰귣벊????쎈솭',
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
                'message' => '?袁⑷퍥 癰귣벊????쎈솭',
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
                'message' => '?④쑴伊??袁⑹뵠???袁⑥뵭'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?袁⑹읈??????쎈솭',
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
                    'message' => '??????④쑴伊??袁⑹뵠?遺? ??곷뮸??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->purgeBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?醫뤾문 ?袁⑹읈??????쎈솭',
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
            'message' => '?袁⑷퍥 ?袁⑹읈??????쎈솭',
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
                     'message' => '癰궰野??怨쀬뵠????곸벉'
                 ], JSON_UNESCAPED_UNICODE);
                 exit;
             }

             $this->service->reorder($changes);

             echo json_encode([
                 'success' => true,
                 'message' => '?類ｌ졊 ?????袁⑥┷'
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

            // ?逾??곗뮆??甕곌쑵???λ뜃由??(餓λ쵐??
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadTemplate();

        } catch (\Throwable $e) {

            http_response_code(500);

            header('Content-Type: text/plain; charset=UTF-8');

            echo '?臾? ??쀫탣????쇱뒲嚥≪뮆諭???쎈솭: ' . $e->getMessage();
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
                    'message' => '???뵬????낆쨮??뺣┷筌왖 ??녿릭??щ빍??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $fileTmp  = $_FILES['excel']['tmp_name'];
            $fileName = $_FILES['excel']['name'];
            $fileSize = $_FILES['excel']['size'];

            /* =========================================================
             * ???뵬 野꺜筌?
             * ========================================================= */
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '?臾? ???뵬筌???낆쨮??揶쎛?館鍮??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode([
                    'success' => false,
                    'message' => '???뵬 ??몄쎗 ?λ뜃??(筌ㅼ뮆? 10MB)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
             * ??뺥돩???紐꾪뀱 (?逾?餓λ쵐?? SYSTEM ACTOR)
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
             * ?逾??곗뮆??甕곌쑵????볤탢 (?臾? 繹먥뫁彛?獄쎻뫗?)
             * ========================================================= */
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadExcel();

        } catch (\Throwable $e) {

            http_response_code(500);

            header('Content-Type: text/plain; charset=UTF-8');

            echo '?臾? ??쇱뒲嚥≪뮆諭???쎈솭: ' . $e->getMessage();
        }

        exit;
    }


}
