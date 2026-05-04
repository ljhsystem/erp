CREATE TABLE IF NOT EXISTS `ledger_data_upload_batches` (
    `id` VARCHAR(36) NOT NULL COMMENT '고유 ID (UUID)',
    `file_name` VARCHAR(255) NOT NULL COMMENT '업로드 파일명',
    `data_type` VARCHAR(20) NOT NULL COMMENT '자료유형',
    `format_id` VARCHAR(36) NOT NULL COMMENT '양식 ID',
    `total_rows` INT NOT NULL DEFAULT 0 COMMENT '총 행 수',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NULL COMMENT '생성자',
    PRIMARY KEY (`id`),
    INDEX `idx_data_type` (`data_type`),
    INDEX `idx_format` (`format_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB COMMENT='자료 업로드 배치';

CREATE TABLE IF NOT EXISTS `ledger_data_upload_rows` (
    `id` VARCHAR(36) NOT NULL COMMENT '고유 ID (UUID)',
    `batch_id` VARCHAR(36) NOT NULL COMMENT '업로드 배치 ID',
    `row_no` INT NOT NULL COMMENT '원본 행 번호',
    `raw_payload` TEXT NOT NULL COMMENT '원본 행 JSON 문자열',
    `mapped_payload` TEXT NOT NULL COMMENT '매핑 결과 JSON 문자열',
    `status` VARCHAR(30) NOT NULL COMMENT '처리 상태',
    `error_message` TEXT NULL COMMENT '오류/검증 메시지',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `created_by` VARCHAR(100) NULL COMMENT '생성자',
    PRIMARY KEY (`id`),
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_ledger_data_upload_rows_batch`
        FOREIGN KEY (`batch_id`) REFERENCES `ledger_data_upload_batches` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='자료 업로드 행';
