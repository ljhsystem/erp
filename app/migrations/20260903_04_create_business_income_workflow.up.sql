CREATE TABLE institution_business_income_commands (
    id VARCHAR(36) NOT NULL,
    business_income_id VARCHAR(36) NOT NULL,
    command_type VARCHAR(40) NOT NULL,
    request_key VARCHAR(100) NOT NULL,
    command_status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING',
    result_reference_id VARCHAR(36) NULL,
    requested_by VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_command_request (request_key),
    KEY idx_business_income_command_header (business_income_id,created_at),
    CONSTRAINT fk_business_income_command_header FOREIGN KEY (business_income_id) REFERENCES institution_business_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 멱등 Command';

CREATE TABLE institution_business_income_closures (
    id VARCHAR(36) NOT NULL,
    business_income_id VARCHAR(36) NOT NULL,
    approval_request_id VARCHAR(36) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING',
    processing_token VARCHAR(64) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    processed_by VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_closure (business_income_id,approval_request_id),
    CONSTRAINT fk_business_income_closure_header FOREIGN KEY (business_income_id) REFERENCES institution_business_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_closure_approval FOREIGN KEY (approval_request_id) REFERENCES user_approval_requests(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 최종승인 Closure';

CREATE TABLE institution_business_income_artifact_links (
    id VARCHAR(36) NOT NULL,
    closure_id VARCHAR(36) NOT NULL,
    business_income_id VARCHAR(36) NOT NULL,
    business_income_item_id VARCHAR(36) NOT NULL,
    evidence_id VARCHAR(36) NULL,
    transaction_id VARCHAR(36) NULL,
    generation_status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING',
    result_hash CHAR(64) NULL,
    processed_by VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_business_income_artifact_item (business_income_item_id),
    KEY idx_business_income_artifact_header (business_income_id,generation_status),
    CONSTRAINT fk_business_income_artifact_closure FOREIGN KEY (closure_id) REFERENCES institution_business_income_closures(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_artifact_header FOREIGN KEY (business_income_id) REFERENCES institution_business_incomes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_business_income_artifact_item FOREIGN KEY (business_income_item_id) REFERENCES institution_business_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 승인 Evidence·Transaction 산출물 연결';

ALTER TABLE institution_business_incomes
    ADD CONSTRAINT fk_business_income_current_approval FOREIGN KEY (current_approval_request_id) REFERENCES user_approval_requests(id) ON DELETE RESTRICT ON UPDATE CASCADE;
