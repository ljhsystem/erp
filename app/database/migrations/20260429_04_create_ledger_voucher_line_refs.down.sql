ALTER TABLE `ledger_voucher_lines`
  ADD COLUMN `ref_type` VARCHAR(50) NULL DEFAULT NULL COMMENT '보조계정 유형 (CLIENT, PROJECT, EMPLOYEE 등)' AFTER `line_no`,
  ADD COLUMN `ref_id` CHAR(36) NULL DEFAULT NULL COMMENT '보조계정 ID' AFTER `ref_type`;

UPDATE `ledger_voucher_lines` l
LEFT JOIN (
  SELECT
    r.`voucher_line_id`,
    MIN(r.`ref_type`) AS `ref_type`,
    MIN(r.`ref_id`) AS `ref_id`
  FROM `ledger_voucher_line_refs` r
  GROUP BY r.`voucher_line_id`
) refs
  ON refs.`voucher_line_id` = l.`id`
SET
  l.`ref_type` = refs.`ref_type`,
  l.`ref_id` = refs.`ref_id`;

DROP TABLE IF EXISTS `ledger_voucher_line_refs`;
