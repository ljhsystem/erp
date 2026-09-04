ALTER TABLE institution_daily_employment_income_lines
    ADD COLUMN calculated_amount DECIMAL(18,2) NULL AFTER calculation_before_rounding,
    MODIFY COLUMN final_amount DECIMAL(18,2) NULL DEFAULT NULL,
    ADD COLUMN adjustment_amount DECIMAL(18,2)
        AS (
            CASE
                WHEN calculated_amount IS NULL OR final_amount IS NULL THEN NULL
                ELSE final_amount - calculated_amount
            END
        ) STORED AFTER final_amount,
    ADD COLUMN adjustment_reason VARCHAR(500) NULL AFTER adjustment_amount,
    ADD COLUMN statutory_calculation_source_code_id VARCHAR(36) NULL AFTER adjustment_reason,
    ADD COLUMN actual_application_source_code_id VARCHAR(36) NULL AFTER statutory_calculation_source_code_id,
    ADD COLUMN processed_at DATETIME NULL AFTER actual_application_source_code_id,
    ADD COLUMN processed_by VARCHAR(100) NULL AFTER processed_at;

ALTER TABLE institution_daily_employment_income_lines
    ADD CONSTRAINT ck_daily_income_line_scope CHECK (
        (daily_employment_income_workday_id IS NULL AND workday_scope_key='ITEM')
        OR
        (daily_employment_income_workday_id IS NOT NULL AND workday_scope_key=daily_employment_income_workday_id)
    ),
    ADD CONSTRAINT ck_daily_income_line_adjustment_reason CHECK (
        adjustment_reason IS NULL
        OR (
            CHAR_LENGTH(adjustment_reason) BETWEEN 1 AND 500
            AND OCTET_LENGTH(adjustment_reason)=OCTET_LENGTH(TRIM(adjustment_reason))
        )
    ),
    ADD CONSTRAINT ck_daily_income_line_adjustment_reason_required CHECK (
        adjustment_amount IS NULL OR adjustment_amount=0
        OR CHAR_LENGTH(TRIM(COALESCE(adjustment_reason,'')))>0
    ),
    ADD CONSTRAINT ck_daily_income_line_non_negative_actual CHECK (
        line_type_code NOT IN ('DEDUCTION','EMPLOYER_BURDEN')
        OR final_amount IS NULL OR final_amount>=0
    ),
    ADD CONSTRAINT fk_daily_income_line_statutory_source_code FOREIGN KEY (statutory_calculation_source_code_id)
        REFERENCES system_codes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_daily_income_line_actual_source_code FOREIGN KEY (actual_application_source_code_id)
        REFERENCES system_codes(id) ON DELETE RESTRICT ON UPDATE CASCADE;
