ALTER TABLE `system_codes`
    ADD COLUMN IF NOT EXISTS `group_name` VARCHAR(100) NULL
        COMMENT '코드그룹 UI 표시명 (예: 사업, 거래처, 거래, 자료유형 등)'
        AFTER `code_group`;

UPDATE `system_codes`
SET `group_name` = CASE `code_group`
    WHEN 'REF_TARGET' THEN '참조대상'
    WHEN 'BUSINESS_UNIT' THEN '사업'
    WHEN 'DOMAIN_TYPE' THEN '업무영역'
    WHEN 'CLIENT_TYPE' THEN '거래처구분'
    WHEN 'TRANSACTION_TYPE' THEN '거래'
    WHEN 'RAW_DATA_TYPE' THEN '자료유형'
    WHEN 'SOURCE_TYPE' THEN '자료출처'
    WHEN 'TAX_TYPE' THEN '과세구분'
    WHEN 'PAYMENT_METHOD' THEN '결제수단'
    WHEN 'PAYMENT_TERM' THEN '결제조건'
    WHEN 'TRADE_DIRECTION' THEN '거래방향'
    WHEN 'FLOW_DIRECTION' THEN '자금방향'
    WHEN 'COST_TYPE' THEN '원가분류'
    WHEN 'VOUCHER_TYPE' THEN '전표유형'
    WHEN 'VOUCHER_STATUS' THEN '전표상태'
    WHEN 'CURRENCY' THEN '통화'
    WHEN 'UNIT' THEN '단위'
    ELSE COALESCE(NULLIF(TRIM(`group_name`), ''), `code_group`)
END
WHERE `group_name` IS NULL
   OR TRIM(`group_name`) = ''
   OR `code_group` IN (
        'REF_TARGET',
        'BUSINESS_UNIT',
        'DOMAIN_TYPE',
        'CLIENT_TYPE',
        'TRANSACTION_TYPE',
        'RAW_DATA_TYPE',
        'SOURCE_TYPE',
        'TAX_TYPE',
        'PAYMENT_METHOD',
        'PAYMENT_TERM',
        'TRADE_DIRECTION',
        'FLOW_DIRECTION',
        'COST_TYPE',
        'VOUCHER_TYPE',
        'VOUCHER_STATUS',
        'CURRENCY',
        'UNIT'
   );

ALTER TABLE `system_codes`
    MODIFY COLUMN `group_name` VARCHAR(100) NOT NULL
        COMMENT '코드그룹 UI 표시명 (예: 사업, 거래처, 거래, 자료유형 등)'
        AFTER `code_group`;

CREATE INDEX IF NOT EXISTS `idx_system_codes_group_name_sort`
    ON `system_codes` (`group_name`, `code_group`, `sort_no`);
