DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_payment_obligation_policy_down$$
CREATE PROCEDURE migrate_payment_obligation_policy_down()
BEGIN
    DECLARE v_schedule_count INT DEFAULT 0;
    DECLARE v_unexpected_policy_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_schedule_count
      FROM ledger_payment_schedules;

    IF v_schedule_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '지급예정 운영 데이터가 존재하여 지급의무 정책 Migration을 되돌릴 수 없습니다.';
    END IF;

    SELECT COUNT(*) INTO v_unexpected_policy_count
      FROM ledger_accounts
     WHERE creates_payment_obligation = 1
       AND id NOT IN (
           '48a4581b-caf0-4a18-a5ee-5f31656865ce',
           'a6fc9147-f244-444c-84dc-d14aff40491d',
           '89bb9037-2a86-4075-ac4c-4f73a701ad1f',
           '0e6378f9-cf0d-43b9-aec3-49d07f527d3d',
           'aa540cf7-c2d5-407c-b2df-9ee57ebf4946'
       );

    IF v_unexpected_policy_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '승인 범위 밖의 지급의무 계정 정책이 존재하여 Migration을 되돌릴 수 없습니다.';
    END IF;

    UPDATE ledger_accounts
       SET creates_payment_obligation = 0,
           payment_obligation_type = NULL,
           updated_at = NOW(),
           updated_by = 'SYSTEM:PAYMENT_OBLIGATION_MIGRATION_DOWN'
     WHERE id IN (
         '48a4581b-caf0-4a18-a5ee-5f31656865ce',
         'a6fc9147-f244-444c-84dc-d14aff40491d',
         '89bb9037-2a86-4075-ac4c-4f73a701ad1f',
         '0e6378f9-cf0d-43b9-aec3-49d07f527d3d',
         'aa540cf7-c2d5-407c-b2df-9ee57ebf4946'
     );

    DROP INDEX IF EXISTS idx_payment_schedule_lifecycle ON ledger_payment_schedules;
    ALTER TABLE ledger_payment_schedules
        DROP CONSTRAINT IF EXISTS chk_payment_schedule_lifecycle,
        DROP COLUMN IF EXISTS cancellation_reason,
        DROP COLUMN IF EXISTS cancelled_at,
        DROP COLUMN IF EXISTS cancelled_by,
        DROP COLUMN IF EXISTS obligation_lifecycle_status,
        MODIFY COLUMN payment_due_date DATE NOT NULL COMMENT '지급예정일';

    ALTER TABLE ledger_accounts
        DROP CONSTRAINT IF EXISTS chk_ledger_accounts_payment_obligation,
        DROP COLUMN IF EXISTS payment_obligation_type,
        DROP COLUMN IF EXISTS creates_payment_obligation;
END$$

CALL migrate_payment_obligation_policy_down()$$
DROP PROCEDURE migrate_payment_obligation_policy_down$$

DELIMITER ;
