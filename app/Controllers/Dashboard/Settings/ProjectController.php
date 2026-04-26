<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/ProjectController.php'
// ??쒕낫???ㅼ젙>湲곗큹?뺣낫愿由??꾨줈?앺듃 API 而⑦롤러
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\System\ProjectService;


class ProjectController
{
    private ProjectService $service;

    public function __construct()
    {
        $this->service = new ProjectService(DbPdo::conn());
    }
    // ============================================================
    // API: ?꾨줈?앺듃 紐⑸줉 議고쉶
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

            // ?뵦 嫄곕옒泥섏? ?숈씪: getList ?섎굹留??ъ슜
            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?꾨줈?앺듃 紐⑸줉 議고쉶 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: ?꾨줈?앺듃 ?곸꽭 議고쉶
    // URL: GET /api/settings/base-info/project/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?로?트 ?이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '?꾨줈?앺듃 ?놁쓬'
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
                'message' => '?꾨줈?앺듃 議고쉶 ?ㅽ뙣',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
    * API: ?꾨줈?앺듃 寃???먮룞?꾩꽦
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
                'data'    => [],   // ?뵦 諛섎뱶???덉뼱????
                'message' => '寃???ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    /* ============================================================
    * API: ?꾨줈?앺듃 ???(?좉퇋 + ?섏젙)
    * URL: POST /api/settings/base-info/project/save
    * ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            payload ?앹꽦 (?뵦 ?⑥닚?섍쾶 ?좎?)
            ========================================================= */

            $payload = [

                'id' => $_POST['id'] ?? null,
                'sort_no' => $_POST['sort_no'] ?? null,

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

                /* ?뵦 ??젣 ?뚮옒洹?(Service???) */
                'delete_project_image' => $_POST['delete_project_image'] ?? '0',

                'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
            ];

            /* =========================================================
            ?수?泥댄겕
            ========================================================= */

            if ($payload['project_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '리명? ?수?니??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['contract_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload['contract_date'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '계약?자??YYYY-MM-DD ?뺤떇?댁뼱???⑸땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['start_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload['start_date'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '李⑷났?쇱옄??YYYY-MM-DD ?뺤떇?댁뼱???⑸땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['completion_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload['completion_date'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '공일?는 YYYY-MM-DD ?뺤떇?댁뼱???⑸땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (
                $payload['start_date'] &&
                $payload['completion_date'] &&
                $payload['start_date'] > $payload['completion_date']
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => '공일?는 李⑷났?쇱옄蹂대떎 鍮좊? ???놁뒿?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (
                $payload['initial_contract_amount'] !== null &&
                $payload['initial_contract_amount'] !== '' &&
                !is_numeric($payload['initial_contract_amount'])
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => '理초 계약금액? ?자留??낅젰?????덉뒿?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            ???(?뵦 ?뚯씪 ?ы븿 Service??임)
            ========================================================= */

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES   // ?뵦 諛섎뱶???꾨떖
            );

            /* =========================================================
            ?먮윭 硫붿떆吏 泥섎━ (?요???장)
            ========================================================= */

            if (!$result['success']) {

                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? '?꾨줈?앺듃 ????ㅽ뙣'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            ?뺤긽
            ========================================================= */

            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'sort_no'    => $result['sort_no'] ?? null,
                'message' => '????꾨즺'
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?꾨줈?앺듃 ???以??ㅻ쪟 諛쒖깮'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    // ============================================================
    // API: ?꾨줈?앺듃 ??젣
    // URL: POST /api/settings/base-info/project/delete
    // ============================================================
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?로?트 ?이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->delete($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?꾨줈?앺듃 ??젣 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }




    /* ============================================================
     * API: ?꾨줈?앺듃 ?댁???紐⑸줉
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
                'message' => '?댁???議고쉶 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: ?꾨줈?앺듃 蹂듭썝
    // URL: POST /api/settings/base-info/project/restore
    // ============================================================
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?로?트 ?이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?꾨줈?앺듃 蹂듭썝 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: ?꾨줈?앺듃 ?좏깮 蹂듭썝
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
                    'message' => '蹂듭썝???꾨줈?앺듃 ?꾩씠?붽? ?놁뒿?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?좏깮 蹂듭썝 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: ?로?트 ?체 복원
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
                'message' => '?체 복원 ?패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }




    // ============================================================
    // API: ?꾨줈?앺듃 ?꾩쟾??젣
    // URL: POST /api/settings/base-info/project/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?로?트 ?이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?전?? ?패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: ?꾨줈?앺듃 ?좏깮 ?꾩쟾??젣
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
                    'message' => '??젣???꾨줈?앺듃 ?꾩씠?붽? ?놁뒿?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->purgeBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?택 ?전?? ?패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: ?로?트 ?체 ?전??
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
                'message' => '?체 ?전?? ?패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: ?꾨줈?앺듃 ?쒖꽌 蹂寃?(RowReorder)
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
                    'message' => '蹂寃??곗씠???놁쓬'
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
                'message' => '?쒖꽌 ????ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: ?꾨줈?앺듃 ?묒떇 ?묒? ?ㅼ슫濡쒕뱶
    // URL: GET /api/settings/base-info/project/template
    // ============================================================
    public function apiDownloadTemplate(): void
    {
        try {

            $this->service->downloadMigrationTemplate();

        } catch (\Throwable $e) {

            http_response_code(500);
            echo '?? ?플??운로드 ?패 : ' . $e->getMessage();
            exit;
        }
    }






    // ============================================================
    // API: ?꾨줈?앺듃 ?묒? ?낅줈??
    // URL: POST /api/settings/base-info/project/excel-upload
    // ============================================================
    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '?뚯씪???낅줈?쒕릺吏 ?딆븯?듬땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $file = $_FILES['excel']['tmp_name'];

            $result = $this->service->saveFromMigrationExcelFile($file);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?묒? ?낅줈???ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: ?로?트 ?체 ?? ?운로드
    // URL: GET /api/settings/base-info/project/excel
    // ============================================================
    public function apiDownload(): void
    {
        try {

            $this->service->downloadMigrationExcel();

        } catch (\Throwable $e) {

            http_response_code(500);
            echo '?? ?운로드 ?패 : ' . $e->getMessage();
            exit;
        }
    }


}
