ALTER TABLE institution_leave_types
    ADD COLUMN minimum_hourly_minutes SMALLINT UNSIGNED NULL COMMENT '시간차 최소 신청 분' AFTER allowed_units_json,
    ADD COLUMN accrual_mode_code VARCHAR(30) NOT NULL DEFAULT 'MANUAL' COMMENT '발생 방식: MANUAL, CALCULATED_CONFIRMATION' AFTER minimum_hourly_minutes,
    ADD COLUMN carryover_policy_code VARCHAR(20) NOT NULL DEFAULT 'NONE' COMMENT '이월 정책: NONE, ALLOW, LIMITED' AFTER accrual_mode_code,
    ADD COLUMN carryover_limit_minutes INT UNSIGNED NULL COMMENT '제한 이월 최대 분' AFTER carryover_policy_code,
    ADD CONSTRAINT chk_leave_type_allowed_units_json CHECK (JSON_VALID(allowed_units_json)),
    ADD CONSTRAINT chk_leave_type_minimum_hourly CHECK (minimum_hourly_minutes IS NULL OR (minimum_hourly_minutes > 0 AND JSON_CONTAINS(allowed_units_json, JSON_QUOTE('HOURLY')) = 1)),
    ADD CONSTRAINT chk_leave_type_accrual_mode CHECK (accrual_mode_code IN ('MANUAL','CALCULATED_CONFIRMATION')),
    ADD CONSTRAINT chk_leave_type_carryover_policy CHECK (carryover_policy_code IN ('NONE','ALLOW','LIMITED')),
    ADD CONSTRAINT chk_leave_type_carryover_limit CHECK ((carryover_policy_code = 'LIMITED' AND carryover_limit_minutes IS NOT NULL AND carryover_limit_minutes > 0) OR (carryover_policy_code IN ('NONE','ALLOW') AND carryover_limit_minutes IS NULL));

ALTER TABLE institution_leave_grants
    ADD COLUMN grant_source_code VARCHAR(30) NOT NULL DEFAULT 'MANUAL' COMMENT '부여 출처: MANUAL, CALCULATED_CONFIRMATION, CARRYOVER, HISTORICAL_IMPORT' AFTER expires_on,
    ADD COLUMN calculation_basis_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT '계산확정 부여 근거 JSON' AFTER grant_source_code,
    ADD CONSTRAINT chk_leave_grant_source CHECK (grant_source_code IN ('MANUAL','CALCULATED_CONFIRMATION','CARRYOVER','HISTORICAL_IMPORT')),
    ADD CONSTRAINT chk_leave_grant_calculation_basis CHECK ((grant_source_code = 'CALCULATED_CONFIRMATION' AND calculation_basis_json IS NOT NULL AND JSON_VALID(calculation_basis_json)) OR (grant_source_code <> 'CALCULATED_CONFIRMATION' AND calculation_basis_json IS NULL)),
    ADD INDEX idx_leave_grant_expiration (expires_on, employee_id, leave_type_id);

ALTER TABLE institution_leave_ledger_entries
    DROP CONSTRAINT chk_leave_ledger_source,
    ADD COLUMN grant_id CHAR(36) NULL COMMENT '소비·복원·이월·소멸 대상 Grant' AFTER leave_type_id,
    ADD CONSTRAINT fk_leave_ledger_grant FOREIGN KEY (grant_id) REFERENCES institution_leave_grants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT chk_leave_ledger_grant_binding CHECK ((entry_type_code IN ('GRANT','USAGE','RESTORE','CARRYOVER','EXPIRATION') AND grant_id IS NOT NULL) OR (entry_type_code = 'ADJUSTMENT' AND grant_id IS NULL)),
    ADD CONSTRAINT chk_leave_ledger_source CHECK (source_domain_code IN ('GRANT','USAGE','CANCELLATION','ADJUSTMENT','CARRYOVER','EXPIRATION','HISTORICAL_IMPORT')),
    ADD INDEX idx_leave_ledger_grant_occurred (grant_id, occurred_on);
