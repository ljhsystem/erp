-- 외부원본식별키는 자료유형별 Body 테이블 안에서 유일해야 한다.
-- 기존 중복은 임의 수정하지 않고 Migration을 중단하여 사전 정리를 요구한다.
DELIMITER $$
CREATE PROCEDURE `sp_add_evidence_external_key_unique`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE table_name_value VARCHAR(128);
    DECLARE duplicate_count BIGINT DEFAULT 0;
    DECLARE table_cursor CURSOR FOR
        SELECT TABLE_NAME
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND COLUMN_NAME = 'external_key'
           AND TABLE_NAME IN (
               'ledger_evidence_bank_transaction', 'ledger_evidence_tax_invoice',
               'ledger_evidence_tax_invoice_manual', 'ledger_evidence_cash_receipt',
               'ledger_evidence_card_hometax', 'ledger_evidence_card_statement',
               'ledger_evidence_employee_expense', 'ledger_evidence_payroll',
               'ledger_evidence_daily_worker', 'ledger_evidence_business_income',
               'ledger_evidence_cash_sales', 'ledger_evidence_business_data'
           );
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN table_cursor;
    preflight_loop: LOOP
        FETCH table_cursor INTO table_name_value;
        IF done = 1 THEN LEAVE preflight_loop; END IF;
        SET @duplicate_count = 0;
        SET @sql = CONCAT(
            'SELECT COUNT(*) INTO @duplicate_count FROM (SELECT external_key FROM `',
            table_name_value,
            '` WHERE external_key IS NOT NULL GROUP BY external_key HAVING COUNT(*) > 1) duplicate_keys'
        );
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
        SET duplicate_count = @duplicate_count;
        IF duplicate_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_key duplicates exist; run collision report before unique index migration';
        END IF;
    END LOOP;
    CLOSE table_cursor;

    SET done = 0;
    OPEN table_cursor;
    alter_loop: LOOP
        FETCH table_cursor INTO table_name_value;
        IF done = 1 THEN LEAVE alter_loop; END IF;
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = table_name_value
               AND NON_UNIQUE = 0 AND COLUMN_NAME = 'external_key'
        ) THEN
            SET @sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD UNIQUE INDEX `uq_evidence_external_key` (`external_key`)');
            PREPARE statement FROM @sql;
            EXECUTE statement;
            DEALLOCATE PREPARE statement;
        END IF;
    END LOOP;
    CLOSE table_cursor;
END$$
DELIMITER ;
CALL `sp_add_evidence_external_key_unique`();
DROP PROCEDURE `sp_add_evidence_external_key_unique`;