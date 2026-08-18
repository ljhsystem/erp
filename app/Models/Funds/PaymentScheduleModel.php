<?php

namespace App\Models\Funds;

use Core\Helpers\UuidHelper;
use PDO;

class PaymentScheduleModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data, string $actor): string
    {
        $id = UuidHelper::generate();
        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_payment_schedules (
                id, sort_no, source_type, source_id, source_line_key,
                payment_due_date, scheduled_amount, client_id, project_id,
                assignee_id, payment_bank_account_id, memo, created_by, created_at
            )
            VALUES (
                :id,
                COALESCE((SELECT MAX(existing.sort_no) + 1 FROM ledger_payment_schedules existing), 1),
                :source_type, :source_id, :source_line_key,
                :payment_due_date, :scheduled_amount, :client_id, :project_id,
                :assignee_id, :payment_bank_account_id, :memo, :created_by, NOW()
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':source_type' => $data['source_type'],
            ':source_id' => $data['source_id'],
            ':source_line_key' => $data['source_line_key'],
            ':payment_due_date' => $data['payment_due_date'],
            ':scheduled_amount' => $data['scheduled_amount'],
            ':client_id' => $data['client_id'] ?: null,
            ':project_id' => $data['project_id'] ?: null,
            ':assignee_id' => $data['assignee_id'] ?: null,
            ':payment_bank_account_id' => $data['payment_bank_account_id'] ?: null,
            ':memo' => $data['memo'] ?: null,
            ':created_by' => $actor,
        ]);
        return $id;
    }

    public function createFromVoucherLine(array $data, string $actor): string
    {
        $id = UuidHelper::generate();
        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_payment_schedules (
                id, sort_no, source_type, source_id, source_line_key,
                payment_due_date, scheduled_amount, obligation_lifecycle_status,
                client_id, project_id, assignee_id, payment_bank_account_id,
                memo, created_by, created_at
            )
            VALUES (
                :id,
                COALESCE((SELECT MAX(existing.sort_no) + 1 FROM ledger_payment_schedules existing), 1),
                'VOUCHER_LINE', :voucher_id, :voucher_line_id,
                NULL, :scheduled_amount, 'ACTIVE',
                :client_id, :project_id, :assignee_id, NULL,
                :memo, :created_by, NOW()
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':voucher_id' => $data['voucher_id'],
            ':voucher_line_id' => $data['voucher_line_id'],
            ':scheduled_amount' => $data['scheduled_amount'],
            ':client_id' => $data['client_id'] ?: null,
            ':project_id' => $data['project_id'] ?: null,
            ':assignee_id' => $data['assignee_id'] ?: null,
            ':memo' => $data['memo'] ?: null,
            ':created_by' => $actor,
        ]);

        return $id;
    }

    public function existsByVoucherLine(string $voucherId, string $voucherLineId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_payment_schedules
            WHERE source_type = 'VOUCHER_LINE'
              AND source_id = :voucher_id
              AND source_line_key = :voucher_line_id
            LIMIT 1
        ");
        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':voucher_line_id' => $voucherLineId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function lockBySourceVoucher(string $voucherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_payment_schedules
            WHERE source_type = 'VOUCHER_LINE'
              AND source_id = :voucher_id
              AND deleted_at IS NULL
            FOR UPDATE
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setLifecycle(
        string $id,
        string $status,
        string $reason,
        string $actor
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_payment_schedules
            SET obligation_lifecycle_status = :status,
                cancelled_by = :cancelled_by,
                cancelled_at = NOW(),
                cancellation_reason = :reason,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':reason' => $reason,
            ':cancelled_by' => $actor,
            ':updated_by' => $actor,
        ]);
    }

    public function lock(string $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_payment_schedules
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare($this->projectionSql('WHERE schedule.id = :id') . ' LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function reportProjection(string $reportDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT schedule.payment_bank_account_id,
                   schedule.payment_due_date,
                   schedule.is_on_hold,
                   GREATEST(schedule.scheduled_amount - COALESCE(payment.paid_amount, 0), 0) AS remaining_amount
            FROM ledger_payment_schedules schedule
            LEFT JOIN (
                SELECT target_id, SUM(amount) AS paid_amount
                FROM ledger_evidence_links
                WHERE target_type = 'PAYMENT_SCHEDULE'
                  AND deleted_at IS NULL
                GROUP BY target_id
            ) payment ON payment.target_id = schedule.id
            WHERE schedule.deleted_at IS NULL
              AND schedule.obligation_lifecycle_status = 'ACTIVE'
              AND schedule.payment_due_date IS NOT NULL
              AND schedule.payment_due_date <= DATE_ADD(:report_date, INTERVAL 30 DAY)
        ");
        $stmt->execute([':report_date' => $reportDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function search(array $filters, int $start, int $length, string $sortField, string $sortDirection): array
    {
        [$where, $params] = $this->searchWhere($filters);
        $sortable = [
            'sort_no' => 'schedule.sort_no',
            'payment_due_date' => 'schedule.payment_due_date',
            'payment_status' => 'payment_status',
            'source_type' => 'schedule.source_type',
            'client_name' => 'client.client_name',
            'project_name' => 'project.project_name',
            'assignee_name' => 'assignee.employee_name',
            'payment_bank_account_name' => 'bank.account_name',
            'scheduled_amount' => 'schedule.scheduled_amount',
            'paid_amount' => 'paid_amount',
            'remaining_amount' => 'remaining_amount',
            'overdue_days' => 'overdue_days',
            'created_at' => 'schedule.created_at',
            'updated_at' => 'schedule.updated_at',
        ];
        $orderBy = $sortable[$sortField] ?? 'schedule.payment_due_date';
        $direction = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $sql = $this->projectionSql($where) . " ORDER BY {$orderBy} {$direction}, schedule.sort_no ASC LIMIT :start, :length";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':start', max(0, $start), PDO::PARAM_INT);
        $stmt->bindValue(':length', max(1, min(50000, $length)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->searchWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM (" . $this->projectionSql($where) . ') projected');
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function summary(array $filters): array
    {
        [$where, $params] = $this->searchWhere($filters);
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS row_count,
                   COALESCE(SUM(scheduled_amount), 0) AS scheduled_amount,
                   COALESCE(SUM(paid_amount), 0) AS paid_amount,
                   COALESCE(SUM(remaining_amount), 0) AS remaining_amount,
                   COALESCE(SUM(CASE WHEN payment_status = 'OVERDUE' THEN remaining_amount ELSE 0 END), 0) AS overdue_remaining_amount,
                   COALESCE(SUM(CASE WHEN payment_status = 'ON_HOLD' THEN remaining_amount ELSE 0 END), 0) AS hold_remaining_amount
            FROM (" . $this->projectionSql($where) . ') projected
        ');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function update(string $id, array $data, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_payment_schedules
            SET source_type = :source_type,
                source_id = :source_id,
                source_line_key = :source_line_key,
                payment_due_date = :payment_due_date,
                scheduled_amount = :scheduled_amount,
                client_id = :client_id,
                project_id = :project_id,
                assignee_id = :assignee_id,
                payment_bank_account_id = :payment_bank_account_id,
                memo = :memo,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':source_type' => $data['source_type'],
            ':source_id' => $data['source_id'],
            ':source_line_key' => $data['source_line_key'],
            ':payment_due_date' => $data['payment_due_date'],
            ':scheduled_amount' => $data['scheduled_amount'],
            ':client_id' => $data['client_id'] ?: null,
            ':project_id' => $data['project_id'] ?: null,
            ':assignee_id' => $data['assignee_id'] ?: null,
            ':payment_bank_account_id' => $data['payment_bank_account_id'] ?: null,
            ':memo' => $data['memo'] ?: null,
            ':updated_by' => $actor,
        ]);
    }

    public function setHold(string $id, bool $hold, ?string $reason, string $actor): void
    {
        $stmt = $this->pdo->prepare($hold
            ? "UPDATE ledger_payment_schedules
               SET is_on_hold = 1, hold_reason = :reason, held_by = :held_by, held_at = NOW(),
                   released_by = NULL, released_at = NULL, updated_by = :updated_by, updated_at = NOW()
               WHERE id = :id AND deleted_at IS NULL"
            : "UPDATE ledger_payment_schedules
               SET is_on_hold = 0, hold_reason = NULL, released_by = :released_by, released_at = NOW(),
                   updated_by = :updated_by, updated_at = NOW()
               WHERE id = :id AND deleted_at IS NULL");
        $params = [':id' => $id, ':updated_by' => $actor];
        if ($hold) {
            $params[':reason'] = $reason;
            $params[':held_by'] = $actor;
        } else {
            $params[':released_by'] = $actor;
        }
        $stmt->execute($params);
    }

    public function softDelete(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_payment_schedules
            SET deleted_at = NOW(), deleted_by = :deleted_by, updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id, ':deleted_by' => $actor, ':updated_by' => $actor]);
    }

    public function restore(string $id, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_payment_schedules
            SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :actor
            WHERE id = :id AND deleted_at IS NOT NULL
        ");
        $stmt->execute([':id' => $id, ':actor' => $actor]);
    }

    public function options(string $table, string $idField, string $labelField): array
    {
        $allowed = [
            'system_clients' => ['id', 'client_name'],
            'system_projects' => ['id', 'project_name'],
            'user_employees' => ['id', 'employee_name'],
            'system_bank_accounts' => ['id', 'account_name'],
        ];
        if (($allowed[$table] ?? null) !== [$idField, $labelField]) {
            return [];
        }
        $where = $table === 'user_employees'
            ? "WHERE COALESCE({$idField}, '') <> ''"
            : 'WHERE deleted_at IS NULL AND COALESCE(is_active, 1) = 1';
        $stmt = $this->pdo->query("SELECT {$idField} AS id, {$labelField} AS name FROM {$table} {$where} ORDER BY {$labelField}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function referenceExists(string $table, string $id): bool
    {
        $allowed = ['system_clients', 'system_projects', 'user_employees', 'system_bank_accounts'];
        if ($id === '' || !in_array($table, $allowed, true)) {
            return $id === '';
        }
        $where = $table === 'user_employees'
            ? 'id = :id'
            : 'id = :id AND deleted_at IS NULL AND COALESCE(is_active, 1) = 1';
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE {$where} LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function sourceExists(string $sourceType, string $sourceId): bool
    {
        if ($sourceType === 'VOUCHER_LINE') {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_vouchers
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':id' => $sourceId]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("
            SELECT source_table
            FROM ledger_evidence_metadata
            WHERE import_type = :source_type AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':source_type' => $sourceType]);
        $table = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($table === '' || preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            return false;
        }
        $source = $this->pdo->prepare("SELECT 1 FROM `{$table}` WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $source->execute([':id' => $sourceId]);
        return (bool) $source->fetchColumn();
    }

    public function sourceTypes(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT import_type AS id, import_type AS name
            FROM ledger_evidence_metadata
            WHERE deleted_at IS NULL
              AND COALESCE(import_type, '') <> ''
            ORDER BY import_type
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function projectionSql(string $where): string
    {
        return "
            SELECT schedule.*,
                   COALESCE(client.client_name, '') AS client_name,
                   COALESCE(project.project_name, '') AS project_name,
                   COALESCE(assignee.employee_name, '') AS assignee_name,
                   COALESCE(bank.account_name, '') AS payment_bank_account_name,
                   COALESCE(bank.bank_name, '') AS payment_bank_name,
                   COALESCE(bank.account_number, '') AS payment_bank_account_number,
                   voucher.voucher_date,
                   voucher.voucher_no,
                   voucher_line.line_no AS voucher_line_no,
                   COALESCE(obligation_account.account_name, '') AS obligation_account_name,
                   COALESCE(obligation_account.payment_obligation_type, '') AS payment_obligation_type,
                   COALESCE(payment.paid_amount, 0) AS paid_amount,
                   GREATEST(schedule.scheduled_amount - COALESCE(payment.paid_amount, 0), 0) AS remaining_amount,
                   payment.last_payment_at,
                   GREATEST(DATEDIFF(CURDATE(), schedule.payment_due_date), 0) AS overdue_days,
                   CASE
                       WHEN schedule.obligation_lifecycle_status = 'CANCELLED' THEN 'CANCELLED'
                       WHEN schedule.obligation_lifecycle_status = 'REVIEW_REQUIRED' THEN 'REVIEW_REQUIRED'
                       WHEN schedule.is_on_hold = 1 THEN 'ON_HOLD'
                       WHEN schedule.scheduled_amount - COALESCE(payment.paid_amount, 0) <= 0 THEN 'COMPLETED'
                       WHEN schedule.payment_due_date IS NOT NULL AND schedule.payment_due_date < CURDATE() THEN 'OVERDUE'
                       WHEN COALESCE(payment.paid_amount, 0) > 0 THEN 'PARTIAL'
                       ELSE 'WAITING'
                   END AS payment_status,
                   CASE
                       WHEN schedule.source_type = 'VOUCHER_LINE'
                           THEN CONCAT(
                               COALESCE(voucher.voucher_no, schedule.source_id),
                               ' / ',
                               COALESCE(obligation_account.account_name, '계정 미지정'),
                               ' / ',
                               COALESCE(voucher_line.line_no, 0),
                               '행'
                           )
                       ELSE CONCAT(schedule.source_type, ' / ', schedule.source_id)
                   END AS source_name
            FROM ledger_payment_schedules schedule
            LEFT JOIN system_clients client ON client.id = schedule.client_id
            LEFT JOIN system_projects project ON project.id = schedule.project_id
            LEFT JOIN user_employees assignee ON assignee.id = schedule.assignee_id
            LEFT JOIN system_bank_accounts bank ON bank.id = schedule.payment_bank_account_id
            LEFT JOIN ledger_vouchers voucher
              ON schedule.source_type = 'VOUCHER_LINE'
             AND voucher.id = schedule.source_id
            LEFT JOIN ledger_voucher_lines voucher_line
              ON schedule.source_type = 'VOUCHER_LINE'
             AND voucher_line.id = schedule.source_line_key
             AND voucher_line.voucher_id = schedule.source_id
            LEFT JOIN ledger_accounts obligation_account
              ON obligation_account.id = voucher_line.account_id
            LEFT JOIN (
                SELECT target_id, SUM(amount) AS paid_amount, MAX(created_at) AS last_payment_at
                FROM ledger_evidence_links
                WHERE target_type = 'PAYMENT_SCHEDULE' AND deleted_at IS NULL
                GROUP BY target_id
            ) payment ON payment.target_id = schedule.id
            {$where}
        ";
    }

    private function searchWhere(array $filters): array
    {
        $conditions = [];
        $params = [];
        $scope = strtoupper((string) ($filters['deleted_scope'] ?? 'ACTIVE'));
        $conditions[] = $scope === 'DELETED' ? 'schedule.deleted_at IS NOT NULL' : 'schedule.deleted_at IS NULL';
        $map = [
            'client_id' => 'schedule.client_id',
            'project_id' => 'schedule.project_id',
            'assignee_id' => 'schedule.assignee_id',
            'payment_bank_account_id' => 'schedule.payment_bank_account_id',
            'source_type' => 'schedule.source_type',
        ];
        foreach ($map as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $conditions[] = "{$column} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $conditions[] = "schedule.payment_due_date {$operator} :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        $status = strtoupper(trim((string) ($filters['payment_status'] ?? '')));
        if ($status !== '') {
            $conditions[] = "(CASE
                WHEN schedule.obligation_lifecycle_status = 'CANCELLED' THEN 'CANCELLED'
                WHEN schedule.obligation_lifecycle_status = 'REVIEW_REQUIRED' THEN 'REVIEW_REQUIRED'
                WHEN schedule.is_on_hold = 1 THEN 'ON_HOLD'
                WHEN schedule.scheduled_amount - COALESCE(payment.paid_amount, 0) <= 0 THEN 'COMPLETED'
                WHEN schedule.payment_due_date IS NOT NULL AND schedule.payment_due_date < CURDATE() THEN 'OVERDUE'
                WHEN COALESCE(payment.paid_amount, 0) > 0 THEN 'PARTIAL' ELSE 'WAITING' END) = :payment_status";
            $params[':payment_status'] = $status;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = "(schedule.source_id LIKE :q OR schedule.source_line_key LIKE :q
                OR schedule.memo LIKE :q OR client.client_name LIKE :q OR project.project_name LIKE :q
                OR assignee.employee_name LIKE :q OR bank.account_name LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
