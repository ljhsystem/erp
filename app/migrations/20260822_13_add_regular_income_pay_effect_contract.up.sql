ALTER TABLE institution_regular_employment_income_line_items
  ADD COLUMN pay_effect_code varchar(30) NULL COMMENT 'PAY 효과: CONTRACT_BASE/INCREASE/DECREASE' AFTER item_type_code,
  ADD COLUMN business_source_code varchar(40) NULL COMMENT '업무원천: EMPLOYMENT_CONTRACT/ATTENDANCE_CLOSURE/LEAVE_USAGE/MANUAL/HISTORICAL_IMPORT' AFTER calculation_source_code,
  ADD COLUMN source_reference_id varchar(36) NULL COMMENT '업무원천 ID' AFTER business_source_code,
  ADD COLUMN source_key varchar(120) NULL COMMENT '원천별 중복방지키' AFTER source_reference_id,
  ADD COLUMN business_reason varchar(500) NULL COMMENT '증액·감액 업무사유' AFTER source_key,
  ADD COLUMN processed_at datetime NULL COMMENT '증액·감액 처리일시' AFTER business_reason,
  ADD COLUMN processed_by varchar(100) NULL COMMENT '증액·감액 처리 Actor' AFTER processed_at,
  ADD UNIQUE KEY uk_regular_income_line_source (regular_employment_income_item_id,business_source_code,source_key),
  ADD CONSTRAINT chk_regular_income_pay_effect CHECK (
    (item_type_code='PAY' AND pay_effect_code IN('CONTRACT_BASE','INCREASE','DECREASE'))
    OR (item_type_code<>'PAY' AND pay_effect_code IS NULL)
  ),
  ADD CONSTRAINT chk_regular_income_pay_amount CHECK (item_type_code<>'PAY' OR final_amount>=0),
  ADD CONSTRAINT chk_regular_income_pay_business CHECK (
    item_type_code<>'PAY'
    OR (pay_effect_code='CONTRACT_BASE' AND business_source_code='EMPLOYMENT_CONTRACT')
    OR (pay_effect_code IN('INCREASE','DECREASE')
      AND business_source_code IN('ATTENDANCE_CLOSURE','LEAVE_USAGE','MANUAL','HISTORICAL_IMPORT')
      AND business_reason IS NOT NULL AND CHAR_LENGTH(TRIM(business_reason))>0
      AND processed_at IS NOT NULL AND processed_by IS NOT NULL)
  ),
  ADD CONSTRAINT chk_regular_income_pay_source_key CHECK (
    business_source_code NOT IN('ATTENDANCE_CLOSURE','LEAVE_USAGE') OR source_key IS NOT NULL
  );
