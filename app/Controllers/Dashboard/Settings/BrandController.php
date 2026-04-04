<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/BrandController.php'
// 대시보드>설정>기초정보관리>브랜드 API 컨트롤러
namespace App\Controllers\Dashboard\Settings; //네임스페이스

use Core\Session;
use Core\DbPdo; //네임스페이스 경로를 짧게 쓰기 위한 별칭 정의
use App\Services\System\BrandService;

class BrandController //기능묶음 단위
{
    private BrandService $service;

    public function __construct() //생성자
    {
        Session::requireAuth();
        $this->service = new BrandService(DbPdo::conn()); //의존성주입
    }

    /* ============================================================
    API: 브랜드 자산 목록 조회
    URL: POST /api/settings/base-info/brand/list
    permission: api.settings.baseinfo.brand.list
    controller: BrandController@apiBrandList
    ============================================================ */
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $type = $_POST['asset_type'] ?? null; // 🔥 asset_type이 없으면 모든 타입 조회
        $data = $type ? $this->service->getByType($type) : $this->service->getList();

        // 🔥 디버깅 로그 추가
        error_log("🔍 apiBrandList 응답: " . json_encode($data));

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    /* ============================================================
    API: 브랜드 자산 조회 (타입별)
    URL: POST /api/settings/base-info/brand/get
    permission: api.settings.baseinfo.brand.view
    controller: BrandController@apiBrandGet
    ============================================================ */
    public function apiSearch()
    {
        header('Content-Type: application/json; charset=utf-8');

        $type = $_POST['asset_type'] ?? '';
        if (!$type) {
            echo json_encode(['success' => false, 'message' => '자산 타입이 누락되었습니다.']);
            return;
        }

        $data = $this->service->getActive($type);

        // 🔥 디버깅 로그 추가
        error_log("🔍 apiBrandGet 응답: " . json_encode($data));

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }



    /* ============================================================
   API: 브랜드 자산 업로드
   URL: POST /api/settings/base-info/brand/upload
   permission: api.settings.baseinfo.brand.save
   controller: BrandController@apiBrandUpload
   ============================================================ */
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => '인증 오류']);
            return;
        }

        $assetType = $_POST['asset_type'] ?? '';
        $file      = $_FILES['file'] ?? null;

        if (!$assetType || !$file) {
            echo json_encode([
                'success' => false,
                'message' => '필수 값 누락'
            ]);
            return;
        }

        $result = $this->service->save($assetType, $file, $userId);

        // 🔥 디버깅 로그 추가
        error_log("📤 apiBrandUpload 응답: " . json_encode($result));

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }



    /* ============================================================
    API: 브랜드 자산 활성화
    URL: POST /api/settings/base-info/brand/activate
    permission: api.settings.baseinfo.brand.activate
    controller: BrandController@apiBrandActivate
    ============================================================ */
    public function apiActivate()
    {
        header('Content-Type: application/json; charset=utf-8');

        $fileId = $_POST['file_id'] ?? '';
        if (!$fileId) {
            echo json_encode(['success' => false, 'message' => '파일 ID가 누락되었습니다.']);
            return;
        }

        $result = $this->service->activate($fileId, $_SESSION['user']['id']);

        if ($result['success']) {
            // 활성화된 파일 정보 반환
            $file = $this->service->getById($fileId);
            $result['data'] = [
                'asset_type' => $file['asset_type'],
                'url'        => $file['url'],
            ];
        }

        // 🔥 디버깅 로그 추가
        error_log("🔄 apiBrandActivate 응답: " . json_encode($result));

        echo json_encode($result);
    }




    /* ============================================================
    API: 브랜드 자산 삭제
    URL: POST /api/settings/base-info/brand/delete
    permission: api.settings.baseinfo.brand.delete
    controller: BrandController@apiBrandDelete
    ============================================================ */
    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $fileId = $_POST['file_id'] ?? '';
        if (!$fileId) {
            echo json_encode(['success' => false, 'message' => '파일 ID가 누락되었습니다.']);
            return;
        }

        $result = $this->service->delete($fileId);

        // 🔥 디버깅 로그 추가
        error_log("🗑 apiBrandDelete 응답: " . json_encode($result));

        echo json_encode($result);
    }
}
