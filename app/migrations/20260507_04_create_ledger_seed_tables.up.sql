CREATE TABLE IF NOT EXISTS `ledger_data_seed_batches` (
    `id` VARCHAR(36) NOT NULL COMMENT 'UUID',
    `source_type` VARCHAR(30) NOT NULL COMMENT 'Source data type',
    `file_name` VARCHAR(255) NULL COMMENT 'Upload file name',
    `format_id` VARCHAR(36) NULL COMMENT 'Data format ID',
    `total_rows` INT NOT NULL DEFAULT 0 COMMENT 'Total seed rows',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at',
    `created_by` VARCHAR(100) NULL COMMENT 'Created by',
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at',
    `updated_by` VARCHAR(100) NULL COMMENT 'Updated by',
    PRIMARY KEY (`id`),
    INDEX `idx_source_type` (`source_type`),
    INDEX `idx_format` (`format_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Ledger data seed batches';

CREATE TABLE IF NOT EXISTS `ledger_data_seed_rows` (
    `id` VARCHAR(36) NOT NULL COMMENT 'UUID',
    `seed_batch_id` VARCHAR(36) NOT NULL COMMENT 'Seed batch ID',
    `source_type` VARCHAR(30) NOT NULL COMMENT 'Source data type',
    `source_key` VARCHAR(255) NULL COMMENT 'Source duplicate key',
    `row_no` INT NOT NULL COMMENT 'Source row number',
    `raw_json` LONGTEXT NOT NULL COMMENT 'Raw row JSON',
    `parsed_json` LONGTEXT NOT NULL COMMENT 'Parsed seed JSON',
    `process_status` VARCHAR(30) NOT NULL DEFAULT 'READY' COMMENT 'READY, PROCESSED, ERROR, DUPLICATED, UNCHANGED, UPDATED',
    `error_message` TEXT NULL COMMENT 'Error message',
    `transaction_id` VARCHAR(36) NULL COMMENT 'Created transaction ID',
    `processed_at` DATETIME NULL DEFAULT NULL COMMENT 'Processed at',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at',
    `created_by` VARCHAR(100) NULL COMMENT 'Created by',
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at',
    `updated_by` VARCHAR(100) NULL COMMENT 'Updated by',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'Deleted at',
    `deleted_by` VARCHAR(100) NULL COMMENT 'Deleted by',
    PRIMARY KEY (`id`),
    INDEX `idx_seed_batch` (`seed_batch_id`),
    INDEX `idx_source_type` (`source_type`),
    INDEX `idx_source_key` (`source_type`, `source_key`),
    INDEX `idx_process_status` (`process_status`),
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_processed_at` (`processed_at`),
    INDEX `idx_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_ledger_data_seed_rows_batch`
        FOREIGN KEY (`seed_batch_id`) REFERENCES `ledger_data_seed_batches` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Ledger data seed rows';
