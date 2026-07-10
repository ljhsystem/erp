<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\System\UserSettingService;
use Core\DbPdo;

class UserSettingController
{
    private UserSettingService $service;

    public function __construct()
    {
        $this->service = new UserSettingService(DbPdo::conn());
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $pageKey = trim((string) ($_GET['page_key'] ?? $_POST['page_key'] ?? ''));
            $settingType = trim((string) ($_GET['setting_type'] ?? $_POST['setting_type'] ?? ''));

            echo json_encode([
                'success' => true,
                'data' => $this->service->detail($pageKey, $settingType),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '조회 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = $this->requestPayload();
            $pageKey = trim((string) ($payload['page_key'] ?? ''));
            $settingType = trim((string) ($payload['setting_type'] ?? ''));
            $description = trim((string) ($payload['description'] ?? ''));
            $settingsJson = $payload['settings_json'] ?? [];

            echo json_encode([
                'success' => true,
                'data' => $this->service->save(
                    $pageKey,
                    $settingType,
                    $description,
                    is_array($settingsJson) ? $settingsJson : []
                ),
                'message' => '저장되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = $this->requestPayload();
            $pageKey = trim((string) ($payload['page_key'] ?? $_POST['page_key'] ?? ''));
            $settingType = trim((string) ($payload['setting_type'] ?? $_POST['setting_type'] ?? ''));

            echo json_encode([
                'success' => true,
                'data' => $this->service->delete($pageKey, $settingType),
                'message' => '삭제되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function requestPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST;
    }
}
