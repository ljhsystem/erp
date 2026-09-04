# 사업소득 P1 Closure (2026-09-03)

> 문서 상태: `SUPERSEDED`
> 아래 판정은 운영 정책 적용 전의 중간 Closure 기록이다. 현재 기준은 `StatutoryStandardSupersessionClosure20260903.md`, `trigger-removal-closure-20260903.md`, `BusinessIncomeAmountAndDatabaseCommentClosure20260903.md` 및 최신 Architecture·Dictionary다.

## 확정 범위

사업소득 작성·계산·저장 → 결재요청 → 최종승인 → Evidence 원본·승인 계산 Raw Line·Transaction Item·공제 Settlement 생성 → Evidence와 Transaction 연결 → Closure 완료까지만 사업소득 P1이 책임진다.

전표·분개·계정과목·보조계정·Voucher·Journal·Posting은 범위 밖이며 사업소득 코드가 생성하거나 선결정하지 않는다.

## 운영 사전점검 결과

- 대상: `sukhyang`, MariaDB `10.11.11`.
- 사업소득 신규 테이블: 적용 전 0개.
- 선행 공용 테이블과 일용근로소득 결재 Template 2단계: 존재.
- `BUSINESS_INCOME_WITHHOLDING`: 운영 법정기준 1건이나 `rate_value`만 있어 계산정책이 미완성이다.
- `LOCAL_INCOME_TAX_WITHHOLDING`: 운영 법정기준 2건이나 사업소득 계산기가 요구하는 독립 소액부징수 표현이 없다. 2013 Revision은 기존 계산·Evidence에서 이미 참조한다.
- 법정기준 필수 계약: `rate_value`, `calculation_policy.method`, `discard_below_unit`, `stage`, `base_value_code`, `aggregation_unit`, `application_order`, `threshold`, `threshold_comparison`.
- 기존 Preflight가 실제 컬럼 `standard_type_code`를 판별하지 못해 0건으로 오판한 사실을 정정했다. 공식 정책과 불변 Correction/Supersession 구조가 함께 확정되기 전 계산 확정, 결재요청 및 Migration 운영 적용을 차단한다.

## 당시 판정

`B. 코드 완료 / 운영 정책 차단`. Migration은 사전조건 미충족으로 0건 적용했고 운영 데이터 변경도 0건이다. 따라서 실제 승인 Callback, 멱등 재호출, 실패 주입 Rollback 및 브라우저 E2E는 실행하지 않았다.
