<?php

namespace App\Controllers\System;

use App\Services\Auth\AuthSessionService;
use App\Services\System\NotificationService;
use Core\DbPdo;
use PDO;

class NotificationController
{
    private NotificationService $service;
    private AuthSessionService $authSession;

    public function __construct(?PDO $pdo = null)
    {
        $this->service = new NotificationService($pdo ?? DbPdo::conn());
        $this->authSession = new AuthSessionService();
    }

    public function apiList(): void
    {
        $this->jsonResponse(function (): array {
            $userId = $this->currentUserId();
            $notifications = $this->service->getNotifications($userId, 20);
            $unreadCount = count(array_filter(
                $notifications,
                static fn(array $row): bool => (int) ($row['is_read'] ?? 0) === 0
            ));

            return [
                'success' => true,
                'data' => $notifications,
                'unread_count' => $unreadCount,
            ];
        });
    }

    public function apiRead(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestValue('id');
            if ($id === '') {
                throw new \RuntimeException('알림 ID가 없습니다.');
            }

            $this->service->markAsRead($id, $this->currentUserId());

            return [
                'success' => true,
                'message' => '읽음 처리되었습니다.',
            ];
        });
    }

    public function apiReadAll(): void
    {
        $this->jsonResponse(function (): array {
            $this->service->markAllAsRead($this->currentUserId());

            return [
                'success' => true,
                'message' => '모든 알림을 읽음 처리했습니다.',
            ];
        });
    }

    private function currentUserId(): string
    {
        $userId = $this->authSession->getCurrentUserId();
        if (!$userId) {
            throw new \RuntimeException('로그인 정보가 없습니다.');
        }

        return $userId;
    }

    private function requestValue(string $key): string
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $json = [];
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input') ?: '[]', true);
            $json = is_array($json) ? $json : [];
        }

        return trim((string) ($json[$key] ?? $_POST[$key] ?? $_GET[$key] ?? ''));
    }

    private function jsonResponse(callable $callback): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode($callback(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        exit;
    }
}
