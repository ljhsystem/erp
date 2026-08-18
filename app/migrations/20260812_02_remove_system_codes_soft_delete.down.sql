ALTER TABLE `system_codes`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '삭제일시' AFTER `updated_by`,
    ADD COLUMN `deleted_by` VARCHAR(100) NULL DEFAULT NULL COMMENT '삭제자' AFTER `deleted_at`;
