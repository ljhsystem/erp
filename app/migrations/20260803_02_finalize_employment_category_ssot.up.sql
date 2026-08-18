SET @employment_contract_table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'institution_employment_contracts'
);
SET @sql := IF(
  @employment_contract_table_exists = 1,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''institution_employment_contracts 테이블이 없습니다.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @employment_contract_count := (
  SELECT COUNT(*) FROM `institution_employment_contracts`
);
SET @sql := IF(
  @employment_contract_count = 0,
  'SELECT 1',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''근로계약 데이터가 존재하여 고용구분 SSOT를 자동 전환할 수 없습니다.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_employment_category := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'institution_employment_contracts'
    AND COLUMN_NAME = 'employment_category'
);
SET @has_retired_category_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'institution_employment_contracts'
    AND COLUMN_NAME = 'engagement_type'
);
SET @sql := CASE
  WHEN @has_employment_category = 1 AND @has_retired_category_column = 1 THEN
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''employment_category와 engagement_type이 동시에 존재합니다.'''
  WHEN @has_employment_category = 0 AND @has_retired_category_column = 0 THEN
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''고용구분 전환 대상 컬럼이 없습니다.'''
  ELSE 'SELECT 1'
END;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @retired_code_count := (
  SELECT COUNT(*) FROM `system_codes`
  WHERE `code_group` = 'EMPLOYMENT_ENGAGEMENT_TYPE'
);
SET @category_code_count := (
  SELECT COUNT(*) FROM `system_codes`
  WHERE `code_group` = 'EMPLOYMENT_CATEGORY'
);
SET @sql := CASE
  WHEN @retired_code_count > 0 AND @category_code_count > 0 THEN
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''기존 코드그룹과 EMPLOYMENT_CATEGORY가 동시에 존재합니다.'''
  WHEN @retired_code_count NOT IN (0, 6) OR @category_code_count NOT IN (0, 6) THEN
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''고용구분 코드 개수가 예상값과 다릅니다.'''
  WHEN @retired_code_count = 0 AND @category_code_count = 0 THEN
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''고용구분 코드 전환 대상이 없습니다.'''
  ELSE 'SELECT 1'
END;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_retired_category_column = 1 AND EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_engagement_type'
  ),
  'ALTER TABLE `institution_employment_contracts` DROP CONSTRAINT `chk_institution_employment_contract_engagement_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_retired_category_column = 1 AND EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'institution_employment_contracts'
      AND INDEX_NAME = 'idx_institution_employment_contract_engagement_type'
  ),
  'ALTER TABLE `institution_employment_contracts` DROP INDEX `idx_institution_employment_contract_engagement_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_retired_category_column = 1,
  'ALTER TABLE `institution_employment_contracts` CHANGE COLUMN `engagement_type` `employment_category` varchar(30) NOT NULL COMMENT ''고용구분: GENERAL/DAILY/INTERN/REPLACEMENT/PROJECT/OTHER''',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `institution_employment_contracts`
  DROP COLUMN IF EXISTS `employment_type`;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'institution_employment_contracts'
      AND CONSTRAINT_NAME = 'chk_institution_employment_contract_employment_category'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD CONSTRAINT `chk_institution_employment_contract_employment_category` CHECK (`employment_category` IN (''GENERAL'',''DAILY'',''INTERN'',''REPLACEMENT'',''PROJECT'',''OTHER''))'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'institution_employment_contracts'
      AND INDEX_NAME = 'idx_institution_employment_contract_employment_category'
  ),
  'SELECT 1',
  'ALTER TABLE `institution_employment_contracts` ADD INDEX `idx_institution_employment_contract_employment_category` (`employment_category`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `system_codes`
SET `code_group` = 'EMPLOYMENT_CATEGORY',
    `group_name` = '고용구분'
WHERE `code_group` = 'EMPLOYMENT_ENGAGEMENT_TYPE';

DELETE FROM `system_codes`
WHERE `code_group` = 'EMPLOYMENT_TYPE';

UPDATE `system_user_settings`
SET `settings_json` = REPLACE(
  REPLACE(
    REPLACE(`settings_json`, 'employment_type', 'employment_category'),
    'engagement_type',
    'employment_category'
  ),
  '고용형태',
  '고용구분'
)
WHERE `page_key` = 'institution.human_resources.employment_contracts'
  AND `setting_type` = 'TABLE';
