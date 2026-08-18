DELETE FROM `system_codes`
WHERE `deleted_at` IS NOT NULL;

ALTER TABLE `system_codes`
    DROP COLUMN `deleted_by`,
    DROP COLUMN `deleted_at`;
