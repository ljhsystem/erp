<?php

namespace App\Services\System;

use App\Models\Approval\ApprovalInboxModel;
use App\Models\Auth\UserModel;
use App\Models\System\NotificationModel;
use Core\Helpers\UuidHelper;
use PDO;

class NotificationService
{
    private NotificationModel $notificationModel;
    private ApprovalInboxModel $approvalInboxModel;
    private UserModel $userModel;

    public function __construct(PDO $pdo)
    {
        $this->notificationModel = new NotificationModel($pdo);
        $this->approvalInboxModel = new ApprovalInboxModel($pdo);
        $this->userModel = new UserModel($pdo);
    }

    public function getNavigationFeed(string $userId, int $storedLimit = 20): array
    {
        $stored = array_map(static function (array $row): array {
            $row['notification_kind'] = 'stored';
            $row['action_url'] = (
                (string) ($row['ref_table'] ?? '') === 'user_approval_requests'
                && trim((string) ($row['ref_id'] ?? '')) !== ''
            )
                ? '/approval/status?box=submitted&request_id=' . rawurlencode((string) $row['ref_id'])
                : null;
            return $row;
        }, $this->getNotifications($userId, $storedLimit));

        $approvals = array_map(static function (array $row): array {
            $documentTypeName = match ((string) $row['document_type']) {
                'PERSONAL_EXPENSE' => '개인경비',
                'EMPLOYMENT_CONTRACT' => '근로계약',
                'PERSONNEL_ACTION' => '인사발령',
                'LEAVE_REQUEST' => '휴가신청',
                default => (string) $row['document_type'],
            };
            return [
                'id' => 'approval:' . (string) $row['step_id'],
                'notification_kind' => 'approval_actionable',
                'action_type' => 'APPROVAL_REQUEST',
                'ref_table' => 'user_approval_requests',
                'ref_id' => (string) $row['request_id'],
                'title' => '[' . $documentTypeName . ' 결재요청]',
                'message' => sprintf(
                    '%s · #%s · %s · %s원 · %s',
                    (string) $row['requester_name'],
                    (string) $row['document_no'],
                    (string) $row['title'],
                    number_format((float) $row['total_amount']),
                    (string) $row['current_step_name']
                ),
                'is_read' => 0,
                'created_at' => $row['arrived_at'],
                'action_url' => '/approval/status?box=actionable&request_id=' . rawurlencode((string) $row['request_id']),
            ];
        }, $this->approvalInboxModel->actionableNotifications($userId));

        $notifications = [...$approvals, ...$stored];
        usort($notifications, static fn(array $left, array $right): int =>
            strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''))
        );
        $storedUnreadCount = count(array_filter(
            $stored,
            static fn(array $row): bool => (int) ($row['is_read'] ?? 0) === 0
        ));

        return [
            'notifications' => $notifications,
            'approval_pending_count' => count($approvals),
            'unread_count' => $storedUnreadCount + count($approvals),
        ];
    }

    public function getNotifications(string $userId, int $limit = 20): array
    {
        return $this->notificationModel->findByRecipient($userId, max(1, min($limit, 50)));
    }

    public function markAsRead(string $id, string $userId): bool
    {
        return $this->notificationModel->markAsRead($id, $userId);
    }

    public function markAllAsRead(string $userId): bool
    {
        return $this->notificationModel->markAllAsRead($userId);
    }

    public function createNotification(array $data): bool
    {
        $recipientUserId = trim((string) ($data['recipient_user_id'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $actionType = trim((string) ($data['action_type'] ?? ''));

        if ($recipientUserId === '' || $title === '' || $message === '' || $actionType === '') {
            return false;
        }

        return $this->notificationModel->insert([
            ':id' => $data['id'] ?? UuidHelper::generate(),
            ':recipient_user_id' => $recipientUserId,
            ':actor_user_id' => $this->nullableString($data['actor_user_id'] ?? null),
            ':action_type' => $actionType,
            ':ref_table' => $this->nullableString($data['ref_table'] ?? null),
            ':ref_id' => $this->nullableString($data['ref_id'] ?? null),
            ':title' => $title,
            ':message' => $message,
        ]);
    }

    public function getAdminUserIds(): array
    {
        return $this->userModel->getActiveAdminUserIds();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
