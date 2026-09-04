# 내부 승인형 Evidence P1 Forward 정규화

> 이 문서는 [EvidenceOriginalContract.md](../architecture/EvidenceOriginalContract.md)를 적용하는 구현·Migration 계획이다. Architecture SSOT와 충돌하면 SSOT를 우선한다.

## 범위

- `ledger_evidence_salary_report`
- `ledger_evidence_employee_personal_expense`
- `ledger_evidence_daily_employment_income`

은행·세금계산서·현금영수증·카드 등 업로드형 Evidence, Transaction, Voucher, 외부 파일 Batch·Sheet·행 추적은 범위에서 제외한다.

## 이전 P1 산출물 판정

| 산출물 | 판정 | 처리 |
|---|---|---|
| 일용 `_01~06` | KEEP | 운영 적용된 Header raw·Raw Line 계약 유지 |
| 상용 `_11~13` | REMOVE | 미적용 구 계획 제거, `_41~42`, `_47`로 대체 |
| 개인경비 `_21~22` | REMOVE | 미적용 구 계획 제거, `_43~44`, `_47`로 대체 |
| Canonical Metadata `_31` | REMOVE | 일용 페이지 활성화 작업과 중복되는 미적용 산출물 제거 |
| Envelope·상태 코드 `_32` | REMOVE | 상태 다중분리와 별도 Envelope 코드 확장 제거 |
| Metadata Source 계약 `_33` | REMOVE | 외부 Metadata 누락 시 업로드 차단 계약 제거 |
| 외부 Source 추적 `_11~13` | REMOVE | Batch·Sheet·행과 수기 세금계산서 재정규화 제거 |
| 세금계산서 Raw Line `_21~22` | REMOVE | 대표 품목 복제 기반 Raw Line 제거 |
| `audit_evidence_p1_normalization.php` | MODIFY | 내부 승인형 3종 전용 읽기 전용 Dry-run으로 축소 |
| 기존 전수감사·설계문서 | LEGACY_REFERENCE | 실행계약이 아니라 판단 근거로만 유지 |

## 최종 Migration

1. `20260902_41`: 상용급여 공통·표준 raw·신규 승인 Snapshot 컬럼 추가
2. `20260902_42`: 상용급여 기존 2건의 결정 가능한 Source·표준 금액만 backfill
3. `20260902_43`: 개인경비 공통·승인추적·원 신청 raw 컬럼 추가
4. `20260902_44`: 개인경비 기존 9건의 결정 가능한 승인·원 신청값만 backfill
5. `20260902_45`: 일용근로소득 누락 공통컬럼과 Group raw 참조 추가
6. `20260902_46`: 일용 기존 1건의 공통값과 Group raw 참조 backfill
7. `20260902_47`: 내부 승인형 3종 TableSettings Legacy key를 표준 key로 전환

구 컬럼은 제거하지 않는다. `team_id`, `raw_gross_amount`, `raw_deduction_amount`, 일용 `total_*`와 `evidence_status_code`는 dual-write/read-fallback 기간의 Legacy 컬럼이다.

## raw 매핑

| 유형 | 원 업무 | Evidence raw | 공용 정규화 |
|---|---|---|---|
| 상용급여 | 귀속연월·지급일·직원별 계산 결과 | 기존 `raw_*`, 표준 지급·공제 금액 | 직원, 사업구분, 거래방향, 업무유형 |
| 개인경비 | 신청일, Item의 프로젝트·거래처·지출 사실 | `raw_application_date`, `raw_project_id`, `raw_client_id`, 기존 지출 `raw_*` | 직원, 거래처, 프로젝트, 거래방향, 업무유형 |
| 일용근로소득 | Group 사업구분·프로젝트·작업팀, Worker Item·계산 | `raw_business_unit`, `raw_project_id`, `raw_work_team_id`, 기존 Header raw·Raw Line | 근로자 거래처, 사업구분, 프로젝트, 작업팀 |

원 업무에 없는 상용·개인경비 `raw_business_unit`은 만들지 않는다. 적용되지 않는 공용 계좌·카드·작업팀·직원 참조는 NULL을 정상값으로 유지한다.

## Legacy 자료

기존 상용·개인경비 행에는 승인 당시 없었던 Snapshot 또는 Source Hash를 사후 승인자료처럼 생성하지 않는다. 신규 승인부터 `APPROVAL_CAPTURED` Snapshot과 Hash를 저장할 수 있으며, 기존 행의 Snapshot 관련 필드는 NULL을 유지한다.

## 운영 적용 전 조건

- `php tools/audit_evidence_p1_normalization.php --schema=sukhyang` 결과 `manual_review_required` 0건
- 원 업무 연결·금액식·활성 Link 고아 0건
- 각 DDL 적용 후 해당 backfill을 순차 실행
- TableSettings JSON 유효성 확인 후 `_47` 실행
- 운영 Migration은 별도 승인 전 실행 금지
