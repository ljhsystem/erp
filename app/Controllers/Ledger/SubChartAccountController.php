<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Ledger/SubChartAccountController.php'
// 설명:
//  - 회계 계정과목 관리 보조 컨트롤러
namespace App\Controllers\Ledger;

use Core\Session;
use Core\DbPdo;
use App\Services\Ledger\SubChartAccountService;

class SubChartAccountController
{
    private SubChartAccountService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new SubChartAccountService(DbPdo::conn());
    }

    /* ================================================
     * 보조계정 목록
     * GET /api/ledger/sub-account/list
     * ================================================ */
    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $accountId = $_GET['account_id'] ?? null;

            if (!$accountId) {

                echo json_encode([
                    'success' => false,
                    'message' => 'account_id가 없습니다.'
                ]);

                exit;
            }

            $rows = $this->service->getByAccountId($accountId);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    /* ================================================
     * 보조계정 저장
     * POST /api/ledger/sub-account/save
     * ================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $userId = $_SESSION['user_id'] ?? 'system';

            $payload = [
                'account_id' => $_POST['account_id'] ?? null,
                'sub_name'   => $_POST['sub_name'] ?? null,
                'note'       => $_POST['note'] ?? null,
                'memo'       => $_POST['memo'] ?? null,
                'created_by' => $userId
            ];

            $result = $this->service->create($payload);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }
    public function apiUpdate(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $id = $_POST['id'] ?? null;
    
            if(!$id){
                echo json_encode([
                    'success'=>false,
                    'message'=>'id 없음'
                ]);
                exit;
            }
    
            $payload = [
                'sub_name' => $_POST['sub_name'] ?? null,
                'note'     => $_POST['note'] ?? null,
                'memo'     => $_POST['memo'] ?? null
            ];
    
            $result = $this->service->update($id, $payload);
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success'=>false,
                'message'=>$e->getMessage()
            ]);
        }
    
        exit;
    }
    /* ================================================
     * 보조계정 삭제
     * POST /api/ledger/sub-account/delete
     * ================================================ */
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $id = $_POST['id'] ?? null;
    
            if (!$id) {
    
                echo json_encode([
                    'success' => false,
                    'message' => 'id가 없습니다.'
                ]);
    
                exit;
            }
    
            /* 🔥 userId 제거 */
            $result = $this->service->delete($id);
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        exit;
    }
}