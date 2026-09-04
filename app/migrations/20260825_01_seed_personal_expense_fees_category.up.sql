DROP PROCEDURE IF EXISTS `migrate_20260825_01_seed_personal_expense_fees_category`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260825_01_seed_personal_expense_fees_category`()
BEGIN
    DECLARE taxes_sort_no int;
    DECLARE supplies_sort_no int;

    IF COALESCE(@personal_expense_category_actor,'') = '' OR @personal_expense_category_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 @personal_expense_category_actor가 필요합니다.';
    END IF;
    IF EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code`='FEES_AND_COMMISSIONS') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FEES_AND_COMMISSIONS 코드가 이미 존재합니다.';
    END IF;
    SELECT `sort_no` INTO taxes_sort_no FROM `system_codes`
     WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code`='TAXES_AND_DUES' AND `is_active`=1 LIMIT 1;
    SELECT `sort_no` INTO supplies_sort_no FROM `system_codes`
     WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code`='SUPPLIES' AND `is_active`=1 LIMIT 1;
    IF taxes_sort_no IS NULL OR supplies_sort_no IS NULL OR taxes_sort_no = supplies_sort_no THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 분류 정렬 기준을 확정할 수 없습니다.';
    END IF;

    IF taxes_sort_no < supplies_sort_no THEN
        UPDATE `system_codes`
           SET `sort_no`=`sort_no`+1,`updated_at`=NOW(),`updated_by`=@personal_expense_category_actor
         WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `sort_no`>taxes_sort_no;
    ELSE
        UPDATE `system_codes`
           SET `sort_no`=CASE
                 WHEN `code`='TAXES_AND_DUES' THEN supplies_sort_no
                 WHEN `sort_no`>=supplies_sort_no AND `sort_no`<taxes_sort_no THEN `sort_no`+2
                 WHEN `sort_no`>taxes_sort_no THEN `sort_no`+1
                 ELSE `sort_no`
               END,
               `updated_at`=NOW(),`updated_by`=@personal_expense_category_actor
         WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND (`code`='TAXES_AND_DUES' OR `sort_no`>=supplies_sort_no);
        SET taxes_sort_no = supplies_sort_no;
    END IF;

    INSERT INTO `system_codes`
      (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`extra_data`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
    VALUES
      (UUID(),taxes_sort_no+1,'PERSONAL_EXPENSE_CATEGORY','개인경비 분류','FEES_AND_COMMISSIONS','지급수수료','[]','수수료, 이용료 및 각종 업무처리 대가로 지급한 개인경비',NULL,1,NOW(),@personal_expense_category_actor,NOW(),@personal_expense_category_actor);

    IF (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code`='FEES_AND_COMMISSIONS' AND `code_name`='지급수수료' AND `sort_no`=taxes_sort_no+1 AND `is_active`=1) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FEES_AND_COMMISSIONS 공식 코드 등록 검증에 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL `migrate_20260825_01_seed_personal_expense_fees_category`();
DROP PROCEDURE `migrate_20260825_01_seed_personal_expense_fees_category`;
