UPDATE `system_bank_accounts`
SET `account_type` = CASE
    WHEN `id` IN (
        '029b4605-1faa-48e8-b5eb-da0a42cff1a8',
        '7d57c70a-c06e-4e05-a748-7398631717f5',
        '7ad49f52-4df2-4e2b-8a2f-19c94d1c7c55'
    ) THEN ''
    WHEN `id` = 'be3be99e-0dda-4310-8180-f0a106d6ec2e' THEN '기타'
    WHEN `id` = '36f16aa9-f254-48ba-ac78-152fc5ed491a' THEN '대출'
    WHEN `id` IN (
        'fd59059e-3f0b-4e6e-bdaa-335df082d27c',
        'ec824562-85cf-4ac8-89f6-b20408acb119',
        '7e35afdb-473d-44bf-9836-dcc1e1f094a9',
        '6fa8093b-d7fc-425f-834a-2f1d9b4b815d'
    ) THEN '보통예금'
    WHEN `id` IN (
        'f48e8ce2-9fa1-4048-bf23-bd1a122226a1',
        '359a3ff7-2908-46dc-9dd5-2b8a60e581d3'
    ) THEN '적금'
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

DELETE FROM `system_codes`
WHERE (`id` = 'f1b291d0-22d8-4a25-9239-a80f0118ca01' AND `code_group` = 'BANK_ACCOUNT_TYPE' AND `code` = 'CASH')
   OR (`id` = '47d497f8-69e4-4799-898d-49d66328ca02' AND `code_group` = 'BANK_ACCOUNT_TYPE' AND `code` = 'LOAN');
