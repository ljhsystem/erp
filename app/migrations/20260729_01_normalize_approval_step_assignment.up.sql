DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_normalize_approval_step_assignment_up$$
CREATE PROCEDURE migrate_normalize_approval_step_assignment_up()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_approval_template_steps'
    ) OR NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_approval_request_steps'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '전자결재 단계 테이블을 찾을 수 없습니다.';
    END IF;

    ALTER TABLE user_approval_template_steps
        ADD COLUMN IF NOT EXISTS step_type ENUM('SUBMIT', 'APPROVAL', 'FINAL_APPROVAL') NULL
            COMMENT '단계유형' AFTER step_name;

    ALTER TABLE user_approval_request_steps
        ADD COLUMN IF NOT EXISTS step_type ENUM('SUBMIT', 'APPROVAL', 'FINAL_APPROVAL') NULL
            COMMENT '단계유형 스냅샷' AFTER step_name;

    UPDATE user_approval_template_steps
       SET step_type = 'SUBMIT'
     WHERE step_type IS NULL
       AND step_name = '발의';

    UPDATE user_approval_template_steps step_row
    JOIN (
        SELECT template_id, MAX(sort_no) AS final_sort_no
          FROM user_approval_template_steps
         WHERE is_active = 1
           AND step_name <> '발의'
         GROUP BY template_id
    ) final_step
      ON final_step.template_id = step_row.template_id
     AND final_step.final_sort_no = step_row.sort_no
       SET step_row.step_type = 'FINAL_APPROVAL'
     WHERE step_row.step_type IS NULL;

    UPDATE user_approval_template_steps
       SET step_type = 'APPROVAL'
     WHERE step_type IS NULL;

    UPDATE user_approval_request_steps
       SET step_type = 'SUBMIT'
     WHERE step_type IS NULL
       AND step_name = '발의';

    UPDATE user_approval_request_steps step_row
    JOIN (
        SELECT request_id, MAX(sort_no) AS final_sort_no
          FROM user_approval_request_steps
         WHERE is_active = 1
           AND step_name <> '발의'
         GROUP BY request_id
    ) final_step
      ON final_step.request_id = step_row.request_id
     AND final_step.final_sort_no = step_row.sort_no
       SET step_row.step_type = 'FINAL_APPROVAL'
     WHERE step_row.step_type IS NULL;

    UPDATE user_approval_request_steps
       SET step_type = 'APPROVAL'
     WHERE step_type IS NULL;

    IF EXISTS (
        SELECT 1 FROM user_approval_template_steps WHERE step_type IS NULL
    ) OR EXISTS (
        SELECT 1 FROM user_approval_request_steps WHERE step_type IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '전자결재 단계유형 백필을 완료하지 못했습니다.';
    END IF;

    ALTER TABLE user_approval_template_steps
        MODIFY COLUMN step_type ENUM('SUBMIT', 'APPROVAL', 'FINAL_APPROVAL') NOT NULL
            COMMENT '단계유형' AFTER step_name;

    ALTER TABLE user_approval_request_steps
        MODIFY COLUMN step_type ENUM('SUBMIT', 'APPROVAL', 'FINAL_APPROVAL') NOT NULL
            COMMENT '단계유형 스냅샷' AFTER step_name,
        MODIFY COLUMN approver_id VARCHAR(36) NULL
            COMMENT '지정결재자';

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'user_approval_request_steps'
           AND INDEX_NAME = 'idx_request_steps_role_status_active'
    ) THEN
        CREATE INDEX idx_request_steps_role_status_active
            ON user_approval_request_steps(role_id, status, is_active);
    END IF;
END$$

CALL migrate_normalize_approval_step_assignment_up()$$
DROP PROCEDURE migrate_normalize_approval_step_assignment_up$$

DELIMITER ;
