CREATE TABLE IF NOT EXISTS `system_client_name_history` (
    `id` CHAR(36) NOT NULL COMMENT '이력 ID (UUID)' COLLATE 'utf8mb4_general_ci',
    `client_id` VARCHAR(36) NOT NULL COMMENT '거래처 ID' COLLATE 'utf8mb4_general_ci',
    `old_company_name` VARCHAR(200) NOT NULL COMMENT '이전 상호' COLLATE 'utf8mb4_general_ci',
    `new_company_name` VARCHAR(200) NOT NULL COMMENT '변경 상호' COLLATE 'utf8mb4_general_ci',
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '변경일시',
    `changed_by` VARCHAR(100) NULL DEFAULT NULL COMMENT '변경자' COLLATE 'utf8mb4_general_ci',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `idx_client` (`client_id`) USING BTREE,
    CONSTRAINT `fk_client_history_client` FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COMMENT='거래처 상호 변경 이력'
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB;
