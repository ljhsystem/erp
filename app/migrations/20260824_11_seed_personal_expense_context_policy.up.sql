DROP PROCEDURE IF EXISTS `migrate_20260824_11_seed_personal_expense_context_policy`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_11_seed_personal_expense_context_policy`()
BEGIN
    DECLARE target_company_id varchar(50);
    DECLARE target_found_date date;
    DECLARE target_policy_count int DEFAULT 0;
    DECLARE target_account_count int DEFAULT 0;
    DECLARE target_seed_count int DEFAULT 0;

    IF COALESCE(@journal_context_actor,'') = '' OR @journal_context_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 @journal_context_actor가 필요합니다.';
    END IF;

    SELECT `id`,`found_date` INTO target_company_id,target_found_date
      FROM `system_company` WHERE `id`='e2509853-5961-4db6-a2ee-1080da4ca98f' LIMIT 1;
    IF target_company_id IS NULL OR target_found_date <> '2013-07-19' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='회사정보 SSOT의 회사 ID 또는 사업개시일이 승인값과 다릅니다.';
    END IF;

    SELECT COUNT(*) INTO target_account_count FROM `ledger_accounts`
     WHERE `id`='0e6378f9-cf0d-43b9-aec3-49d07f527d3d' AND `account_code`='216100'
       AND `account_name`='미지급비용' AND `is_active`=1 AND `is_posting`=1 AND `deleted_at` IS NULL;
    IF target_account_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='승인된 미지급비용 계정을 사용할 수 없습니다.';
    END IF;

    SELECT COUNT(*) INTO target_policy_count FROM `ledger_accounts_sub`
     WHERE `id`='1df4c54c-f9a7-44e8-b99a-55984ff78192'
       AND `account_id`='0e6378f9-cf0d-43b9-aec3-49d07f527d3d' AND `ref_target`='EMPLOYEE';
    IF target_policy_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='승인된 미지급비용 EMPLOYEE 허용정책을 찾을 수 없습니다.';
    END IF;

    SELECT COUNT(*) INTO target_seed_count FROM `ledger_account_context_ref_policies`
     WHERE `id` IN ('10c31fd2-3dbc-4c45-80fa-202608240011','abbe6ee3-f1b2-487f-b476-202608240012')
        OR (`company_id`=target_company_id
            AND `account_sub_policy_id`='1df4c54c-f9a7-44e8-b99a-55984ff78192'
            AND `operation_type`='PERSONAL_EXPENSE'
            AND `accounting_role_code` IN ('EMPLOYEE_ACCRUED_EXPENSE','EMPLOYEE_ACCRUED_EXPENSE_SETTLEMENT'));
    IF target_seed_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 조건부 정책 대상행이 이미 존재합니다.';
    END IF;

    INSERT INTO `system_codes` (`id`,`sort_no`,`code_group`,`group_name`,`code`,`code_name`,`extra_data`,`note`,`memo`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
    SELECT UUID(),COALESCE(MAX_CODE.max_sort_no,0)+ROLE_DATA.role_order,'JOURNAL_ACCOUNTING_ROLE','분개 회계역할',ROLE_DATA.role_code,ROLE_DATA.role_name,'[]','분개추천·Source Ref 공용 회계역할',NULL,1,NOW(),@journal_context_actor,NOW(),@journal_context_actor
      FROM (
        SELECT 'EXPENSE' role_code,'비용 인식' role_name,1 role_order UNION ALL
        SELECT 'INPUT_VAT','부가가치세대급금',2 UNION ALL
        SELECT 'EMPLOYEE_ACCRUED_EXPENSE','직원 미지급비용 발생',3 UNION ALL
        SELECT 'EMPLOYEE_ACCRUED_EXPENSE_SETTLEMENT','직원 지급으로 미지급비용 감소',4 UNION ALL
        SELECT 'CARD_ACCRUED_EXPENSE','법인카드 사용으로 미지급비용 발생',5 UNION ALL
        SELECT 'CARD_ACCRUED_EXPENSE_SETTLEMENT','카드대금 지급으로 미지급비용 감소',6 UNION ALL
        SELECT 'BANK_PAYMENT','은행계좌 지급',7
      ) ROLE_DATA
      CROSS JOIN (SELECT MAX(`sort_no`) max_sort_no FROM `system_codes`) MAX_CODE
     WHERE NOT EXISTS (SELECT 1 FROM `system_codes` C WHERE C.`code_group`='JOURNAL_ACCOUNTING_ROLE' AND C.`code`=ROLE_DATA.role_code);

    IF (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='JOURNAL_ACCOUNTING_ROLE' AND `code` IN ('EXPENSE','INPUT_VAT','EMPLOYEE_ACCRUED_EXPENSE','EMPLOYEE_ACCRUED_EXPENSE_SETTLEMENT','CARD_ACCRUED_EXPENSE','CARD_ACCRUED_EXPENSE_SETTLEMENT','BANK_PAYMENT') AND `is_active`=1) <> 7 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='공식 회계역할 Registry가 완전하지 않습니다.';
    END IF;

    INSERT INTO `ledger_account_context_ref_policies`
      (`id`,`company_id`,`account_sub_policy_id`,`operation_type`,`accounting_role_code`,`effective_from`,`effective_to`,`is_active`,`sort_no`,`created_by`,`updated_by`)
    VALUES
      ('10c31fd2-3dbc-4c45-80fa-202608240011',target_company_id,'1df4c54c-f9a7-44e8-b99a-55984ff78192','PERSONAL_EXPENSE','EMPLOYEE_ACCRUED_EXPENSE',target_found_date,NULL,1,1,@journal_context_actor,@journal_context_actor),
      ('abbe6ee3-f1b2-487f-b476-202608240012',target_company_id,'1df4c54c-f9a7-44e8-b99a-55984ff78192','PERSONAL_EXPENSE','EMPLOYEE_ACCRUED_EXPENSE_SETTLEMENT',target_found_date,NULL,1,2,@journal_context_actor,@journal_context_actor);
END$$
DELIMITER ;
CALL `migrate_20260824_11_seed_personal_expense_context_policy`();
DROP PROCEDURE `migrate_20260824_11_seed_personal_expense_context_policy`;
