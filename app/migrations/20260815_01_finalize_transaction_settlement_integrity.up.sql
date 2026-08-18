DROP PROCEDURE IF EXISTS `migrate_20260815_01_finalize_transaction_settlement_integrity`;

DELIMITER $$

CREATE PROCEDURE `migrate_20260815_01_finalize_transaction_settlement_integrity`()
BEGIN
    DECLARE orphan_count INT DEFAULT 0;
    DECLARE approved_orphan_count INT DEFAULT 0;
    DECLARE null_transaction_count INT DEFAULT 0;
    DECLARE dependency_count INT DEFAULT 0;
    DECLARE existing_fk_count INT DEFAULT 0;

    SELECT COUNT(*) INTO orphan_count
    FROM `ledger_transaction_settlements` s
    LEFT JOIN `ledger_transactions` t ON BINARY t.id = BINARY s.transaction_id
    WHERE t.id IS NULL;

    SELECT COUNT(*) INTO approved_orphan_count
    FROM `ledger_transaction_settlements`
    WHERE id = '7c2a61a5-e160-464a-89e1-d84b5f8869d2'
      AND transaction_id = 'd67903f3-c7eb-486e-a9bb-d015d8e665a0'
      AND transaction_item_id IS NULL
      AND sort_no = 1
      AND settlement_type = 'VAT'
      AND amount_sign = 'PLUS'
      AND amount = 10000.00
      AND settlement_description IS NULL;

    SELECT COUNT(*) INTO null_transaction_count
    FROM `ledger_transaction_settlements`
    WHERE transaction_id IS NULL OR transaction_id = '';

    SELECT
        (SELECT COUNT(*)
         FROM `ledger_evidence_links`
         WHERE target_id IN (
             '7c2a61a5-e160-464a-89e1-d84b5f8869d2',
             'd67903f3-c7eb-486e-a9bb-d015d8e665a0'
         ))
        +
        (SELECT COUNT(*)
         FROM `ledger_transaction_files`
         WHERE transaction_id = 'd67903f3-c7eb-486e-a9bb-d015d8e665a0')
    INTO dependency_count;

    SELECT COUNT(*) INTO existing_fk_count
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_transaction_settlements';

    IF orphan_count <> 1 OR approved_orphan_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved orphan settlement preflight failed';
    END IF;
    IF null_transaction_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement transaction_id null preflight failed';
    END IF;
    IF dependency_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved orphan has dependent rows';
    END IF;
    IF existing_fk_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement foreign key already exists';
    END IF;

    DELETE FROM `ledger_transaction_settlements`
    WHERE id = '7c2a61a5-e160-464a-89e1-d84b5f8869d2'
      AND transaction_id = 'd67903f3-c7eb-486e-a9bb-d015d8e665a0';

    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved orphan settlement delete failed';
    END IF;

    ALTER TABLE `ledger_transaction_settlements`
      MODIFY `transaction_id` varchar(36)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
        NOT NULL COMMENT '거래 헤더 ID';

    ALTER TABLE `ledger_transaction_settlements`
      ADD CONSTRAINT `fk_transaction_settlement_transaction`
      FOREIGN KEY (`transaction_id`) REFERENCES `ledger_transactions` (`id`)
      ON DELETE CASCADE ON UPDATE CASCADE;
END$$

CALL `migrate_20260815_01_finalize_transaction_settlement_integrity`()$$
DROP PROCEDURE `migrate_20260815_01_finalize_transaction_settlement_integrity`$$

DELIMITER ;
