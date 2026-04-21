<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CoverController.php'
// 대시보드>설정>기초정보관리>커버이미지 API 컨트롤러
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
       목록
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
       오픈목록
    ============================================================ */
    public function apiPublicList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getPublicList();

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       단건조회
    ============================================================ */
    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_GET['id'] ?? $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID 누락'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->service->getById($id);

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       저장
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
            'file'        => $_FILES['cover_image'] ?? null,
        ];

        echo json_encode($this->service->save($payload), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       소프트 삭제
    ============================================================ */
    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $input = json_decode(file_get_contents('php://input'), true);
    
        $id = $_POST['id'] ?? $input['id'] ?? null;
    
        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID 누락'], JSON_UNESCAPED_UNICODE);
            return;
        }
    
        echo json_encode($this->service->delete($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       휴지통 목록
    ============================================================ */
    public function apiTrashList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getTrashList();

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       단건 복원
    ============================================================ */
    public function apiRestore()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID 누락'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->restore($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       선택 복원
    ============================================================ */
    public function apiRestoreBulk()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $input = json_decode(file_get_contents('php://input'), true);
    
        $ids = $input['ids'] ?? $_POST['ids'] ?? [];
    
        echo json_encode($this->service->restoreBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       전체 복원
    ============================================================ */
    public function apiRestoreAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->restoreAll(), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       영구삭제 단건
    ============================================================ */
    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            echo json_encode(['success'=>false,'message'=>'ID 누락'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->service->purge($id), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       영구삭제 선택
    ============================================================ */
    public function apiPurgeBulk()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $input = json_decode(file_get_contents('php://input'), true);
    
        $ids = $input['ids'] ?? $_POST['ids'] ?? [];
    
        echo json_encode($this->service->purgeBulk($ids), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       영구삭제 전체
    ============================================================ */
    public function apiPurgeAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->service->purgeAll(), JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       순서변경
    ============================================================ */
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
}
