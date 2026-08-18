DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_payment_obligation_policy_up$$
CREATE PROCEDURE migrate_payment_obligation_policy_up()
BEGIN
    DECLARE v_target_count INT DEFAULT 0;
    DECLARE v_invalid_count INT DEFAULT 0;

    SELECT COUNT(*),
           COALESCE(SUM(
               CASE
                   WHEN is_posting <> 1 OR is_active <> 1 OR deleted_at IS NOT NULL THEN 1
                   ELSE 0
               END
           ), 0)
      INTO v_target_count, v_invalid_count
      FROM ledger_accounts
     WHERE (id = '48a4581b-caf0-4a18-a5ee-5f31656865ce' AND account_code = '211100')
        OR (id = 'a6fc9147-f244-444c-84dc-d14aff40491d' AND account_code = '211200')
        OR (id = '89bb9037-2a86-4075-ac4c-4f73a701ad1f' AND account_code = '213400')
        OR (id = '0e6378f9-cf0d-43b9-aec3-49d07f527d3d' AND account_code = '216100')
        OR (id = 'aa540cf7-c2d5-407c-b2df-9ee57ebf4946' AND account_code = '224100');

    IF v_target_count <> 5 OR v_invalid_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '지급의무 정책 대상 계정 5건의 식별값 또는 상태가 승인 내용과 일치하지 않습니다.';
    END IF;

    ALTER TABLE ledger_accounts
        ADD COLUMN IF NOT EXISTS creates_payment_obligation TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '전표승인 시 지급의무 생성 여부'
            AFTER is_posting,
        ADD COLUMN IF NOT EXISTS payment_obligation_type VARCHAR(40) NULL
            COMMENT '지급의무 유형'
            AFTER creates_payment_obligation;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'ledger_accounts'
           AND CONSTRAINT_NAME = 'chk_ledger_accounts_payment_obligation'
    ) THEN
        ALTER TABLE ledger_accounts
            ADD CONSTRAINT chk_ledger_accounts_payment_obligation
            CHECK (
                (creates_payment_obligation = 0 AND payment_obligation_type IS NULL)
                OR
                (
                    creates_payment_obligation = 1
                    AND is_posting = 1
                    AND payment_obligation_type IN (
                        'TRADE_PAYABLE',
                        'OTHER_PAYABLE',
                        'NOTE_PAYABLE',
                        'TAX_PAYABLE',
                        'WITHHOLDING_PAYABLE',
                        'ACCRUED_EXPENSE',
                        'OTHER'
                    )
                )
            );
    END IF;

    UPDATE ledger_accounts
       SET creates_payment_obligation = 1,
           payment_obligation_type = CASE id
               WHEN '48a4581b-caf0-4a18-a5ee-5f31656865ce' THEN 'TRADE_PAYABLE'
               WHEN 'a6fc9147-f244-444c-84dc-d14aff40491d' THEN 'NOTE_PAYABLE'
               WHEN '89bb9037-2a86-4075-ac4c-4f73a701ad1f' THEN 'OTHER_PAYABLE'
               WHEN '0e6378f9-cf0d-43b9-aec3-49d07f527d3d' THEN 'ACCRUED_EXPENSE'
               WHEN 'aa540cf7-c2d5-407c-b2df-9ee57ebf4946' THEN 'OTHER_PAYABLE'
           END,
           updated_at = NOW(),
           updated_by = 'SYSTEM:PAYMENT_OBLIGATION_MIGRATION'
     WHERE id IN (
         '48a4581b-caf0-4a18-a5ee-5f31656865ce',
         'a6fc9147-f244-444c-84dc-d14aff40491d',
         '89bb9037-2a86-4075-ac4c-4f73a701ad1f',
         '0e6378f9-cf0d-43b9-aec3-49d07f527d3d',
         'aa540cf7-c2d5-407c-b2df-9ee57ebf4946'
     );

    ALTER TABLE ledger_payment_schedules
        MODIFY COLUMN payment_due_date DATE NULL COMMENT '지급예정일, 미확정 시 NULL',
        ADD COLUMN IF NOT EXISTS obligation_lifecycle_status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE'
            COMMENT '지급의무 생명주기 상태'
            AFTER scheduled_amount,
        ADD COLUMN IF NOT EXISTS cancelled_by VARCHAR(100) NULL
            AFTER obligation_lifecycle_status,
        ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL
            AFTER cancelled_by,
        ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(500) NULL
            AFTER cancelled_at;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'ledger_payment_schedules'
           AND CONSTRAINT_NAME = 'chk_payment_schedule_lifecycle'
    ) THEN
        ALTER TABLE ledger_payment_schedules
            ADD CONSTRAINT chk_payment_schedule_lifecycle
            CHECK (obligation_lifecycle_status IN ('ACTIVE', 'CANCELLED', 'REVIEW_REQUIRED'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'ledger_payment_schedules'
           AND INDEX_NAME = 'idx_payment_schedule_lifecycle'
    ) THEN
        CREATE INDEX idx_payment_schedule_lifecycle
            ON ledger_payment_schedules (obligation_lifecycle_status, payment_due_date);
    END IF;
END$$

CALL migrate_payment_obligation_policy_up()$$
DROP PROCEDURE migrate_payment_obligation_policy_up$$

DELIMITER ;
