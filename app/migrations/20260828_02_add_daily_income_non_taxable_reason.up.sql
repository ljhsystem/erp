ALTER TABLE institution_daily_employment_income_workdays
    ADD COLUMN non_taxable_reason VARCHAR(500) NULL
        COMMENT '비과세 적용사유'
        AFTER non_taxable_amount,
    ADD CONSTRAINT ck_daily_workday_non_taxable_reason
        CHECK (
            non_taxable_reason IS NULL
            OR (
                CHAR_LENGTH(non_taxable_reason) BETWEEN 1 AND 500
                AND OCTET_LENGTH(non_taxable_reason)
                    = OCTET_LENGTH(TRIM(non_taxable_reason))
            )
        ),
    ADD CONSTRAINT ck_daily_workday_non_taxable_reason_required
        CHECK (
            non_taxable_amount = 0
            OR CHAR_LENGTH(TRIM(COALESCE(non_taxable_reason, ''))) > 0
        );
