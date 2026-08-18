DROP PROCEDURE IF EXISTS `sp_normalize_fund_account_types`;
DELIMITER $$
CREATE PROCEDURE `sp_normalize_fund_account_types`()
BEGIN
    DECLARE target_count INT DEFAULT 0;
    DECLARE matched_count INT DEFAULT 0;

    SELECT COUNT(*) INTO target_count
    FROM `system_bank_accounts`
    WHERE `id` IN (
        '029b4605-1faa-48e8-b5eb-da0a42cff1a8',
        '7d57c70a-c06e-4e05-a748-7398631717f5',
        '7ad49f52-4df2-4e2b-8a2f-19c94d1c7c55',
        'be3be99e-0dda-4310-8180-f0a106d6ec2e',
        '36f16aa9-f254-48ba-ac78-152fc5ed491a',
        'fd59059e-3f0b-4e6e-bdaa-335df082d27c',
        'ec824562-85cf-4ac8-89f6-b20408acb119',
        '7e35afdb-473d-44bf-9836-dcc1e1f094a9',
        '6fa8093b-d7fc-425f-834a-2f1d9b4b815d',
        'f48e8ce2-9fa1-4048-bf23-bd1a122226a1',
        '359a3ff7-2908-46dc-9dd5-2b8a60e581d3'
    );

    IF target_count <> 11 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'approved bank account target count is not 11';
    END IF;

    SELECT COUNT(*) INTO matched_count
    FROM `system_bank_accounts`
    WHERE
        (`id` IN (
            '029b4605-1faa-48e8-b5eb-da0a42cff1a8',
            '7d57c70a-c06e-4e05-a748-7398631717f5',
            '7ad49f52-4df2-4e2b-8a2f-19c94d1c7c55'
        ) AND COALESCE(`account_type`, '') IN ('', 'LOAN'))
        OR (`id` = 'be3be99e-0dda-4310-8180-f0a106d6ec2e' AND `account_type` IN ('기타', 'CASH'))
        OR (`id` = '36f16aa9-f254-48ba-ac78-152fc5ed491a' AND `account_type` IN ('대출', 'LOAN'))
        OR (`id` IN (
            'fd59059e-3f0b-4e6e-bdaa-335df082d27c',
            'ec824562-85cf-4ac8-89f6-b20408acb119',
            '7e35afdb-473d-44bf-9836-dcc1e1f094a9',
            '6fa8093b-d7fc-425f-834a-2f1d9b4b815d'
        ) AND `account_type` IN ('보통예금', 'NORMAL_DEPOSIT'))
        OR (`id` IN (
            'f48e8ce2-9fa1-4048-bf23-bd1a122226a1',
            '359a3ff7-2908-46dc-9dd5-2b8a60e581d3'
        ) AND `account_type` IN ('적금', 'INSTALLMENT_SAVINGS'));

    IF matched_count <> 11 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'approved bank account values changed; normalization stopped';
    END IF;

    INSERT INTO `system_codes`
        (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`,
         `is_active`, `created_at`, `created_by`)
    SELECT
        'f1b291d0-22d8-4a25-9239-a80f0118ca01', 74,
        'BANK_ACCOUNT_TYPE', '계좌구분', 'CASH', '현금',
        1, CURRENT_TIMESTAMP, 'SYSTEM:MIGRATION'
    WHERE NOT EXISTS (
        SELECT 1 FROM `system_codes`
        WHERE `code_group` = 'BANK_ACCOUNT_TYPE' AND `code` = 'CASH'
    );

    INSERT INTO `system_codes`
        (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`,
         `is_active`, `created_at`, `created_by`)
    SELECT
        '47d497f8-69e4-4799-898d-49d66328ca02', 75,
        'BANK_ACCOUNT_TYPE', '계좌구분', 'LOAN', '대출',
        1, CURRENT_TIMESTAMP, 'SYSTEM:MIGRATION'
    WHERE NOT EXISTS (
        SELECT 1 FROM `system_codes`
        WHERE `code_group` = 'BANK_ACCOUNT_TYPE' AND `code` = 'LOAN'
    );

    UPDATE `system_bank_accounts`
    SET `account_type` = CASE
        WHEN `id` IN (
            '029b4605-1faa-48e8-b5eb-da0a42cff1a8',
            '7d57c70a-c06e-4e05-a748-7398631717f5',
            '7ad49f52-4df2-4e2b-8a2f-19c94d1c7c55',
            '36f16aa9-f254-48ba-ac78-152fc5ed491a'
        ) THEN 'LOAN'
        WHEN `id` = 'be3be99e-0dda-4310-8180-f0a106d6ec2e' THEN 'CASH'
        WHEN `id` IN (
            'fd59059e-3f0b-4e6e-bdaa-335df082d27c',
            'ec824562-85cf-4ac8-89f6-b20408acb119',
            '7e35afdb-473d-44bf-9836-dcc1e1f094a9',
            '6fa8093b-d7fc-425f-834a-2f1d9b4b815d'
        ) THEN 'NORMAL_DEPOSIT'
        WHEN `id` IN (
            'f48e8ce2-9fa1-4048-bf23-bd1a122226a1',
            '359a3ff7-2908-46dc-9dd5-2b8a60e581d3'
        ) THEN 'INSTALLMENT_SAVINGS'
        ELSE `account_type`
    END,
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
    WHERE `id` IN (
        '029b4605-1faa-48e8-b5eb-da0a42cff1a8',
        '7d57c70a-c06e-4e05-a748-7398631717f5',
        '7ad49f52-4df2-4e2b-8a2f-19c94d1c7c55',
        'be3be99e-0dda-4310-8180-f0a106d6ec2e',
        '36f16aa9-f254-48ba-ac78-152fc5ed491a',
        'fd59059e-3f0b-4e6e-bdaa-335df082d27c',
        'ec824562-85cf-4ac8-89f6-b20408acb119',
        '7e35afdb-473d-44bf-9836-dcc1e1f094a9',
        '6fa8093b-d7fc-425f-834a-2f1d9b4b815d',
        'f48e8ce2-9fa1-4048-bf23-bd1a122226a1',
        '359a3ff7-2908-46dc-9dd5-2b8a60e581d3'
    );
END$$
DELIMITER ;

CALL `sp_normalize_fund_account_types`();
DROP PROCEDURE `sp_normalize_fund_account_types`;
