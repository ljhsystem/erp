DROP PROCEDURE IF EXISTS `migrate_20260825_03_personal_expense_category_coverage`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260825_03_personal_expense_category_coverage`()
seed: BEGIN
    DECLARE v_company_id varchar(50);
    DECLARE v_actor varchar(100);
    DECLARE v_now datetime;
    DECLARE v_base_sort int DEFAULT 0;
    DECLARE v_rule_count int DEFAULT 0;
    DECLARE v_revision_count int DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET v_actor = NULLIF(TRIM(COALESCE(@personal_expense_category_coverage_seed_actor, '')), '');
    IF v_actor IS NULL OR v_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 Migration Actor가 필요합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `system_company`) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seed 대상 회사가 정확히 1건이어야 합니다.';
    END IF;
    SELECT `id` INTO v_company_id FROM `system_company` LIMIT 1;

    IF (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code` IN ('MEAL','TRANSPORTATION','FUEL','PARKING','TOLL','ACCOMMODATION','TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES','COMMUNICATION','ENTERTAINMENT','OFFICE_SUPPLIES','FREIGHT','EQUIPMENT_RENTAL','OTHER') AND `is_active`=1) <> 15 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 활성 공식 비용분류 15건이 필요합니다.';
    END IF;
    IF (SELECT COUNT(DISTINCT `account_code`) FROM `ledger_accounts` WHERE `account_code` IN ('551130','551040','551230','551060','551360','551240','551050','551380') AND `is_active`=1 AND COALESCE(`is_posting`,1)=1 AND `deleted_at` IS NULL) <> 8 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='누락 비용분류의 공식 판관비 계정 8건이 모두 활성·전기 가능해야 합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `ledger_journal_rules` WHERE `rule_code` IN ('PE_DEBIT_TAXES_AND_DUES','PE_DEBIT_FEES_AND_COMMISSIONS','PE_DEBIT_SUPPLIES','PE_DEBIT_TRANSPORTATION','PE_DEBIT_MEAL','PE_CREDIT_EMPLOYEE_ACCRUED')) <> 6
       OR (SELECT COUNT(*) FROM `ledger_journal_rule_revisions` rv JOIN `ledger_journal_rules` r ON r.id=rv.rule_id WHERE r.rule_code IN ('PE_DEBIT_TAXES_AND_DUES','PE_DEBIT_FEES_AND_COMMISSIONS','PE_DEBIT_SUPPLIES','PE_DEBIT_TRANSPORTATION','PE_DEBIT_MEAL','PE_CREDIT_EMPLOYEE_ACCRUED') AND rv.action_code='CREATE') <> 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='공식 선행 개인경비 역할형 Rule 6건과 CREATE Revision 6건이 필요합니다.';
    END IF;

    SELECT COUNT(*) INTO v_rule_count FROM `ledger_journal_rules` WHERE `rule_code` IN ('PE_DEBIT_FUEL','PE_DEBIT_PARKING','PE_DEBIT_TOLL','PE_DEBIT_ACCOMMODATION','PE_DEBIT_COMMUNICATION','PE_DEBIT_ENTERTAINMENT','PE_DEBIT_OFFICE_SUPPLIES','PE_DEBIT_FREIGHT','PE_DEBIT_EQUIPMENT_RENTAL','PE_DEBIT_OTHER');
    SELECT COUNT(*) INTO v_revision_count FROM `ledger_journal_rule_revisions` WHERE `rule_id` IN ('9e3bc6d4-1f40-4ebd-9011-202608250301','9e3bc6d4-1f40-4ebd-9012-202608250302','9e3bc6d4-1f40-4ebd-9013-202608250303','9e3bc6d4-1f40-4ebd-9014-202608250304','9e3bc6d4-1f40-4ebd-9015-202608250305','9e3bc6d4-1f40-4ebd-9016-202608250306','9e3bc6d4-1f40-4ebd-9017-202608250307','9e3bc6d4-1f40-4ebd-9018-202608250308','9e3bc6d4-1f40-4ebd-9019-202608250309','9e3bc6d4-1f40-4ebd-9020-202608250310');

    IF v_rule_count = 10 AND v_revision_count = 10 THEN
        IF (SELECT COUNT(*) FROM `ledger_journal_rules` r JOIN `ledger_accounts` a ON a.id=r.account_id
            WHERE r.rule_code IN ('PE_DEBIT_FUEL','PE_DEBIT_PARKING','PE_DEBIT_TOLL','PE_DEBIT_ACCOMMODATION','PE_DEBIT_COMMUNICATION','PE_DEBIT_ENTERTAINMENT','PE_DEBIT_OFFICE_SUPPLIES','PE_DEBIT_FREIGHT','PE_DEBIT_EQUIPMENT_RENTAL','PE_DEBIT_OTHER')
              AND r.company_id=v_company_id AND r.origin_code='USER' AND r.rule_status='ACTIVE' AND r.is_active=1
              AND r.business_unit='CONSTRUCTION' AND r.operation_type='PERSONAL_EXPENSE' AND r.transaction_direction='OUT' AND r.client_type IS NULL
              AND r.import_type='EMPLOYEE_EXPENSE_PERSONAL' AND r.source_type='PERSONAL_EXPENSE_ITEM' AND r.source_line_type='ITEM'
              AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.amount_policy_code='SOURCE_AMOUNT'
              AND r.effective_from='2013-07-19' AND r.effective_to IS NULL AND r.priority_no=100 AND r.revision_no=1
              AND r.debit_account_id IS NULL AND r.credit_account_id IS NULL AND r.vat_account_id IS NULL
              AND r.condition_hash=SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"',v_company_id,'","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"',r.item_code,'","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'),256)
              AND ((r.item_code IN ('FUEL','PARKING','TOLL') AND a.account_code='551130') OR (r.item_code='ACCOMMODATION' AND a.account_code='551040') OR (r.item_code='COMMUNICATION' AND a.account_code='551230') OR (r.item_code='ENTERTAINMENT' AND a.account_code='551060') OR (r.item_code='OFFICE_SUPPLIES' AND a.account_code='551360') OR (r.item_code='FREIGHT' AND a.account_code='551240') OR (r.item_code='EQUIPMENT_RENTAL' AND a.account_code='551050') OR (r.item_code='OTHER' AND a.account_code='551380'))) <> 10
           OR (SELECT COUNT(*) FROM `ledger_journal_rule_revisions` rv JOIN `ledger_journal_rules` r ON r.id=rv.rule_id WHERE r.rule_code IN ('PE_DEBIT_FUEL','PE_DEBIT_PARKING','PE_DEBIT_TOLL','PE_DEBIT_ACCOMMODATION','PE_DEBIT_COMMUNICATION','PE_DEBIT_ENTERTAINMENT','PE_DEBIT_OFFICE_SUPPLIES','PE_DEBIT_FREIGHT','PE_DEBIT_EQUIPMENT_RENTAL','PE_DEBIT_OTHER') AND rv.action_code='CREATE' AND rv.revision_no=1 AND rv.before_snapshot IS NULL AND JSON_VALID(rv.after_snapshot) AND JSON_UNQUOTE(JSON_EXTRACT(rv.after_snapshot,'$.condition_hash'))=r.condition_hash) <> 10 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Coverage Seed Payload 또는 CREATE Revision이 공식 계약과 다릅니다.';
        END IF;
        LEAVE seed;
    END IF;
    IF v_rule_count <> 0 OR v_revision_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Coverage Seed가 부분 적용 또는 충돌 상태입니다.';
    END IF;

    START TRANSACTION;
    SET v_now=NOW();
    SELECT COALESCE(MAX(`sort_no`),0) INTO v_base_sort FROM `ledger_journal_rules` FOR UPDATE;
    INSERT INTO `ledger_journal_rules` (`id`,`company_id`,`sort_no`,`rule_code`,`rule_name`,`business_unit`,`operation_type`,`transaction_direction`,`client_type`,`import_type`,`source_type`,`source_line_type`,`item_code`,`condition_hash`,`origin_code`,`rule_status`,`accounting_role_code`,`debit_credit`,`account_id`,`amount_policy_code`,`is_locked`,`auto_apply_enabled`,`effective_from`,`effective_to`,`priority_no`,`revision_no`,`debit_account_id`,`credit_account_id`,`vat_account_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
    SELECT x.id,v_company_id,v_base_sort+x.seq,x.rule_code,x.rule_name,'CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM',x.item_code,SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"',v_company_id,'","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"',x.item_code,'","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'),256),'USER','ACTIVE','EXPENSE','DEBIT',a.id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,x.description,1,v_now,v_actor,v_now,v_actor
    FROM (
        SELECT 1 seq,'9e3bc6d4-1f40-4ebd-9011-202608250301' id,'PE_DEBIT_FUEL' rule_code,'개인경비 유류비 차변' rule_name,'FUEL' item_code,'551130' account_code,'차량 운행 유류비의 기본 판관비 계정' description
        UNION ALL SELECT 2,'9e3bc6d4-1f40-4ebd-9012-202608250302','PE_DEBIT_PARKING','개인경비 주차비 차변','PARKING','551130','차량 운행 주차비의 기본 판관비 계정'
        UNION ALL SELECT 3,'9e3bc6d4-1f40-4ebd-9013-202608250303','PE_DEBIT_TOLL','개인경비 통행료 차변','TOLL','551130','차량 운행 통행료의 기본 판관비 계정'
        UNION ALL SELECT 4,'9e3bc6d4-1f40-4ebd-9014-202608250304','PE_DEBIT_ACCOMMODATION','개인경비 숙박비 차변','ACCOMMODATION','551040','출장 숙박비의 기본 판관비 계정'
        UNION ALL SELECT 5,'9e3bc6d4-1f40-4ebd-9015-202608250305','PE_DEBIT_COMMUNICATION','개인경비 통신비 차변','COMMUNICATION','551230','통신비의 기본 판관비 계정'
        UNION ALL SELECT 6,'9e3bc6d4-1f40-4ebd-9016-202608250306','PE_DEBIT_ENTERTAINMENT','개인경비 접대비 차변','ENTERTAINMENT','551060','접대비의 기본 판관비 계정'
        UNION ALL SELECT 7,'9e3bc6d4-1f40-4ebd-9017-202608250307','PE_DEBIT_OFFICE_SUPPLIES','개인경비 사무용품비 차변','OFFICE_SUPPLIES','551360','사무용품비의 기본 판관비 계정'
        UNION ALL SELECT 8,'9e3bc6d4-1f40-4ebd-9018-202608250308','PE_DEBIT_FREIGHT','개인경비 운반비 차변','FREIGHT','551240','운반비의 기본 판관비 계정'
        UNION ALL SELECT 9,'9e3bc6d4-1f40-4ebd-9019-202608250309','PE_DEBIT_EQUIPMENT_RENTAL','개인경비 장비사용료 차변','EQUIPMENT_RENTAL','551050','장비 임차·사용료의 기본 판관비 계정'
        UNION ALL SELECT 10,'9e3bc6d4-1f40-4ebd-9020-202608250310','PE_DEBIT_OTHER','개인경비 기타경비 차변','OTHER','551380','공식 기타경비 분류의 기본 판관비 계정'
    ) x JOIN `ledger_accounts` a ON a.account_code=x.account_code AND a.is_active=1 AND COALESCE(a.is_posting,1)=1 AND a.deleted_at IS NULL;
    IF ROW_COUNT() <> 10 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Coverage Rule 10건 생성에 실패했습니다.'; END IF;

    INSERT INTO `ledger_journal_rule_revisions` (`id`,`company_id`,`rule_id`,`revision_no`,`action_code`,`before_snapshot`,`after_snapshot`,`change_reason`,`policy_revision`,`created_at`,`created_by`)
    SELECT CONCAT('a4fc56d1-82b0-4aa1-',LPAD(9010+ROW_NUMBER() OVER (ORDER BY r.sort_no),4,'0'),'-2026082503',LPAD(ROW_NUMBER() OVER (ORDER BY r.sort_no),2,'0')),r.company_id,r.id,1,'CREATE',NULL,
      JSON_OBJECT('account_id',r.account_id,'accounting_role_code',r.accounting_role_code,'amount_policy_code',r.amount_policy_code,'auto_apply_enabled',r.auto_apply_enabled,'business_unit',r.business_unit,'client_type',r.client_type,'company_id',r.company_id,'condition_hash',r.condition_hash,'created_at',DATE_FORMAT(r.created_at,'%Y-%m-%d %H:%i:%s'),'created_by',r.created_by,'credit_account_id',r.credit_account_id,'debit_account_id',r.debit_account_id,'debit_credit',r.debit_credit,'description',r.description,'effective_from',DATE_FORMAT(r.effective_from,'%Y-%m-%d'),'effective_to',r.effective_to,'id',r.id,'import_type',r.import_type,'is_active',r.is_active,'is_locked',r.is_locked,'item_code',r.item_code,'operation_type',r.operation_type,'origin_code',r.origin_code,'priority_no',r.priority_no,'revision_no',1,'rule_code',r.rule_code,'rule_name',r.rule_name,'rule_status',r.rule_status,'sort_no',r.sort_no,'source_line_type',r.source_line_type,'source_type',r.source_type,'transaction_direction',r.transaction_direction,'updated_at',NULL,'updated_by',r.updated_by,'vat_account_id',r.vat_account_id),
      '20260825_03 개인경비 공식 분류 Coverage 기본 Rule Seed',NULL,v_now,v_actor
    FROM `ledger_journal_rules` r WHERE r.id IN ('9e3bc6d4-1f40-4ebd-9011-202608250301','9e3bc6d4-1f40-4ebd-9012-202608250302','9e3bc6d4-1f40-4ebd-9013-202608250303','9e3bc6d4-1f40-4ebd-9014-202608250304','9e3bc6d4-1f40-4ebd-9015-202608250305','9e3bc6d4-1f40-4ebd-9016-202608250306','9e3bc6d4-1f40-4ebd-9017-202608250307','9e3bc6d4-1f40-4ebd-9018-202608250308','9e3bc6d4-1f40-4ebd-9019-202608250309','9e3bc6d4-1f40-4ebd-9020-202608250310');
    IF ROW_COUNT() <> 10 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Coverage CREATE Revision 10건 생성에 실패했습니다.'; END IF;
    COMMIT;
END$$
DELIMITER ;
CALL `migrate_20260825_03_personal_expense_category_coverage`();
DROP PROCEDURE `migrate_20260825_03_personal_expense_category_coverage`;
