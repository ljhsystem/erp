UPDATE system_user_settings SET settings_json=REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
 settings_json,'"income_year_month"','"raw_income_year_month"'),'"payment_date"','"raw_payment_date"'),
 '"total_work_days"','"raw_work_day_count"'),'"total_gross_amount"','"raw_gross_payment_amount"'),
 '"total_deduction_amount"','"raw_worker_deduction_amount"'),'"total_net_payment_amount"','"raw_net_payment_amount"'),
 '"total_employer_burden_amount"','"raw_employer_burden_amount"'),'"evidence_status_code"','"evidence_status"')
WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1;
