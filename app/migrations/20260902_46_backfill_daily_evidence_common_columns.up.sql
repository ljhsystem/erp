DROP PROCEDURE IF EXISTS migrate_20260902_46_daily_evidence_common_backfill;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_46_daily_evidence_common_backfill()
BEGIN
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_daily_employment_income evidence
        LEFT JOIN institution_daily_employment_income_groups income_group
          ON income_group.id=evidence.daily_employment_income_group_id
         AND income_group.daily_employment_income_id=evidence.source_daily_employment_income_id
        LEFT JOIN institution_daily_employment_income_items item
          ON item.id=evidence.daily_employment_income_item_id
         AND item.daily_employment_income_group_id=evidence.daily_employment_income_group_id
        WHERE income_group.id IS NULL OR item.id IS NULL OR item.worker_client_id<>evidence.worker_client_id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 원 업무 연결이 불일치합니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_daily_employment_income
        WHERE (sort_no IS NULL)<>(external_key IS NULL)
           OR (sort_no IS NULL)<>(source_type IS NULL)
           OR (sort_no IS NULL)<>(import_type IS NULL)
           OR (sort_no IS NULL)<>(client_id IS NULL)
           OR (sort_no IS NULL)<>(raw_business_unit IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 공통값이 부분 backfill된 상태입니다.';
    END IF;
    UPDATE ledger_evidence_daily_employment_income evidence
    JOIN institution_daily_employment_income_groups income_group
      ON income_group.id=evidence.daily_employment_income_group_id
    JOIN institution_daily_employment_income_items item
      ON item.id=evidence.daily_employment_income_item_id
    SET evidence.sort_no=item.sort_no,
        evidence.external_key=CONCAT('DEI:',evidence.business_key_hash),
        evidence.source_type='APPROVAL',
        evidence.import_type='DAILY_EMPLOYMENT_INCOME',
        evidence.client_id=evidence.worker_client_id,
        evidence.employee_id=NULL,
        evidence.bank_account_id=NULL,
        evidence.card_id=NULL,
        evidence.raw_business_unit=income_group.business_unit,
        evidence.raw_project_id=income_group.project_id,
        evidence.raw_work_team_id=income_group.work_team_id
    WHERE evidence.sort_no IS NULL;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_daily_employment_income evidence
        JOIN institution_daily_employment_income_groups income_group ON income_group.id=evidence.daily_employment_income_group_id
        WHERE evidence.sort_no IS NULL OR evidence.external_key<>CONCAT('DEI:',evidence.business_key_hash)
           OR evidence.source_type<>'APPROVAL' OR evidence.import_type<>'DAILY_EMPLOYMENT_INCOME'
           OR evidence.client_id<>evidence.worker_client_id OR evidence.employee_id IS NOT NULL
           OR evidence.bank_account_id IS NOT NULL OR evidence.card_id IS NOT NULL
           OR evidence.raw_business_unit<>income_group.business_unit
           OR NOT (evidence.raw_project_id<=>income_group.project_id)
           OR NOT (evidence.raw_work_team_id<=>income_group.work_team_id)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 공통컬럼 backfill 후 대사가 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL migrate_20260902_46_daily_evidence_common_backfill();
DROP PROCEDURE migrate_20260902_46_daily_evidence_common_backfill;
