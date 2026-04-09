<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/ProjectController.php'
// 대시보드>설정>기초정보관리>프로젝트 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\ProjectService;


class ProjectController
{
    private ProjectService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new ProjectService(DbPdo::conn());
    }
    // ============================================================
    // API: 프로젝트 목록 조회
    // URL: GET /api/settings/base-info/project/list
    // controller: ProjectController@apiList
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

            // 🔥 거래처와 동일: getList 하나만 사용
            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '프로젝트 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 프로젝트 상세 조회
    // URL: GET /api/settings/base-info/project/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '프로젝트 없음'
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
                'message' => '프로젝트 조회 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
    * API: 프로젝트 검색 자동완성
    * URL: GET /api/settings/base-info/project/search?q=
    * ============================================================ */
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
                'data'    => [],   // 🔥 반드시 있어야 함
                'message' => '검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    /* ============================================================
    * API: 프로젝트 저장 (신규 + 수정)
    * URL: POST /api/settings/base-info/project/save
    * ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            payload 생성 (🔥 단순하게 유지)
            ========================================================= */

            $payload = [

                'id' => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,

                'project_name' => trim($_POST['project_name'] ?? ''),

                'client_id' => $_POST['client_id'] ?? null,
                'employee_id' => $_POST['employee_id'] ?? null,

                'site_agent' => $_POST['site_agent'] ?? null,
                'contract_type' => $_POST['contract_type'] ?? null,
                'director' => $_POST['director'] ?? null,
                'manager' => $_POST['manager'] ?? null,

                'business_type' => $_POST['business_type'] ?? null,
                'housing_type' => $_POST['housing_type'] ?? null,

                'construction_name' => $_POST['construction_name'] ?? null,

                'site_region_city' => $_POST['site_region_city'] ?? null,
                'site_region_district' => $_POST['site_region_district'] ?? null,
                'site_region_address' => $_POST['site_region_address'] ?? null,
                'site_region_address_detail' => $_POST['site_region_address_detail'] ?? null,

                'work_type' => $_POST['work_type'] ?? null,
                'work_subtype' => $_POST['work_subtype'] ?? null,
                'work_detail_type' => $_POST['work_detail_type'] ?? null,
                'contract_work_type' => $_POST['contract_work_type'] ?? null,

                'bid_type' => $_POST['bid_type'] ?? null,

                'client_name' => $_POST['client_name'] ?? null,
                'client_type' => $_POST['client_type'] ?? null,

                'permit_agency' => $_POST['permit_agency'] ?? null,
                'permit_date' => $_POST['permit_date'] ?? null,
                'contract_date' => $_POST['contract_date'] ?? null,
                'start_date' => $_POST['start_date'] ?? null,
                'completion_date' => $_POST['completion_date'] ?? null,
                'bid_notice_date' => $_POST['bid_notice_date'] ?? null,

                'initial_contract_amount' => $_POST['initial_contract_amount'] ?? null,

                'authorized_company_seal' => $_POST['authorized_company_seal'] ?? null,

                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,

                /* 🔥 삭제 플래그 (Service로 넘김) */
                'delete_project_image' => $_POST['delete_project_image'] ?? '0',

                'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
            ];

            /* =========================================================
            필수값 체크
            ========================================================= */

            if ($payload['project_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '관리명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            저장 (🔥 파일 포함 Service로 위임)
            ========================================================= */

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES   // 🔥 반드시 전달
            );

            /* =========================================================
            에러 메시지 처리 (필요시 확장)
            ========================================================= */

            if (!$result['success']) {

                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? '프로젝트 저장 실패'
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

            echo json_encode([
                'success' => false,
                'message' => '프로젝트 저장 중 오류 발생'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    // ============================================================
    // API: 프로젝트 삭제
    // URL: POST /api/settings/base-info/project/delete
    // ============================================================
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->delete($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '프로젝트 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }




    /* ============================================================
     * API: 프로젝트 휴지통 목록
     * URL: GET /api/settings/base-info/project/trash
     * ============================================================ */
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
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 프로젝트 복원
    // URL: POST /api/settings/base-info/project/restore
    // ============================================================
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '프로젝트 복원 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 프로젝트 선택 복원
    // URL: POST /api/settings/base-info/project/restore-bulk
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
                    'message' => '복원할 프로젝트 아이디가 없습니다.'
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


    // ============================================================
    // API: 프로젝트 전체 복원
    // URL: POST /api/settings/base-info/project/restore-all
    // ============================================================
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
    // API: 프로젝트 완전삭제
    // URL: POST /api/settings/base-info/project/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 아이디 누락'
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



    // ============================================================
    // API: 프로젝트 선택 완전삭제
    // URL: POST /api/settings/base-info/project/purge-bulk
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
                    'message' => '삭제할 프로젝트 아이디가 없습니다.'
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
    // API: 프로젝트 전체 완전삭제
    // URL: POST /api/settings/base-info/project/purge-all
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
    // API: 프로젝트 순서 변경 (RowReorder)
    // URL: POST /api/settings/base-info/project/reorder
    // ============================================================
    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $input = json_decode(file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];

            if (empty($changes)) {
                echo json_encode([
                    'success' => false,
                    'message' => '변경 데이터 없음'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->service->reorder($changes);

            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 프로젝트 양식 엑셀 다운로드
    // URL: GET /api/settings/base-info/project/template
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
    // API: 프로젝트 엑셀 업로드
    // URL: POST /api/settings/base-info/project/excel-upload
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
    // API: 프로젝트 전체 엑셀 다운로드
    // URL: GET /api/settings/base-info/project/excel
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