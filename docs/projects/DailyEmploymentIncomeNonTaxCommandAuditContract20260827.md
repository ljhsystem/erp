# 일용근로소득 비과세 Command·Audit·STALE 저장계약

## 판정

- 사용 가능한 신규 Migration ID는 `20260827_21_add_daily_income_non_tax_command_audit`다.
- 기존 `institution_daily_employment_income_commands`를 확장 재사용한다. 현재 전역 `request_key` UNIQUE는 `(문서, 명령종류, request_key)`보다 엄격하며 기존 저장계약과 일치하므로 유지한다.
- 공용 Audit는 명령 ID, 비과세 Revision ID, 상태 전이, 전후 Snapshot, request key와 payload hash를 한 원장에 모두 보존하지 못한다. 전용 추가전용 Audit를 생성한다.
- 운영 DB에는 `_19`, `_20`, Calculation Revision, Reconciliation이 존재하지 않는다. `_21`은 운영 미적용이며 `_19` 선행 없이는 실행을 차단한다.

## 기존 Command 실제 DDL 요약

- PK: `id VARCHAR(36)`
- 멱등키: `UNIQUE uk_daily_income_command_request(request_key)`
- 공식 hash: `payload_hash CHAR(64)` 및 소문자 SHA-256 CHECK
- 상태: `PROCESSING`, `COMPLETED`, `FAILED`
- 결과: `result_version`, `result_reference_id`
- Actor·시각: `processed_by`, `started_at`, `completed_at`, `failed_at`, `created_at`
- 명령 CHECK 전: `SAVE`, `UPDATE`, `DELETE`, `SUBMIT`, `WITHDRAW`, `RETRY_CLOSURE`

## `_21` 변경 후 Command 계약

- 추가 명령: `NON_TAX_CREATE`, `NON_TAX_CONFIRM`, `NON_TAX_CORRECT`, `NON_TAX_ATTACHMENT_LINK`, `NON_TAX_ATTACHMENT_UNLINK`
- 추가 참조: `target_revision_id`, `result_revision_id`
- 두 참조는 `_19` 비과세 Revision FK이며 삭제는 RESTRICT다.
- `_19` Revision 상태 CHECK에는 정식 반려·대체 상태인 `REJECTED`, `SUPERSEDED`를 추가한다. 기존 `CORRECTED`, `CANCELLED`는 기존 자료 호환을 위해 유지한다.
- `request_key`, `payload_hash`, 기존 결과식별자는 유지한다.
- 동일 request key·동일 hash는 기존 결과를 반환하고 업무행을 추가하지 않는다.
- 동일 request key·다른 hash는 409 충돌 계약이다.
- 생성 Transaction 내부의 Attachment 연결은 별도 Attachment Command를 만들지 않는다. 독립 연결·해제 API만 별도 Command를 사용한다.

## Payload canonicalization

서버는 명령종류, 문서·Item·Workday/기간, 항목, 금액, 사유, 법적 근거, 산정내역, 법정기준, 대상 Revision, 정렬된 Attachment ID를 정규화한다. 화면표시명, 임시 URL·경로, 클라이언트 합계, 요청시각은 제외한다. 클라이언트 hash는 사용하지 않는다.

## Audit 실제 DDL 요약

`institution_daily_employment_income_non_taxable_audits`는 Header, Item, Revision, Command FK와 Event, 이전·이후 상태, 이전·이후 JSON Snapshot, request key, payload hash, `processed_by`, `occurred_at`을 가진다. `(command_id,event_type_code,non_taxable_revision_id)` UNIQUE로 동일 명령의 동일 Event 중복을 막는다. Runtime UPDATE·DELETE는 허용하지 않는다.

## Calculation STALE 판정

별도 STALE 테이블은 만들지 않는다. Calculation Revision이 공식 SSOT이고 Item·Header는 Projection이다. 그러나 현재 Repository와 운영 DB 모두 Calculation Revision 및 Reconciliation 물리 저장계약이 없다. 따라서 `_21`에서 임시 테이블·JSON·중복컬럼으로 우회하지 않는다. 해당 공식 Migration이 승인·작성된 뒤 상태 CHECK에 `STALE`을 추가하고 비과세 확정·정정 Transaction에서 기존 결과를 보존한 채 최신 Revision과 Reconciliation만 STALE로 전환해야 한다.

## Up 순서

1. Command, `_19` Revision, Audit 미존재 Preflight
2. Command CHECK 제거
3. 대상·결과 Revision FK 컬럼과 인덱스 추가
4. 확장 Command CHECK 생성
5. Revision 상태 CHECK에 `REJECTED`, `SUPERSEDED` 추가
6. 전용 Audit와 FK·UNIQUE·CHECK·인덱스 생성

## Down 차단

Audit 자료 또는 비과세 Command가 한 건이라도 있으면 Down을 차단한다. 자료가 없을 때만 Audit를 삭제하고 Command를 `_04` 계약으로 되돌린다.

## 운영 Preflight 결과

- Command: 0건
- Header: 0건
- Item: 0건
- Workday: 0건
- Line: 0건
- `_19` Revision 테이블: 없음
- Calculation Revision 테이블: 없음
- Reconciliation 테이블: 없음
- 예상 `_21` Seed: 0건
- 운영 변경: 0건

## Runtime 활성화 차단

운영 `_18~_21`, Permission Seed, Role Mapping, Attachment Upload는 실행하지 않는다. Route 등록도 DB 미적용 상태에서 Runtime을 노출하므로 보류한다. 생성·확인·정정 Service Transaction과 STALE 갱신은 `_19~_21` 및 Calculation 저장계약 운영 적용 후 활성화한다.
