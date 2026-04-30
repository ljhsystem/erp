CREATE TABLE IF NOT EXISTS `ledger_voucher_line_refs` (
  `id` CHAR(36) NOT NULL COMMENT '보조계정 UUID',
  `voucher_line_id` CHAR(36) NOT NULL COMMENT '전표라인 ID (ledger_voucher_lines.id)',
  `ref_type` VARCHAR(50) NOT NULL COMMENT '보조계정 유형 (CLIENT, PROJECT, EMPLOYEE, ACCOUNT, CARD 등)',
  `ref_id` CHAR(36) NOT NULL COMMENT '보조계정 ID',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_line` (`voucher_line_id`),
  INDEX `idx_ref` (`ref_type`, `ref_id`),
  CONSTRAINT `fk_line_refs_line`
    FOREIGN KEY (`voucher_line_id`)
    REFERENCES `ledger_voucher_lines` (`id`)
    ON DELETE CASCADE
)
COMMENT='전표라인 보조계정 (다중 구조)'
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

INSERT INTO `ledger_voucher_line_refs` (
  `id`,
  `voucher_line_id`,
  `ref_type`,
  `ref_id`,
  `created_at`,
  `created_by`
)
SELECT
  UUID(),
  `id`,
  `ref_type`,
  `ref_id`,
  COALESCE(`created_at`, NOW()),
  `created_by`
FROM `ledger_voucher_lines`
WHERE `ref_type` IS NOT NULL
  AND `ref_type` <> ''
  AND `ref_id` IS NOT NULL
  AND `ref_id` <> '';

ALTER TABLE `ledger_voucher_lines`
  DROP COLUMN `ref_type`,
  DROP COLUMN `ref_id`;
