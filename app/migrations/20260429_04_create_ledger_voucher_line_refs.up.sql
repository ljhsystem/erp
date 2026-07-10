CREATE TABLE IF NOT EXISTS `ledger_voucher_line_refs` (
  `id` CHAR(36) NOT NULL COMMENT '보조계정 UUID',
  `voucher_line_id` CHAR(36) NOT NULL COMMENT '전표라인 ID (ledger_voucher_lines.id)',
  `ref_target` VARCHAR(50) NOT NULL COMMENT '보조계정 유형 (CLIENT, PROJECT, EMPLOYEE, ACCOUNT, CARD 등)',
  `ref_id` CHAR(36) NOT NULL COMMENT '보조계정 ID',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
  `created_by` VARCHAR(100) NULL COMMENT '생성자',
  PRIMARY KEY (`id`),
  INDEX `idx_line` (`voucher_line_id`),
  INDEX `idx_ref` (`ref_target`, `ref_id`),
  CONSTRAINT `fk_line_refs_line`
    FOREIGN KEY (`voucher_line_id`)
    REFERENCES `ledger_voucher_lines` (`id`)
    ON DELETE CASCADE
)
COMMENT='전표라인 보조계정 (다중 구조)'
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;
