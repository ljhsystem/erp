UPDATE `system_settings_config`
   SET `config_value`='{"policy_revision":1,"minimum_sample_count":3,"minimum_agreement_ratio":1.0,"maximum_user_modified_ratio":0.0,"maximum_conflict_count":0,"recency_days":365,"minimum_successful_apply_count":3,"auto_promotion_enabled":false,"domain_enabled":{"EMPLOYEE_EXPENSE_PERSONAL":false,"PAYROLL":false,"PAYROLL_REPORT":false,"PAYROLL_WITHHOLDING":false}}',
       `updated_at`=NOW(),`updated_by`=@journal_learning_actor
 WHERE `config_key`='journal_learning_policy.default'
   AND `config_value`='{"policy_revision":2,"minimum_sample_count":3,"minimum_agreement_ratio":1.0,"maximum_user_modified_ratio":0.0,"maximum_conflict_count":0,"recency_days":365,"minimum_successful_apply_count":3,"auto_promotion_enabled":false,"guards":{"operation_type":{"PERSONAL_EXPENSE":false,"PAYROLL":false},"import_type":{"CARD_HOMETAX":false,"CARD_STATEMENT":false,"PAYROLL_REPORT":false,"PAYROLL_WITHHOLDING":false},"workflow":{"CARD_PAYMENT":false}}}';
