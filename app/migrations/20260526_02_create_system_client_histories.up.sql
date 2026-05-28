CREATE TABLE IF NOT EXISTS `system_client_histories` (
    `id` CHAR(36) NOT NULL,
    `client_id` VARCHAR(36) NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `source_type` VARCHAR(80) NULL,
    `source_evidence_id` VARCHAR(36) NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `changed_by` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_client_changed_at` (`client_id`, `changed_at`),
    INDEX `idx_source_evidence` (`source_evidence_id`),
    CONSTRAINT `fk_system_client_histories_client`
        FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`)
        ON UPDATE RESTRICT ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;
