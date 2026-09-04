DROP PROCEDURE IF EXISTS migrate_20260902_41_salary_evidence_ssot;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_41_salary_evidence_ssot()
procedure_body: BEGIN
    DECLARE v_existing INT DEFAULT 0;
    IF (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND TABLE_TYPE='BASE TABLE')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence 테이블을 찾을 수 없습니다.';
    END IF;
    SELECT COUNT(*) INTO v_existing FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report'
       AND COLUMN_NAME IN ('source_document_id','source_item_id','business_key_hash','work_team_id',
                           'raw_gross_payment_amount','raw_worker_deduction_amount','snapshot_json','snapshot_version',
                           'snapshot_origin_code','source_hash','reconstruction_hash','calculation_version');
    IF v_existing=12 THEN LEAVE procedure_body; END IF;
    IF v_existing<>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용급여 Evidence SSOT 컬럼이 부분 적용된 상태입니다.';
    END IF;
    ALTER TABLE ledger_evidence_salary_report
        ADD COLUMN source_document_id VARCHAR(36) NULL COMMENT '상용근로소득 원 문서 ID' AFTER source_regular_employment_income_id,
        ADD COLUMN source_item_id VARCHAR(36) NULL COMMENT '상용근로소득 원 직원 Item ID' AFTER regular_employment_income_item_id,
        ADD COLUMN business_key_hash CHAR(64) NULL COMMENT '승인문서와 직원 Item Grain 멱등키 Hash' AFTER source_item_id,
        ADD COLUMN work_team_id VARCHAR(36) NULL COMMENT '정규화된 작업팀 ID' AFTER team_id,
        ADD COLUMN raw_gross_payment_amount DECIMAL(18,2) NULL COMMENT '승인된 공제 전 지급액 원천 사실' AFTER raw_gross_amount,
        ADD COLUMN raw_worker_deduction_amount DECIMAL(18,2) NULL COMMENT '승인된 근로자 공제액 원천 사실' AFTER raw_deduction_amount,
        ADD COLUMN snapshot_json LONGTEXT NULL COMMENT '신규 승인 당시 불변 Snapshot' AFTER raw_description,
        ADD COLUMN snapshot_version SMALLINT UNSIGNED NULL COMMENT 'Snapshot 계약 버전' AFTER snapshot_json,
        ADD COLUMN snapshot_origin_code VARCHAR(30) NULL COMMENT 'Snapshot 생성 원천 코드' AFTER snapshot_version,
        ADD COLUMN source_hash CHAR(64) NULL COMMENT '신규 승인 당시 원천 Snapshot Hash' AFTER snapshot_origin_code,
        ADD COLUMN reconstruction_hash CHAR(64) NULL COMMENT '사후 재구성 검증 Hash' AFTER source_hash,
        ADD COLUMN calculation_version VARCHAR(30) NULL COMMENT '적용 계산정책 버전' AFTER reconstruction_hash,
        ADD UNIQUE KEY uk_salary_report_business_key_hash (business_key_hash),
        ADD KEY idx_salary_report_work_team (work_team_id);
END$$
DELIMITER ;
CALL migrate_20260902_41_salary_evidence_ssot();
DROP PROCEDURE migrate_20260902_41_salary_evidence_ssot;
