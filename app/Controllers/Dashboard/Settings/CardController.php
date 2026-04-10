<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CardController.php'

namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\CardService;

class CardController
{
    private CardService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new CardService(DbPdo::conn());
    }

    /* ============================================================
     API: 카드 목록
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
                'message' => '카드 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     API: 계좌 상세
     ============================================================ */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '카드 ID 누락'
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
                'message' => '계좌 조회 실패',
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
    * API: 카드 저장
    * ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            * 🔥 카드 payload (스키마 기준)
            * ========================================================= */
            $payload = [
                'id' => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,

                'card_name'   => trim((string)($_POST['card_name'] ?? '')),
                'card_type'   => trim((string)($_POST['card_type'] ?? '')),
                'card_number' => trim((string)($_POST['card_number'] ?? '')),

                'client_id'  => $_POST['client_id'] ?? null,
                'account_id' => $_POST['account_id'] ?? null,

                'expiry_year'  => trim((string)($_POST['expiry_year'] ?? '')),
                'expiry_month' => trim((string)($_POST['expiry_month'] ?? '')),

                'currency' => trim((string)($_POST['currency'] ?? 'KRW')),
                'limit_amount' => isset($_POST['limit_amount'])
                    ? (float)$_POST['limit_amount']
                    : 0,

                'note' => trim((string)($_POST['note'] ?? '')),
                'memo' => trim((string)($_POST['memo'] ?? '')),

                'is_active' => isset($_POST['is_active'])
                    ? (int)$_POST['is_active']
                    : 1,

                /* 🔥 파일 */
                'delete_card_file' => $_POST['delete_card_file'] ?? '0',
            ];

            /* =========================================================
            * 🔥 필수값 검증
            * ========================================================= */
            if ($payload['card_name'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '카드명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['card_type'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '카드유형은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            * 🔥 저장
            * ========================================================= */
            $result = $this->service->save($payload, 'USER', $_FILES);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '카드 저장 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     API: 삭제
     ============================================================ */
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID 누락'
            ]);
            exit;
        }

        $result = $this->service->delete($id, 'USER');

        echo json_encode($result);
        exit;
    }

    /* ============================================================
     API: 휴지통
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
                'message' => '카드 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        try {
    
            $result = $this->service->restore($id, 'USER');
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '카드 복원 실패',
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
                    'message' => '복원할 카드 아이디가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $result = $this->service->restoreBulk($ids, 'USER');
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '선택 복원 실패',
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
                'message' => '전체 복원 실패',
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
                'message' => '계좌 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        try {
    
            $result = $this->service->purge($id, 'USER');
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '완전삭제 실패',
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
                    'message' => '삭제할 카드 아이디가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $result = $this->service->purgeBulk($ids, 'USER');
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '선택 완전삭제 실패',
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
            'message' => '전체 완전삭제 실패',
            'error'   => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


    /* ============================================================
     API: 정렬
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
                     'message' => '변경 데이터 없음'
                 ], JSON_UNESCAPED_UNICODE);
                 exit;
             }
     
             $this->service->reorder($changes);
     
             echo json_encode([
                 'success' => true,
                 'message' => '정렬 저장 완료'
             ], JSON_UNESCAPED_UNICODE);
     
         } catch (\Throwable $e) {
     
             echo json_encode([
                 'success' => false,
                 'message' => '정렬 저장 실패',
                 'error'   => $e->getMessage()
             ], JSON_UNESCAPED_UNICODE);
         }
     
         exit;
     }


     public function apiDownloadTemplate(): void
    {
        try {

            // 🔥 출력 버퍼 초기화 (중요)
            if (ob_get_length()) {
                ob_end_clean();
            }

            $this->service->downloadTemplate();

        } catch (\Throwable $e) {

            http_response_code(500);

            header('Content-Type: text/plain; charset=UTF-8');

            echo '엑셀 템플릿 다운로드 실패: ' . $e->getMessage();
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
                    'message' => '파일이 업로드되지 않았습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $fileTmp  = $_FILES['excel']['tmp_name'];
            $fileName = $_FILES['excel']['name'];
            $fileSize = $_FILES['excel']['size'];
    
            /* =========================================================
             * 파일 검증
             * ========================================================= */
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
            if (!in_array($ext, ['xlsx', 'xls'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일만 업로드 가능합니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode([
                    'success' => false,
                    'message' => '파일 용량 초과 (최대 10MB)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            /* =========================================================
             * 서비스 호출 (🔥 중요: SYSTEM ACTOR)
             * ========================================================= */
            $actor = 'SYSTEM:EXCEL_UPLOAD';
    
            $result = $this->service->saveFromExcelFile($fileTmp, $actor);
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '엑셀 업로드 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    public function apiDownload(): void
    {
        try {
    
            /* =========================================================
             * 🔥 출력 버퍼 제거 (엑셀 깨짐 방지)
             * ========================================================= */
            if (ob_get_length()) {
                ob_end_clean();
            }
    
            $this->service->downloadExcel();
    
        } catch (\Throwable $e) {
    
            http_response_code(500);
    
            header('Content-Type: text/plain; charset=UTF-8');
    
            echo '엑셀 다운로드 실패: ' . $e->getMessage();
        }
    
        exit;
    }


}