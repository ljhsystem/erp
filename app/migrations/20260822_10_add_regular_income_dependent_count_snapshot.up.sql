ALTER TABLE institution_regular_employment_income_items
  ADD COLUMN dependent_count_snapshot SMALLINT UNSIGNED NULL COMMENT '귀속월 공제대상 가족수 Snapshot' AFTER employment_contract_id;
