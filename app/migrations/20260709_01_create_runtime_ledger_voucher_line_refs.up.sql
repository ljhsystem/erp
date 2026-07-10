CREATE TABLE IF NOT EXISTS `ledger_voucher_line_refs` (
  `id` CHAR(36) NOT NULL COMMENT 'ID',
  `voucher_line_id` CHAR(36) NOT NULL COMMENT '전표라인',
  `ref_target` VARCHAR(50) NOT NULL COMMENT '보조계정대상',
  `ref_id` CHAR(36) NOT NULL COMMENT '보조계정',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
  `created_by` VARCHAR(100) NULL COMMENT '생성자',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
  `updated_by` VARCHAR(100) NULL COMMENT '수정자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ledger_voucher_line_refs_line_target` (`voucher_line_id`, `ref_target`),
  KEY `idx_ledger_voucher_line_refs_line_id` (`voucher_line_id`),
  KEY `idx_ledger_voucher_line_refs_target_ref_id` (`ref_target`, `ref_id`),
  CONSTRAINT `fk_ledger_voucher_line_refs_line_id`
    FOREIGN KEY (`voucher_line_id`)
    REFERENCES `ledger_voucher_lines` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci
COMMENT='전표라인 보조계정';
