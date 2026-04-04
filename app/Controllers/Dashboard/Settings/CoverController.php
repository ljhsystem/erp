<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CoverController.php'
// 대시보드>설정>기초정보관리>커버이미지 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\CoverImageService;

class CoverController
{
    private CoverImageService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new CoverImageService(DbPdo::conn());
    }



    /* ============================================================
       API: 커버 이미지 목록 조회
       URL: POST /api/settings/base-info/cover/list
       permission: api.settings.baseinfo.cover.list
       controller: CoverController@apiCoverList
       ============================================================ */
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $rawFilters = $_POST['filters'] ?? $_GET['filters'] ?? '[]';

        if (is_string($rawFilters)) {
            $filters = json_decode($rawFilters, true);
        } else {
            $filters = $rawFilters;
        }

        if (!is_array($filters)) {
            $filters = [];
        }

        $data = $this->service->getList($filters);

        echo json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       API: 커버 이미지 저장 (신규/수정)
       URL: POST /api/settings/base-info/cover/save
       permission: api.settings.baseinfo.cover.save
       controller: CoverController@apiCoverSave
       ============================================================ */
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'cover_id'    => $_POST['cover_id'] ?? '',
            'year'        => $_POST['year'] ?? '',
            'title'       => $_POST['title'] ?? '',
            'alt'         => $_POST['alt'] ?? '',
            'description' => $_POST['description'] ?? '',
            'file'        => $_FILES['cover_image'] ?? null,
            'created_by'  => $_SESSION['user']['id'] ?? null,
            'updated_by'  => $_SESSION['user']['id'] ?? null,
        ];

        $result = $this->service->save($payload);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
       API: 커버 이미지 삭제
       URL: POST /api/settings/base-info/cover/delete
       permission: api.settings.baseinfo.cover.delete
       controller: CoverController@apiCoverDelete
       ============================================================ */
    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_POST['cover_id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '커버 이미지 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service->delete($id);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
        API: 커버 이미지 휴지통 목록 조회
        ============================================================ */
    public function apiTrashList()
    {
        Session::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getTrashList();

        echo json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
        API: 커버 이미지 복원
        ============================================================ */
    public function apiRestore()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_POST['cover_id'] ?? $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID 누락'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service->restore($id);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    /* ============================================================
        API: 커버 이미지 하드삭제
        ============================================================ */
    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_POST['cover_id'] ?? null;

        if ($id === '') {
            echo json_encode([
                'success' => false,
                'message' => 'cover_id가 누락되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service->purge($id);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }




    public function apiRestoreBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        $ids = $json['ids'] ?? [];

        $result = $this->service->restoreBulk($ids);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    public function apiPurgeBulk()
    {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        $ids = $json['ids'] ?? [];

        $result = $this->service->purgeBulk($ids);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function apiPurgeAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $result = $this->service->purgeAll();

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '전체 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }




    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $payload = json_decode(file_get_contents('php://input'), true);
            $changes = $payload['changes'] ?? [];

            if (!$changes) {
                echo json_encode([
                    'success' => false,
                    'message' => '변경 데이터 없음'
                ]);
                return;
            }

            // 🔥 한번에 reorder 처리
            $this->service->reorder($changes);

            echo json_encode([
                'success' => true
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ]);
        }

        exit;
    }
}
