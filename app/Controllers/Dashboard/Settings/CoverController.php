<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CoverController.php'
// ??쒕낫???ㅼ젙>湲곗큹?뺣낫愿由?而ㅻ쾭?대?吏 API 而⑦듃濡ㅻ윭
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\System\CoverImageService;

class CoverController
{
    private CoverImageService $service;

    public function __construct()
    {
        $this->service = new CoverImageService(DbPdo::conn());
    }

    /* ============================================================
       紐⑸줉
    ============================================================ */
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $filters = $_POST['filters'] ?? $_GET['filters'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        }

        $data = $this->service->getList($filters);

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?ㅽ뵂紐⑸줉
    ============================================================ */
    public function apiPublicList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getPublicList();

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?④굔議고쉶
    ============================================================ */
    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_GET['id'] ?? $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID ?꾨씫'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->service->getById($id);

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ???
    ============================================================ */
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'id'          => $_POST['id'] ?? $_POST['cover_id'] ?? '',
            'year'        => $_POST['year'] ?? '',
            'title'       => $_POST['title'] ?? '',
            'alt'         => $_POST['alt'] ?? '',
            'description' => $_POST['description'] ?? '',
            'is_active'   => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            'file'        => $_FILES['cover_image'] ?? null,
        ];

        echo json_encode($this->service->save($payload), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?뚰봽????젣
    ============================================================ */
    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);

        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID ?꾨씫'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->delete($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?댁???紐⑸줉
    ============================================================ */
    public function apiTrashList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getTrashList();

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?④굔 蹂듭썝
    ============================================================ */
    public function apiRestore()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID ?꾨씫'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->restore($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?좏깮 蹂듭썝
    ============================================================ */
    public function apiRestoreBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);

        $ids = $input['ids'] ?? $_POST['ids'] ?? [];

        echo json_encode($this->service->restoreBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?꾩껜 蹂듭썝
    ============================================================ */
    public function apiRestoreAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->restoreAll(), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?곴뎄??젣 ?④굔
    ============================================================ */
    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID ?꾨씫'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->purge($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?곴뎄??젣 ?좏깮
    ============================================================ */
    public function apiPurgeBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);

        $ids = $input['ids'] ?? $_POST['ids'] ?? [];

        echo json_encode($this->service->purgeBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?곴뎄??젣 ?꾩껜
    ============================================================ */
    public function apiPurgeAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->purgeAll(), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       ?쒖꽌蹂寃?
    ============================================================ */
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
}
