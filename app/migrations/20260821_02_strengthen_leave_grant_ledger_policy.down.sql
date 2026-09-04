-- 운영 데이터가 생성된 뒤에는 Grant-Ledger 연결과 계산 근거를 잃으므로 이 Down Migration을 실행하지 않는다.
-- institution_leave_grants 및 institution_leave_ledger_entries가 모두 0건인 구조 검증 환경에서만 허용한다.
ALTER TABLE institution_leave_ledger_entries
    DROP INDEX idx_leave_ledger_grant_occurred,
    DROP CONSTRAINT chk_leave_ledger_source,
    DROP CONSTRAINT chk_leave_ledger_grant_binding,
    DROP FOREIGN KEY fk_leave_ledger_grant,
    DROP COLUMN grant_id,
    ADD CONSTRAINT chk_leave_ledger_source CHECK (source_domain_code IN ('GRANT','USAGE','CANCELLATION','ADJUSTMENT'));

ALTER TABLE institution_leave_grants
    DROP INDEX idx_leave_grant_expiration,
    DROP CONSTRAINT chk_leave_grant_calculation_basis,
    DROP CONSTRAINT chk_leave_grant_source,
    DROP COLUMN calculation_basis_json,
    DROP COLUMN grant_source_code;

ALTER TABLE institution_leave_types
    DROP CONSTRAINT chk_leave_type_carryover_limit,
    DROP CONSTRAINT chk_leave_type_carryover_policy,
    DROP CONSTRAINT chk_leave_type_accrual_mode,
    DROP CONSTRAINT chk_leave_type_minimum_hourly,
    DROP CONSTRAINT chk_leave_type_allowed_units_json,
    DROP COLUMN carryover_limit_minutes,
    DROP COLUMN carryover_policy_code,
    DROP COLUMN accrual_mode_code,
    DROP COLUMN minimum_hourly_minutes;
