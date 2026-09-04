DROP PROCEDURE IF EXISTS migrate_20260902_44_personal_expense_evidence_backfill;
DELIMITER $$
CREATE PROCEDURE migrate_20260902_44_personal_expense_evidence_backfill()
BEGIN
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_employee_personal_expense evidence
        LEFT JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id
        LEFT JOIN approval_personal_expenses header ON header.id=item.personal_expense_id
        LEFT JOIN user_approval_requests request
          ON request.id=header.current_approval_request_id
         AND request.document_type='PERSONAL_EXPENSE' AND request.document_id=header.id
        WHERE item.id IS NULL OR header.id IS NULL OR request.id IS NULL OR request.status<>'approved'
           OR (SELECT COUNT(*) FROM user_approval_request_steps final_step
               WHERE final_step.request_id=request.id AND final_step.step_type='FINAL_APPROVAL'
                 AND final_step.status='approved' AND final_step.is_active=1
                 AND final_step.action_at IS NOT NULL AND final_step.acted_by IS NOT NULL)<>1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MANUAL_REVIEW_REQUIRED: 개인경비 Evidence 승인 연결을 결정할 수 없습니다.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_employee_personal_expense
        WHERE (source_document_id IS NULL)<>(source_item_id IS NULL)
           OR (source_document_id IS NULL)<>(approval_request_id IS NULL)
           OR (source_document_id IS NULL)<>(business_key_hash IS NULL)
           OR (source_document_id IS NULL)<>(approved_at IS NULL)
           OR (source_document_id IS NULL)<>(approved_by IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 Evidence 핵심 SSOT 값이 부분 backfill된 상태입니다.';
    END IF;
    UPDATE ledger_evidence_employee_personal_expense evidence
    JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id
    JOIN approval_personal_expenses header ON header.id=item.personal_expense_id
    JOIN user_approval_requests request
      ON request.id=header.current_approval_request_id
     AND request.document_type='PERSONAL_EXPENSE' AND request.document_id=header.id AND request.status='approved'
    JOIN user_approval_request_steps final_step
      ON final_step.request_id=request.id AND final_step.step_type='FINAL_APPROVAL'
     AND final_step.status='approved' AND final_step.is_active=1
    SET evidence.source_document_id=header.id,
        evidence.source_item_id=item.id,
        evidence.approval_request_id=request.id,
        evidence.business_key_hash=SHA2(CONCAT('EMPLOYEE_EXPENSE_PERSONAL|',header.id,'|',item.id),256),
        evidence.work_team_id=evidence.team_id,
        evidence.raw_application_date=header.application_date,
        evidence.raw_project_id=item.project_id,
        evidence.raw_client_id=item.client_id,
        evidence.approved_at=final_step.action_at,
        evidence.approved_by=final_step.acted_by
    WHERE evidence.source_document_id IS NULL;
    IF EXISTS (
        SELECT 1 FROM ledger_evidence_employee_personal_expense evidence
        JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id
        JOIN approval_personal_expenses header ON header.id=item.personal_expense_id
        WHERE evidence.source_document_id<>header.id OR evidence.source_item_id<>item.id
           OR evidence.business_key_hash NOT REGEXP '^[0-9a-f]{64}$'
           OR NOT (evidence.work_team_id<=>evidence.team_id)
           OR evidence.raw_application_date<>header.application_date
           OR NOT (evidence.raw_project_id<=>item.project_id)
           OR NOT (evidence.raw_client_id<=>item.client_id)
           OR evidence.approved_at IS NULL OR evidence.approved_by IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='개인경비 Evidence SSOT backfill 후 대사가 실패했습니다.';
    END IF;
END$$
DELIMITER ;
CALL migrate_20260902_44_personal_expense_evidence_backfill();
DROP PROCEDURE migrate_20260902_44_personal_expense_evidence_backfill;
