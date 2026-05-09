DROP INDEX IF EXISTS `idx_system_codes_group_name_sort`
    ON `system_codes`;

ALTER TABLE `system_codes`
    DROP COLUMN IF EXISTS `group_name`;
