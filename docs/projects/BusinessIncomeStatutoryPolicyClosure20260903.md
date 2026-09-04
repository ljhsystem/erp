# 사업소득 법정기준 운영 SSOT Closure 조사 (2026-09-03)

> 문서 상태: `SUPERSEDED`
> 이 문서는 Supersession 구조 도입 전의 차단 근거를 보존한다. 현재 운영 계약과 적용 결과는 `StatutoryStandardSupersessionClosure20260903.md`, `trigger-removal-closure-20260903.md`, `BusinessIncomeAmountAndDatabaseCommentClosure20260903.md`를 따른다.

## 공식 정책

- 소득세법 제127조·제129조: 원천징수대상 사업소득의 수입금액을 지급할 때 3%를 원천징수한다.
- 소득세법 제86조 및 국세청 원천징수 안내: 원천징수세액 1천 원 미만 소액부징수를 적용하되, 거주자 인적용역 사업소득은 2024년 7월 1일 이후 지급분부터 소액부징수 예외로 원천징수한다.
- 지방세법 제103조의13: 소득세 원천징수액의 10%를 소득세와 동시에 개인지방소득세로 특별징수한다.
- 국고금 관리법 제47조: 국고금 수입의 10원 미만 끝수는 계산하지 않는다.

사업소득 정책은 최소 `2013-01-01~2024-06-30`, `2024-07-01~NULL`로 구분해야 한다. 지방소득세는 기존 법적 구분대로 `2013-01-01~2013-12-31`, `2014-01-01~NULL`을 유지한다.

## 운영 DB 사실

- `BUSINESS_INCOME_WITHHOLDING`: 1개. `1998-01-01~NULL`, `rate_value`만 존재한다.
- `LOCAL_INCOME_TAX_WITHHOLDING`: 2개. `2010-01-01~2013-12-31`, `2014-01-01~NULL`이다.
- 2013 지방소득세 Revision은 일용 계산 Line 5건, 상용 계산 Line 2건 및 일용 Evidence Raw Line 5건이 참조한다.
- 법정기준 본체와 Source의 확정상태·Correction chain을 표현하는 컬럼이나 별도 테이블은 현재 운영 스키마에 없다. 2026-08-11 Migration에서 과거 Correction 구조가 명시적으로 제거됐다.

## 당시 차단 판단

기존 참조 Revision의 `value_data` 또는 적용기간을 UPDATE하면 확정 계산 이력을 변경한다. 같은 기간의 신규 행을 추가하면 현재 Resolver가 중복으로 차단한다. Correction overlay나 supersession을 임시 JSON 필드로 추가하는 것도 SSOT 우회이므로 금지한다.

따라서 다음 구조 결정 전 운영 DB 정책 적용을 중단한다.

1. 참조된 법정기준 Revision을 보존하면서 신규 Resolver가 정정 Revision을 선택하는 공용 Correction/Supersession 구조를 복원할지 결정한다.
2. 기존 Source의 불변·확정 상태와 Source Correction 관계를 물리적으로 표현할지 결정한다.
3. Correction이 과거 계산 Snapshot에는 소급되지 않고 신규 계산에만 적용되는 기준일·기록시점 선택 계약을 확정한다.

이 결정 없이 기존 행 UPDATE, 중복 기간 INSERT, 사업소득 전용 Fallback은 수행하지 않는다.
