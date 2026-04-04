<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Api/ExternalIntegrationController.php'
namespace App\Controllers\Api;

use Core\Session;
use App\Services\Integration\ExternalIntegrationService;


class ExternalIntegrationController
{
    private ExternalIntegrationService $integrationService;

    public function __construct()
    {
        $this->integrationService = new ExternalIntegrationService();
    }

    public function apiBizStatus()
    {
        Session::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents("php://input"), true);

        $bizNo = preg_replace('/[^0-9]/', '', $input['business_number'] ?? '');

        if (!$bizNo) {
            echo json_encode([
                "success" => false,
                "message" => "사업자번호 없음"
            ]);
            exit;
        }

        if (strlen($bizNo) !== 10) {
            echo json_encode([
                "success" => false,
                "message" => "사업자번호 형식 오류"
            ]);
            exit;
        }

        try {

            $result = $this->integrationService->getBizStatus($bizNo);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '사업자 상태 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}