<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/ClientController.php'
// ??쒕낫???ㅼ젙>湲곗큹?뺣낫愿由?嫄곕옒泥?API 而⑦롤러
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\System\ClientService;



class ClientController
{
    private ClientService $service;

    public function __construct()
    {
        $this->service = new ClientService(DbPdo::conn());

    }

    // ============================================================
    // API: 嫄곕옒泥?紐⑸줉 議고쉶
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

            // ?뵦 臾댁“嫄?getList ?섎굹留??ъ슜
            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥?紐⑸줉 議고쉶 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 嫄곕옒泥??곸꽭 議고쉶
    // URL: GET /api/settings/base-info/client/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥??이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '嫄곕옒泥??놁쓬'
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
                'message' => '嫄곕옒泥?議고쉶 ?ㅽ뙣',
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
            $options = [];

            if (isset($_GET['client_type'])) {
                $options['client_type'] = trim((string)$_GET['client_type']);
            }

            if (isset($_GET['is_active'])) {
                $options['is_active'] = (int)$_GET['is_active'];
            }

            $rows = $this->service->searchPicker($keyword, $options);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'data'    => [],
                'message' => '寃???ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 嫄곕옒泥????(?좉퇋 + ?섏젙)
    // URL: POST /api/settings/base-info/client/save
    // permission:
    // controller: ClientController@apiSave
    // ============================================================
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            payload ?앹꽦
            ========================================================= */

            $payload = [

                'id' => $_POST['id'] ?? null,
                'sort_no' => $_POST['sort_no'] ?? null,

                'client_name' => trim($_POST['client_name'] ?? ''),
                'company_name' => $_POST['company_name'] ?? null,

                'registration_date' => $_POST['registration_date'] ?? null,

                'business_number' => (
                    isset($_POST['business_number']) &&
                    trim($_POST['business_number']) !== ''
                )
                    ? trim($_POST['business_number'])
                    : null,
                'rrn' => (
                    isset($_POST['rrn']) &&
                    trim($_POST['rrn']) !== ''
                )
                    ? trim($_POST['rrn'])
                    : null,

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

                /* ?뵦 ??젣 ?뚮옒洹?(Service??달) */
                'delete_business_certificate' => $_POST['delete_business_certificate'] ?? '0',
                'delete_rrn_image'            => $_POST['delete_rrn_image'] ?? '0',
                'delete_bank_file'            => $_POST['delete_bank_file'] ?? '0',

                'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
            ];

            /* =========================================================
            ?수?泥댄겕
            ========================================================= */

            if ($payload['client_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '嫄곕옒泥명? ?수?니??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            ???(?뵦 ?뚯씪 ?ы븿 Service??임)
            ========================================================= */

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES   // ?뵦 ?듭떖
            );

            /* =========================================================
            ?먮윭 硫붿떆吏 蹂??
            ========================================================= */

            if (!$result['success']) {

                $msg = $result['message'] ?? '';

                if (
                    str_contains($msg, 'Duplicate entry') &&
                    str_contains($msg, 'uq_business_number')
                ) {
                    echo json_encode([
                        'success' => false,
                        'message' => '?대? ?깅줉???ъ뾽?먮벑濡앸쾲?몄엯?덈떎.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                echo json_encode([
                    'success' => false,
                    'message' => $msg ?: '嫄곕옒泥?????ㅽ뙣'
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

            $msg = $e->getMessage();

            if (
                str_contains($msg, 'Duplicate entry') &&
                str_contains($msg, 'uq_business_number')
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => '?대? ?깅줉???ъ뾽?먮벑濡앸쾲?몄엯?덈떎.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥????以??ㅻ쪟 諛쒖깮'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 嫄곕옒泥???젣
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
                'message' => '嫄곕옒泥??이???락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete($id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥???젣 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    // ============================================================
    // API: 嫄곕옒泥??댁???紐⑸줉
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
                'message' => '?댁???議고쉶 ?ㅽ뙣',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 嫄곕옒泥?蹂듭썝
    // URL: POST /api/settings/base-info/client/restore
    // ============================================================
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥??이???락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥?蹂듭썝 ?ㅽ뙣',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }






    // ============================================================
    // API: 嫄곕옒泥??좏깮 蹂듭썝
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
                    'message' => '蹂듭썝??嫄곕옒泥??꾩씠?붽? ?놁뒿?덈떎.'
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
    // API: 嫄곕옒泥??전??
    // URL: POST /api/settings/base-info/client/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '嫄곕옒泥??이???락'
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
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 嫄곕옒泥??택 ?전??
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
                    'message' => '??젣??嫄곕옒泥??꾩씠?붽? ?놁뒿?덈떎.'
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
    // API: 嫄곕옒泥??체 ?전??
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
                'message' => '?체 ?전?? ?패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }





    // ============================================================
    // API: 嫄곕옒泥??쒖꽌 蹂寃?(RowReorder)
    // URL: POST /api/settings/base-info/client/reorder
    // permission:
    // controller: ClientController@apiReorder
    // ============================================================
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success'=>false,'message'=>'蹂寃??곗씠???놁쓬']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success'=>true]);
    }



    // ============================================================
    // API: 嫄곕옒泥??식 ?? ?운로드
    // URL: GET /api/settings/base-info/clients/template
    // permission:
    // controller: ClientController@apiDownloadTemplate
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
    // API: 嫄곕옒泥??묒? ?낅줈??
    // URL: POST /api/settings/base-info/client/excel-upload
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
    // API: 嫄곕옒泥??체 ?? ?운로드
    // URL: GET /api/settings/base-info/clients/excel
    // permission:
    // controller: ClientController@apidownload
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
