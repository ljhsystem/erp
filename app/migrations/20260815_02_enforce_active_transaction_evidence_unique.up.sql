DROP PROCEDURE IF EXISTS `migrate_20260815_02_enforce_active_transaction_evidence_unique`;

DELIMITER $$

CREATE PROCEDURE `migrate_20260815_02_enforce_active_transaction_evidence_unique`()
BEGIN
    DECLARE duplicate_count INT DEFAULT 0;
    DECLARE generated_column_count INT DEFAULT 0;
    DECLARE unique_index_count INT DEFAULT 0;

    SELECT COUNT(*) INTO duplicate_count
    FROM (
        SELECT evidence_type, evidence_id
        FROM `ledger_evidence_links`
        WHERE target_type = 'TRANSACTION' AND deleted_at IS NULL
        GROUP BY evidence_type, evidence_id
        HAVING COUNT(*) > 1
    ) duplicates;

    SELECT COUNT(*) INTO generated_column_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_evidence_links'
      AND COLUMN_NAME IN (
          'active_transaction_evidence_type',
          'active_transaction_evidence_id'
      );

    SELECT COUNT(*) INTO unique_index_count
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_evidence_links'
      AND INDEX_NAME = 'uk_evl_active_transaction_evidence';

    IF duplicate_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active transaction evidence duplicates exist';
    END IF;
    IF generated_column_count <> 0 OR unique_index_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active transaction evidence guard already exists';
    END IF;

    ALTER TABLE `ledger_evidence_links`
      ADD COLUMN `active_transaction_evidence_type` varchar(40)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
        GENERATED ALWAYS AS (
          CASE
            WHEN target_type = 'TRANSACTION' AND deleted_at IS NULL THEN evidence_type
            ELSE NULL
          END
        ) PERSISTENT COMMENT '활성 거래 증빙유형 유일성 키',
      ADD COLUMN `active_transaction_evidence_id` varchar(36)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
        GENERATED ALWAYS AS (
          CASE
            WHEN target_type = 'TRANSACTION' AND deleted_at IS NULL THEN evidence_id
            ELSE NULL
          END
        ) PERSISTENT COMMENT '활성 거래 증빙 ID 유일성 키',
      ADD UNIQUE KEY `uk_evl_active_transaction_evidence` (
        `active_transaction_evidence_type`,
        `active_transaction_evidence_id`
      );
END$$

CALL `migrate_20260815_02_enforce_active_transaction_evidence_unique`()$$
DROP PROCEDURE `migrate_20260815_02_enforce_active_transaction_evidence_unique`$$

DELIMITER ;
