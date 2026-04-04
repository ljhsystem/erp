<?php
// 경로: PROJECT_ROOT . '/app/Controllers/User/ExternalAccountController.php'
namespace App\Controllers\User;

use Core\Session;
use Core\DbPdo;
use App\Services\User\ExternalAccountService;

class ExternalAccountController
{
    private ExternalAccountService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new ExternalAccountService(DbPdo::conn());
    }

    // ============================================================
    // API: 내 외부 서비스 계정 전체 목록
    // URL: GET /api/user/external-accounts
    // permission: api.user.external_accounts.view
    // controller: ExternalAccountController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = $this->service->getMyAccounts();

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 외부 서비스 계정 단일 조회
    // URL: GET /api/user/external-accounts/get?provider=xxx
    // permission: api.user.external_accounts.view
    // controller: ExternalAccountController@apiGet
    // ============================================================
    public function apiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        $serviceKey = $_GET['service_key']
        ?? $_GET['provider']
        ?? '';
    
        if ($serviceKey === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'service_key 값이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $data = $this->service->getMyAccount($serviceKey);    

        /* 🔥 추가 시작 */
        try {
            $this->service->verifyConnection($serviceKey);
        } catch (\Throwable $e) {
            // 실패해도 페이지는 열려야 함
        }
        /* 🔥 추가 끝 */

        echo json_encode([
            'success' => true,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 외부 서비스 계정 저장 / 수정
    // URL: POST /api/user/external-accounts/save
    // permission: api.user.external_accounts.edit
    // controller: ExternalAccountController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw   = file_get_contents('php://input');
            $input = json_decode($raw, true);
            
            // ✅ 핵심: service_key 우선, provider는 호환용
            $serviceKey = $input['service_key']
                ?? $input['provider']
                ?? null;
            
            if (!$serviceKey) {
                throw new \RuntimeException('service_key 값이 필요합니다.');
            }
            
            // 내부 로직에서는 제거
            unset($input['service_key'], $input['provider']);
            
            $result = $this->service->saveMyAccount($serviceKey, $input);
            /* 🔥 추가 시작 */
            try {
                $this->service->verifyConnection($serviceKey);
            } catch (\Throwable $e) {
                // 여기서 죽이면 안됨 (저장은 이미 성공했기 때문)
            }
            /* 🔥 추가 끝 */
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);            

        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: 외부 서비스 연결 해제
    // URL: POST /api/user/external-accounts/delete
    // permission: api.user.external_accounts.delete
    // controller: ExternalAccountController@apiDelete
    // ============================================================
    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $serviceKey = $input['service_key']
                ?? $input['provider']
                ?? null;
            
            if (!$serviceKey) {
                throw new \RuntimeException('service_key 값이 필요합니다.');
            }
            
            $result = $this->service->deleteMyAccount($serviceKey);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);            
    
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    
    

}
