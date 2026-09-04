DROP PROCEDURE IF EXISTS `migrate_20260824_09_seed_journal_learning_policy_baseline`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_09_seed_journal_learning_policy_baseline`()
BEGIN
    IF COALESCE(@journal_learning_actor,'') = '' OR @journal_learning_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ActorHelper::system()으로 공급한 @journal_learning_actor가 필요합니다.';
    END IF;
    INSERT INTO `system_settings_config`
        (`config_key`,`config_value`,`category`,`description`,`is_editable`,`created_by`,`updated_by`)
    VALUES
        ('journal_learning_policy.default','{"policy_revision":1,"minimum_sample_count":3,"minimum_agreement_ratio":1.0,"maximum_user_modified_ratio":0.0,"maximum_conflict_count":0,"recency_days":365,"minimum_successful_apply_count":3,"auto_promotion_enabled":false,"domain_enabled":{"EMPLOYEE_EXPENSE_PERSONAL":false,"PAYROLL":false,"PAYROLL_REPORT":false,"PAYROLL_WITHHOLDING":false}}','JOURNAL_LEARNING','분개추천 지속학습 전역 Baseline',0,@journal_learning_actor,@journal_learning_actor)
    ON DUPLICATE KEY UPDATE `config_key`=`config_key`;
END$$
DELIMITER ;
CALL `migrate_20260824_09_seed_journal_learning_policy_baseline`();
DROP PROCEDURE `migrate_20260824_09_seed_journal_learning_policy_baseline`;
