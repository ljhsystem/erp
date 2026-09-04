# 일용근로소득 Line 자동계산액·실제 적용액 계약

## 적용 Migration

- `20260828_03_seed_income_calculation_code_ssot`
- `20260828_04_add_daily_income_line_adjustment_contract`

## Grain

- Workday PAY·세금 Line: `workday_scope_key=Workday UUID`
- Item 보험 공제·사용자부담 Line: `workday_scope_key=ITEM`
- UK: `(daily_employment_income_item_id,workday_scope_key,line_type_code,line_code)`

## 금액

- `calculated_amount`: 법정·자동 계산액이며 미확정이면 NULL
- `final_amount`: 실제 적용액이며 미확정이면 NULL, 실제 무공제 확인은 0
- `adjustment_amount`: 두 값이 모두 있을 때만 `final_amount-calculated_amount`로 생성되는 Stored Generated 컬럼
- DEDUCTION·EMPLOYER_BURDEN 실제 적용액은 음수를 허용하지 않는다. 환급은 별도 정산 Line 책임이다.

## 원천과 Actor

- 법정 계산원천과 실제 적용원천은 각각 지정된 공용 `system_codes` 그룹의 ID를 저장한다.
- 기존 Line 30건에는 원천을 추정 Backfill하지 않는다.
- `processed_by`는 `ActorHelper`가 공급한 Actor Token만 저장한다.

## 단계형 적용

MariaDB DDL 자동 커밋을 고려해 코드 Seed, nullable 컬럼, Scope Backfill 검증, NOT NULL, UK 전환, CHECK·FK 순서로 적용한다. 적용 도구는 부분 구조를 발견하면 임의 재개하지 않고 현재 단계와 별도 재개조건을 보고한다.
