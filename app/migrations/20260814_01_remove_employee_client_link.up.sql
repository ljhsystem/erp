ALTER TABLE `user_employees`
    DROP FOREIGN KEY `fk_user_employees_client`,
    DROP INDEX `idx_client_id`,
    DROP COLUMN `client_id`;
