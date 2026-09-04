ALTER TABLE institution_daily_employment_income_workdays
    ADD COLUMN calculation_note VARCHAR(500) NULL
        COMMENT '지급액 산정 특이사항, 선택 입력'
        AFTER non_taxable_amount,
    ADD CONSTRAINT ck_daily_workday_calculation_note
        CHECK (
            calculation_note IS NULL
            OR (
                CHAR_LENGTH(calculation_note) BETWEEN 1 AND 500
                AND OCTET_LENGTH(calculation_note)
                    = OCTET_LENGTH(TRIM(calculation_note))
            )
        );
