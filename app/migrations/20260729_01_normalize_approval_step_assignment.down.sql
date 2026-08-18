DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_normalize_approval_step_assignment_down$$
CREATE PROCEDURE migrate_normalize_approval_step_assignment_down()
BEGIN
    IF EXISTS (
        SELECT 1 FROM user_approval_request_steps WHERE approver_id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '역할 공동결재 요청이 존재하여 전자결재 단계 Migration을 되돌릴 수 없습니다.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'user_approval_request_steps'
           AND INDEX_NAME = 'idx_request_steps_role_status_active'
    ) THEN
        DROP INDEX idx_request_steps_role_status_active
            ON user_approval_request_steps;
    END IF;

    ALTER TABLE user_approval_request_steps
        MODIFY COLUMN approver_id VARCHAR(36) NOT NULL
            COMMENT '지정결재자',
        DROP COLUMN IF EXISTS step_type;

    ALTER TABLE user_approval_template_steps
        DROP COLUMN IF EXISTS step_type;
END$$

CALL migrate_normalize_approval_step_assignment_down()$$
DROP PROCEDURE migrate_normalize_approval_step_assignment_down$$

DELIMITER ;
