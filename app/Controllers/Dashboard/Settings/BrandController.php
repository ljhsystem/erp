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
    ============================================================ */
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $filters = [];
    
        if (!empty($_POST['asset_type'])) {
            $filters['asset_type'] = $_POST['asset_type'];
        }
    
        if (isset($_POST['is_active'])) {
            $filters['is_active'] = (int)$_POST['is_active'];
        }
    
        $data = $this->service->getList($filters);
    
        error_log("🔍 apiBrandList 응답: " . json_encode($data));
    
        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }
    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $id = $_POST['id'] ?? '';
    
        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID 누락'
            ]);
            return;
        }
    
        $data = $this->service->getById($id);
    
        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }
    public function apiActiveType(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $assetType = $_POST['asset_type'] ?? '';
    
        if (!$assetType) {
            echo json_encode([
                'success' => false,
                'message' => 'asset_type 누락'
            ]);
            return;
        }
    
        $data = $this->service->getActive($assetType);
    
        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }
    /* ============================================================
   API: 브랜드 자산 업로드
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

        $result = $this->service->save($assetType, $file);

        // 🔥 디버깅 로그 추가
        error_log("📤 apiBrandUpload 응답: " . json_encode($result));

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    /* ============================================================
    API: 브랜드 자산 삭제
  
    ============================================================ */
    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $fileId = $_POST['file_id'] ?? '';
        if (!$fileId) {
            echo json_encode(['success' => false, 'message' => '파일 ID가 누락되었습니다.']);
            return;
        }

        $result = $this->service->purge($fileId);

        // 🔥 디버깅 로그 추가
        error_log("🗑 apiBrandDelete 응답: " . json_encode($result));

        echo json_encode($result);
    }

    /* ============================================================
    API: 브랜드 자산 활성화
    ============================================================ */
    public function apiUpdateStatus()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $id     = $_POST['id'] ?? '';
        $status = isset($_POST['status']) ? (int)$_POST['status'] : null;
    
        if (!$id || $status === null) {
            echo json_encode([
                'success' => false,
                'message' => '필수값 누락'
            ]);
            return;
        }
    
        if ($status === 1) {
            $result = $this->service->activate($id);
        } else {
            $result = $this->service->deactivate($id);
        }
    
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

}
