<?php

namespace App\Models\Funds;

use Core\Helpers\UuidHelper;
use PDO;

class PaymentScheduleHistoryModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        string $paymentScheduleId,
        string $actionType,
        ?array $before,
        ?array $after,
        ?string $reason,
        string $actor
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_payment_schedule_histories (
                id, payment_schedule_id, action_type, before_value,
                after_value, reason, actor_id, acted_at
            )
            VALUES (
                :id, :payment_schedule_id, :action_type, :before_value,
                :after_value, :reason, :actor_id, NOW()
            )
        ");
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':payment_schedule_id' => $paymentScheduleId,
            ':action_type' => $actionType,
            ':before_value' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':after_value' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':reason' => $reason,
            ':actor_id' => $actor,
        ]);
    }

    public function listBySchedule(string $paymentScheduleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, payment_schedule_id, action_type, before_value,
                   after_value, reason, actor_id, acted_at
            FROM ledger_payment_schedule_histories
            WHERE payment_schedule_id = :payment_schedule_id
            ORDER BY acted_at DESC, id DESC
        ");
        $stmt->execute([':payment_schedule_id' => $paymentScheduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
