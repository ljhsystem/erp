DROP PROCEDURE IF EXISTS migrate_20260902_45_daily_evidence_common_columns;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_45_daily_evidence_common_columns()
procedure_body: BEGIN
    DECLARE v_existing INT DEFAULT 0;
    IF (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND TABLE_TYPE='BASE TABLE')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용근로소득 Evidence 테이블을 찾을 수 없습니다.';
    END IF;
    SELECT COUNT(*) INTO v_existing FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income'
       AND COLUMN_NAME IN ('sort_no','external_key','source_type','import_type','client_id','employee_id',
                           'bank_account_id','card_id','raw_business_unit','raw_project_id','raw_work_team_id',
                           'deleted_at','deleted_by');
    IF v_existing=13 THEN LEAVE procedure_body; END IF;
    IF v_existing<>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='일용 Evidence 공통컬럼이 부분 적용된 상태입니다.';
    END IF;
    ALTER TABLE ledger_evidence_daily_employment_income
        ADD COLUMN sort_no INT UNSIGNED NULL COMMENT '자료유형 내부 표시 순서' AFTER id,
        ADD COLUMN external_key VARCHAR(120) NULL COMMENT '승인원본의 표준 식별값' AFTER sort_no,
        ADD COLUMN source_type VARCHAR(40) NULL COMMENT '자료출처' AFTER external_key,
        ADD COLUMN import_type VARCHAR(30) NULL COMMENT '자료유형' AFTER source_type,
        ADD COLUMN client_id VARCHAR(36) NULL COMMENT '정규화된 근로자 거래처 ID' AFTER worker_client_id,
        ADD COLUMN employee_id VARCHAR(36) NULL COMMENT '정규화된 직원 ID' AFTER client_id,
        ADD COLUMN bank_account_id VARCHAR(36) NULL COMMENT '정규화된 계좌 ID' AFTER project_id,
        ADD COLUMN card_id VARCHAR(36) NULL COMMENT '정규화된 카드 ID' AFTER bank_account_id,
        ADD COLUMN raw_business_unit VARCHAR(30) NULL COMMENT '승인 Group의 사업구분 원천 사실' AFTER card_id,
        ADD COLUMN raw_project_id VARCHAR(36) NULL COMMENT '승인 Group의 프로젝트 ID 원천 사실' AFTER raw_business_unit,
        ADD COLUMN raw_work_team_id VARCHAR(36) NULL COMMENT '승인 Group의 작업팀 ID 원천 사실' AFTER raw_project_id,
        ADD COLUMN deleted_at DATETIME NULL COMMENT '삭제 시각' AFTER updated_by,
        ADD COLUMN deleted_by VARCHAR(100) NULL COMMENT '삭제 Actor' AFTER deleted_at,
        ADD KEY idx_daily_evidence_sort_no (sort_no),
        ADD KEY idx_daily_evidence_external_key (external_key),
        ADD KEY idx_daily_evidence_client (client_id);
END$$
DELIMITER ;
CALL migrate_20260902_45_daily_evidence_common_columns();
DROP PROCEDURE migrate_20260902_45_daily_evidence_common_columns;
