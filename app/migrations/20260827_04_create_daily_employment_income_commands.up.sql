SET NAMES utf8mb4;

CREATE TABLE institution_daily_employment_income_commands (
    id VARCHAR(36) NOT NULL,
    request_key VARCHAR(191) NOT NULL COMMENT '재호출 방지 전역 업무키',
    command_type VARCHAR(30) NOT NULL COMMENT 'SAVE UPDATE DELETE SUBMIT WITHDRAW RETRY_CLOSURE',
    daily_employment_income_id VARCHAR(36) NOT NULL COMMENT '문서 식별값',
    payload_hash CHAR(64) NOT NULL COMMENT '서버 정규화 Payload SHA-256',
    command_status VARCHAR(20) NOT NULL COMMENT 'PROCESSING COMPLETED FAILED',
    result_version INT UNSIGNED NULL,
    result_reference_id VARCHAR(191) NULL,
    processed_by VARCHAR(100) NOT NULL COMMENT 'ActorHelper Actor Token',
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    error_code VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_daily_income_command_request (request_key),
    KEY idx_daily_income_command_document (daily_employment_income_id, command_type, created_at),
    CONSTRAINT chk_daily_income_command_type CHECK (command_type IN ('SAVE','UPDATE','DELETE','SUBMIT','WITHDRAW','RETRY_CLOSURE')),
    CONSTRAINT chk_daily_income_command_status CHECK (command_status IN ('PROCESSING','COMPLETED','FAILED')),
    CONSTRAINT chk_daily_income_command_hash CHECK (payload_hash REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_daily_income_command_result CHECK (
        (command_status='COMPLETED' AND completed_at IS NOT NULL AND failed_at IS NULL)
        OR (command_status='FAILED' AND failed_at IS NOT NULL AND completed_at IS NULL)
        OR (command_status='PROCESSING' AND completed_at IS NULL AND failed_at IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='일용근로소득 저장명령 멱등 원장';
