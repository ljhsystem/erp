-- 개인경비 증빙원본의 출처를 신청 헤더에서 신청 아이템으로 전환한다.
-- 기존 데이터는 헤더별 아이템이 정확히 1건인 경우에만 자동 매핑하며,
-- 0건 또는 2건 이상이면 임의 매핑하지 않고 Migration을 중단한다.
DELIMITER $$
CREATE PROCEDURE `sp_link_personal_expense_evidence_to_item`()
BEGIN
    DECLARE invalid_mapping_count BIGINT DEFAULT 0;

    SELECT COUNT(*)
      INTO invalid_mapping_count
      FROM `ledger_evidence_employee_personal_expense` evidence
      LEFT JOIN (
          SELECT `personal_expense_id`, COUNT(*) AS `item_count`
            FROM `approval_personal_expense_items`
           GROUP BY `personal_expense_id`
      ) item_summary
        ON item_summary.`personal_expense_id` = evidence.`source_personal_expense_id`
     WHERE COALESCE(item_summary.`item_count`, 0) <> 1;

    IF invalid_mapping_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'personal expense evidence cannot be mapped to exactly one item';
    END IF;

    ALTER TABLE `ledger_evidence_employee_personal_expense`
        DROP FOREIGN KEY `fk_employee_personal_expense_source`,
        DROP INDEX `uk_employee_personal_expense_source`,
        CHANGE COLUMN `source_personal_expense_id`
            `source_personal_expense_item_id` VARCHAR(36) NOT NULL
            COMMENT '개인경비 신청 아이템';

    UPDATE `ledger_evidence_employee_personal_expense` evidence
    INNER JOIN `approval_personal_expense_items` item
        ON item.`personal_expense_id` = evidence.`source_personal_expense_item_id`
       SET evidence.`source_personal_expense_item_id` = item.`id`;

    ALTER TABLE `ledger_evidence_employee_personal_expense`
        ADD COLUMN `raw_merchant_address_detail` VARCHAR(255) NULL
            COMMENT '가맹점상세주소'
            AFTER `raw_merchant_address`,
        ADD UNIQUE INDEX `uk_employee_personal_expense_source_item`
            (`source_personal_expense_item_id`),
        ADD CONSTRAINT `fk_employee_personal_expense_source_item`
            FOREIGN KEY (`source_personal_expense_item_id`)
            REFERENCES `approval_personal_expense_items` (`id`)
            ON UPDATE RESTRICT
            ON DELETE RESTRICT;
END$$
DELIMITER ;

CALL `sp_link_personal_expense_evidence_to_item`();
DROP PROCEDURE `sp_link_personal_expense_evidence_to_item`;
