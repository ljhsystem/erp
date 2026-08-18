SET @legacy_employment_type_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'institution_employment_contracts'
    AND COLUMN_NAME = 'employment_type'
);
SET @employment_contract_count := (SELECT COUNT(*) FROM `institution_employment_contracts`);
SET @sql := IF(
  @legacy_employment_type_exists = 1 AND @employment_contract_count > 0,
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''근로계약 분류체계 전환 전에 기존 근로계약 데이터를 정리해야 합니다.''',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `institution_employment_contracts`
  ADD COLUMN IF NOT EXISTS `contract_period_type` varchar(30) NOT NULL COMMENT '계약기간구분: INDEFINITE/FIXED_TERM' AFTER `contract_type`,
  ADD COLUMN IF NOT EXISTS `engagement_type` varchar(30) NOT NULL COMMENT '채용고용방식: GENERAL/DAILY/INTERN/REPLACEMENT/PROJECT/OTHER' AFTER `contract_period_type`,
  ADD COLUMN IF NOT EXISTS `working_time_type` varchar(30) NOT NULL COMMENT '근로시간구분: FULL_TIME/PART_TIME' AFTER `engagement_type`,
  DROP COLUMN IF EXISTS `employment_type`;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND INDEX_NAME = 'idx_institution_employment_contract_period_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD INDEX `idx_institution_employment_contract_period_type` (`contract_period_type`,`contract_end_date`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND INDEX_NAME = 'idx_institution_employment_contract_engagement_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD INDEX `idx_institution_employment_contract_engagement_type` (`engagement_type`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND INDEX_NAME = 'idx_institution_employment_contract_working_time_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD INDEX `idx_institution_employment_contract_working_time_type` (`working_time_type`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_period_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD CONSTRAINT `chk_institution_employment_contract_period_type` CHECK (`contract_period_type` IN (''INDEFINITE'',''FIXED_TERM''))'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_engagement_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD CONSTRAINT `chk_institution_employment_contract_engagement_type` CHECK (`engagement_type` IN (''GENERAL'',''DAILY'',''INTERN'',''REPLACEMENT'',''PROJECT'',''OTHER''))'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_working_time_type'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD CONSTRAINT `chk_institution_employment_contract_working_time_type` CHECK (`working_time_type` IN (''FULL_TIME'',''PART_TIME''))'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_period_policy'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD CONSTRAINT `chk_institution_employment_contract_period_policy` CHECK (`contract_period_type` = ''INDEFINITE'' AND `contract_end_date` IS NULL AND `fixed_term_reason_code` IS NULL AND `fixed_term_reason_detail` IS NULL OR `contract_period_type` = ''FIXED_TERM'' AND `contract_end_date` IS NOT NULL AND `contract_end_date` >= `contract_start_date` AND `fixed_term_reason_code` IS NOT NULL)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
