DROP PROCEDURE IF EXISTS `migrate_20260825_02_personal_rule_seed`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260825_02_personal_rule_seed`()
seed: BEGIN
    DECLARE v_company_id varchar(50);
    DECLARE v_actor varchar(100);
    DECLARE v_now datetime;
    DECLARE v_base_sort int DEFAULT 0;
    DECLARE v_existing_rules int DEFAULT 0;
    DECLARE v_existing_revisions int DEFAULT 0;
    DECLARE v_hash_taxes char(64);
    DECLARE v_hash_fees char(64);
    DECLARE v_hash_supplies char(64);
    DECLARE v_hash_transportation char(64);
    DECLARE v_hash_meal char(64);
    DECLARE v_hash_credit char(64);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET v_actor = NULLIF(TRIM(COALESCE(@personal_expense_journal_rule_seed_actor, '')), '');
    IF v_actor IS NULL OR v_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 @personal_expense_journal_rule_seed_actor가 필요합니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `system_company`) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seed 대상 회사가 정확히 1건이어야 합니다.';
    END IF;
    SELECT `id` INTO v_company_id FROM `system_company` LIMIT 1;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('source_type','source_line_type','item_code') AND IS_NULLABLE='YES') <> 3
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('debit_account_id','credit_account_id','vat_account_id') AND IS_NULLABLE='YES') <> 3
       OR (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rule_revisions') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='역할형 분개규칙 및 Revision 선행 구조가 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='JOURNAL_ACCOUNTING_ROLE' AND `is_active`=1) <> 7
       OR (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='JOURNAL_ACCOUNTING_ROLE' AND `code` IN ('EXPENSE','EMPLOYEE_ACCRUED_EXPENSE') AND `is_active`=1) <> 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='역할 Registry 선행조건이 승인 상태와 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `system_codes` WHERE `code_group`='PERSONAL_EXPENSE_CATEGORY' AND `code` IN ('TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES','TRANSPORTATION','MEAL') AND `is_active`=1) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 공식 비용분류 선행조건이 충족되지 않았습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `ledger_accounts` WHERE `account_code` IN ('551091','551200','551220','551040','551030','216100') AND `is_active`=1 AND COALESCE(`is_posting`,1)=1 AND `deleted_at` IS NULL) <> 6
       OR (SELECT COUNT(DISTINCT `account_code`) FROM `ledger_accounts` WHERE `account_code` IN ('551091','551200','551220','551040','551030','216100') AND `is_active`=1 AND COALESCE(`is_posting`,1)=1 AND `deleted_at` IS NULL) <> 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='대상 계정코드 6건이 각각 정확히 1건·활성·전기 가능이어야 합니다.';
    END IF;

    SET v_hash_taxes = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"TAXES_AND_DUES","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);
    SET v_hash_fees = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"FEES_AND_COMMISSIONS","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);
    SET v_hash_supplies = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"SUPPLIES","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);
    SET v_hash_transportation = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"TRANSPORTATION","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);
    SET v_hash_meal = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"MEAL","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);
    SET v_hash_credit = SHA2(CONCAT('{"business_unit":"CONSTRUCTION","client_type":"","company_id":"', v_company_id, '","import_type":"EMPLOYEE_EXPENSE_PERSONAL","item_code":"","operation_type":"PERSONAL_EXPENSE","source_line_type":"ITEM","source_type":"PERSONAL_EXPENSE_ITEM","transaction_direction":"OUT"}'), 256);

    SELECT COUNT(*) INTO v_existing_rules FROM `ledger_journal_rules` WHERE `rule_code` IN ('PE_DEBIT_TAXES_AND_DUES','PE_DEBIT_FEES_AND_COMMISSIONS','PE_DEBIT_SUPPLIES','PE_DEBIT_TRANSPORTATION','PE_DEBIT_MEAL','PE_CREDIT_EMPLOYEE_ACCRUED');
    SELECT COUNT(*) INTO v_existing_revisions FROM `ledger_journal_rule_revisions` WHERE `rule_id` IN ('9e3bc6d4-1f40-4ebd-9001-202608250201','9e3bc6d4-1f40-4ebd-9002-202608250202','9e3bc6d4-1f40-4ebd-9003-202608250203','9e3bc6d4-1f40-4ebd-9004-202608250204','9e3bc6d4-1f40-4ebd-9005-202608250205','9e3bc6d4-1f40-4ebd-9006-202608250206');

    IF v_existing_rules = 6 AND v_existing_revisions = 6 THEN
        IF (SELECT COUNT(*) FROM `ledger_journal_rules` r JOIN `ledger_accounts` a ON a.id=r.account_id WHERE r.company_id=v_company_id AND r.origin_code='USER' AND r.rule_status='ACTIVE' AND r.is_active=1 AND r.business_unit='CONSTRUCTION' AND r.operation_type='PERSONAL_EXPENSE' AND r.transaction_direction='OUT' AND r.client_type IS NULL AND r.import_type='EMPLOYEE_EXPENSE_PERSONAL' AND r.source_type='PERSONAL_EXPENSE_ITEM' AND r.source_line_type='ITEM' AND r.effective_from='2013-07-19' AND r.effective_to IS NULL AND r.priority_no=100 AND r.revision_no=1 AND r.debit_account_id IS NULL AND r.credit_account_id IS NULL AND r.vat_account_id IS NULL AND ((r.rule_code='PE_DEBIT_TAXES_AND_DUES' AND r.rule_name='개인경비 세금과공과 차변' AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.item_code='TAXES_AND_DUES' AND r.condition_hash=v_hash_taxes AND a.account_code='551091') OR (r.rule_code='PE_DEBIT_FEES_AND_COMMISSIONS' AND r.rule_name='개인경비 지급수수료 차변' AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.item_code='FEES_AND_COMMISSIONS' AND r.condition_hash=v_hash_fees AND a.account_code='551200') OR (r.rule_code='PE_DEBIT_SUPPLIES' AND r.rule_name='개인경비 소모품비 차변' AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.item_code='SUPPLIES' AND r.condition_hash=v_hash_supplies AND a.account_code='551220') OR (r.rule_code='PE_DEBIT_TRANSPORTATION' AND r.rule_name='개인경비 여비교통비 차변' AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.item_code='TRANSPORTATION' AND r.condition_hash=v_hash_transportation AND a.account_code='551040') OR (r.rule_code='PE_DEBIT_MEAL' AND r.rule_name='개인경비 복리후생비 차변' AND r.accounting_role_code='EXPENSE' AND r.debit_credit='DEBIT' AND r.item_code='MEAL' AND r.condition_hash=v_hash_meal AND a.account_code='551030') OR (r.rule_code='PE_CREDIT_EMPLOYEE_ACCRUED' AND r.rule_name='개인경비 직원미지급비용 대변' AND r.accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE' AND r.debit_credit='CREDIT' AND r.item_code IS NULL AND r.condition_hash=v_hash_credit AND a.account_code='216100'))) <> 6
           OR (SELECT COUNT(*) FROM `ledger_journal_rule_revisions` rv JOIN `ledger_journal_rules` r ON r.id=rv.rule_id WHERE rv.action_code='CREATE' AND rv.revision_no=1 AND rv.before_snapshot IS NULL AND JSON_VALID(rv.after_snapshot) AND JSON_UNQUOTE(JSON_EXTRACT(rv.after_snapshot,'$.rule_code'))=r.rule_code AND JSON_UNQUOTE(JSON_EXTRACT(rv.after_snapshot,'$.condition_hash'))=r.condition_hash) <> 6 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Seed Rule 또는 CREATE Revision Payload가 승인값과 충돌합니다.';
        END IF;
        LEAVE seed;
    END IF;
    IF v_existing_rules <> 0 OR v_existing_revisions <> 0 OR (SELECT COUNT(*) FROM `ledger_journal_rules`) <> 0 OR (SELECT COUNT(*) FROM `ledger_journal_rule_revisions`) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Rule·Revision이 빈 상태도 완전한 동일 Seed 상태도 아닙니다.';
    END IF;
    IF (SELECT COUNT(*) FROM `ledger_journal_rules` WHERE `condition_hash` IN (v_hash_taxes,v_hash_fees,v_hash_supplies,v_hash_transportation,v_hash_meal,v_hash_credit)) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='다른 규칙이 승인 조건 hash를 이미 사용합니다.';
    END IF;

    START TRANSACTION;
    SET v_now = NOW();
    SELECT COALESCE(MAX(`sort_no`),0) INTO v_base_sort FROM `ledger_journal_rules` FOR UPDATE;
    INSERT INTO `ledger_journal_rules` (`id`,`company_id`,`sort_no`,`rule_code`,`rule_name`,`business_unit`,`operation_type`,`transaction_direction`,`client_type`,`import_type`,`source_type`,`source_line_type`,`item_code`,`condition_hash`,`origin_code`,`rule_status`,`accounting_role_code`,`debit_credit`,`account_id`,`amount_policy_code`,`is_locked`,`auto_apply_enabled`,`effective_from`,`effective_to`,`priority_no`,`revision_no`,`debit_account_id`,`credit_account_id`,`vat_account_id`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
    SELECT '9e3bc6d4-1f40-4ebd-9001-202608250201',v_company_id,v_base_sort+1,'PE_DEBIT_TAXES_AND_DUES','개인경비 세금과공과 차변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','TAXES_AND_DUES',v_hash_taxes,'USER','ACTIVE','EXPENSE','DEBIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='551091' AND is_active=1 AND deleted_at IS NULL
    UNION ALL SELECT '9e3bc6d4-1f40-4ebd-9002-202608250202',v_company_id,v_base_sort+2,'PE_DEBIT_FEES_AND_COMMISSIONS','개인경비 지급수수료 차변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','FEES_AND_COMMISSIONS',v_hash_fees,'USER','ACTIVE','EXPENSE','DEBIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='551200' AND is_active=1 AND deleted_at IS NULL
    UNION ALL SELECT '9e3bc6d4-1f40-4ebd-9003-202608250203',v_company_id,v_base_sort+3,'PE_DEBIT_SUPPLIES','개인경비 소모품비 차변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','SUPPLIES',v_hash_supplies,'USER','ACTIVE','EXPENSE','DEBIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='551220' AND is_active=1 AND deleted_at IS NULL
    UNION ALL SELECT '9e3bc6d4-1f40-4ebd-9004-202608250204',v_company_id,v_base_sort+4,'PE_DEBIT_TRANSPORTATION','개인경비 여비교통비 차변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','TRANSPORTATION',v_hash_transportation,'USER','ACTIVE','EXPENSE','DEBIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='551040' AND is_active=1 AND deleted_at IS NULL
    UNION ALL SELECT '9e3bc6d4-1f40-4ebd-9005-202608250205',v_company_id,v_base_sort+5,'PE_DEBIT_MEAL','개인경비 복리후생비 차변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','MEAL',v_hash_meal,'USER','ACTIVE','EXPENSE','DEBIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='551030' AND is_active=1 AND deleted_at IS NULL
    UNION ALL SELECT '9e3bc6d4-1f40-4ebd-9006-202608250206',v_company_id,v_base_sort+6,'PE_CREDIT_EMPLOYEE_ACCRUED','개인경비 직원미지급비용 대변','CONSTRUCTION','PERSONAL_EXPENSE','OUT',NULL,'EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM',NULL,v_hash_credit,'USER','ACTIVE','EMPLOYEE_ACCRUED_EXPENSE','CREDIT',id,'SOURCE_AMOUNT',0,0,'2013-07-19',NULL,100,1,NULL,NULL,NULL,NULL,1,v_now,v_actor,v_now,v_actor FROM ledger_accounts WHERE account_code='216100' AND is_active=1 AND deleted_at IS NULL;
    IF ROW_COUNT() <> 6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seed Rule 6건 생성에 실패했습니다.'; END IF;

    INSERT INTO `ledger_journal_rule_revisions` (`id`,`company_id`,`rule_id`,`revision_no`,`action_code`,`before_snapshot`,`after_snapshot`,`change_reason`,`policy_revision`,`created_at`,`created_by`)
    SELECT CASE r.rule_code
        WHEN 'PE_DEBIT_TAXES_AND_DUES' THEN 'a4fc56d1-82b0-4aa1-9001-202608250201'
        WHEN 'PE_DEBIT_FEES_AND_COMMISSIONS' THEN 'a4fc56d1-82b0-4aa1-9002-202608250202'
        WHEN 'PE_DEBIT_SUPPLIES' THEN 'a4fc56d1-82b0-4aa1-9003-202608250203'
        WHEN 'PE_DEBIT_TRANSPORTATION' THEN 'a4fc56d1-82b0-4aa1-9004-202608250204'
        WHEN 'PE_DEBIT_MEAL' THEN 'a4fc56d1-82b0-4aa1-9005-202608250205'
        WHEN 'PE_CREDIT_EMPLOYEE_ACCRUED' THEN 'a4fc56d1-82b0-4aa1-9006-202608250206'
      END,r.company_id,r.id,1,'CREATE',NULL,
      JSON_OBJECT('account_id',r.account_id,'accounting_role_code',r.accounting_role_code,'amount_policy_code',r.amount_policy_code,'auto_apply_enabled',r.auto_apply_enabled,'business_unit',r.business_unit,'client_type',r.client_type,'company_id',r.company_id,'condition_hash',r.condition_hash,'confidence_score',r.confidence_score,'created_at',DATE_FORMAT(r.created_at,'%Y-%m-%d %H:%i:%s'),'created_by',r.created_by,'credit_account_id',r.credit_account_id,'debit_account_id',r.debit_account_id,'debit_credit',r.debit_credit,'deleted_at',r.deleted_at,'deleted_by',r.deleted_by,'description',r.description,'effective_from',DATE_FORMAT(r.effective_from,'%Y-%m-%d'),'effective_to',r.effective_to,'id',r.id,'import_type',r.import_type,'is_active',r.is_active,'is_locked',r.is_locked,'item_code',r.item_code,'last_used_at',r.last_used_at,'operation_type',r.operation_type,'origin_code',r.origin_code,'parent_rule_id',r.parent_rule_id,'policy_revision',r.policy_revision,'priority_no',r.priority_no,'revision_no',1,'rule_code',r.rule_code,'rule_name',r.rule_name,'rule_status',r.rule_status,'sort_no',r.sort_no,'source_line_type',r.source_line_type,'source_type',r.source_type,'transaction_direction',r.transaction_direction,'updated_at',NULL,'updated_by',r.updated_by,'usage_count',r.usage_count,'vat_account_id',r.vat_account_id),
      '20260825_02 개인경비 역할형 USER 분개규칙 공식 Seed',NULL,v_now,v_actor
    FROM `ledger_journal_rules` r WHERE r.id IN ('9e3bc6d4-1f40-4ebd-9001-202608250201','9e3bc6d4-1f40-4ebd-9002-202608250202','9e3bc6d4-1f40-4ebd-9003-202608250203','9e3bc6d4-1f40-4ebd-9004-202608250204','9e3bc6d4-1f40-4ebd-9005-202608250205','9e3bc6d4-1f40-4ebd-9006-202608250206');
    IF ROW_COUNT() <> 6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CREATE Revision 6건 생성에 실패했습니다.'; END IF;
    COMMIT;
END$$
DELIMITER ;
CALL `migrate_20260825_02_personal_rule_seed`();
DROP PROCEDURE `migrate_20260825_02_personal_rule_seed`;
