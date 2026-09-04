<?php
namespace App\Controllers\Main\Settings;

use App\Services\System\CoverService;
use Core\DbPdo;

class CoverController
{
    private CoverService $service;

    public function __construct()
    {
        $this->service = new CoverService(DbPdo::conn());
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $filters = $_POST['filters'] ?? $_GET['filters'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        }

        $data = $this->service->getList($filters);

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    public function apiPublicList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getPublicList();

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_GET['id'] ?? $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->service->getById($id);

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'id'          => $_POST['id'] ?? $_POST['cover_id'] ?? '',
            'year'        => $_POST['year'] ?? '',
            'title'       => $_POST['title'] ?? '',
            'alt'         => $_POST['alt'] ?? '',
            'description' => $_POST['description'] ?? '',
            'is_active'   => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
            'file'        => $_FILES['cover_image'] ?? null,
        ];

        echo json_encode($this->service->save($payload), JSON_UNESCAPED_UNICODE);
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->delete($id), JSON_UNESCAPED_UNICODE);
    }

    public function apiTrashList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getTrashList();

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }


    public function apiRestore()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->restore($id), JSON_UNESCAPED_UNICODE);
    }

    public function apiRestoreBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? $_POST['ids'] ?? [];

        echo json_encode($this->service->restoreBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    public function apiRestoreAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->restoreAll(), JSON_UNESCAPED_UNICODE);
    }

    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->purge($id), JSON_UNESCAPED_UNICODE);
    }

    public function apiPurgeBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? $_POST['ids'] ?? [];

        echo json_encode($this->service->purgeBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    public function apiPurgeAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->purgeAll(), JSON_UNESCAPED_UNICODE);
    }

    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success' => false, 'message' => '순서 변경 데이터가 없습니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    }
}
