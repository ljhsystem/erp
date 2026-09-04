DROP PROCEDURE IF EXISTS migrate_20260902_43_personal_expense_evidence_ssot;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_43_personal_expense_evidence_ssot()
procedure_body: BEGIN
    DECLARE v_existing INT DEFAULT 0;
    IF (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_employee_personal_expense' AND TABLE_TYPE='BASE TABLE')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 Evidence 테이블을 찾을 수 없습니다.';
    END IF;
    SELECT COUNT(*) INTO v_existing FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_employee_personal_expense'
       AND COLUMN_NAME IN ('source_document_id','source_item_id','approval_request_id','business_key_hash','work_team_id',
                           'raw_application_date','raw_project_id','raw_client_id','snapshot_json','snapshot_version',
                           'snapshot_origin_code','source_hash','reconstruction_hash','approved_at','approved_by');
    IF v_existing=15 THEN LEAVE procedure_body; END IF;
    IF v_existing<>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 Evidence SSOT 컬럼이 부분 적용된 상태입니다.';
    END IF;
    ALTER TABLE ledger_evidence_employee_personal_expense
        ADD COLUMN source_document_id VARCHAR(36) NULL COMMENT '개인경비 원 신청 문서 ID' AFTER source_personal_expense_item_id,
        ADD COLUMN source_item_id VARCHAR(36) NULL COMMENT '개인경비 원 신청 Item ID' AFTER source_document_id,
        ADD COLUMN approval_request_id VARCHAR(36) NULL COMMENT '원 승인요청 ID' AFTER source_item_id,
        ADD COLUMN business_key_hash CHAR(64) NULL COMMENT '승인문서와 Item Grain 멱등키 Hash' AFTER approval_request_id,
        ADD COLUMN work_team_id VARCHAR(36) NULL COMMENT '정규화된 작업팀 ID' AFTER team_id,
        ADD COLUMN raw_application_date DATE NULL COMMENT '신청 원본의 신청일' AFTER employee_id,
        ADD COLUMN raw_project_id VARCHAR(36) NULL COMMENT '신청 Item 원본 프로젝트 ID' AFTER raw_application_date,
        ADD COLUMN raw_client_id VARCHAR(36) NULL COMMENT '신청 Item 원본 거래처 ID' AFTER raw_project_id,
        ADD COLUMN snapshot_json LONGTEXT NULL COMMENT '신규 승인 당시 불변 Snapshot' AFTER raw_memo,
        ADD COLUMN snapshot_version SMALLINT UNSIGNED NULL COMMENT 'Snapshot 계약 버전' AFTER snapshot_json,
        ADD COLUMN snapshot_origin_code VARCHAR(30) NULL COMMENT 'Snapshot 생성 원천 코드' AFTER snapshot_version,
        ADD COLUMN source_hash CHAR(64) NULL COMMENT '신규 승인 당시 원천 Snapshot Hash' AFTER snapshot_origin_code,
        ADD COLUMN reconstruction_hash CHAR(64) NULL COMMENT '사후 재구성 검증 Hash' AFTER source_hash,
        ADD COLUMN approved_at DATETIME NULL COMMENT '최종승인 시각' AFTER evidence_status,
        ADD COLUMN approved_by VARCHAR(100) NULL COMMENT '최종승인 Actor' AFTER approved_at,
        ADD KEY idx_personal_expense_evidence_approval_request (approval_request_id),
        ADD KEY idx_personal_expense_evidence_work_team (work_team_id),
        ADD UNIQUE KEY uk_personal_expense_evidence_business_key_hash (business_key_hash),
        ADD CONSTRAINT fk_personal_expense_evidence_approval_request
            FOREIGN KEY (approval_request_id) REFERENCES user_approval_requests(id);
END$$
DELIMITER ;
CALL migrate_20260902_43_personal_expense_evidence_ssot();
DROP PROCEDURE migrate_20260902_43_personal_expense_evidence_ssot;
