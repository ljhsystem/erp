-- 운영 발령 또는 Baseline 이외 기간 이력이 존재하면 롤백을 중단한다.
DELIMITER $$
CREATE PROCEDURE `sp_preflight_personnel_action_runtime_down`()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_personnel_actions LIMIT 1)
       OR EXISTS (SELECT 1 FROM user_employee_department_assignments WHERE created_by <> 'SYSTEM:PERSONNEL_RUNTIME_BASELINE' LIMIT 1)
       OR EXISTS (SELECT 1 FROM user_employee_position_assignments WHERE created_by <> 'SYSTEM:PERSONNEL_RUNTIME_BASELINE' LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personnel action runtime data exists; rollback is blocked';
    END IF;
END$$
DELIMITER ;

CALL `sp_preflight_personnel_action_runtime_down`();
DROP PROCEDURE `sp_preflight_personnel_action_runtime_down`;

DROP TABLE `user_employee_position_assignments`;
DROP TABLE `user_employee_department_assignments`;

ALTER TABLE `institution_personnel_action_targets`
    DROP COLUMN `applied_by`;

ALTER TABLE `institution_personnel_actions`
    DROP INDEX `idx_personnel_actions_issued_date`,
    DROP COLUMN `issued_date`;
