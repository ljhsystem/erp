# 공용 법정기준 불변 Correction/Supersession Closure

> 문서 상태: `COMPLETED_RECORD`
> 이 문서의 Supersession 구조와 운영 적용 결과는 유효하다. 아래 `20260903_09` 차단은 후속 `BusinessIncomeAmountAndDatabaseCommentClosure20260903.md`에서 해소되었으므로 현재 차단으로 해석하지 않는다.

## 판정

공용 구조와 사업소득 법정정책 등록은 운영 적용 완료다. 사업소득 법정정책 Preflight도 PASS했다. 다만 사업소득 P1 `20260903_09`가 leaf가 아닌 역사 Revision 전체를 검사하는 Runtime 결함이 있어 사업소득 01~09 적용과 E2E는 재개하지 않았다.

## 모델

- `system_statutory_standards`의 각 행이 Revision이다.
- `system_statutory_standard_sources`는 해당 Revision의 공식 Source다.
- `system_statutory_standard_supersessions`는 원 Revision→신규 Revision의 불변 선형 관계다.
- 기존 Calculation/Evidence FK는 당시 Revision ID를 계속 보존한다.

## Resolver

동일 Type·Scope와 기준일 적용기간 후보를 조회하고, 같은 chain에서 기준일에 유효한 descendant가 존재하는 ancestor를 제외한다. leaf 1건만 성공하며 0건과 복수건은 각각 `POLICY_NOT_FOUND`, `AMBIGUOUS_POLICY`다.

## 운영 결과

- 구조 적용 전: Revision 128, Source 173, Supersession 0
- 정책 적용 후: Revision 132, Source 177, Supersession 4
- 기존 Revision 내용 변경: 0
- 기존 법정기준 Resolver 성공 사례 변화: 0
- rollback fixture 잔존: 0
- 사업소득 경계일 정책 검사: 8/8 PASS

## 사업소득 정책

- 2013-01-01~2024-06-30: 3%, 10원 미만 절사, 1,000원 미만 소액부징수
- 2024-07-01~현재: 3%, 10원 미만 절사, 인적용역 사업소득 소액부징수 제외
- 지방소득세: 확정 사업소득세의 10%, 10원 미만 절사, 독립 소액부징수 없음

## 당시 남은 차단과 후속 해소

당시 `20260903_09_activate_business_income_runtime.up.sql`은 Supersession으로 보존된 과거 불완전 Revision까지 검사해 leaf Resolver 기반 Forward Fix Migration이 필요했다. 기존 Migration을 직접 수정하지 않는 원칙으로 후속 정비했으며, 사업소득 금액 Grain·멱등성·원자성 Runtime 검증까지 `BusinessIncomeAmountAndDatabaseCommentClosure20260903.md`에서 완료했다.
