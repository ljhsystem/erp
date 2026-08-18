ALTER TABLE `user_employees`
  DROP FOREIGN KEY `fk_employee_representative_qualification`,
  DROP INDEX `idx_employee_representative_qualification`,
  DROP COLUMN `representative_qualification_id`;
