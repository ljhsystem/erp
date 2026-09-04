# 상용근로소득 공제 정산 Line 구조 Closure

## 범위

- 국민연금, 건강보험, 장기요양보험, 고용보험, 근로소득세, 지방소득세의 당월 정상공제와 과거기간 정산을 분리한다.
- 기존 `institution_regular_employment_income_line_items`를 재사용하며 DB 구조는 변경하지 않는다.

## Line 계약

- 당월 정상공제: 기존 공제코드 Line, `final_amount = calculated_amount + adjustment_amount`.
- 정산: 별도 DEDUCTION Line, 금액은 양수, `adjustment_amount=0`.
- 정산 원천키: `SETTLEMENT|{공제코드}|{ADDITIONAL_COLLECTION 또는 REFUND}|{대상기간}|{멱등토큰}`.
- 추징은 공제총액을 증가시키고 환급은 공제총액을 감소시킨다.
- 연말정산 도메인이 생기면 `source_reference_id/source_key`로 연결하며 현재는 수동 원천을 사용한다.

## UI와 회계 Projection

- 정산 입력은 각 공제 카드 내부에서 정산유형, 대상기간·귀속연도, 금액, 사유를 받는다.
- 카드 최종 공제는 당월 적용금액 + 추가징수 - 환급이다.
- Transaction Settlement는 당월 `*_CURRENT/MINUS`, 추징 `*_SETTLEMENT/MINUS`, 환급 `*_REFUND/PLUS`로 구분한다.

## 검증 Fixture

- 건강보험 29,120 + 추징 15,000 - 환급 2,000 = 42,120.
- 근로소득세 20,000 + 추가징수 50,000 = 70,000.
- 근로소득세 20,000 - 환급 30,000 = -10,000이며 실지급액은 10,000 증가한다.
- 지방소득세 정산은 근로소득세와 별도 Line이다.
- 반복 Projection 결과는 동일하다.

## 후속 범위

- 회사부담 정산도 현재 Line Item 구조로 표현 가능하지만 직원 공제와 분리된 업무 UX와 회계 Projection 정책 확정 전까지 자동계산 회사부담만 유지한다.
- 연말정산 도메인 구현 전에는 선제 FK를 만들지 않는다.
