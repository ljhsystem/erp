# 법정기준 계산정책 및 적용기간 정비

## Closure 상태 (2026-08-31)

- 법정기준 등록·개정·정정·확정, Revision·Source·Source Correction 불변성, 기준일·적용범위 Resolver, 목록 기준값 표시를 운영 화면에서 확인했다.
- 법정기준관리 기능 자체는 `A — Closure 확정`이다.
- 고용보험·산재보험 `ELIGIBILITY` 공식 Revision과 Source는 `20260831_07_seed_employment_industrial_eligibility`로 등록한다. 고용보험은 실업급여와 고용안정·직업능력개발 구성요소를 분리하고, 산재보험은 사업장 적용성·근로자성·실제 근로 종사 단계를 분리한다. 실제 회사 보험사업장과 개인 Coverage는 법정기준 Seed에 포함하지 않는다.
- 고용보험·산재보험 가입자격 보강은 공식 법령 연혁, 세부 보험사업별 적용범위, 외국인 적용, 산재 사업장 적용성과 도급 책임을 검증한 뒤에만 완료로 전환한다.
- 상용·일용근로소득 등 소비 기능의 계산 연결성은 각 소비 기능 Closure에서 별도로 판정한다.

## 책임 경계

- 법정기준관리는 법령·공식기관 자료로 확인된 기준값과 계산계약만 저장한다.
- 급여 과세표준 조정, 신고차액, 현장별 원본과 개인 합산 차이, 회계전표 보정은 업무 Consumer 책임이다.
- `REPORT_ROUNDING_RULE`, 전용 rounding 테이블, Legacy fallback, 계산 API는 만들지 않는다.

## 적용 Type

- 국민연금: 신고 소득월액의 천 원 미만 버림을 보험료율 적용 전 가입자별 월 기준소득월액 확정 단계에 적용한다.
- 일용근로소득: 세액공제 후 원 미만을 버리고 원천징수의무자·소득자·근로일별 지급 단위로 계산한 뒤 사업장별 1천 원 미만 소액부징수를 판정한다.
- 근로소득 간이세액표: Matrix 조회세액과 상한초과 계산결과를 동일 최종 판정 입력으로 구분하고 원천징수의무자·소득자·지급월 단위의 1천 원 미만 소액부징수를 저장한다.
- 건강보험: 보험료 계산 결과의 10원 미만을 버린다. 결과 상·하한과 끝수처리의 명시적 선후관계는 저장하지 않되, 상·하한이 모두 10원 단위라 두 순서가 같은 결과임을 Consumer Fixture로 검증한다.
- 장기요양보험: 확정 건강보험료를 계산기초로 보험료를 산정한 뒤 10원 미만을 버린다.
- 지방소득세 특별징수: 확정 국세 원천징수액을 계산기초로 하고 10원 미만 버림을 국세 확정 후 적용한다. 2013년 소득분과 2014년 이후 개인지방소득세 특별징수의 법적 기간을 분리한다.

## 보류 Type

고용보험, 산재보험, 사업소득 원천징수, 법인세, 법인지방소득세는 공식 계산단계와 판정범위가 충분히 확인되기 전까지 계산정책을 선언하지 않는다. 최저임금과 부가가치세에도 계산정책을 두지 않는다.

## 운영 동기화

`tools/apply_statutory_calculation_policy_contract.php up`은 기존 2테이블을 유지하면서 Type Metadata, 공식 기간별 기준값, 같은 행의 계산정책과 Source를 원자적으로 동기화한다. 제거된 Scope 컬럼은 참조하지 않는다. `verify`는 Type별 기간과 계산정책 활성 Type을 조회하며 DB Schema는 변경하지 않는다.
## 2026-08-28 2013년 교차 법정기준 적용기간 보정

- `BUSINESS_INCOME_WITHHOLDING` 3%: `1998-01-01~NULL`
- `CORPORATE_TAX` 2012년 이후 개시 사업연도 세율: `2012-01-01~2017-12-31`
- `DAILY_WORKER_INCOME_TAX` 일 10만원 공제·6%·55% 계산계약: `2009-01-01~2018-12-31`
- `EMPLOYMENT_INSURANCE` 노사 각 0.55%와 사업규모별 추가 사용자부담: `2011-04-01~2013-06-30`
- `LOCAL_INCOME_TAX_WITHHOLDING` 지방소득세 소득분 특별징수: `2010-01-01~2013-12-31`
- 건강보험·산재보험·최저임금처럼 연도별 고시 또는 연도별 요율인 기준은 해당 연도 `01-01~12-31`을 유지한다.
- 2013년 자료 복원을 이유로 법정기준 시작일을 일괄 `2013-01-01`로 자르지 않는다. Resolver는 실제 시행기간과 지급일·귀속일 계약으로 Revision을 선택한다.

## 2026-08-31 보험별 가입자격 통합

- 별도 `INSURANCE_ELIGIBILITY` Type은 폐기하고 가입자격을 국민연금·건강보험·장기요양보험 등 기존 보험 Type의 `ELIGIBILITY` 구성요소로 관리한다.
- 보험료와 가입자격 Timeline은 `policy_component_code`, `employment_type_code`, `work_scope_code`, 추가 Dimension, 적용기간별로 독립 관리한다.
- 신규 Resolver는 정확한 고용형태·업무 Scope만 선택하며 REGULAR↔DAILY 또는 HEAD_OFFICE↔CONSTRUCTION_SITE fallback을 하지 않는다.
- 기존 22개 정책과 Source는 결정적 신규 Revision으로 1:1 이관하고 승인된 계산결과 3건의 참조·Snapshot만 결정적으로 치환한다. 계산금액·판정·과거 보험료 Revision은 변경하지 않는다.
- 정책값은 코드관리의 구성요소별 중첩 필드 카드로 표시하며 원시 JSON 입력을 제공하지 않는다.

## 2026-08-31 ELIGIBILITY 정책계약 v2

- `decision_model_code`가 없는 기존 22개 Revision은 `SIMPLE_PERSON_ELIGIBILITY`로 해석하며 원본 JSON을 변경하지 않는다.
- 고용보험은 `COMPONENT_ELIGIBILITY`로 실업급여와 고용안정·직업능력개발 구성요소를 각각 판정하고, 일부 구성요소만 확정 적용되면 `PARTIALLY_ELIGIBLE`로 판정한다.
- 산재보험은 `BUSINESS_AND_WORKER_ELIGIBILITY`로 사업장 적용성, 근로자성, 실제 근로 단계를 순서대로 판정한다. 확정 제외가 하나라도 있으면 제외하고, 확정 제외 없이 판정 필수 사실이 누락되면 `CONFIRMATION_REQUIRED`로 판정한다.
- 구성요소와 단계 조건은 Revision의 구조화 규칙이 소유하며 Validator와 Resolver에는 보험별 요율·법령조건을 하드코딩하지 않는다.
- `OPTIONAL`은 최종 상태가 아니라 가입신청 필요구분이다. 실제 Coverage·공단 신고·공식 신청사실이 확인되기 전에는 적용으로 확정하지 않는다.
# 법정기준 선택코드 SSOT 전환 (2026-08-31)

- Forward Migration: `20260831_06_unify_statutory_standard_select_codes`
- 범위: 법정기준 Header, 보험 5종 가입자격 기준값 카드, 기간 적용상태 표시코드
- 등록: 기간상태 포함 15개 코드그룹·34개 코드행
- Template 계약: `option_source=SYSTEM_CODES`, `option_code_group`, `allowed_codes`, `allow_inactive_for_existing_value`, `nullable`
- 불변: 법정기준 Revision·Source, Calculation Revision·Result, 보험료 계산정책과 Resolver 의미
- 운영 적용: 격리 MariaDB 10.11.11 Up/Down 및 불변 Hash 검증 후 별도 승인 단계에서 수행
