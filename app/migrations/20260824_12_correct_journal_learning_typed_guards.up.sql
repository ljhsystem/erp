DROP PROCEDURE IF EXISTS `migrate_20260824_12_correct_journal_learning_typed_guards`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260824_12_correct_journal_learning_typed_guards`()
BEGIN
    IF COALESCE(@journal_learning_actor,'') = '' OR @journal_learning_actor NOT LIKE 'SYSTEM:%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 @journal_learning_actor가 필요합니다.';
    END IF;

    IF (SELECT COUNT(*) FROM `system_settings_config`
         WHERE `config_key`='journal_learning_policy.default'
           AND `config_value`='{"policy_revision":1,"minimum_sample_count":3,"minimum_agreement_ratio":1.0,"maximum_user_modified_ratio":0.0,"maximum_conflict_count":0,"recency_days":365,"minimum_successful_apply_count":3,"auto_promotion_enabled":false,"domain_enabled":{"EMPLOYEE_EXPENSE_PERSONAL":false,"PAYROLL":false,"PAYROLL_REPORT":false,"PAYROLL_WITHHOLDING":false}}') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='학습정책 Baseline이 승인된 1단계 Snapshot과 다릅니다.';
    END IF;

    UPDATE `system_settings_config`
       SET `config_value`='{"policy_revision":2,"minimum_sample_count":3,"minimum_agreement_ratio":1.0,"maximum_user_modified_ratio":0.0,"maximum_conflict_count":0,"recency_days":365,"minimum_successful_apply_count":3,"auto_promotion_enabled":false,"guards":{"operation_type":{"PERSONAL_EXPENSE":false,"PAYROLL":false},"import_type":{"CARD_HOMETAX":false,"CARD_STATEMENT":false,"PAYROLL_REPORT":false,"PAYROLL_WITHHOLDING":false},"workflow":{"CARD_PAYMENT":false}}}',
           `updated_at`=NOW(),`updated_by`=@journal_learning_actor
     WHERE `config_key`='journal_learning_policy.default';
    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='학습정책 typed Guard를 정확히 1건 정정하지 못했습니다.';
    END IF;
END$$
DELIMITER ;
CALL `migrate_20260824_12_correct_journal_learning_typed_guards`();
DROP PROCEDURE `migrate_20260824_12_correct_journal_learning_typed_guards`;
