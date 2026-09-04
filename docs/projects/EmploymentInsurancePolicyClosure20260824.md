# 고용보험 법정기준 2013년~현재 계산정책 Closure

## 범위

- `EMPLOYMENT_INSURANCE`의 2013-01-01 이후 적용기간 연속성, 근로자 부담률, 계산기초와 끝수처리를 검증한다.
- 기존 4개 적용기간 행과 공식 요율 Source는 보존한다.
- DB 물리구조와 Route는 변경하지 않는다.

## 확정 계산계약

- 계산기초: 비과세 근로소득을 제외한 보수(`INSURABLE_REMUNERATION`)
- 계산: 보수 × 해당 적용기간의 근로자 실업급여 부담률
- 끝수처리: 요율 적용 후 10원 미만 버림(`TRUNCATE`, `discard_below_unit=10`)
- 집계단위: 피보험자별 보수 지급 건(`INSURED_PERSON_PAYMENT`)
- 자격 우선순위: 확정된 Coverage 적용제외 정보가 있을 때만 자동계산에서 제외

## 적용기간

| 적용기간 | 근로자 부담률 |
|---|---:|
| 2013-01-01~2013-06-30 | 0.55% |
| 2013-07-01~2019-09-30 | 0.65% |
| 2019-10-01~2022-06-30 | 0.8% |
| 2022-07-01~현재 | 0.9% |

## 2013년 8월 실제 급여 검증

- 지급총액 1,088,890원 중 비과세 식대 100,000원을 제외한 보수는 988,890원이다.
- `988,890 × 0.65% = 6,427.785원`이며 10원 미만을 버려 자동계산액은 6,420원이다.
- 박정현 원본 6,420원은 자동계산과 일치한다.
- 이정호 원본 0원은 2013년 대표자 적용제외를 확정할 당시 자료가 없으므로 자동계산액 6,420원과 조정액 -6,420원을 함께 보존하고 사유 확인 대상으로 남긴다.

## 실행·검증

- 적용: `php tools/apply_employment_insurance_policy_closure.php up`
- 무결성: `php tools/apply_employment_insurance_policy_closure.php verify`
- 경계일·끝수: `php tests/regression/employment_insurance_policy_closure.php`
- 실제 자료 저장: `php tools/save_regular_income_201308_actual.php`
