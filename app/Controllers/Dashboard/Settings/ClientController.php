<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/ClientController.php'
// 대시보드>설정>기초정보관리>거래처 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\ClientService;



class ClientController
{
    private ClientService $service;


    public function __construct()
    {
        Session::requireAuth();
        $this->service = new ClientService(DbPdo::conn());

    }

    // ============================================================
    // API: 거래처 목록 조회
    // URL: GET /api/settings/base-info/client/list
    // permission: 
    // controller: ClientController@apiList
    // ============================================================
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
    
            // 🔥 무조건 getList 하나만 사용
            $rows = $this->service->getList($filters);
    
            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '거래처 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }



    // ============================================================
    // API: 거래처 상세 조회
    // URL: GET /api/settings/base-info/client/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '거래처 없음'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $row
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 조회 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $keyword = trim($_GET['q'] ?? '');
            $rows = $this->service->searchPicker($keyword);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'data'    => [],
                'message' => '검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 거래처 저장 (신규 + 수정)
    // URL: POST /api/settings/base-info/client/save
    // permission: 
    // controller: ClientController@apiSave
    // ============================================================
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            /* =========================================================
            payload 생성
            ========================================================= */
    
            $payload = [
    
                'id' => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,
    
                'client_name' => trim($_POST['client_name'] ?? ''),
                'company_name' => $_POST['company_name'] ?? null,
    
                'registration_date' => $_POST['registration_date'] ?? null,
    
                'business_number' => $_POST['business_number'] ?? null,
                'rrn' => $_POST['rrn'] ?? null,
    
                'business_type' => $_POST['business_type'] ?? null,
                'business_category' => $_POST['business_category'] ?? null,
                'business_status' => $_POST['business_status'] ?? null,
    
                'address' => $_POST['address'] ?? null,
                'address_detail' => $_POST['address_detail'] ?? null,
    
                'phone' => $_POST['phone'] ?? null,
                'fax' => $_POST['fax'] ?? null,
                'email' => $_POST['email'] ?? null,
    
                'ceo_name' => $_POST['ceo_name'] ?? null,
                'ceo_phone' => $_POST['ceo_phone'] ?? null,
    
                'manager_name' => $_POST['manager_name'] ?? null,
                'manager_phone' => $_POST['manager_phone'] ?? null,
    
                'homepage' => $_POST['homepage'] ?? null,
    
                'bank_name' => $_POST['bank_name'] ?? null,
                'account_number' => $_POST['account_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,
    
                'trade_category' => $_POST['trade_category'] ?? null,
                'item_category' => $_POST['item_category'] ?? null,
    
                'client_category' => $_POST['client_category'] ?? null,
                'client_type' => $_POST['client_type'] ?? null,
                'tax_type' => $_POST['tax_type'] ?? null,
                'payment_term' => $_POST['payment_term'] ?? null,
    
                'client_grade' => $_POST['client_grade'] ?? null,
    
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
    
                /* 🔥 삭제 플래그 (Service로 전달) */
                'delete_business_certificate' => $_POST['delete_business_certificate'] ?? '0',
                'delete_rrn_image'            => $_POST['delete_rrn_image'] ?? '0',
                'delete_bank_file'            => $_POST['delete_bank_file'] ?? '0',

                'is_active' => 1
            ];
    
            /* =========================================================
            필수값 체크
            ========================================================= */
    
            if ($payload['client_name'] === '') {
    
                echo json_encode([
                    'success' => false,
                    'message' => '거래처명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            /* =========================================================
            저장 (🔥 파일 포함 Service로 위임)
            ========================================================= */
    
            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES   // 🔥 핵심
            );
    
            /* =========================================================
            에러 메시지 변환
            ========================================================= */
    
            if (!$result['success']) {
    
                $msg = $result['message'] ?? '';
    
                if (
                    str_contains($msg, 'Duplicate entry') &&
                    str_contains($msg, 'uq_business_number')
                ) {
                    echo json_encode([
                        'success' => false,
                        'message' => '이미 등록된 사업자등록번호입니다.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
    
                echo json_encode([
                    'success' => false,
                    'message' => $msg ?: '거래처 저장 실패'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            /* =========================================================
            정상
            ========================================================= */
    
            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'code'    => $result['code'] ?? null,
                'message' => '저장 완료'
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            $msg = $e->getMessage();
    
            if (
                str_contains($msg, 'Duplicate entry') &&
                str_contains($msg, 'uq_business_number')
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => '이미 등록된 사업자등록번호입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            echo json_encode([
                'success' => false,
                'message' => '거래처 저장 중 오류 발생'
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }

    // ============================================================
    // API: 거래처 삭제
    // URL: POST /api/settings/base-info/client/delete
    // permission: 
    // controller: ClientController@apiDelete
    // ============================================================
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete($id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    // ============================================================
    // API: 거래처 휴지통 목록
    // URL: GET /api/settings/base-info/client/trash
    // ============================================================
    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $rows = $this->service->getTrashList();

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '휴지통 조회 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 거래처 복원
    // URL: POST /api/settings/base-info/client/restore
    // ============================================================
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 복원 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }






    // ============================================================
    // API: 거래처 선택 복원
    // URL: POST /api/settings/base-info/client/restore-bulk
    // ============================================================
    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '복원할 거래처 아이디가 없습니다.'
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



    // ============================================================
    // API: 거래처 완전삭제
    // URL: POST /api/settings/base-info/client/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
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
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 거래처 선택 완전삭제
    // URL: POST /api/settings/base-info/client/purge-bulk
    // ============================================================
    public function apiPurgeBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '삭제할 거래처 아이디가 없습니다.'
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


    // ============================================================
    // API: 거래처 전체 완전삭제
    // URL: POST /api/settings/base-info/client/purge-all
    // ============================================================
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





    // ============================================================
    // API: 거래처 순서 변경 (RowReorder)
    // URL: POST /api/settings/base-info/client/reorder
    // permission: 
    // controller: ClientController@apiReorder
    // ============================================================
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success'=>false,'message'=>'변경 데이터 없음']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success'=>true]);
    }



    // ============================================================
    // API: 거래처 양식 엑셀 다운로드
    // URL: GET /api/settings/base-info/clients/template
    // permission: 
    // controller: ClientController@apiDownloadTemplate
    // ============================================================    
    public function apiDownloadTemplate(): void
    {
        try {
    
            $this->service->downloadTemplate();
    
        } catch (\Throwable $e) {
    
            http_response_code(500);
            echo '엑셀 템플릿 다운로드 실패 : ' . $e->getMessage();
            exit;
        }
    }



    // ============================================================
    // API: 거래처 엑셀 업로드
    // URL: POST /api/settings/base-info/client/excel-upload
    // ============================================================
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

            $file = $_FILES['excel']['tmp_name'];

            $result = $this->service->saveFromExcelFile($file);

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


    // ============================================================
    // API: 거래처 전체 엑셀 다운로드
    // URL: GET /api/settings/base-info/clients/excel
    // permission: 
    // controller: ClientController@apidownload
    // ============================================================    
    public function apiDownload(): void
    {
        try {

            $this->service->downloadExcel();

        } catch (\Throwable $e) {

            http_response_code(500);
            echo '엑셀 다운로드 실패 : ' . $e->getMessage();
            exit;
        }
    }



}
