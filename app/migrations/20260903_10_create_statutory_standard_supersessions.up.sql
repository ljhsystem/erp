CREATE TABLE system_statutory_standard_supersessions (
    id CHAR(36) NOT NULL,
    predecessor_revision_id CHAR(36) NOT NULL COMMENT '정정 대상 원 Revision',
    successor_revision_id CHAR(36) NOT NULL COMMENT '신규 정정 Revision',
    correction_reason VARCHAR(1000) NOT NULL COMMENT 'Revision 정정 사유',
    created_at DATETIME NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statutory_supersession_predecessor (predecessor_revision_id),
    UNIQUE KEY uq_statutory_supersession_successor (successor_revision_id),
    KEY idx_statutory_supersession_successor (successor_revision_id),
    CONSTRAINT fk_statutory_supersession_predecessor FOREIGN KEY (predecessor_revision_id)
        REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_statutory_supersession_successor FOREIGN KEY (successor_revision_id)
        REFERENCES system_statutory_standards(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_statutory_supersession_distinct CHECK (predecessor_revision_id <> successor_revision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='법정기준 Revision 정정·대체 선형 관계';

DELIMITER $$
CREATE TRIGGER trg_statutory_supersession_bi
BEFORE INSERT ON system_statutory_standard_supersessions
FOR EACH ROW
BEGIN
    DECLARE v_predecessor_type VARCHAR(100);
    DECLARE v_successor_type VARCHAR(100);
    DECLARE v_predecessor_component VARCHAR(50);
    DECLARE v_successor_component VARCHAR(50);
    DECLARE v_predecessor_employment VARCHAR(50);
    DECLARE v_successor_employment VARCHAR(50);
    DECLARE v_predecessor_scope VARCHAR(50);
    DECLARE v_successor_scope VARCHAR(50);
    DECLARE v_predecessor_dimension CHAR(64);
    DECLARE v_successor_dimension CHAR(64);
    DECLARE v_cursor CHAR(36);
    DECLARE v_next CHAR(36);
    DECLARE v_depth INT DEFAULT 0;

    IF NEW.predecessor_revision_id = NEW.successor_revision_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 Revision은 자기 자신을 대체할 수 없습니다.';
    END IF;

    SELECT standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_key
      INTO v_predecessor_type,v_predecessor_component,v_predecessor_employment,v_predecessor_scope,v_predecessor_dimension
      FROM system_statutory_standards WHERE id=NEW.predecessor_revision_id FOR UPDATE;
    SELECT standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_key
      INTO v_successor_type,v_successor_component,v_successor_employment,v_successor_scope,v_successor_dimension
      FROM system_statutory_standards WHERE id=NEW.successor_revision_id FOR UPDATE;

    IF NOT (v_predecessor_type <=> v_successor_type)
       OR NOT (v_predecessor_component <=> v_successor_component)
       OR NOT (v_predecessor_employment <=> v_successor_employment)
       OR NOT (v_predecessor_scope <=> v_successor_scope)
       OR NOT (v_predecessor_dimension <=> v_successor_dimension) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='동일한 법정기준 Type과 Scope Revision만 대체할 수 있습니다.';
    END IF;

    SET v_cursor=NEW.successor_revision_id;
    chain_loop: LOOP
        IF v_cursor=NEW.predecessor_revision_id THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 Revision 대체 관계에 cycle을 만들 수 없습니다.';
        END IF;
        SET v_depth=v_depth+1;
        IF v_depth>1000 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 Revision 대체 체인이 허용 길이를 초과했습니다.';
        END IF;
        SET v_next=NULL;
        SELECT successor_revision_id INTO v_next
          FROM system_statutory_standard_supersessions
         WHERE predecessor_revision_id=v_cursor LIMIT 1;
        IF v_next IS NULL THEN
            LEAVE chain_loop;
        END IF;
        SET v_cursor=v_next;
    END LOOP;
END$$

CREATE TRIGGER trg_statutory_supersession_bu
BEFORE UPDATE ON system_statutory_standard_supersessions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision 대체 관계는 수정할 수 없습니다.';
END$$

CREATE TRIGGER trg_statutory_supersession_bd
BEFORE DELETE ON system_statutory_standard_supersessions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision 대체 관계는 삭제할 수 없습니다.';
END$$
DELIMITER ;
