DELIMITER $$
CREATE PROCEDURE `down_20260826_01`()
BEGIN
  IF EXISTS (SELECT 1 FROM `institution_workplace_size_periods` LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '회사규모 기간 데이터가 있어 Down할 수 없습니다.';
  END IF;
  ALTER TABLE `institution_regular_employment_income_line_items`
    DROP FOREIGN KEY `fk_regular_income_line_workplace_size`,
    DROP FOREIGN KEY `fk_regular_income_line_coverage`,
    DROP FOREIGN KEY `fk_regular_income_line_statutory`,
    DROP INDEX `idx_regular_income_line_workplace_size`,
    DROP INDEX `idx_regular_income_line_coverage`,
    DROP INDEX `idx_regular_income_line_statutory`,
    DROP CONSTRAINT `chk_regular_income_line_rounding_method`,
    DROP CONSTRAINT `chk_regular_income_line_rounding_unit`,
    DROP CONSTRAINT `chk_regular_income_line_rate`,
    DROP CONSTRAINT `chk_regular_income_line_basis_amount`,
    DROP CONSTRAINT `chk_regular_income_line_application`,
    DROP COLUMN `workplace_size_period_id`,
    DROP COLUMN `social_insurance_coverage_id`,
    DROP COLUMN `statutory_standard_id`,
    DROP COLUMN `rounding_unit`,
    DROP COLUMN `rounding_method_code`,
    DROP COLUMN `calculation_before_rounding`,
    DROP COLUMN `calculation_rate`,
    DROP COLUMN `calculation_basis_amount`,
    DROP COLUMN `application_status_code`;
  DROP TABLE `institution_workplace_size_periods`;
END$$
DELIMITER ;
CALL `down_20260826_01`();
DROP PROCEDURE `down_20260826_01`;
