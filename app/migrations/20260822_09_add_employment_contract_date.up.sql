ALTER TABLE `institution_employment_contracts`
  ADD COLUMN `contract_date` date DEFAULT NULL COMMENT '계약 체결일'
  AFTER `contract_no`;
