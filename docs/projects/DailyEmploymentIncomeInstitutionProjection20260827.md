# 일용근로소득 기관별 Projection 저장구조 설계

## 판정

현재 `Header → Group → Item → Workday → Line` 구조는 회사의 실제 입력 Grain과 초안 계산내역을 보존할 수 있다. 그러나 결재 당시 기관별 확정 계산, 계산 Revision, 원가 배부 및 대사결과를 불변 Snapshot으로 완전하게 재현할 수는 없다.

`institution_daily_employment_income_lines`는 Item 또는 Workday에 귀속되므로 여러 Group의 동일 근로자·동일 근무일을 하나의 지급회차로 취합한 세무 계산결과의 원본 Grain이 아니다. 보험별 사업장·근로자·적용기간 결과도 하나의 Workday FK로만 표현할 수 없고, 확정액을 여러 Group·Item·Workday에 배부한 근거와 1원 단위 잔액 처리도 저장하지 않는다. Closure와 Accounting Link는 후속 생성 실행상태이지 계산 Snapshot 또는 대사 SSOT가 아니다.

따라서 기관별 상세모드와 결재요청 차단을 확정 기능으로 만들려면 신규 forward-only Migration 승인이 필요하다. 승인 전에는 임시 JSON, Header/Item 중복컬럼 또는 계산 시점 재계산값을 승인 Snapshot처럼 저장하지 않는다.

## 최소 신규 구조안

### `institution_daily_employment_income_calculation_revisions`

- 문서 FK, revision 번호, 계산 상태, 계산 기준시각, 입력 hash, 정책 hash
- 지급회차, 계산·확정·승인 Actor 및 시각
- 문서별 revision UNIQUE
- 승인 문서는 확정 revision을 가리키되 기존 Header 컬럼 추가가 필요함

### `institution_daily_employment_income_calculation_results`

- revision FK
- 기관코드: `TAX_OFFICE`, `NATIONAL_PENSION`, `HEALTH_INSURANCE`, `LONG_TERM_CARE`, `EMPLOYMENT_INSURANCE`, `INDUSTRIAL_ACCIDENT_INSURANCE`
- 계산 Grain 키: 근로자, 지급회차, 적용 사업장, 적용기간
- 계산기초, 법정기준 FK, 요율, 근로자부담, 사용자부담, 제외·조정사유, 계산상태
- 기관 Grain 중복을 막는 UNIQUE와 조회 인덱스

### `institution_daily_employment_income_allocations`

- calculation result FK
- Group·Item·Workday FK
- 지급액, 확정세액, 근로자부담 보험료, 사용자부담 보험료 배부액
- 배부 순번과 잔액 배부 여부
- 결과별 배부 대상 UNIQUE

### `institution_daily_employment_income_reconciliations`

- revision FK, 대사항목 코드, 원천금액, Projection 금액, 차액, 상태, 오류 대상 식별값
- 대사항목: 지급액, 근로자부담, 사용자부담, 실지급액, Group/Header 합계
- 차액 0만 `MATCHED`; 하나라도 불일치하면 revision 확정 및 결재요청 금지

### Header 보강

- 입력 요구사항의 `description` 또는 `remark` 컬럼
- 확정 revision FK
- 기존 Migration은 수정하지 않고 신규 Migration에서만 추가

## 기존 구조 영향

- Service: 입력 계산과 기관별 Projection 계산을 분리하고 Revision 확정 Service를 신규 책임으로 둔다.
- Model: Result, Allocation, Reconciliation을 각각 단일 저장소 Model로 분리한다.
- 승인: 결재요청 전 확정 revision과 전 대사 `MATCHED`를 검사한다.
- 상세: DRAFT는 최신 Preview, 승인 문서는 확정 revision만 조회한다.
- 삭제·복구: DRAFT Preview는 재생성 가능하나 승인 Snapshot은 삭제하지 않는다.
- Evidence/거래: Item별 기존 Evidence 구조는 근로자 지급회차 Header와 Group Detail 구조로 별도 전환 검토가 필요하다.
- Down: 운영 확정 Snapshot이 존재하면 Down을 차단한다.

## 승인 전 제한

- 위 신규 테이블·Header 컬럼은 운영 DB에 생성하지 않는다.
- 기관별 공식 계산완료, 배부완료, 대사완료를 현재 Workday/Line만으로 표시하지 않는다.
- 입력모드의 Group 카드와 Workday 편집은 승인된 `_10` 구조로 계속 사용할 수 있다.

## Group 기본단가 제거 정정

- 확정 정책: 단가는 Group 속성이 아니라 작업자별 기본단가이며 날짜별 변경은 Workday Override다.
- 준비 Migration: `20260827_11_remove_daily_employment_income_group_default_rate`
- Up: Group의 `ck_daily_income_group_rate`와 `default_daily_rate`를 제거한다.
- Down: 컬럼을 0 기본값으로 복원하므로 과거 작업자 단가를 Group 값으로 역산하지 않는다.
- 운영 DB 적용 상태: `20260827_11_remove_daily_employment_income_group_default_rate` 적용 완료. 기존 적용 Migration `_10`은 수정하지 않았다.

## 2026-08-27 AG Grid 전환 이후 신규 DB 설계안 — 운영 적용 금지

아래 ID는 책임 경계를 명시하기 위한 설계안이며 Migration 파일 작성 및 운영 DB 적용은 별도 승인을 받는다.

1. `20260827_12_add_daily_income_item_work_type_and_payment_snapshots`
   - Item에 `work_type_code`, 과세·비과세 증감, 과세·비과세 소득, 세전 지급액 Snapshot을 분리한다.
   - 기존 Item은 공종을 임의 백필하지 않고 `NULL + NEEDS_CLASSIFICATION` 전환상태를 사용한다. `(group_id, worker_id)` UNIQUE는 유지한다.
2. `20260827_13_create_daily_income_non_taxable_revisions`
   - 비과세 항목·금액·적용 근무일·사유·법적 근거·산정내역·법정기준 Revision·처리 Actor/시각·승인상태를 별도 Revision Grain으로 보존한다.
   - 일반 조정금액 컬럼은 추가하지 않는다.
3. `20260827_14_create_daily_income_calculation_revisions_and_results`
   - Calculation Revision과 기관별 Result를 분리한다. 세금 Grain은 회사·근로자·근무일·지급회차, 보험 Grain은 보험별 사업장·근로자·적용기간이다.
4. `20260827_15_create_daily_income_allocations_and_reconciliations`
   - 확정 Result의 Workday→Item→Group 배부와 원 단위 잔액순서를 보존한다. 원천금액·Projection·배부합계·차액을 대사하며 차액 0만 `MATCHED`로 확정한다.
5. `20260827_16_create_business_document_attachments_and_links`
   - 현재 재사용 가능한 파일 SSOT는 거래 확정 후의 `ledger_transaction_files`뿐이며, 결재 전 비과세 근거를 담을 공용 업무문서 첨부 SSOT는 없다.
   - 일용 전용 파일경로 컬럼 대신 공용 업무문서 Attachment와 대상 Link를 검토한다. 해시·MIME·크기·원본명·Storage 경로·업로드 Actor·삭제/복구·보존기간·결재 Snapshot Link가 필요하다.

### 현재 전환 제한

- 위 구조 승인 전 `work_type_code`, 비과세 근거, 기관별 확정액은 공식 저장 Snapshot이 아니다.
- AG Grid 계산값은 Preview이며 서버 재계산을 대체하지 않는다.
- 비과세 증감이 0원이 아닌 문서는 근거·권한·Revision 구조 적용 전 결재요청을 허용하면 안 된다.
