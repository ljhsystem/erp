-- 롤백은 신청 헤더당 증빙원본이 1건 이하인 경우에만 허용한다.
DELIMITER $$
CREATE PROCEDURE `sp_restore_personal_expense_evidence_header_source`()
BEGIN
    DECLARE duplicate_header_count BIGINT DEFAULT 0;

    SELECT COUNT(*)
      INTO duplicate_header_count
      FROM (
          SELECT item.`personal_expense_id`
            FROM `ledger_evidence_employee_personal_expense` evidence
            INNER JOIN `approval_personal_expense_items` item
              ON item.`id` = evidence.`source_personal_expense_item_id`
           GROUP BY item.`personal_expense_id`
          HAVING COUNT(*) > 1
      ) duplicate_headers;

    IF duplicate_header_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'multiple item evidences exist for one header; down migration is unsafe';
    END IF;

    ALTER TABLE `ledger_evidence_employee_personal_expense`
        DROP FOREIGN KEY `fk_employee_personal_expense_source_item`,
        DROP INDEX `uk_employee_personal_expense_source_item`;

    UPDATE `ledger_evidence_employee_personal_expense` evidence
    INNER JOIN `approval_personal_expense_items` item
        ON item.`id` = evidence.`source_personal_expense_item_id`
       SET evidence.`source_personal_expense_item_id` = item.`personal_expense_id`;

    ALTER TABLE `ledger_evidence_employee_personal_expense`
        CHANGE COLUMN `source_personal_expense_item_id`
            `source_personal_expense_id` VARCHAR(36) NOT NULL
            COMMENT '개인경비 신청내역',
        DROP COLUMN `raw_merchant_address_detail`,
        ADD UNIQUE INDEX `uk_employee_personal_expense_source`
            (`source_personal_expense_id`),
        ADD CONSTRAINT `fk_employee_personal_expense_source`
            FOREIGN KEY (`source_personal_expense_id`)
            REFERENCES `approval_personal_expenses` (`id`)
            ON UPDATE RESTRICT
            ON DELETE RESTRICT;
END$$
DELIMITER ;

CALL `sp_restore_personal_expense_evidence_header_source`();
DROP PROCEDURE `sp_restore_personal_expense_evidence_header_source`;
