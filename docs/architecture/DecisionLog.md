- 2026-08-11: 설정 상위 메뉴의 `법정기준관리`를 `기준관리`로 재구성하고 하위 탭을 `코드`, `법정기준` 순서로 둔다. 각 페이지의 업무명·제목·권한명은 `코드관리`, `법정기준관리`를 유지한다. 코드관리의 Controller·Service·Model·View·JS·API·권한은 복제하거나 변경하지 않고 기존 단일 구현을 새 Web 경로에서 재사용한다.
- 2026-08-06: 법정기준관리 페이지는 공용 Excel Manager와 공용 휴지통을 사용하지 않는다. 삭제 권한이 있는 사용자는 상세모달에서 기준과 관련근거를 복구 불가능하게 완전삭제한다.
- 2026-08-06: 국가 법정 세율·요율·계산기준은 회사 정책이나 업무 계산결과와 분리하여 `system_statutory_standards_*`를 ERP 공통 SSOT로 사용한다. 단순값·구간·행렬을 물리 구조로 분리하고 공식 숫자는 임의 Seed하지 않는다.
- 2026-08-06: `employment_rules`를 회사 정책 SSOT로 확정했다. 문서, 불변 개정본, 구조화 정책값, 적용범위, 감사 저장소를 분리한다. 승인된 개정본은 공용 전자결재를 거친 새 revision으로만 변경한다. 근로계약의 승인 스냅샷과 근태·휴가·급여의 실제 원장은 덮어쓰지 않는다.
- 2026-07-16: Evidence uploads use deterministic external-source identity and skip-on-duplicate semantics.
  - Decision: `EvidenceExternalKeyService` is the only upload `external_key` formula. Provider identifiers are preferred; otherwise an ordered raw-source allowlist is canonicalized and SHA-256 hashed.
  - Why: upload filename, time, user, row order, UUID, workflow state, and corrected ERP values made identical source records unstable or mutable. Source identity must remain independent from ERP workflow.
  - Consequence: an existing key, including a soft-deleted, completed, or linked row, is never updated or recreated. File duplicates and concurrent unique-key races are counted as skipped duplicates; different content under one key is surfaced as a conflict.
  - Migration constraint: unique-index migration aborts when pre-existing duplicate keys exist. It does not delete, merge, or rewrite existing evidence rows; collision review and any backfill require a separate approved data operation.
# Decision Log

## 2026-08-19 DataTables server-side 전송 방식

- DataTables server-side 목록은 전체 열·정렬·검색 구조 때문에 URL 길이가 데이터와 화면 구성에 비례하므로 공용 `createDataTable()`에서 form-urlencoded POST body를 기본 계약으로 사용한다. 웹서버 URI 한도 증가는 배포 환경마다 재발하고 애플리케이션 원인을 남기므로 선택하지 않는다.
- POST는 목록 조회의 transport일 뿐 업무 mutation이 아니다. detail, options, picker, metadata와 같은 짧은 읽기 API는 GET을 유지하고, 화면별 method override와 GET 실패 후 POST 재시도는 허용하지 않는다.
- 서버는 공용 `DataTableRequestHelper`에서 짧은 URL scope와 POST body를 한 번 병합한다. 기존 draw·start·length·columns·order·search 및 커스텀 검색조건과 응답의 recordsTotal·recordsFiltered 계약은 변경하지 않는다.

## 2026-08-18 — 직무·배치 기준일 Resolver와 직원 1행 Projection

- Current Master는 오늘 현재 캐시이고, 과거·미래 재현은 기간이력과 명시적 `as_of_date`를 사용한다.
- 기간은 시작일과 종료일을 모두 포함한다. 기준일이 시작일 전이면 `PLANNED`, 기간 안이면 `ACTIVE`, 종료일 후면 `ENDED`이며 명시적 `CANCELLED`가 우선한다.
- 프로젝트 이력의 저장 `status_code`는 저장 당시 상태와 명시적 무효를 보존한다. 화면·검색의 effective 상태는 `EmployeeAssignmentResolver`가 기간에서 파생하므로 날짜 경과용 Scheduler는 필요하지 않다.
- 인사발령과 직접 프로젝트 배치는 동일 Resolver로 저장 당시 상태를 결정한다. source와 직접 종료권한만 다르다.
- 직무·배치 목록은 직원 1명당 1행이다. 직무·프로젝트·근무지는 직원 단위 deterministic aggregate로 축약하고 복수 비주요 프로젝트는 첫 프로젝트명과 나머지 건수로 요약한다. 전체 이력은 상세 API가 담당한다.
- 목록은 검색 전 직원 COUNT와 검색 후 직원 COUNT를 분리하며 직원별 Resolver 호출을 금지한다.
- 근태 snapshot은 단일 근무일 batch SQL, 상용근로소득은 지급기간과 이력기간의 교차 조회, 휴직은 `actual_end_date` 우선 기간이라는 서로 다른 projection을 가지므로 Resolver 단건 호출로 치환하지 않는다. 이 소비자들은 동일한 양끝 포함 정책을 SQL에 유지하고, 직무·배치 목록의 effective 상태 식만 Resolver를 직접 사용한다.

## 2026-08-14 직원 대표 자격증과 자격·교육 원장 연결

- 직원은 복수 자격증을 `institution_qualifications_employee_records`에 보유한다.
- 직원 모달은 그중 하나를 대표 자격증으로 등록·수정·삭제할 수 있다.
- 대표 자격증 이름과 파일을 `user_employees`에 복제하지 않고 `representative_qualification_id` 연결만 저장한다.
- 대표 자격증의 상세 정보는 자격·교육관리와 동일 원장 행을 사용한다.
- 이 결정은 2026-08-06의 대표 자격값 완전 폐기 결정을 대체하며, 중복 저장 금지 원칙은 유지한다.

## 2026-08-04 — PERSONNEL_ACTION 2단계 결재선 확정

- 석향 인사발령의 결재선은 `발의(SUBMIT) → 대표이사 최종승인(FINAL_APPROVAL)` 2단계로 확정하고 관리자 중간승인은 사용하지 않는다.
- 활성 `PERSONNEL_ACTION` 템플릿 SSOT는 `7540cbd0-679b-4bd4-919c-afede9622d6c` 하나다. 다른 문서유형 템플릿을 대체 사용하지 않는다.
- 최종승인 단계에 역할과 지정결재자가 함께 있으면 `approver_id`를 우선한다. 역할은 지정결재자의 적격성 검증에 사용하며, 같은 역할의 다른 사용자에게 결재권한이 확장되지 않는다.
- 현재 최종승인 지정자는 이정호(`f113b666-ff40-4f93-a7e7-8cea4cdc9c28`)이다. 결재함 노출과 처리 조건은 모두 지정자 일치를 사용한다.

## 2026-08-04 — 인사발령 Runtime 최소 Baseline과 적용 경계

- `institution_personnel_actions.issued_date`는 문서상 발령일이고 기존 `action_date`는 효력일이다. `created_at`과 `approved_at`은 발령일 대체값으로 사용하지 않는다.
- 부서와 직위·직책은 각각 `institution_job_assignments_department_histories`, `institution_job_assignments_position_histories`가 기간 이력 SSOT다. 직원 마스터는 현재값이며 `PersonnelActionApplyService`만 두 저장소를 한 트랜잭션에서 동기화한다.
- `user_positions`는 현행 직위·직책 통합 SSOT다. 실제 독립 요구가 확정되기 전에는 별도 직책 마스터나 호환 컬럼을 만들지 않는다.
- 기존 직원 Backfill은 재직상태 이력의 최초 시작일을 우선하고 실제·문서상 입사일을 보조로 사용한다. 퇴직자는 실제·문서상 퇴직일을 종료일로 사용하며 과거 변경을 추정하지 않는다.
- 대상자별 적용 감사는 `application_status`, `application_error`, `applied_at`, `applied_by`를 사용한다. 헤더 `updated_by`를 적용처리자 보존용으로 사용하지 않는다.
- 변경행에는 범용 종료일·사유를 중복 저장하지 않는다. 종료일은 각 기간 SSOT, 사유는 발령 헤더와 대상자가 소유한다.
- 승인과 적용은 분리한다. 미래 발령은 `APPROVED`로 대기하고 효력일 도래 후 공식 적용 Service가 처리한다. 변경 전 스냅샷과 현재 SSOT가 다르면 자동 덮어쓰지 않고 대상자를 `FAILED`로 기록한다.

## 2026-08-04 — 인사·노무 공통 SSOT Baseline 확정

- `institution_personnel_actions_changes`는 제한된 인사 변경명령을 FK·코드·날짜 물리 컬럼과 유형별 CHECK로 표현하며 범용 EAV가 아니다. 현재 11개 변경유형은 하나의 적용 트랜잭션에서 함께 검증·정렬해야 하므로 유형별 상세 테이블로 분리하지 않는다.
- 신규 유형이 독립 생명주기, 다중 자식행 또는 별도 권한을 갖게 되면 해당 업무 도메인 테이블을 만들고 발령 변경행은 그 업무를 지시하는 참조만 보존한다. 단순 유형 증가만으로 범용 문자열·JSON 컬럼을 추가하지 않는다.
- 재직 핵심 상태는 `ACTIVE`, `ON_LEAVE`, `RETIRED`다. `PENDING_HIRE`는 미래 발령의 결재상태를 복제하는 값이 아니라, 직원 UUID가 발급되어 발령 대상·계약 당사자·사전 준비업무에서 참조되지만 아직 재직을 시작하지 않은 직원 마스터 행의 현재 생명주기 상태다. 승인된 입사 발령은 `APPROVED`와 `action_date`로 대기하고, 발령일 전까지 `PENDING_HIRE` 직원은 재직자 조회·근태·휴가 대상에서 제외한다. 따라서 `PENDING_HIRE`는 유지하되 미래 입사 승인 여부를 판정하는 SSOT로 사용하지 않는다. `SUSPENDED` 등 미확정 상태는 문서 후보로만 기록하고 코드·CHECK에는 추가하지 않는다.
- 허용 전이는 `PENDING_HIRE → ACTIVE`, `ACTIVE → ON_LEAVE`, `ON_LEAVE → ACTIVE`, `ACTIVE|ON_LEAVE → RETIRED`다. 퇴직 후 복원은 기존 행을 직접 되돌리지 않고 정정·취소 발령 정책을 거친다. 로그인 활성상태는 이 전이와 독립이다.
- 프로젝트의 `job_id`는 조직 공통 직무를, `assignment_role`은 해당 프로젝트에서 맡는 현장 역할 문구를 뜻한다. 역할을 직무 코드나 별도 마스터로 합치지 않으며, 반복 표준화 필요가 확인될 때만 별도 승인으로 역할 마스터를 검토한다.
- 근무지 유형은 `HEAD_OFFICE`, `PROJECT`, `BUSINESS_TRIP`, `REMOTE`, `OTHER`를 Baseline으로 한다. 프로젝트 현장만 `project_id`를 필수로 하고 나머지는 장소·주소 스냅샷으로 표현한다.
- 미래 발령의 적용 실행주체는 아직 구현되지 않았다. 현재는 효력일이 도래한 `APPROVED` 발령을 화면의 수동 적용 API로 처리할 수 있으며, 향후 Scheduler를 도입할 경우 적용대상 조회와 실행시각 제어만 담당하고 `PersonnelActionApplyService` 하나만 호출해야 한다. 현재상태·기간이력 갱신, 중복 잠금 검증, 적용 결과와 `APPLIED` 전환은 Service의 단일 트랜잭션 책임이다.
- `CANCELLED`는 정정·취소 원본 구조를 위해 예약된 업무상태이며 현재 인사발령 화면에는 상태 전환 API가 없다. DataTable 정비에서 임의 취소 경로를 추가하지 않는다.
- 이 결정을 인사·노무 공통 SSOT Baseline으로 고정한다. 화면 개발 편의나 개별 메뉴 요구만으로 현재 테이블을 우회하거나 중복 SSOT를 만들지 않는다.

## 2026-08-04 — 인사·노무 공통 현재상태·기간이력과 인사발령 SSOT

- 근로계약은 계약 당시 조건 스냅샷, 인사발령은 변경 기안·결재·적용 근거, 직원 마스터는 현재상태, 기간 테이블은 재직·휴직·직무·근무지·프로젝트 배치 이력을 담당한다.
- 직원 현재 재직상태는 `user_employees.employment_status`로 명시하고 `institution_job_assignments_employment_status_histories`가 날짜별 상태를 보존한다. 입퇴사일은 문서상·실제 일자 책임을 유지하며 로그인 `auth_users.is_active`와 합치지 않는다.
- 직무는 코드가 아니라 독립 `institution_job_assignments_jobs` 마스터를 사용한다. 향후 직무별 필수 자격·교육·프로젝트 적격성 관계가 이 ID를 참조한다.
- 현재 직무 조회를 위해 `user_employees.job_id`를 두되 `institution_job_assignments_job_histories`와의 유일한 동기화 주체는 향후 인사발령 적용 Service다.
- 프로젝트 배치와 근무지는 서로 다른 기간 SSOT다. 프로젝트 배치는 다중 참여를 허용하고 근무지는 특정 날짜의 주 근무장소를 나타낸다.
- MariaDB에 기간 exclusion constraint가 없으므로 기본 UK·기간 인덱스·현재 활성 generated key와 Service의 `SELECT ... FOR UPDATE` 기간중복 검증을 하나의 정책으로 사용한다.
- 인사발령 변경행은 범용 EAV 대신 변경유형별 FK·코드·날짜 물리 컬럼을 사용하며 CHECK로 다른 유형의 컬럼 사용을 차단한다.
- 승인과 적용은 분리한다. 미래 발령은 `APPROVED`로 대기하고 발령일 도래 후 적용 Service가 직원 현재상태와 기간이력을 한 트랜잭션에서 `APPLIED`로 전환한다.
- 기존 직원 Backfill은 실제 입사일을 우선하고 문서상 입사일을 보조로 사용한다. 실제 퇴사일을 우선하고 문서상 퇴사일을 보조로 사용하며 로그인 활성 여부는 재직상태 판정값으로 사용하지 않는다.

## 2026-07-31: 대외기관업무를 인사·노무와 소득자료 선행 구조로 재분류

- 기존 세무서·지방자치단체·근로복지공단·건강보험공단·국민연금공단 중심 메뉴를 제거하고 인사·노무관리, 소득자료관리, 국세업무, 지방세업무, 4대보험업무, 세무사업무, 신고이력 구조로 단일화한다.
- 직원 기본정보는 기존 `auth_users`, `user_employees`가 SSOT이므로 대외기관업무에 직원관리·직원인사정보·사용자관리 메뉴를 추가하지 않는다.
- 인사·노무관리에서 휴가관리 다음, 성과평가관리 전에 `자격·교육관리` 카테고리를 둔다. 이번 단계는 공용 Placeholder·Route·권한·메뉴 연결만 제공하며 자격증, 교육, 교육이력, 자격증이력, 법정교육 저장구조는 만들지 않는다.
- 목표관리와 평가는 성과평가관리로 통합한다. 인사발령 이력은 향후 인사발령관리에서 자동 생성하는 구조로 설계하므로 목표·성과관리, 인사평가, 인사이력 메뉴를 별도로 두지 않는다.
- 업무 흐름은 `근로계약 → 인사발령 → 직무·배치 → 근태·휴가 → 성과평가 → 보상 → 소득자료 → 기관신고`다.
- 이번 결정은 카테고리와 접근 구조만 확정하며 DB·Migration·API·Service와 업무 기능은 생성하지 않는다.

## 2026-07-30: 내부이체 관계를 기존 증빙·전표 SSOT로 단일화

- 별도 `ledger_fund_transfer_links`는 운영 데이터와 쓰기 진입점이 없고 동일 관계를 확정 전표의 증빙 연결과 계좌 참조로 표현할 수 있으므로 유지하지 않는다.
- 내부이체는 `ledger_evidence_bank_transaction`, `ledger_evidence_links`, 확정 `ledger_vouchers`, `ledger_voucher_lines`, canonical `ACCOUNT`의 `ledger_voucher_line_refs`, `system_bank_accounts`만 사용해 파생한다.
- 활성 은행 증빙이 정확히 출금 1건·입금 1건이고 금액이 같으며 서로 다른 자사계좌이고, 출금 대변·입금 차변 라인의 계좌 참조와 금액이 각각 정확히 일치할 때만 확정한다.
- `draft`, `review_requested`, 삭제·역분개 전표와 추가 증빙·추가 계좌 참조·모호한 관계는 제외한다. 적요·계정명·날짜·유사금액 추론과 legacy fallback은 사용하지 않는다.
- 지급예정 본체와 History는 실제 업무 요구사항 재확정 전 보류하며 이번 내부이체 전환에서 구조를 변경하지 않는다.

## 2026-07-30: 신규 DB 구조는 업무·SSOT·DDL 사전승인 후 생성

- Table·Column·View·Trigger·Routine·Event·핵심 제약·Backfill·운영 Migration은 실제 업무 문제, 기존 SSOT와 대안, 저장/파생값, 생명주기, 감사, DDL 및 Rollback 영향을 먼저 보고하고 사용자 승인을 받은 뒤 작성한다.
- 미래 가능성, 개발 편의, legacy 호환, 임시 fallback만을 이유로 DB 구조를 만들지 않으며 Git 미등록 상태에서 운영 DB를 먼저 변경하지 않는다.

## 2026-07-30: 개인경비 공식 재처리 구조 제거

- `ledger_personal_expense_reprocess_batches`와 `ledger_personal_expense_reprocess_items`는 실제 데이터 0건, API 호출 로그 0건, 역할 권한 배정 0건, 사용자 화면 및 상태조회 API 0건이므로 제거한다.
- 정상 최종 승인은 개인경비 아이템 1건마다 증빙 1건과 거래 1건을 생성하거나 재사용한다. 모든 증빙이 단일 원천 거래에 연결됐다고 가정한 재처리 Service는 현행 승인 구조와 일치하지 않는다.
- expected/actual 건수·금액과 기존/대체 거래 매핑은 개인경비 원본, 결재, 증빙, 거래, 거래품목·정산, `ledger_evidence_links`에서 중복되거나 계산 가능한 값이며 별도 감사 보존 정책도 없다.
- 개인경비 최종 SSOT는 `approval_personal_expenses`, `approval_personal_expense_items`, `user_approval_requests`, `user_approval_request_steps`, `ledger_evidence_employee_personal_expense`, 거래 테이블, `ledger_evidence_links`다.

## 2026-08-15: 개인경비 최종 유지보수·성능 정비

- 개인경비 UI가 실제로 사용하는 상신 경로는 저장과 상신을 원자적으로 처리하는 `save-submit` 하나이므로 Runtime 참조가 없는 별도 `/api/approval/personal-expense/submit` Route와 Controller action을 제거한다. Service 내부 `submit()`은 `saveAndSubmit()`의 결재 처리 단위로 유지한다.
- 휴지통은 헤더별 아이템 조회를 반복하지 않고 활성 아이템을 한 번에 조회해 신청서 ID로 그룹화한다. 일반 목록은 최대 500건으로 제한하고 필터 건수 조회에서 불필요한 결재 JOIN을 제거한다.
- 마지막 처리단계와 반려정보의 상관 조회를 전체 단계 윈도 함수 파생테이블로 전환하는 안은 운영 데이터 200회 실측에서 기존 인덱스 기반 조회보다 느려 적용하지 않는다. 예정 결재자와 실제 처리자의 기존 의미도 유지한다.
- 운영 DB에 남은 `api.approval.personal-expense.act`, `pending`, `reprocess-accounting` 권한과 이번에 Runtime Route를 제거한 `submit` 권한은 역할·사용자 배정이 존재한다. 운영 DB 변경 승인 후 신규 forward-only Migration에서 배정 참조를 먼저 제거하고 권한을 제거해야 하며 이번 정비에서는 DB를 변경하지 않는다.
- Repository에는 전역 Migration 이력 테이블·통합 실행기·무데이터 최초 schema baseline이 없고, 개인경비 최초 CREATE TABLE은 운영 데이터가 포함된 `storage/db_backup` dump에서만 확인된다. 현재 운영 DB는 정상이나 백업 없이 증분 Migration만 적용하는 신규 환경 재현은 불가능하므로 전 프로젝트 범위의 별도 baseline 정책 승인이 필요하다. 개인경비 정상 테이블을 추측으로 재생성하는 Migration은 추가하지 않는다.
- 업무내용 정정은 향후 승인 취소·정정 승인 정책으로 다루고, 장애 복구가 필요하면 현재 상태를 검사해 누락 결과만 복구하는 제한적 멱등 흐름으로 별도 설계한다. 재처리 Batch·Item·fallback은 다시 만들지 않는다.

## 2026-07-30: Evidence Split 및 processing 구조 제거

- 실제 운영 데이터, Split 링크, 최근 6개월 사용 이력이 모두 0건이고 사용자 업무는 거래 item·settlement·voucher line으로 처리할 수 있으므로 증빙 Split 기능을 제거한다.
- `ledger_processing_items`와 `ledger_processing_item_actions`는 생성센터 잔존 구조이므로 신규 삭제 Migration으로 제거한다.
- 새 Split SSOT, archive, 호환 View, 이중 조회, fallback은 만들지 않는다.
- 증빙 식별 SSOT는 각 Evidence Body의 `(canonical import_type, id)`, 외부 중복 식별은 `EvidenceExternalKeyService`, 거래·전표 연결은 `ledger_evidence_links`다.
- 폐기 구조와 데이터를 복원할 수 없으므로 down Migration은 재생성하지 않는다.

## 2026-07-28 자금관리 3영역 분리

- 실제 자금은 은행·현금 증빙원본의 입금-출금으로 계산한다.
- 회계 자금은 POSTED/CLOSED 전표라인 중 BANK_ACCOUNT 참조가 지정된 라인의 차변-대변으로 계산한다.
- 지급 전망은 `ledger_payment_schedules`의 활성 지급의무와 PAYMENT 배분으로 계산한다.
- 세 값을 합성 잔액 하나로 섞지 않으며 자금현황과 자금일보에서 같은 기준으로 대사한다.
- 계좌별거래내역은 동일 은행 원본의 전표연결, 지급배분, 확정 내부이체 상태를 각각 표시한다.
- 2026-07-16: evidence output detection uses the canonical link SSOT
  - Why: transaction IDs, voucher source IDs, fingerprints, and evidence-id-only lookup can cross evidence types or revive retired generation-center coupling.
  - Decision: active transaction/voucher output detection uses `ledger_evidence_links` with canonical `(import_type, evidence_id)` identity. The physical `evidence_type` column receives canonical `import_type`; policy `evidence_type` values are not identities.
  - Impact: earlier processing-table and source-ID fallback decisions are superseded for runtime output detection. Body tables remain the evidence source SSOT.


- 2026-07-15: system metadata and runtime status query ownership
  - Decision: fixed replication status queries belong to `DatabaseReplicationStatusModel`; topology normalization and result interpretation remain in `DatabaseReplicationStatusService`.
  - Decision: allowlisted `information_schema` access is centralized in `SystemSchemaModel`, while `DataTableColumnMetaService` retains domain mapping and presentation metadata composition.
  - Decision: layout composition reuses employee and setting SSOTs. Notification persistence uses `NotificationModel`, while administrator recipient lookup remains an authentication user concern.
  - Constraint: this boundary does not extend the backup/restore/sync raw-SQL exception and does not permit arbitrary SQL or caller-provided identifiers.

- 2026-07-14: evidence dynamic dropdown and reference reads use bounded Models
  - Why: evidence policy selects physical tables and columns dynamically, but passing SQL fragments from Services or exposing an arbitrary-table Model would only disguise direct SQL and create a reusable bypass.
  - Decision: reference types use a fixed `EvidenceReferenceModel` map, and dropdown reads use `EvidenceDropdownModel` with identifier validation plus a ledger/system table allowlist. Schema existence and expression selection use the existing `EvidenceSchemaModel`.
  - Impact: Services retain policy, cache, fallback, label, and workbook responsibilities; values remain bound and only server-validated identifiers reach Model SQL.

- 2026-07-14: core DB infrastructure exception is restricted to connection, transaction, and Controller injection
  - Why: `Database`, `DbPdo`, and `Router` must establish the shared PDO connection and preserve the existing Controller constructor contract during bootstrap, while permission, settings, menu, page, and user SQL are ordinary domain responsibilities.
  - Decision: only connection creation/options, transaction primitives, and Router constructor injection remain in core infrastructure. Middleware and ConfigHelper obtain business data through Services, and Services delegate SQL to Models. Raw SQL input and business-table SQL are prohibited in the infrastructure files.
  - Impact: the exception cannot be reused by Controllers, Views, Traits, Helpers, or ordinary Services to bypass Model ownership. Calendar remains outside phase 1-1 and retains its existing shared contracts.

- 2026-07-06: `EvidenceGenerationService` read refactor must shrink by moving processing SQL policy out
  - Why: the body-table read conversion had started to add `ledger_evidence_processing` existence checks, JOIN SQL fragments, and processing status select rules directly into `EvidenceGenerationService`, which pushes the service toward a God Service and violates the evidence refactor direction of keeping generation focused on runtime read orchestration only.
  - Superseded decision: the temporary `EvidenceProcessingPolicyService` boundary was removed when the processing table was retired. Body reads now use the Body `evidence_status`, link identity is canonical `import_type + evidence_id`, and physical schema projection is handled by Models without Model-to-Service dependencies.
  - Impact: follow-up read conversion must continue shrinking `EvidenceGenerationService` by moving non-read-policy concerns outward rather than adding more type/page/field/UI/upload logic into the service.
- 2026-07-06: `EvidenceGenerationService` body-table query blocks move to `EvidenceBodyReadService`
  - Why: even after policy extraction, runtime body-table row query and count SQL still kept `EvidenceGenerationService` above the 1,500-line service limit and mixed orchestration with low-level body-table read details.
  - Decision: body-table row reads, count queries, and evidence-type count helpers move to `EvidenceBodyReadService`. `EvidenceGenerationService` remains the read orchestrator and delegates DB-physical-column-based body queries outward.
  - Impact: the read pipeline keeps the same controller entrypoints while reducing `EvidenceGenerationService` size under the AGENTS rule and making later read-runtime conversion steps safer.
- 2026-07-06: `EvidenceBodyReadService` must not own SQL directly
  - Why: AGENTS already defines `Controller -> Service -> Model` as the project standard, minimizes direct SQL in services, and assigns DB access responsibility to Model. Service-local logging remains acceptable, but query text inside the service violates the project read/write boundary.
  - Decision: `EvidenceBodyReadService` becomes flow-only orchestration and delegates concrete body-table SQL to domain-specific read models such as `BankEvidenceReadModel`, `TaxInvoiceReadModel`, `CashReceiptEvidenceReadModel`, `CardStatementEvidenceReadModel`, `BusinessDataEvidenceReadModel`, `PayrollEvidenceReadModel`, and `ConstructionEvidenceReadModel`.
  - Impact: runtime read behavior stays in the same service pipeline, while SQL ownership moves to the model layer without introducing a repository-only exception structure for the evidence domain.

- 2026-06-05: Employee sequence SSOT fixed to `user_employees.sort_no`
  - Why: `auth_users.sort_no` is already used as account code in approval mail/token and account lookup flows, while employee list ordering, reorder, and external `employee_code` flows use `user_employees.sort_no`.
  - Impact: employee UI/docs must not interpret `auth_users.sort_no` as employee sequence; `user_sort_no` remains optional account-code metadata only.

- 2026-07-06: Ledger evidence field meta SSOT phase 1 fixed to `SystemFieldService + DB physical columns`
  - Why: `EvidenceTypePolicyService` had started to absorb `meta_domain`, `excel_manager_domain`, `source_key_aliases`, `modal_preset`, `date_options`, and field-label/display concerns, which are page/field metadata responsibilities rather than import/source type normalization policy. Keeping those concerns in the type-policy service would create another God Service and make evidence pages diverge from the shared DataTable/Excel/UserSetting structure already used by client/account/journal-rule screens.
  - Decision: `EvidenceTypePolicyService` remains the SSOT only for import/source type normalization, legacy alias normalization, upload allow lists, and simple business-data classification. Field/meta-domain, modal preset, upload/download field policy, and display label concerns must converge to `SystemFieldService` plus DB physical-column metadata, while ready/planned page rollout stays in page policy and processing-plan logic becomes a separate processing policy concern.
  - Impact: next read-conversion work must consume `SystemFieldService` metadata first, treat `mapped_payload_json` field lists as transitional only, and avoid adding any new field/UI metadata keys to `EvidenceTypePolicyService`.

- 2026-07-06: Ledger evidence field-meta runtime default path switched to DB physical columns
  - Why: `SystemFieldService::fieldOptions()` still merged `referenceFieldOptions()` and `mappedPayloadFieldOptions()`, so runtime field-meta generation was not actually using body-table columns as the default path even after the SSOT decision was documented.
  - Decision: runtime default generation now starts from `targetTableForDataType() -> information_schema.COLUMNS` and reuses that physical-column set for `sourceColumnOptions()`. Controller-side requested-column filtering no longer synthesizes missing field definitions on the fly. Legacy payload-field helpers remain in code as classified compatibility paths but are excluded from the default runtime flow.
  - Impact: evidence import/upload/table/excel field lists now derive from DB physical columns first. Remaining `mapped_payload_json` field metadata is a follow-up cleanup target for the read-conversion stage, not the active default generator.

- 2026-06-04: `BundledVoucherService` split phase 1
  - Scope: `createBundledVoucherFromEvidenceRows`, `tagBundledVoucher`
  - Reused services/helpers: `VoucherCreateService`, `VoucherPolicyService`, `VoucherService`, `EvidenceRuleEngineService`, `EvidenceBankHelperService`, `EvidencePayloadNormalizeService`, `EvidenceStatusHelperService`

- 2026-06-04: `EvidenceUploadParserService` split phase 1
  - Scope: `parseUploadedRows`, `parseUploadedBankWorkbook`, `parseSheetMappedRows`, `loadUploadedSpreadsheet`
  - Added helpers: `hasBankVoucherLineColumns`, `bankLineSheetHasRowTypeColumn`, `normalizeBankVoucherLineRowType`, `uploadHeaderColumnsByName`, `uploadSheetColumnForFormatColumn`, `payloadKeyFromExcelColumn`, `detectCsvEncoding`
  - Kept in caller: `storeUploadBatch`, `enrichUploadRows`, `validatePreviewRowsV2`, `assertNoUploadValidationErrors`
- 2026-06-04: `EvidenceBatchSaveService` split phase 1
  - Scope: `commitUploadChunkIfNeeded`, `uploadStatusFromValidation`, `assignEvidenceJsonSortNo`, `nextEvidenceJsonSortNo`
  - Kept in caller: `storeUploadBatch`
- 2026-06-04: `EvidenceBatchSaveService` split phase 2
  - Scope: validation/status duplicate lookup, payload build, persist parameter assembly
  - Kept in caller: `storeUploadBatch` transaction boundary, schema ensure, bank side effect

- 2026-06-04: `VoucherPolicyService` split phase 1
  - Scope: `applyEvidenceRefsToVoucherLines`, `missingRequiredEvidenceRefsMessage`, `lineHasRefType`, `evidenceRefIdForType`, `voucherRefPoliciesForAccount`, `policyRefTypeFromRow`, `policyRefTypeFromSubPolicy`, `resolveLedgerAccountId`, `voucherRefTypeLabel`, `normalizeVoucherRefType`, `normalizeAccountInput`
  - Used by: `createVoucherFromBankPayload`, `createBundledVoucherFromEvidenceRows`, `tagBundledVoucher`

- 2026-06-04: `VoucherCreateService` split phase 1
  - Scope: `createVoucherFromBankPayload`, existing voucher check helpers, bank voucher line/payment build helpers, voucher link/status helpers
  - Excluded: `createBundledVoucherFromEvidenceRows`, `tagBundledVoucher`, learning helper
  - Kept in controller: `apiTemplate`, `apiFieldOptions` entrypoints only
- 2026-06-04: `EvidenceBusinessRefService` split phase 1
  - Scope: `businessRefIdForStorage`, `businessRefNameForStorage`, `businessRefCandidateValues`, `isEmptySelectionLabel`, `normalizeBusinessRefPayload`, `businessRefPayloadKeyMap`
  - Reused helpers: `EvidenceReferenceResolverService`, `VoucherPolicyService`, `payloadScalarForStorage`
- 2026-06-04: `EvidenceClientSyncService` split phase 1
  - Scope: tax invoice client sync, import client sync, client upsert/update helper, import party/name normalization
  - Reused helpers: `normalizeBusinessNumber`, `cleanCompanyName`, `normalizeCompanyNameForCompare`, `payloadScalarForStorage`
- 2026-06-04: `EvidenceBankHelperService` split phase 1
  - Scope: bank payload normalize, bank evidence sync, bank transaction upsert, bank voucher validation helper
  - Reused services/helpers: `EvidenceReferenceResolverService`, `EvidenceTransactionContextService`, `VoucherCreateService`, `EvidenceBusinessRefService`
- 2026-06-04: `EvidenceStatusHelperService` split phase 1
  - Scope: readiness apply helper, active output detection, transaction/voucher existence helper, evidence status SQL helper
  - Reused services/helpers: `EvidenceRuleEngineService`, `VoucherCreateService`, `EvidenceBankHelperService`
- 2026-06-04: `EvidencePayloadHelperService` split phase 1
  - Scope: evidence payload scalar normalization, storage JSON encode helper, seed row id extraction, evidence total amount calculation, blank value detection
  - Reused helpers: `amountOrNull`, `isEmptySelectionLabel`, `dateValue`, `normalizeDataType`
- 2026-06-04: `EvidenceDeleteRestoreService` split phase 1
  - Scope: evidence soft delete helper, evidence restore helper, evidence body delete/restore helper
  - Reused helpers: `placeholdersForIds`, `tableExists`
- 2026-06-04: `EvidenceLifecycleService` split phase 1
  - Scope: evidence purge lifecycle, evidence processing delete lifecycle, bank transaction sync lifecycle, evidence hard delete lifecycle
  - Reused services/helpers: `EvidenceDeleteRestoreService`, `EvidenceLinkHelperService`, `placeholdersForIds`, `tableExists`
- 2026-06-04: `EvidenceUploadValidationService` split phase 1
  - Scope: upload row enrichment, upload amount normalization, preview validation, business/project validation, upload validation error assertion
  - Reused services/helpers: `EvidenceTransactionContextService`, `EvidenceRuleEngineService`, `EvidenceBusinessRefService`, `EvidencePayloadNormalizeService`
- 2026-06-04: `EvidenceLinkHelperService` split phase 1
  - Scope: evidence purge dependency helper, evidence link soft-delete/delete helper, evidence source reference detach, evidence split item detach on evidence purge
  - Reused helpers: `placeholdersForIds`, `tableExists`, `tableColumnExists`
- 2026-06-04: `EvidenceSortHelperService` split phase 1
  - Scope: evidence payload sort value helper, evidence sort column ensure helper
  - Reused helpers: `normalizeDataType`
- 2026-06-04: `Upload API Entry` split phase 1
  - Scope: `apiSeedUpload`, `apiSeedUploadCancel`, `apiUploadBatches`, `apiUploadBatchRows`, `apiUploadBatchDelete`
  - Kept in `ImportController` as internal handlers: `handleSeedUpload`, `handleSeedUploadCancel`, `handleUploadBatches`, `handleUploadBatchRows`, `handleUploadBatchDelete`
  - Route change: none
- 2026-06-04: `EvidenceBatchSaveService` phase 3
  - Scope: batch counter aggregation, protected/error/new-updated count wrapping, cached seed array build, final upload batch result assembly
  - Kept in `ImportController`: transaction boundary, SQL prepare/execute, schema ensure
- 2026-06-04: `VoucherLearningService` split phase 1
  - Scope: `recordBankVoucherLearning`, `bankVoucherLearningLines`, `normalizedRefPayload`, `amountBucket`
  - Reused services/helpers: `JournalLearningService`, `VoucherPolicyService`, `EvidenceBusinessRefService`, `transactionDirectionForStorage`, `businessUnitForUpload`
- 2026-06-04: `EvidenceTypePolicyService` split phase 1
  - Scope: `transactionDirectionForStorage`, `processingPlanForDataType`, `allowedDataTypes`, `isManualTaxInvoiceDataType`
  - Reused helpers: `normalizeDataType`, `amountOrNull`
- 2026-06-04: `Upload Policy helper consolidation`
  - Scope: `seedSourceKey`, `updateUploadRowStatus`, `isGenerationCorrectionMessage`, `businessUnitForUpload`
  - Reused services/helpers: `EvidenceUploadService`, `EvidenceTypePolicyService`, `EvidenceBusinessRefService`, `amountOrNull`, `dateValue`, `dateTimeValue`, `tableColumnExists`
- 2026-06-04: `handleSeedUpload` phase 1
  - Scope: preview confirm orchestration, required-missing confirmation response build, chunk upload progress/result build
  - Kept in `ImportController`: request branching, HTTP response final return, `storeUploadBatch` call decision
- 2026-06-04: `handleSeedUpload` phase 2
  - Scope: upload file path orchestration, trace payload build, validation response build, preview confirm response build
  - Kept in `ImportController`: request branching, HTTP response final return, `storeUploadBatch` call decision
# Evidence link identity and BOTH policy

# 2026-07-15: Chart-account sub-account policy SSOT

- Why: account policy was split between the live `ledger_accounts_sub.ref_target` structure and compatibility code for an absent historical policy table, while Excel services resolved codes and accounts with their own SQL.
- Decision: `ledger_accounts` remains the chart-account SSOT, `ledger_accounts_sub.ref_target` is the only account-level allowed sub-account-target policy, and voucher-line values remain in `ledger_voucher_line_refs`. 계정과목과 보조계정 정책은 화면 Modal에서만 변경하며 전용 Excel 양식·다운로드·업로드 Service와 Route는 운영하지 않는다. 공용 Excel Manager와 다른 도메인의 Excel 기능은 유지한다.
- Constraint: do not restore `ref_type`, recreate the absent policy table, or duplicate voucher-line reference values in account policy rows.

- 2026-07-11: Evidence links use `(import_type, evidence_id)` as identity, active `ledger_evidence_metadata.evidence_type` as the DATA/FUND/BOTH policy, metadata `source_table` as the direct body locator, and `ledger_evidence_links` as the link SSOT.
- 2026-08-15: Evidence Metadata remains a two-table Runtime Registry plus Semantic Mapping, not a general rule engine. `process_role` and mapping `is_required` are removed from Runtime input while their NOT NULL DB columns remain as compatibility fields pending separate DB approval. Metadata changes never rewrite Evidence Body, transactions, links, vouchers, or voucher lines; historical projections may be reinterpreted by the current mapping. Runtime-required or referenced headers are protected from delete/purge, restore is conflict-checked atomically, and evidence-area `evidence_type` remains distinct from transaction direction.
- The legacy `ledger_evidence_links.evidence_type` column name remains unchanged but stores canonical `import_type` only.
- 2026-07-11: Transaction entry owns DATA/BOTH evidence links only. Direct transaction-to-voucher create, recommend, link, and unlink entry routes and modal controls are removed; voucher relationships must be derived through shared evidence identity rather than `ledger_transaction_links` runtime actions.
- 2026-07-11: Direct transaction-to-voucher persistence is retired. Pre-migration inspection found `ledger_transaction_links` had zero rows (active 0, deleted/inactive 0) and `ledger_voucher_line_refs` had zero `TRANSACTION` references, so there was no direct-link audit data to preserve. `ledger_transaction_links`, direct header columns/status, and transaction-target voucher line references are removed without a replacement archive table because an archive would preserve a second relationship SSOT. Transaction and voucher relationships are independently stored through `ledger_evidence_links`; the migration is data-irreversible for the retired direct-link schema.
- 2026-07-15: Evidence soft delete preserves `ledger_evidence_links` so deletion does not silently sever accounting evidence. Status mutation, soft delete, and purge are rejected for linked evidence; purge is allowed only for an already deleted Body row after that constraint is rechecked.
- 2026-07-15: Transaction soft delete and restore also preserve canonical evidence links. Only hard deletion of an already soft-deleted transaction removes its `target_type=TRANSACTION` links, items, settlements, and header, and those changes share one transaction boundary. This avoids turning temporary transaction trash state into evidence unlinking and keeps `(import_type, evidence_id)` as the only evidence identity.
- A `BOTH` evidence is searchable from both voucher buckets but is stored once per voucher; bucket is a UI/search concern and does not create a second policy SSOT in `link_type`.
- Voucher, journal-line, line-ref, and evidence-link writes are one `VoucherService` transaction. Separate controller link/unlink writes are not part of the runtime contract.

# Evidence source projection boundary

- 2026-07-11: `EvidenceSourceRepository` is the shared integrated evidence read repository. Its paged projection represents current evidence-source state only and contains exactly four responsibility groups: `identity`, `body`, `processing`, and `links`. Body reference IDs remain the stored SSOT, while projection display names are resolved from the corresponding base-information tables; date, representative content, and amount displays use `ledger_evidence_metadata_columns` semantic mappings rather than per-screen field guesses. A configured `DESCRIPTION` remains authoritative even when its text equals the counterparty display name.
- 2026-08-15: 증빙원본 Evidence Type Selector는 URL 기반 Dataset Navigation으로 유지한다. 고유 URL, 새로고침, History, Type별 SearchForm/Table Setting/View State를 보존하고 in-page AJAX Tab으로 바꾸지 않는다. 자료유형이 길고 13개 이상 표시될 수 있으므로 한 줄 Bootstrap Tab 대신 실제 URL anchor와 `aria-current`를 사용하는 여러 줄 segmented navigation을 적용한다. 목록은 공용 DataTable의 server-side 계약을 사용하고 Count는 단일 summary API에서 canonical Type별 전체 활성 원본 수를 반환한다. Type의 활성·표시명·순서는 `system_codes(IMPORT_TYPE)`, Runtime capability는 `EvidenceTypePolicyService`, 원본·Semantic Mapping은 Metadata 2테이블, 거래·전표 연결은 `ledger_evidence_links`가 각각 담당하며 Excel 노출도 Runtime Policy를 따른다. 모든 Type별 고유 URL은 공통 `view` 권한 검사를 수행하며 직접 URL이 권한검사를 우회하지 않는다.
- `identity` contains canonical evidence identity and paging state, `body` contains the source Body state, `processing` contains current processing-item rows, and `links` contains current evidence-link rows.
- The projection is not a recommendation model or UI model. It must not contain Rule Engine results, recommendation or candidate results, workspace/DataTable DTOs, ViewModels, UI labels, or derived display strings.
- Rule Engine execution, candidate and recommendation calculation, processing-tree presentation, DataTable/Workspace DTO construction, and display-name composition remain Service responsibilities. This boundary prevents persistence-oriented read state from becoming a second recommendation or UI SSOT.

# Creation-center DataTable settings identity (폐기된 과거 결정)

- 2026-07-11: The creation-center list uses the shared `createDataTable` TABLE/VIEW settings contract with isolated page key `ledger.data.create`, table key `evidence-create`, and metadata domain `evidence-create`. This domain is a semantic Projection rather than physical DB metadata: table settings expose the fixed common business columns followed by the six evidence-policy meanings, while each import type's physical mapping remains internal to value resolution.
- Creation-center status columns are limited to `evidence_status` from the Body plus two virtual projections. `generation_status` combines active transaction/voucher link existence, and `bundle_status` identifies vouchers linked to multiple evidence identities. Internal processing workflow state is not exposed as a separate table column.
- Creation-center single-evidence transaction/voucher creation is performed only inside the Workspace modal after review. The former table-toolbar bulk `거래/전표생성` shortcut is removed because it bypassed Workspace review and its label did not identify the actual policy-selected target. The table toolbar retains only multi-selection work that inherently requires the list context, such as bundled-voucher issuance.
- The metadata domain is a runtime composite of common evidence Projection columns and active `ledger_evidence_metadata_columns` Body mappings. It does not create another evidence SSOT; reset-to-default always rebuilds from current DB metadata and removes deleted columns through the shared settings normalizer.
- TABLE state owns visibility, order, display name, and requirement policy. VIEW state owns width, page length, sorting, current page, and search-form state. Creation-center code must not persist a second localStorage/sessionStorage or page-specific JSON settings model.
# 2026-07-13: Evidence SSOT 중심의 거래입력·전표입력 아키텍처 전환

- Why: 생성센터는 프로젝트 초기 설계에서 `증빙 → 거래 → 전표 생성 Workspace` 역할을 담당했다. 거래입력과 전표입력이 각각 Evidence Selection Module을 통해 증빙을 직접 참조 생성하는 구조로 변경되면서 생성센터는 중복 계층이 되었다.
- Decision: 증빙원본은 조회·수정·상태·Validation·연결정보만 담당한다. 거래입력은 `TransactionCrudService`, 전표입력은 `VoucherService`를 통해 각각 신규 생성과 증빙 참조 생성을 담당한다. 연결 SSOT는 `EvidenceLinkModel`과 `ledger_evidence_links`로 단일화한다.
- Constraint: 거래·전표 직접 FK나 직접 연결 테이블을 만들지 않는다. 거래와 전표는 `ledger_evidence_links`에서 각각 독립적으로 증빙을 연결하며, REVIEWED·POSTED 검증에서 동일 증빙의 활성 거래 존재를 요구하지 않는다.
- Migration order: 전표입력 이관 → 거래입력 이관 → 증빙원본 상태·Validation·연결정보 완성 → 생성센터 참조 0건 확인 → Route/Controller/Service/View/JS/CSS/Menu/Registry/권한/문서 제거.

# 2026-07-14: 별도 생성센터 폐기 완료 결정

- 별도 생성센터와 증빙원본의 일괄 거래·전표 생성 경로를 폐기한다. 거래 생성은 거래입력, 전표 생성은 전표입력만 담당한다.
- 거래입력은 `DATA`/`BOTH` 자료증빙만 연결하며, 전표입력은 `DATA`/`FUND`/`BOTH` 증빙을 연결할 수 있다.
- 증빙 Identity는 `(import_type, evidence_id)`이고 연결 SSOT는 `ledger_evidence_links`로 유지한다. 거래와 전표 사이의 직접 연결은 만들지 않는다.
- 생성센터 processing 및 Split 저장 구조는 운영 미사용으로 제거하며, 거래추천·분개추천·거래·전표 생성과 연결 이력은 각각의 현재 SSOT가 담당한다.

# 2026-07-15: 전표 저장·삭제 DB 책임과 링크 보존 정책

- 전표 업무검증, 상태전이, 번호 정책과 트랜잭션은 Service가 담당하고 실제 조회·저장은 전표 및 참조 Model이 담당한다.
- 전표 소프트삭제·복원은 증빙 연결 이력을 보존한다. 영구삭제만 이미 소프트삭제된 전표를 대상으로 line ref, line, `VOUCHER` target link와 header를 원자적으로 제거한다.
- 증빙 연결 Identity는 canonical `(import_type, evidence_id)`이며 전표와 거래 사이의 직접 FK나 processing 상태를 사용하지 않는다.
- 현행 전표번호는 날짜 접두사의 최신 번호에 1을 더하는 방식을 보존한다. 동시성 강화를 위한 전용 시퀀스 전환은 DB 변경 승인이 필요한 후속 기술부채다.

# 2026-07-15: 카드·프로젝트·업무팀 참조 조회의 Model 경계

- 기준정보 Service는 입력 정규화, 업무검증, 삭제·복원 정책과 Excel 흐름을 담당하고 목록·참조·드롭다운 DB 조회는 해당 테이블 소유 Model에 둔다.
- 프로젝트 import의 기존 공개 계약을 보존하기 위해 `ProjectReferenceResolver`는 유지하되 client/employee 조회는 각각 `ClientModel`, `EmployeeModel`로 위임한다.
- 동적 테이블·컬럼 SQL을 Service에 남기지 않으며 카드와 업무팀 Excel 드롭다운은 서버 allowlist가 있는 Model 메서드만 사용한다.

# 2026-07-15: 거래처·프로젝트 Excel 참조 시트 조회 경계

- Excel Service는 파일 형식, 컬럼 설정, 필수값과 오류 행 집계를 소유하고 DB dropdown 조회는 각 기준정보 Model에 위임한다.
- 기존 EXCEL_UPLOAD/EXCEL_DOWNLOAD 컬럼 설정과 template/download 기본값은 변경하지 않는다.
- 범용 Excel reference 저장소를 만들지 않고 client, account, employee의 기존 SSOT Model에 허용 필드를 명시한다.
- 위의 creation-center 관련 2026-07-11 결정은 감사 이력으로 보존하지만 현재 유효한 런타임 구조가 아니다.
- 최종 런타임 DB에는 `ledger_data_evidences`와 `ledger_evidence_processing`이 존재하지 않는다. 업로드는 자료유형별 Evidence Body에 직접 저장하며, 별도 processing 상태나 제거된 통합 원본 fallback을 복구하지 않는다.
# 2026-07-14 — 공용 Helper DB 접근의 책임 Model 이동

- Actor 표시명 조회는 EmployeeModel에 역의존시키지 않고 읽기 전용 ActorDirectoryModel로 분리한다. EmployeeModel을 포함한 여러 Model이 ActorHelper의 표시 보강 계약을 사용하므로, ActorHelper가 EmployeeModel을 다시 호출하는 직접 순환을 피하기 위한 결정이다.
- 커버 이미지 재채번은 해당 테이블을 소유한 CoverModel이 기존 SQL과 트랜잭션을 그대로 담당한다. DataHelper는 기존 호출 계약을 위한 진입점만 유지한다.
- SequenceHelper의 동적 식별자 검증과 오류 계약은 유지하고 SQL 실행만 SequenceModel로 이동한다. 기존 `MAX + 1` 채번 방식은 이번 구조 이동에서 변경하지 않으며 동시성 개선은 별도 과제로 남긴다.

# 2026-07-16: 생성센터 전체 증빙순번 폐기

- 생성센터가 제거되어 자료유형을 가로지르는 전체 증빙 정렬 Identity가 더 이상 존재하지 않으므로 Evidence Body의 `evidence_sort_no`를 제거한다.
- 각 자료유형별 증빙원본 화면과 저장소의 정렬 SSOT는 기존 `sort_no`를 유지한다. 통합 조회에서도 전역 순번을 재생성하거나 다른 컬럼으로 대체하지 않는다.
- 컬럼 삭제로 과거 전체순번 값과 전역 유일성은 복원하지 않는다. 런타임 참조가 없고 이력 테이블에는 초기 백필만 존재하므로 `ledger_evidence_number_sequences`와 `ledger_evidence_number_histories`도 후속 Migration에서 제거한다.

## 2026-07-17 — Personal expense approval owns the business document, approval keeps immutable execution history

- Decision: keep personal-expense headers in `approval_personal_expenses`, keep their lines in `approval_personal_expense_items`, and create one `user_approval_requests` row per header submission. The header is the approval bundle; each item is an independent accounting transaction unit. Final approval creates one evidence, one transaction header, one transaction item, an optional VAT settlement, and one evidence link per application item in one database transaction.
- Why: storing request state on the business document would duplicate the approval SSOT, while resetting rejected requests would destroy audit history. Item-level transactions preserve each expense date, supplier, project, employee reimbursement target, amount, and accounting judgment without weakening atomic final approval.
- Constraint: `EMPLOYEE_EXPENSE_PERSONAL` is the canonical active import type. The automatically generated evidence page is read-only, and no receipt file is persisted until a shared business-document attachment repository exists.
- 2026-07-24: Personal-expense item Excel operations reuse the shared Excel Manager modal, settings core, DB-backed `EXCEL_UPLOAD`/`EXCEL_DOWNLOAD` setting types, column-policy request helpers, and spreadsheet formatter. Upload is a validated full replacement of the unsaved modal grid; it does not persist rows, trust item/header IDs, or create clients before final approval.
## 2026-07-20 — Approval template step order is template-scoped

- Decision: allocate and normalize `user_approval_template_steps.sort_no` independently per `template_id` under a parent-template row lock.
- Why: the unique key is `(template_id, sort_no)` and approval execution snapshots interpret the number as the order inside one template; a global sequence caused the first step of a new template to start after unrelated templates.
- Menu decision: the current application sidebar is the runtime menu SSOT for the electronic-approval section, so `개인경비 신청` is linked there while route permissions continue through `PermissionRegistry` and the canonical `approval.personal_expense` page-key fallback.

## 2026-07-25 — Integrated approval inbox and current-request policy

- Decision: `user_approval_requests.status` is the overall workflow SSOT and `user_approval_request_steps` is the only step SSOT. The actual DB has no `user_approval_requests_steps` table.
- Decision: `approval_personal_expenses.current_approval_request_id` is the stable pointer to the current immutable request. `document_status` is a synchronized document-side projection used for document policy and is never independently interpreted as a second workflow.
- Decision: rejected or withdrawn resubmission creates a new request and new step snapshots; prior requests and comments stay queryable in the integrated inbox and applicant detail.
- Decision: the canonical implementation domain is `approval-inbox`; the existing `/approval/status` URL is retained while the menu and page-registry metadata are migrated from “결재 현황” to “결재함”.
- Decision: only `PERSONAL_EXPENSE` currently has a complete document adapter. Approval mutations delegate to `PersonalExpenseApprovalService`; final approval creates or reuses one independent transaction per item and does not create a voucher. Existing evidence and active links are reused, and only missing results are created within the final-approval transaction.

### 2026-07-29 — 개인경비 역할 공동결재 및 발의 단계 정상화

- Decision: `requester_id`와 `requested_at`을 발의자·발의시각 SSOT로 사용하고 `SUBMIT` 단계는 상신 즉시 완료한다.
- Decision: `approver_id`가 있는 단계는 지정결재, `role_id`만 있는 단계는 역할 공동결재다. 역할 공동결재는 사전에 특정 사용자를 선정하지 않고 실제 승인·반려 사용자를 `acted_by`에만 기록한다.
- Decision: 역할 적격성은 승인된 활성 사용자, 활성 역할, 재직 중인 직원 조건을 모두 충족해야 한다. 조건부 원자 갱신으로 같은 역할 사용자의 동시 처리를 한 건으로 제한한다.
- Decision: 신청 대상 직원과 발의자는 서로 다른 Actor 개념이며 `created_by`, `updated_by`, `requester_id`, `acted_by`를 혼용하지 않는다.
- Decision: 활성 템플릿의 첫 단계는 항상 `SUBMIT`, 마지막 실제 승인단계는 `FINAL_APPROVAL`, 그 사이 단계는 `APPROVAL`로 정규화한다. `SUBMIT`은 템플릿에 남은 역할·지정결재자를 실행 배정에 사용하지 않고 저장 시 두 값을 NULL로 정리한다.
- Decision: 결재요청이 외부에서 직접 삭제되어 신청서의 현재 요청 포인터와 문서상태만 남은 경우, 다음 정상 상신 트랜잭션이 고아 포인터를 제거한 뒤 새 불변 요청과 단계 스냅샷을 생성한다. 기존 증빙이나 거래는 상신 단계에서 변경하지 않는다.
- Migration: `20260729_01_normalize_approval_step_assignment`에서 요청단계 지정결재자를 NULL 허용하고 양쪽 단계 테이블에 `step_type`을 추가했다. 기존 결재자·상태·처리자는 변경하지 않았다.

## 2026-07-25 — Navbar approval notifications are live workflow projections

- Decision: the existing navbar notification API, badge, dropdown, and `NotificationService` remain the sole notification UI path. A second approval notification table, counter, route, or poller is not introduced.
- Decision: an actionable approval notification is projected only from the active request's current active `pending` step assigned to the logged-in user, with every earlier active step approved and the current personal-expense document valid, not deleted, and pointing to that same request.
- Decision: stored notification unread state and approval processing state remain independent. Opening an actionable approval notification does not reduce the pending count; only an approval workflow transition removes it from the live projection.
- Decision: approval notification clicks deep-link to the actionable inbox tab with a request ID. The detail API independently verifies requester/participant access, and mutation still requires the logged-in user to own the current pending step.
- Decision: submission confirmation, rejection, and final-approval messages for the requester use the existing `system_notifications` read-state policy and are created inside the same approval transaction. These informational records do not participate in actionable approval counts.

## 2026-07-25 — Personal-expense trash and purge boundary

- Decision: `approval_personal_expenses.deleted_at` is the sole trash state. Soft delete never mutates or duplicates item deletion state, and owner-scoped restore only clears the header deletion audit after verifying retained active items.
- Decision: draft, rejected, and withdrawn documents are soft-deletable. Pending, in-progress, and approved documents are blocked by server-side current-request inspection; approved documents use a dedicated immutable accounting-protection message.
- Decision: the actual item-to-header FK is `ON DELETE CASCADE`, but permanent deletion explicitly removes items first and verifies the header deletion in one transaction. This makes item deletion order and rollback behavior visible instead of relying implicitly on Cascade.
- Decision: immutable rejected/withdrawn approval requests and steps are preserved after purge because `document_id` is an audit reference without an FK. Any pending, in-progress, or approved request history blocks purge. Any generated personal-expense evidence reference also blocks purge through both policy and the actual `ON DELETE RESTRICT` FK.

## 2026-07-25 — Role-permission assignment uses one differential batch

- Decision: the permission-assignment screen submits the complete checked permission-ID set once instead of issuing one assign/remove HTTP request per changed checkbox.
- Decision: `RolePermissionService` validates the active role and all selected active permissions, calculates additions and removals against `auth_role_permissions`, and applies at most one bulk delete and one bulk insert in a single transaction.

## 2026-08-14 사용자 개별 Permission 3모드 (기존 Override 설계를 대체)

- Decision: 역할권한은 `auth_role_permissions`에 유지하고 사용자별 적용방식은 `auth_user_permission_profiles`, 직접권한은 `auth_user_permissions`에 저장한다.
- Decision: `PermissionService`가 USER 승인·활성 및 역할·Permission 활성을 먼저 검사하고 `ROLE=Role Set`, `EXTEND=Role Set ∪ User Set`, `REPLACE=User Set`으로 판정한다.
- Decision: `super_admin` Actor는 자기 자신을 포함해 개인권한을 변경할 수 있으며 REPLACE 시 핵심 관리권한을 반드시 개인 Set에 유지한다. `admin` Actor는 자기 자신과 admin/super_admin 대상을 변경할 수 없고 일반 사용자만 관리한다.
- Decision: 과거 Permission별 ALLOW/DENY/INHERIT Override 계약은 폐기하고 사용자 단위 ROLE/EXTEND/REPLACE와 개인 Permission Set만 사용한다.
- Decision: 전체 Set 저장은 canonical hash `state_version`, 변경분 영구 Audit, 마지막 복구관리자 Guard를 포함한 한 트랜잭션이다.
- Decision: 퇴사 직원의 `auth_users.is_active` 자동 전환은 별도 계정 생명주기 과제로 유지한다.
- Compatibility: the existing `api.settings.rolepermission.assign` permission key remains so deployed role mappings keep working, while the canonical write URL is `/role-permission/save`. Legacy single assign/remove HTTP and Service contracts are removed.
- Security baseline: `super_admin` must retain the permission-assignment page, list, and save permissions, and every saved set must leave at least one active approved `super_admin` or `admin` account whose role retains all three recovery permissions.
- Audit baseline: successful role-permission changes record role, before/after permission-ID sets, and the ActorHelper actor in the existing rotating security log. A durable audit table remains a separately approved schema enhancement.
- Scope: 사용자별 3모드 Permission 정책을 권한부여의 개별 탭에 구현한다.
- Performance: the permission-assignment page loads the active Permission Master tree once, then requests only the selected role's mapping rows and merges them in the browser. The permission DataTable is capped at 100 rendered rows per page to avoid recreating all active permissions in the DOM on every role click.

## 2026-08-14 — 개별권한 API 접근 역할 정합성

- Decision: 기존 권한부여 화면, 역할권한 조회, 역할권한 저장을 모두 사용할 수 있는 `admin` 역할에는 개별권한 list/detail/save API Permission도 부여한다.
- Boundary: 이는 화면 접근 정책이며 대상 사용자 보호 정책과 구분한다. `admin` 대상 개인권한 변경은 계속 `super_admin`만 가능하고, `admin`의 자기 변경과 `admin` Actor의 `super_admin` 대상 변경은 차단한다.
- Migration: 미적용 `20260814_04_grant_admin_user_permission_override_permissions.up.sql`은 폐기하고 `20260814_05_finalize_user_permissions_three_modes.up.sql`에서 Legacy 0건 검증 후 3모드 구조와 최소 관리자 매핑을 확정한다.

## 2026-07-26 — 자금관리는 자금현황에서 계좌 거래로 진입한다

- 결정: `/ledger/funds`를 자금관리 canonical 첫 화면으로 사용하고, 계좌 마스터와 증빙원본 입출금(은행)의 최신 거래후잔액을 유형별 보유자금으로 구성한다.
- 결정: 계좌별거래내역은 계좌 미지정 시 전체 운영계좌의 원본 거래를 제공하고, `bank_account_id`가 지정되면 해당 계좌로 조회 범위를 제한한다. 자금현황의 운영계좌 지표는 전체 조회로, 개별 계좌명·현금·최종거래 지표는 해당 계좌 조회로 연결한다.
- 호환성: 실기능 없이 Placeholder였던 `/ledger/funds/account-balances`는 삭제하지 않고 `/ledger/funds`로 이동시킨다. 기존 `web.ledger.funds.account_balances` 권한과 `ledger.funds.account_balances` 페이지 레지스트리를 canonical 자금현황이 재사용하므로 신규 권한·DB Migration을 만들지 않는다.
- 메뉴: 사이드바의 계좌잔액현황 항목은 자금현황에 흡수하고, 기존 URL은 외부 링크와 사용자 즐겨찾기 호환 경로로 유지한다.
## 2026-07-26 예금출납장·현금출납장 독립 기능 제거

- 예금출납장과 현금출납장은 독립 페이지·권한·사용자 설정을 유지하지 않고 계좌별거래내역으로 역할을 통합한다.
- 기존 Migration은 변경하지 않으며, 적용된 레지스트리·권한·사용자 설정은 `20260726_01_remove_deposit_and_cash_ledger_registry`에서 확인된 고유 식별자만 FK 순서로 제거한다.
## 2026-07-26 자금일보 SSOT

- 자금일보는 `ledger_evidence_bank_transaction`, `system_bank_accounts`, `ledger_evidence_links`를 재사용하는 실시간 파생 보고서이며 별도 합계·잔액 테이블을 만들지 않는다.
- 현금은 계좌관리의 현금 자금수단과 동일 은행 증빙 원본 흐름을 사용한다.
- 내부이체 매칭 식별자가 없으므로 금액이나 적요로 내부이체를 추정하지 않고 미지원 상태를 명시한다.
## 2026-07-27 지급예정 및 실제 지급 배분 SSOT

- 지급의무·지급계획은 `ledger_payment_schedules`를 SSOT로 사용하고, 실제 은행 지급 배분은 기존 `(import_type, evidence_id)` Identity의 `ledger_evidence_links`에 `target_type=PAYMENT_SCHEDULE`, `link_type=PAYMENT`, 양수 `amount`로 저장한다.
- 지급상태·기지급액·잔여액·연체정보는 중복 저장하지 않고 활성 링크 합계와 기준일로 계산한다.
- 지급예정 원천 중복은 NOT NULL `source_line_key`를 포함한 `(source_type, source_id, source_line_key)` UNIQUE로 차단한다. 헤더 원천은 `HEADER`를 사용한다.
- 현재 은행 증빙 원본에는 통화 필드가 없고 계좌 통화만 존재하므로 지급예정은 KRW 은행 출금만 우선 지원한다. 현금 지급은 가짜 은행거래나 수동 완료로 처리하지 않으며 별도 현금 원본 SSOT가 마련될 때 확장한다.
- 범용 감사테이블이 지급 연결·해제의 전후값을 표현하지 못하므로 업무이력은 `ledger_payment_schedule_histories`에 보존한다.

### 2026-07-27 자금관리 내부이체·계좌유형·페이지 키 정규화

- 이 시점에 별도 내부이체 링크를 SSOT로 두었던 결정은 2026-07-30 기존 증빙·전표 SSOT 단일화 결정으로 대체됐다. 금액·시간·적요만으로 추정하지 않는 원칙은 유지한다.
- 계좌유형은 `BANK_ACCOUNT_TYPE` 코드만 사용한다. `CASH`, `LOAN`을 추가하고 승인된 11개 계좌만 고유 ID로 정규화했으며 계좌명 문자열 휴리스틱은 제거한다.
- 계좌별거래내역 canonical page key는 `ledger.funds.bank_transactions`, web route key는 `web.ledger.funds.bank_transactions`로 통일한다.
- 사용 경로가 없는 결제정보·계좌대사 Registry와 예금출납장·현금출납장 전용 권한은 신규 정리 Migration에서 제거한다.

## 2026-07-27 지급예정현황 운영 화면 계약

- 지급예정현황은 Placeholder를 사용하지 않고 `payment-schedule` 도메인의 Controller→Service→Model, View, ES module JS, CSS로 구성한다.
- 목록의 지급상태·기지급액·잔여액·연체일수는 저장 컬럼이 아니라 지급예정과 활성 `PAYMENT` 링크 합계로 조회 시 계산하며, 보류 상태가 다른 상태보다 우선한다.
- 생성·수정 시 원천유형은 활성 증빙 메타의 `source_table`로 실제 원천행 존재를 검증한다. 거래처·프로젝트·담당자·지급계좌는 ID를 저장하되 화면에는 서버가 제공한 표시명만 출력한다.
- 실제지급 선택지는 입출금(은행)의 활성 KRW 출금 원본 중 미배분 잔액이 있는 행만 제공한다. 배분·재배분·해제는 기존 `EvidenceLinkModel`과 `PaymentScheduleService`만 사용하며 DOM 또는 별도 완료값으로 상태를 우회하지 않는다.

## 2026-07-28 전표 기반 지급의무 자동 생성 계약

- 지급의무 생성 여부는 계정명·코드 접두사로 추정하지 않고 `ledger_accounts.creates_payment_obligation`과 CHECK로 제한된 `payment_obligation_type`만 사용한다.
- 최초 `REVIEWED → POSTED` 상태 전환과 지급의무 생성은 하나의 DB 트랜잭션이다. 원천 Identity는 `VOUCHER_LINE / voucher_id / voucher_line_id`, 금액은 해당 라인의 양수 `credit - debit`이다.
- 지급일과 지급계좌는 승인 시 임의 추정하지 않고 NULL로 생성한다. 화면은 각각 `지급일 미정`, `지급계좌 미정`으로 표시한다.
- 역분개 전표는 `ledger_vouchers.reversal_of`로 원전표를 식별한다. 지급연결이 없으면 원지급의무를 `CANCELLED`, 연결이 있으면 링크를 보존한 채 `REVIEW_REQUIRED`로 전환한다.
- 지급완료·일부지급·대기·연체는 저장 상태가 아니며 활성 PAYMENT 링크 합계로 계산한다. 취소·검토필요는 별도 생명주기 상태로만 관리한다.
- 화면은 공용 `createDataTable`, DB 기반 테이블 설정, 공용 휴지통을 재사용한다. 관리 컬럼은 컬럼 SSOT의 마지막에 정의한다.
## 2026-07-31 — Employment contract runtime without attachments

- Decision (superseded): 근로계약 저장구조는 이후 실제 운영 단순화 결정으로 대체되었다.
- Approval SSOT: `user_approval_requests` and `user_approval_request_steps`; document-specific behavior is connected through `ApprovalDocumentAdapterRegistry` and shared execution through `ApprovalWorkflowService`.
- 결재함의 5개 탭은 위 SSOT의 사용자 관점별 조회이며 별도 상태를 저장하지 않는다. 목록은 하나의 server-side DataTable을 재사용하고 단순 키워드 검색을 유지한다. Count는 표시용 원문·집계와 분리하며 검색 전 `recordsTotal`과 검색 후 `recordsFiltered`를 구분한다.
- 요청 단계는 상신 당시 템플릿 snapshot이지만 업무 본문은 각 도메인의 현재 원문을 조회한다. 이번 범위에서는 본문 snapshot JSON이나 테이블을 만들지 않으며, 감사 시점 본문 보존은 별도 정책 결정으로 남긴다.
- 문서유형 UI metadata는 Adapter Registry가 제공한다. Workflow와 최종승인 후속처리는 Adapter가 연결한 도메인 Service의 동일 트랜잭션에서 원자적으로 처리한다. Adapter가 없는 유형은 상세를 지원하지 않고 결재 처리를 차단한다.
- 운영 DB의 활성 0단계 템플릿 `CONSTRUCTION_EXECUTION_REPORT`, `EXPENSE_RESOLUTION`, `PURCHASE_REQUEST`는 현재 메뉴·업무 Runtime·상신 Route·Adapter 참조가 없는 과거 placeholder다. 결재함 정비에서는 임의 비활성화하거나 삭제하지 않으며, 요청이 유입되더라도 Registry의 `supported=false` 중립 상세와 `act` 차단으로 fail-closed한다. 운영 템플릿 정리는 별도 DB 변경 승인사항이다.
- State policy: 근로계약 저장 상태는 코드관리의 `DRAFT`, `APPROVAL_PENDING`, `APPROVED`, `TERMINATED`, `CANCELLED`만 사용한다. `EFFECTIVE`, `EXPIRED`는 승인 상태와 날짜로 파생한다. 결재엔진의 `pending`, `in_progress`, `rejected`, `withdrawn`은 결재요청 내부 상태이며 계약에 저장하지 않는다. 반려·회수된 계약은 `DRAFT`, 진행 중 계약은 `APPROVAL_PENDING`, 최종 승인 계약은 `APPROVED`로 projection한다.
- Deletion policy: soft delete is limited to editable or terminated contracts. Purge requires trash state and is blocked when any active approval request history exists, so immutable approval records never retain a dangling employment-contract reference.
- Attachment boundary: attachment upload, list, download, delete, restore, reference protection, and purge checks are deliberately absent until the approved common-file B design completes the user-operated DB creation process.

## 2026-08-18 — 근로계약 유효성·감사 무결성 기준

- 계약 문서상태는 `DRAFT`, `APPROVAL_PENDING`, `APPROVED`, `TERMINATED`, `CANCELLED`를 저장 상태로 사용한다. `EFFECTIVE`, `EXPIRED`는 승인 상태와 계약기간에서 파생하며 신규 저장·판정에 사용하지 않고, `ACTIVE`는 근로계약 상태가 아니다.
- 기준일 유효계약은 `EmploymentContractValidityService`가 단독 판정한다. 기간 경계는 양끝 포함, 종료일 `NULL`은 무기한, 종료 계약은 `terminated_at`의 날짜까지 과거 근거로 인정한다. 같은 개정 계보에서는 가장 높은 승인 revision을 선택한다.
- 중복 방지는 결재요청과 최종 승인 직전에 직원 계약행을 `FOR UPDATE`로 잠근 상태에서 수행하고, 서로 다른 계약 계보의 겹치는 기간을 차단한다.
- 기존 Audit 저장소는 각 업무 전용이라 근로계약 Audit에 재사용하지 않는다. 계약 전용 Audit은 별도 DB 승인 후 생성하며 승인 전 임시 저장이나 다른 Audit 테이블 전용을 금지한다.

## 2026-08-18 — 근로계약 목록·초기 로딩 성능 기준

- 목록 API는 DataTable 표시용 명시 projection과 검색 전/후 COUNT만 담당하며 상세 스냅샷·급여·결재 transient 값은 반환하지 않는다.
- 페이지 최초 렌더링은 직원·프로젝트·급여항목을 선조회하지 않는다. 직원과 프로젝트는 기존 공용 검색 Picker를 사용하고, 입력정책과 급여항목은 신규·상세 Modal 최초 개방 시 한 번만 조회해 페이지 생명주기 메모리에 보관한다.
- 근로계약 목록은 운영 사용자의 명시적 요청에 따라 공용 선택행 상·하 이동과 드래그 순서변경을 사용한다. 순서 SSOT는 기존 `institution_employment_contracts.sort_no`이며 별도 순서 컬럼이나 페이지 전용 reorder 구현을 만들지 않는다.
- 공용 DataTable의 기존 `redrawAfterInitialVisibility` 옵션을 사용해 초기 visibility 후 서버 재조회는 생략한다.
- 승인된 계약 전용 원장은 `institution_employment_contracts_audits`다. 헤더, 주간 일정과 휴게구간, 비고정 근무정책, 지급조건을 업무 필드 중심 JSON Snapshot으로 저장하고 Actor·사유·결재요청을 함께 보존한다. 암호화 식별정보, Actor/시간 감사열, UI 임시값과 파생값은 Snapshot에서 제외한다.
- 감사 원장의 `contract_id`는 합법적인 Draft purge와 대상 물리 ID 보존을 함께 만족시키기 위해 FK를 두지 않는다. `approval_request_id`는 불변 결재이력에 `RESTRICT`로 연결하고 `(contract_id, action_type, request_key)`를 멱등키로 사용한다.
- 상용근로소득 아이템의 `employment_contract_id`는 급여·소득 근거가 계약 purge로 소실되지 않도록 `ON DELETE RESTRICT`로 확정한다.

## 2026-07-31 — 근로계약 법률검토 저장구조 제거

- 근로계약의 법 준수는 별도 검토상태 저장이 아니라 계약 저장·결재요청 시의 근무시간, 휴게시간, 휴일, 연차 및 급여항목 정책 검증으로 처리한다.
- 실제 DB에서 제거된 법률 적용기준·정책버전·법률검토 컬럼과 급여구성 검토자 컬럼은 런타임, 화면, 테스트, 문서에서 함께 제거한다.
- 급여항목 유효성 기준일은 계약시작일이며, 계약 당사자 스냅샷은 직원과 회사 SSOT에서 서버가 생성한다. 클라이언트 입력은 신뢰하지 않는다.
## 2026-08-01 — 근로계약 요일별 소정근로 일정 SSOT 확정

- 2026-07-31의 요일별 일정 제거 결정은 주휴일과 기타 휴무일을 구분할 수 없고 숫자 헤더와 실제 근무요일이 불일치하는 문제가 확인되어 폐기한다.
- `NORMAL`·`NIGHT`는 `institution_employment_contracts_weekly_schedules`만 사용하고, `SELECTIVE`·`SHIFT`는 `institution_employment_contracts_work_schedule_policies`만 사용한다. 기준 반복일정과 추가 정책을 함께 가져야 하는 `FLEXIBLE`·`OTHER`는 두 저장소를 병행한다.
- 요일 상태는 `day_type`, 야간 익일 종료는 `end_day_offset`으로 명시한다. 주간·월평균·통상임금 기준시간은 일정에서 조회 시 파생하며 계약 헤더에 요약값을 중복 저장하지 않는다.
- 교대·탄력근무의 조별 시간대와 반복주기는 향후 근태 연동 기술부채다. 기존 계약을 주 근무일수만으로 월~금 일정으로 추정하지 않는다.

## 2026-07-31 — 근로계약 급여조건 적용기간 테이블 승인 철회

- 근로계약 급여항목 마스터는 범용 지급항목이 아닌 Employment 도메인 전용 `institution_employment_contracts_pay_components`를 SSOT로 사용한다.
- `institution_employment_contract_compensation_periods` 생성안과 관련 Migration은 폐기하며 신규 테이블, NULL 호환, fallback을 만들지 않는다.
- 근로계약은 입사 시 한 건을 작성하고 계약 당시 급여조건을 계약별 components로 저장한다. 급여 인상 이력은 이번 범위에서 구현하지 않고 급여대장 설계 단계에서 별도 재검토한다.
- 계약에는 기본 소정근로조건과 수습조건만 저장한다. 일별 근태, 변동수당, 비정기 상여·성과급, 보험료와 세금은 향후 근태·급여대장 책임으로 유지한다.
- 주 근무일수, 주·일 근무시간, 기본 출퇴근시간, 기본 휴게시간은 계약 본문에 직접 저장하고 Service가 범위와 최소 휴게시간을 검증한다.
- 본사·현장 차이는 근무장소 조건으로 관리하며 실제 일별 근무와 현장 배치는 향후 근태관리 또는 현장배치관리의 책임이다.
- 근태관리는 직원과 실제 근무일자를 기준으로 별도 SSOT를 설계하며 제거된 계약 일정 구조를 재사용하지 않는다.
- 근태 1차는 `attendance` 도메인으로 불변 출퇴근 원본, 일별 실적·구간·예외, 관리자 정정 감사, 월별 현재 마감과 불변 revision 이력을 분리한다. 휴가·전자결재 정정·공휴일·급여 전달은 후속 도메인으로 유지한다.
- 근태 권한은 역할 권한 SSOT를 사용하며 전체조회와 본인조회 범위를 서버에서 분리한다. 본인 출퇴근은 로그인 사용자에 연결된 직원 ID만 사용하고, 관리자 등록·정정·원본 무효화·마감·재오픈은 각각의 API 권한을 사용한다.

## 2026-08-01 — 근로계약 지급조건 Grid와 지급주기 SSOT

- 지급조건은 공용 HTML Grid를 유지하고 전표입력 분개라인과 동일한 editor commit, row lifecycle, validate/serialize 계약을 사용한다.
- 기본 Grid는 급여항목·계약금액과 마지막 명령열만 표시한다. 명령열 헤더의 `+ 추가`는 `grid.addRow()`, 본문의 `− 삭제`는 `grid.deleteRow()`를 호출한다. 법정 가산수당 마스터(`STATUTORY_PREMIUM`)에만 수당 적용 구분, 월 포함 근로시간, 가산율, 초과근로 정산방법, 산정 및 약정 근거를 조건부로 표시한다.
- 계산·세무·임금산입 정책과 항목 코드/명칭은 `institution_employment_contracts_pay_components`에서 계약 component로 스냅샷하며 사용자가 수정하지 않는다.
- 항목별 `payment_cycle` 입력은 헤더 `payment_day`·`payment_timing`과 중복되므로 제거한다. 현재 헤더가 표현하는 월 단위 지급계약은 Service가 내부값 `MONTHLY`로 저장한다.
- 근로계약 지급합계는 `institution_employment_contracts_components.amount` 합계만 사용하며 헤더에 별도 합계 컬럼을 저장하지 않는다. Service와 UI·결재 상세는 같은 합계 정책을 사용하고, 월급은 연 환산액(`합계 × 12`), 연봉은 월 환산액(`합계 ÷ 12`)만 파생한다. 일급·시급은 월 환산 가정이 없으므로 추가 환산액을 표시하지 않는다. 동일 지급항목 중복과 0원 이하 지급항목은 계약 의미가 없으므로 금지한다.
- 지급조건 산식 문자열은 저장하지 않고 `quantity`, `rate`, 선택적 `premium_rate`로 매번 생성한다. 계산값은 원 단위 반올림하며 확정 계약금액과 1원까지 차이를 허용한다.
- 급여항목 마스터가 `FIXED_AMOUNT`여도 `BASE_PAY` 기본급은 정액 표시 대상이 아니며 기준시간(`quantity`)과 기준 단가(`rate`)의 `기준시간 × 기준단가` 산식을 필수로 한다. `STATUTORY_PREMIUM`, 연차수당, 명시적 `FORMULA` 항목은 시간과 기준 단가를 사용하는 파생 산식 정책을 적용한다. `월 정액`은 식대·차량유지비·통신비와 기타 정액 지급 항목에만 사용한다.
- 기준 단가는 기본급 산식의 정형 계산요소 `rate`로 입력하며 산식 문자열을 직접 입력하지 않는다. 기본급과 모든 시간급 기반 수당은 이 단일 기준 단가를 공유하고, 수당 행의 `rate`는 저장 시 Service가 기본급 값으로 다시 정규화한다. 기본급 계약금액은 기준시간·단가, 수당 계약금액은 시간·단가·가산율의 산식 결과로 자동 계산한다.
- 분기·반기·연간·일회 지급의 지급월/지급조건은 현재 헤더로 표현할 수 없어 지원하지 않는다. 실제 업무 요구가 확정되면 별도 지급일정 SSOT와 DB 변경을 승인받아 설계한다.

## 2026-08-03 — 근로계약 결재금액 projection

- 근로계약 결재함의 공용 `총금액`은 `institution_employment_contracts_components` 중 `deleted_at IS NULL`인 행의 `SUM(amount)`로 계산한 월 지급합계다.
- 결재 목록은 결재단계 JOIN 이전에 계약별 지급조건을 집계하여 단계 수에 따른 중복 합산을 차단한다. 결재 상세의 `monthly_total_amount`와 공용 `total_amount`도 같은 합계를 사용한다.
- `annualized_amount`는 월 지급합계의 `× 12` 파생값이며 저장하지 않는다. 근로계약 헤더와 결재요청 헤더에 합계 컬럼이나 결재 전용 금액 SSOT를 추가하지 않는다.
- 기존 결재요청도 문서 ID로 현재 활성 지급조건 합계를 동적 조회하므로 운영 데이터 보정 없이 동일 금액을 표시한다.

## 2026-08-03 — 근로계약 분류체계 SSOT 분리

- 계약기간, 고용구분, 근로시간은 서로 독립된 법적·업무적 분류로 관리한다.
- 계약기간은 `contract_period_type`과 `EMPLOYMENT_CONTRACT_PERIOD_TYPE`, 고용구분은 `employment_category`와 `EMPLOYMENT_CATEGORY`, 근로시간은 `working_time_type`과 `EMPLOYMENT_WORKING_TIME_TYPE`을 각각 단일 SSOT로 사용한다.
- 계약종료일과 기간제 사유 정책은 다른 분류값에서 추론하지 않고 `contract_period_type = FIXED_TERM`만으로 판정한다. `INDEFINITE`는 종료일과 기간제 사유를 `NULL`로 강제한다.
- 폐기된 분류 컬럼과 코드그룹은 fallback으로 병행하지 않는다.
- `20260803_02_finalize_employment_category_ssot`는 운영 스키마의 고용구분 컬럼·인덱스·CHECK와 코드그룹을 `employment_category`·`EMPLOYMENT_CATEGORY`로 전환하며, 폐기된 분류체계로의 자동 롤백은 제공하지 않는다.
- 2026-08-05: 직무·배치 직접 등록은 종료된 과거 직무 이력과 `is_primary=0` 프로젝트 배치로 제한한다. 현재 직무·주 프로젝트·근무지 및 인사발령 생성 이력은 공식 인사발령만 변경한다. 상태·기준일 SSOT는 기존 assignment 테이블이며 `institution_job_assignments_audits`는 물리 도메인·작업·출처·사유·Actor·요청키와 JSON 전후 스냅샷만 보존한다. 전역 UNIQUE `request_key` 재전송은 기존 결과를 반환하고 감사 테이블로 상태를 계산하지 않는다.

## 2026-08-05 — 대외기관업무 인사·노무 테이블 페이지 도메인 통일

- 대외기관업무 페이지 소유 업무 테이블은 `institution_{복수형 page_domain}_*`을 사용한다. 근로계약, 인사발령, 직무·배치, 근태의 기준 접두사는 각각 `institution_employment_contracts_*`, `institution_personnel_actions_*`, `institution_job_assignments_*`, `institution_attendance_*`다.
- `user_employees`, `user_departments`, `user_positions`는 전사 공용 직원·조직 마스터이므로 유지한다. 페이지가 소유하는 거래·기간이력·스냅샷·감사 테이블은 `user_*`을 사용하지 않는다.
- `institution_employment_contracts_pay_components`는 근로계약 화면에서 선택하는 급여항목 기준정보 마스터다. 전사 급여대장이나 보상 지급항목 SSOT가 아니다.
- `institution_employment_contracts_components`는 특정 계약의 항목 코드·명칭·계산정책·세무/임금산입 정책과 금액을 보존하는 계약별 스냅샷이다. 급여항목 마스터와 책임을 합치지 않는다.
- 물리 전환은 `20260805_04_rename_institution_hr_page_tables`의 단일 `RENAME TABLE`로 수행하며 DROP/CREATE, 호환 View, 구형명 fallback, 이중 조회를 허용하지 않는다.
## 2026-08-06 — 일반 휴가 SSOT와 근태 연계 확정

- 장기 휴직과 일반 휴가는 기간·승인·잔액 책임이 달라 별도 SSOT로 유지한다.
- 잔액 정정 가능성과 감사 추적성을 위해 분 단위 불변 원장을 선택하고 별도 잔액 캐시는 두지 않는다.
- 반차·시간차는 고정 4시간이 아니라 유효 근로계약의 예정근로 구간과 실제 휴게구간으로 계산한다. 상세 휴게구간이 없거나 기존 합계와 다르면 추정하지 않고 신청을 차단한다.
- 승인·취소 완료와 usage·원장·근태 재계산은 같은 서비스 트랜잭션에서 처리해 결재 완료만 남는 부분 성공을 막는다.
## 2026-08-06 — 직원 자격·교육 SSOT 통합

- 직원 마스터의 certificate_name, certificate_file 단일 대표값을 폐기하고 직원별 다건 원장으로 이관한다.
- 직원관리와 자격·교육관리의 자격 데이터는 institution_qualifications_employee_records와 QualificationEducationService만 사용한다. 복사·동기화·캐시·fallback은 두지 않는다.
- 교육 이수는 과정 마스터와 직원별 이력으로 분리하고, 자격·교육 코드값은 system_codes를 SSOT로 사용한다.
- 첨부는 신규 업로드 구조를 만들지 않고 FileService의 certificate 정책을 재사용한다.
### Superseded: 2026-08-06~2026-08-10 법정기준 설계

아래 Revision·8테이블·`calculation_version` 결정은 2026-08-11의 최종 2테이블 결정으로 대체되었다. 과거 판단 근거 보존 목적이며 현재 Runtime·DB·UI 계약이 아니다.

- 2026-08-06: 법정기준 종류 템플릿은 신규 테이블이나 코드 하드코딩 대신 `system_codes.STATUTORY_STANDARD_TYPE.extra_data`를 메타데이터 SSOT로 사용한다. 실제 요율·금액은 기존 8개 법정기준 테이블만 소유한다. 사용자는 종류·법정값·시행기간·개정사유·공식 근거만 입력하고 기준코드·분류·값 구조·계산 목적·계산 버전·항목코드·순서·Actor는 시스템이 결정한다.
- 2026-08-06: 법정기준 화면은 SCALAR·BRACKET·MATRIX·COMPOSITE 편집기를 구조별로 분리한다. 실제 업무 계산 연계는 아직 0건이며 다음 연결 대상은 상용근로자 소득자료다.
- 2026-08-11: 법정기준관리는 한 행=한 종류·적용기간인 `system_statutory_standards`와 1:N `system_statutory_standard_sources`만 사용한다. 종류·입력필드는 `STATUTORY_STANDARD_TYPE.extra_data`, 끝수처리는 `STATUTORY_ROUNDING_METHOD`가 SSOT다. 화면은 공용 SearchForm·단일 DataTable·통합 상세모달로 구성하고 유효 여부는 적용기간으로만 판정한다.
- 2026-08-15: `system_statutory_standards`의 적용기간별 행 자체를 법정기준 개정 이력으로 확정한다. 별도 Revision 테이블·번호·parent·chain 및 Draft·Confirm·Correction·History·Audit 생명주기는 사용하지 않는다. 종료된 과거 기준은 `effective_from~effective_to`, 종료되지 않은 현행 기준은 `effective_from~NULL`로 표현하고, 새 기준 시행 시 기존 현행 기간을 종료한 뒤 새 적용시작일 행을 등록한다. UI의 `개정 등록`은 별도 Revision 객체 생성이 아니라 현재 입력을 복제해 기존 INSERT 경로로 신규 적용기간 행을 만드는 용어다. Resolver는 기준일을 포함하는 행이 0건이면 없음, 1건이면 정상, 2건 이상이면 기간 중복 오류로 판정한다.
- 2026-08-15: 개정 등록의 열린 적용기간 처리는 방식 B로 통일한다. 기존 `effective_to=NULL` 행을 먼저 종료해 저장하지 않으면 신규 적용기간 등록을 차단한다. 신규 저장이 기존 행을 암묵적으로 수정하지 않게 하여 적용기간 행의 사용자 입력 책임과 기존 데이터 안전성을 유지한다. 개정 등록 전환 시 새 적용 시작일·종료일은 빈 값으로 초기화하고 Type·값·비고·Source 텍스트만 복사한다. 현재 적용 중인 행의 삭제도 차단하며 선택삭제는 전건 사전검증 후 하나의 DB Transaction에서 처리한다.
- 2026-08-15: 법정기준 Source 저장은 ID 기반 diff로 확정한다. 기존 Source는 UPDATE하여 ID·생성 Actor·미교체 파일을 보존하고, 신규 Source만 INSERT, 제외된 Source만 DELETE한다. 전체 삭제/재삽입으로 근거 식별자와 감사값을 불필요하게 바꾸지 않는다.
- 2026-08-15: 법정기준 목록은 server-side DataTable 초기 숨김 컬럼의 중복 draw를 생략하고, HTTP 목록 응답에서 전체 `value_data`를 제외한다. 대표값은 서버가 생성한 `value_summary`로 표시하며 전체 값·Source는 사용자가 상세를 열 때 `detail` API에서만 조회한다. DB Schema와 SSOT는 변경하지 않는다.
- 2026-08-12: 코드관리에서 확정한 `LOCAL_INCOME_TAX_WITHHOLDING`과 `CORPORATE_LOCAL_INCOME_TAX`를 법정기준 Type SSOT로 유지하고 삭제된 `LOCAL_INCOME_TAX`는 복원·alias 처리하지 않는다. 전자는 국세 원천징수액 기준 단일 `rate`, 후자는 과세표준별 `bracket`이며 공용 Matrix Editor와 공용 rate 변환 계약을 사용한다.
- 2026-08-12: `CORPORATE_TAX`의 raw JSON 입력을 폐기하고 `CORPORATE_LOCAL_INCOME_TAX`와 동일한 공용 `BracketEditor`를 사용한다. 두 Type은 `tax_brackets` 배열에 `(tax_base_from, tax_base_to, tax_rate, progressive_deduction)`을 저장하며, 법정 구간 경계는 `from < 과세표준 <= to`, 첫 구간은 0원부터, 마지막 `to`는 `NULL`로 표현한다. 누진공제액은 법인세법 제55조의 고정액+초과분 계산을 시행기간별로 재현하기 위해 명시적으로 저장하고 `_schema` snapshot으로 과거 계약을 보호한다.
- 2026-08-12: 공용 HTML Grid의 입력 포커스 SSOT는 DOM `focusin`과 Grid `activeCell`의 동기화로 정한다. 키보드 `Tab`/`Shift+Tab`은 편집 가능 셀을 행 우선 순환하며 현재 값을 먼저 commit하고 실제 다음 입력기로 포커스를 이동한다. 화면별 Tab 구현은 두지 않는다.
- 2026-08-12: 법인세율 구간은 대량 표 입력 대상이 아니므로 `CORPORATE_TAX.tax_brackets`를 기본 펼침 카드로 표시하고 엑셀/표 붙여넣기를 제공하지 않는다. 행 추가·선택행 삭제·전체삭제는 공용 BracketEditor Toolbar를 그대로 사용하며 화면별 Toolbar를 만들지 않는다.
- 2026-08-12: 법인지방소득세율도 법인세율과 같은 소규모 구간 기준이므로 `CORPORATE_LOCAL_INCOME_TAX.tax_brackets`에 동일한 기본 펼침 카드와 붙여넣기 비활성 정책을 적용한다. 목록 대표값 역시 공용 bracket 요약인 구간 수·세율 범위를 사용한다.
- 2026-08-12: `CONSTRUCTION_RETIREMENT_MUTUAL_AID`는 공통 법정기준 Resolver가 기간·범용 scope만으로 결정할 기준이 아니므로 `STATUTORY_STANDARD_TYPE`과 법정기준관리 Runtime 계약에서 제외한다. 삭제된 코드는 복원·alias·fallback 처리하지 않는다. 향후 공사별 적용 여부와 부금비는 프로젝트·견적·계약, 실제 근로일수는 근태·출역, 실제 납부는 회계·자금 도메인이 각각 소유한다.

## 2026-08-18 — 근태 계산정책 SSOT

- `AttendanceCalculationPolicy`를 근태 시간 분류의 단일 정책 SSOT로 사용한다. `AttendanceService`는 DB I/O·트랜잭션·감사·마감을 조정한다.
- 출퇴근 사건의 `work_date`는 단순 날짜가 아니라 유효 근로계약 주간일정의 시작·종료·`end_day_offset` 구간으로 판정한다. 익일 종료 사건은 일정 시작일에 귀속하며 일반 다음날 일정과 합치지 않는다.
- `WORK`는 출퇴근 사이의 실제 활동 가능 구간, `BREAK`와 `OUTSIDE`는 그 안에서 제외하는 실제 사실이다. 실제근로는 WORK 합집합에서 WORK와 겹치는 BREAK/OUTSIDE 합집합만 한 번 차감한다.
- 예정 휴게는 계약 상세 휴게구간 SSOT를 사용한다. `break_minutes`는 상세구간 합계 검증용 projection이며 실제 휴게로 자동 확정하지 않는다.
- 정상근로는 실제근로와 휴가 반영 후 계약 예정근로의 작은 값이다. 계약 예정초과는 법정 연장근로와 다른 개념이다.
- 현재 법정기준관리에는 일·주 법정근로시간, 야간시간대, 법정·회사 공휴일 기준이 없다. 해당 기준이 생기기 전까지 8시간·40시간·22시~06시·일요일을 코드에 하드코딩하지 않고 법정 연장·야간·휴일 시간을 확정하지 않는다.
- `NORMAL`, `NIGHT`는 계약 주간일정과 익일 종료 범위까지 지원한다. `FLEXIBLE`, `SELECTIVE`, `SHIFT`, `OTHER`는 세부 반복·코어타임·정산기간 정책이 없어 `NEEDS_CONFIRMATION`으로 처리하며 NORMAL로 추정하지 않는다.
- 휴가는 승인된 `institution_leave_usages`만 참조한다. 휴가 원본은 근태에 복제하지 않으며 휴가와 출퇴근이 함께 존재하면 원본을 보존하고 예외 확인 대상으로 둔다.
- 배치 snapshot과 segment 검증은 `EmployeeAssignmentResolver`의 양끝 포함 날짜정책과 ACTIVE 판정을 재사용한다. 프로젝트와 공식 근무지는 해당 직원·근무일의 유효 배치만 허용한다.
- `AttendanceCalculationPolicy::VERSION`을 일별 `calculation_version`에 기록한다. 현재 컬럼은 정책 숫자는 보존하지만 법정기준 revision ID 전체는 보존하지 못하므로 완전한 과거 법정기준 재현에는 DB 보강 승인이 필요하다.
- 미마감 `daily_records`는 급여 확정 소비 대상이 아니다. 급여·원가·통계는 CLOSED 상태의 `monthly_closure_histories.current_revision`만 소비하며, 재오픈하면 새 revision으로 다시 마감될 때까지 확정 소비를 중단한다.
- 2026-08-18: `WORKING_TIME_STANDARD`와 `PUBLIC_HOLIDAY_CALENDAR`를 법정기준 Type으로 등록하되 실제 8·40시간, 야간시간대와 공휴일 날짜는 Migration에 seed하지 않는다. 공식 출처를 포함한 기준일 적용 row를 법정기준관리에서 등록한다.
- 근태 일별행은 실제 사용한 근로시간·공휴일 기준 row ID를 FK로 보존한다. 계약 예정초과는 `contract_excess_seconds`, 법정 일·주 기준 연장은 `calculated_overtime_seconds`로 분리한다.
- 2026-08-18: Daily 변경 시 해당 직원의 법정 주간 최대 7건을 일괄 잠그고 재평가한다. 일 기준 초과와 주 추가 초과는 발생 날짜에 귀속하며 연장·야간·휴일은 서로 다른 분류 차원으로 중첩을 허용한다.
- 2026-08-18: 귀속월 마지막 날이 포함된 법정 주간이 종료되기 전에는 월 마감을 유예한다. 주간 재평가가 CLOSED 월에 영향을 줄 경우 자동 변경하지 않고 재오픈→재계산→재마감을 사용한다.
- 2026-08-18: 주 종료 후에도 다음 달 근무일·명시적 일정예외의 Daily가 없거나 재계산 상태가 아니면 전월 마감을 차단한다. 재직·휴직 외 날짜와 유효계약의 비근무일은 Daily 미생성을 허용하고, 다음 달 `NEEDS_CONFIRMATION`·blocking exception·CLOSED는 마감을 차단한다. 마감 Service는 다음 달 데이터를 생성하지 않고 검증만 한다.
- 2026-08-18: 월 경계 readiness의 최종 SSOT 검증은 `AttendanceService::close()`가 시작한 DB Transaction 내에서 수행한다. 대상 closure·귀속월 Daily·다음 달 closure·월말 법정 주간 Daily만 `FOR UPDATE`로 제한 잠금하고, blocker·hash·closure·history·audit을 같은 Transaction으로 묶는다. 중간 실패 시 부분 closure/history/Daily 확정은 남지 않는다.
- 2026-08-18: 근태 Closure Fixture Baseline은 정상·휴게·익일·누락·중복·지각·조퇴·결근·계약초과·일·주·야간·공휴일·휴가·휴직·배치·마감·Revision을 정책·Phase1·월경계·법정 Revision rollback Fixture 묶음으로 검증한다. `FLEXIBLE`·`SELECTIVE`·`SHIFT`·`OTHER`는 각각 NORMAL fallback 없이 `NEEDS_CONFIRMATION`으로 고정한다.
- 2026-08-18: 근태가 참조하는 `WORKING_TIME_STANDARD`·`PUBLIC_HOLIDAY_CALENDAR` row는 불변 Revision이다. 참조 후 `value_data`·시작일·종류·Source를 직접 수정하지 않고 새 row로 정정한다.
- 법정 주 범위는 기준 revision의 `week_start_day`를 사용하고 월말에서 끊지 않는다. 한 날짜 변경은 같은 주 후속 일자의 주 누적 분류에 영향을 주므로 주 전체 재평가가 최종 책임이다.
- 공휴일은 PUBLIC_HOLIDAY와 SUBSTITUTE_PUBLIC_HOLIDAY만 법정 calendar에서 판정한다. 계약 비근무일과 회사 지정휴일은 별개이며 회사 지정 날짜휴일은 이번 SSOT에 포함하지 않는다.
- 같은 시각·동일 유형 clock은 저장 차단하고, 서로 다른 시각의 연속 CLOCK_IN/OUT은 원본을 보존한 채 DUPLICATE_CLOCK_IN/OUT 자동 예외와 NEEDS_CONFIRMATION으로 마감을 차단한다.
- 휴직 무출근일은 결근이 아니며 휴직 중 출근은 LEAVE_PERIOD_CONFLICT다. 출퇴근 누락, 계약·일정 충돌, 휴직 충돌, 출퇴근 중복 및 NEEDS_CONFIRMATION은 월 마감 blocker다. 지각·조퇴·결근 자체는 확정 가능한 사실이므로 blocker가 아니다.
- FLEXIBLE, SELECTIVE, SHIFT, OTHER는 필요한 정산기간·코어타임·교대주기 revision이 없어 NORMAL로 추정하지 않고 NEEDS_CONFIRMATION을 유지한다.
- 2026-08-12: 공용 HTML Grid의 금액 컬럼은 blur 이후가 아니라 입력 중 즉시 천 단위 구분을 표시한다. 이 동작은 공용 Number Editor가 `amount`·`currency` 메타데이터를 기준으로 수행하고 caret을 보존하며, 저장 직전에는 기존 숫자 정규화 계약으로 구분기호를 제거한다.
- 2026-08-11: 법정기준 본체의 적용일은 `effective_from`/`effective_to`, 공식 근거자료의 공표일은 `system_statutory_standard_sources.published_at`으로 분리한다. 본체에는 공표일 또는 대표·최신 공표일 파생 SSOT를 두지 않는다.
- 2026-08-10: 법정기준 종류 템플릿의 운영 기준은 활성 15개다. `CONSTRUCTION_INDUSTRIAL_ACCIDENT`는 별도 SSOT에서 제외하고 `INDUSTRIAL_ACCIDENT`의 `industry_code`, `is_construction`, `business_size_code`, `workplace_scope_code` 복합 scope로 통합한다. 2026-08-06 초기 Migration의 16개 정의는 변경 금지된 과거 이력으로만 남으며 런타임 SSOT가 아니다.
- 2026-08-11: 위 산재보험 복합 scope 결정을 실제 Consumer 기준으로 재검토해 `INDUSTRIAL_ACCIDENT`에는 필수 `industry_code`만 유지한다. Resolver는 `scope_data`의 키를 일반 비교할 뿐 `business_size_code`, `is_construction`, `workplace_scope_code`를 별도로 사용하지 않고 운영 데이터도 없었다. 업종이 건설업 여부를 이미 표현하므로 Boolean 중복 SSOT를 만들지 않는다. 다른 법정기준 유형의 scope 계약은 변경하지 않는다.
- 2026-08-11: 구조화 법정기준 값은 textarea JSON이 아니라 Dynamic Structured Editor로 입력한다. `matrix`는 공용 HTML Grid 기반 `MatrixEditor`, 컬럼 SSOT는 코드관리 `extra_data.fields[].columns`, 계산값 SSOT는 `value_data` 배열이다. 2013 간이세액표의 표 축은 월급여액 구간과 공제대상 가족수이며 별도 자녀수 열은 공식 표의 독립 축이 아니므로 만들지 않는다. 원본 문서는 Source가 소유하고 구형 `matrix_cells` 저장소는 복원하지 않는다.
- 2026-08-11: 위 간이세액표 4열 정규화 결정은 공식 원본 한 행을 가족수별 11행으로 증폭하므로 폐기한다. `tax_table`은 월급여 구간 2열과 가족수 1~11명 세액 11열을 합친 원본형 13열을 유지한다. 표 상한 이후 산식은 실행 문자열이 아니라 가족수별 표 기준세액 참조방식과 숫자 파라미터를 가진 `excess_rules`로 저장한다. 계산 Consumer가 생기기 전 별도 계산 API는 만들지 않는다.
- 2026-08-11: 위 고정 13열 결정도 현재 자료의 표현일 뿐 장기 SSOT로는 폐기한다. `value_data.table`이 `dependent_counts`와 각 급여행의 `tax_by_dependents` Map을 함께 소유하고, `excess_rules`와 `adjustment_rules`도 가변 행 배열로 소유한다. 공용 MatrixEditor는 `dynamic_dimension`을 읽어 현재 데이터에 필요한 열만 생성한다. Global Template 변경으로 과거 행을 재해석하지 않으며 80/100/120%는 근로자 선택 정책이므로 법정표 본체에 중복 저장하지 않는다. 문자열 `eval`과 범용 수식엔진은 사용하지 않는다.
- 2026-08-11: 근로소득 간이세액표 관리자는 내부 self-describing 계약을 직접 설계하지 않는다. 화면은 공식 간이세액표, 표 상한 초과 계산기준, 별도 세액 조정기준의 세 접이식 영역만 제공하고 가족수 원시 배열·reference type·rule type은 기본 화면에서 숨긴다. 공용 MatrixEditor가 공식표 붙여넣기와 시스템 기본값 변환을 담당하며 내부 `table/dependent_counts/tax_by_dependents/excess_rules/adjustment_rules/_schema` 계약은 유지한다.
- 2026-08-11: Matrix 입력량에 따라 UX를 분리한다. 수백 행인 공식 간이세액표만 Excel·TSV·Markdown 붙여넣기를 제공하고, 초과 계산기준과 별도 세액 조정기준은 행 추가 후 첫 편집 셀을 즉시 여는 직접 입력만 제공한다. 두 규칙은 Template Metadata가 선택으로 선언하므로 0건 저장을 허용하며 내부 self-describing 저장 계약은 변경하지 않는다.
- 2026-08-11: 간이세액표 Matrix의 Grid SSOT는 프로젝트 공용 `createHtmlGrid`와 `.html-grid-host`로 확정한다. Matrix 전용 렌더러·스크롤·Formatter를 만들지 않으며 공용 Grid가 sticky header와 단일 가로·세로 scroll container를 담당한다. 대량 붙여넣기는 75행 chunk로 Parse·Normalize 진행률을 표시하고 전체 검증 성공 후 기존 Grid 전체를 교체한다. 실패 시 원문과 기존 Grid를 보존하고 성공 시 원문을 폐기한다.
- 2026-08-11: 법정기준 `rate`의 저장 SSOT는 ratio, 입력·표시 단위는 percent로 확정한다. 일반 필드와 Matrix 열 모두 공용 `rateToPercent`/`percentToRate`를 사용하며 Metadata의 `min/max`는 저장 ratio에 적용한다. 초과계산기준의 마지막 `salary_to`는 nullable 무제한 구간으로 보존한다.
- 2026-08-11: `DAILY_WORKER_INCOME_TAX`의 근로자 유형은 유형 코드 자체와 중복되고, 일 공제액·원천징수세율·근로소득 세액공제율을 업종·사업장 규모·건설업 여부·사업장 범위로 분기하는 런타임 Consumer도 없다. 이 유형의 `scope_fields`는 빈 배열로 확정하고 `scope_data={}`만 허용한다. 등록 운영 데이터가 0건이므로 변환·fallback은 두지 않는다.
- 2026-08-10: 법정기준 scope는 `scope_type_code`의 주 차원과 기존 상세 컬럼을 함께 사용한다. Resolver는 일치 조건 수가 많은 범위, 낮은 `priority_no` 순으로 단 하나를 선택하고 최상위 동률은 같은 Revision이어도 충돌로 거부한다. 명시적인 `ALL`만 기본 범위이며 임의 숫자 fallback은 금지한다.
- 2026-08-12: `REPORT_ROUNDING_RULE + target_standard_type_code + 적용기간` 후속정책 구조는 폐기한다. 절사·반올림·올림·소액부징수는 원래 계산대상 법정기준의 `value_data.calculation_policy`에 기준값과 함께 저장하고 동일 적용기간과 동일 Source를 사용한다. Type Metadata의 선택적 `calculation_policy.fields`와 저장 당시 `_schema.calculation_policy.fields`가 계약을 보존하며 Resolver는 대상 법정기준을 한 번만 조회한다. 현재 공식 근거가 확인된 일용근로소득만 끝수처리 방식·숫자형 단위·소액부징수 기준금액을 선언하고 다른 Type에는 추정 정책을 추가하지 않는다.
- 2026-08-12: 위 일용근로소득 3필드 정책은 공식 계산계약을 충분히 표현하지 못하므로 대체한다. 계산정책 공용계약은 `method`, `discard_below_unit`, `stage`, `base_value_code`, `aggregation_unit`, `threshold`, `threshold_comparison`, `workplace_scope`, `application_order` 중 Type에 필요한 필드만 선언한다. 국민연금·일용근로소득·근로소득 간이세액표·장기요양보험·지방소득세 특별징수에만 공식 확인 범위를 저장하며 건강보험·고용보험·산재보험·사업소득 원천징수·법인세·법인지방소득세에는 추정 정책을 넣지 않는다. 실제 급여·신고·회계 조정은 Consumer 책임이며 계산 Consumer는 선제 구현하지 않는다.
- 2026-08-12: 법정기준 개정 등록은 기존 행이나 적용종료일을 자동 수정하지 않고 현재 수정모달 입력을 신규등록 상태로 전환한 뒤 기존 저장 API의 INSERT 경로를 사용한다. Source의 텍스트 근거는 새 Source 행으로 복제하되 ID·감사값·기존 파일참조는 복제하지 않으며, `_schema`는 과거 snapshot을 복사하지 않고 신규 저장 시점 Template로 다시 생성한다. 기간중복은 기존 Service 검증을 우회하지 않는다.
- 2026-08-12: 법정기준관리의 별도 적용조건 계약을 폐기한다. `system_statutory_standards.scope_data`, Type Template의 `scope_fields`, 화면 적용조건 영역·목록 열, 저장 검증과 Resolver context를 함께 제거하고 종류·기준일만으로 기준행 하나를 Resolve한다. 산재보험 업종은 조건이 아니라 `value_data.industry_rates[{industry_name,employer_rate}]` 기준값 차원이며 한 적용기간의 기준행 하나가 복수 사업종류별 요율을 소유한다. 공식 사업종류 코드 SSOT가 없으므로 현재 `industry_name`을 필수·중복금지 텍스트로 사용하고 회사 업종과 자동 연결하지 않는다. 2013년 건설업 3.7% 행은 ID와 Source를 유지한 채 이 구조와 `_schema`로 변환한다. 위 2026-08-10~11 산재보험 scope 결정은 역사기록일 뿐 현재 Runtime 계약이 아니다.
- 2026-08-12: 법정기준 데이터의 적용기간은 회사 설립일이 아니라 법정 효력기간을 저장한다. 이에 따라 국민연금 2013년 상·하한 행은 공식 2012.7.~2013.6. 기간으로, 부가가치세 일반세율 10%는 제정법 시행일인 1977-07-01부터로 정정한다. 운영 정비는 직접 SQL이 아니라 `SYSTEM:STATUTORY_OFFICIAL_DATA_AUDIT` Actor를 주입한 `StatutoryStandardService`를 사용한다. 현재 2테이블에는 확정 Source 불변성과 correction chain을 나타내는 물리·JSON 계약이 없으므로 존재하지 않는 보정이력을 가장하지 않고 기존 Source ID·생성감사값을 보존하는 공식 수정 경로만 사용한다.
- 2026-08-12: 공식자료 전수 감사의 완료 기준선은 활성 13종·Standard 91건·Source 92건이다. 근로소득 간이세액표는 실제 개정일 5구간과 기간별 전체 647행을, 고용보험은 실업급여 노사 부담률과 사업규모 4단계 추가 부담률을, 산재보험은 2013~2026년 건설업 최종 적용요율을 저장한다. 2018년 이후 산재보험 최종요율은 사업종류별 요율과 출퇴근재해 요율의 합계이며 개별 사업장 가감은 포함하지 않는다. 공식 법령 Source인데 공포번호·공표일이 누락된 건은 0건이고, 해당 메타데이터가 없는 공단·기관 안내페이지의 NULL은 미완료가 아니라 자료 성격으로 분류한다.
- 2026-08-10: 법정기준 `calculation_version`은 서버가 `STATUTORY-{standard_code}-R{revision_no}` 형식으로 생성하는 SSOT다. JS와 요청 payload는 별도 기본값을 생성하거나 소유하지 않는다.
- 2026-08-10: 과거 `20260806_08_complete_statutory_standard_templates`는 수정하지 않고 `20260810_01_finalize_statutory_standard_type_contracts`를 후속 적용한다. 신규 Migration은 code 기준 UPDATE와 soft delete만 수행하고 실제 운영 데이터가 폐기 유형을 사용하면 실패한다. 최종 활성 15개 집합과 핵심 입력계약이 아니면 Migration 자체가 실패하며, down은 이미 폐기한 중복 SSOT를 되살리지 않는 forward-only no-op으로 확정한다.
## 2026-08-12 코드관리 soft-delete 컬럼 제거

- 코드관리 휴지통 기능 폐기에 맞춰 `system_codes.deleted_at`, `system_codes.deleted_by`를 제거한다.
- 코드 삭제는 `CodeService`의 참조 무결성 검증 후 단건 영구삭제만 사용한다.
- 코드의 사용 여부는 `is_active`가 담당하며, 모든 `system_codes` Consumer는 삭제 컬럼 조건 없이 활성 상태만 판정한다.
- 기존 Migration은 수정하지 않고 `20260812_02_remove_system_codes_soft_delete` 신규 Migration으로 적용한다.
# 2026-08-14 Organization role protection and inactive-role policy

- `auth_roles` remains the single role-master SSOT and account assignment remains single-role.
- `super_admin` and `admin` cannot be deleted, renamed by key, or deactivated.
- A general role is hard-deleted only after fixed external-reference guards pass; its permission links and master row are removed in one transaction.
- Inactive roles do not grant permissions and are excluded from new selections while retained for an existing selected value.

## 2026-08-15 증빙원본 초기 로딩 임계경로

- 최초 사용 가능 시점은 `window.load`가 아니라 현재 Type의 첫 server-side DataTable 렌더 완료를 기준으로 한다.
- 서버가 렌더한 Evidence Type Policy로 Selector를 즉시 구성하고, 전체 Type Counter는 첫 테이블 요청과 병렬 처리한다. 표시용 코드옵션은 첫 테이블 렌더 이후 조회한다.
- 휴지통 전체목록 및 휴지통 CSS/JS는 최초 열기까지 지연한다. 증빙원본은 `data-list-light` Asset Profile을 사용해 PDF/ZIP/인쇄와 주소검색 자산을 초기 요청에서 제외한다.
- Evidence 목록 GET API는 인증·권한 Middleware 이후 `Session::write()`로 Session Lock을 해제하여 Counter와 DataTable 요청이 서버에서 직렬 대기하지 않도록 한다.
- Evidence SSOT, URL Type Navigation, server-side DataTable, Counter 의미, Trash/Excel 정책과 API 계약은 변경하지 않는다.
- Type별 실제 URL은 `EvidenceController`가 렌더링하므로 View가 선택한 `data-list-light` Profile을 해당 Controller가 Layout까지 전달한다. 증빙 DataTable은 `pageLoading=false`로 전역 Page Overlay를 다시 열지 않고 테이블 내부 Loading만 사용한다.
- 전체 새로고침에서는 이전 행을 다시 보여주지 않도록 `beforeunload`부터 새 문서의 첫 `init.dt`까지 동일한 Table Boot Shell을 유지한다. 첫 테이블은 모달 전용 Field/Format을 조회하지 않으며, 물리 컬럼과 Table Setting은 공용 DataTable Metadata Promise Cache의 단일 요청을 공유한다.
- 2026-08-15: 분개규칙은 전표 자동추천 Source 중 하나로 유지하고 전용 Excel 양식·다운로드·업로드를 제거한다. 추천 조회는 Read-only이며 회계원본은 전표다. 거래처 기본계정은 사용자 명시정책으로 유지하고 자동학습이 변경하지 않는다. 실제 POSTED 전표에서 수정 없이 사용된 Rule의 기존 `usage_count`·`last_used_at`만 갱신한다. manual/system 구분과 시스템 Rule 자동생성·수정은 최소 Schema 변경 승인 전까지 수행하지 않는다.
## 2026-08-15 Transaction input integrity baseline

- Transaction and voucher remain independently linked to Evidence; no direct transaction-voucher relationship is restored.
- Transaction header amounts are server-calculated from transaction items plus signed settlements. A conflicting client snapshot is rejected.
- Existing `updated_at` is the optimistic-lock token for transaction edits; no version column is introduced.
- User CRUD may edit and soft-delete draft transactions only. Completed, closed, and cancelled transactions require an explicit source-domain correction workflow.
- Transaction item TAX_TYPE is retired because it has no persisted runtime consumer. Evidence tax semantics and VAT settlements remain unchanged.
- Transaction Excel management is retired. Manual list ordering uses the retained `ledger_transactions.sort_no` through the transaction-input Reorder API.
- Settlement FK and active-transaction Evidence uniqueness remain separate DB-approval items; no migration is created by this change.
# 2026-08-15: 거래입력 Excel Legacy 제거 및 목록 순서관리 유지

- Decision: 거래입력의 Header·Item·Settlement Excel Route, Controller action, Service는 제거한다. 거래 목록은 체크박스 다음 드래그핸들과 선택행 상·하 이동을 제공하며 `ledger_transactions.sort_no`를 수동 순서 SSOT로 사용한다.
- Why: Header·Item·Settlement 별도 Excel 입력은 원자적 검증·금액계산·Evidence Link 계약을 우회하지만, 사용자가 거래 목록의 업무상 검토 순서를 직접 관리하는 기능은 별도 책임이다.
- Constraint: 드래그 또는 선택행 이동이 저장되면 보기 정렬을 `sort_no ASC`로 전환한다. 그 외 컬럼 정렬과 방향은 사용자 VIEW 설정으로 저장하고 server-side whitelist 정렬을 사용한다.

# 2026-08-15: 거래입력 DB 정합성 최종 기준

- `ledger_transaction_settlements.transaction_id`는 `ledger_transactions.id`와 동일한 `varchar(36) utf8mb4_general_ci NOT NULL` 계약을 사용하며, `fk_transaction_settlement_transaction`의 `ON DELETE/UPDATE CASCADE`로 헤더 생명주기를 따른다. 승인된 고아 정산 1건만 사전 스냅샷과 대조해 삭제했으며 별도 이력 테이블은 만들지 않는다.
- 거래 증빙 연결의 Application Guard는 유지하되 최종 동시성 Guard는 `ledger_evidence_links`의 조건부 생성 컬럼과 `uk_evl_active_transaction_evidence`다. 활성 `target_type=TRANSACTION` 행만 `(evidence_type, evidence_id)`당 하나이며, soft-delete 후 재연결과 VOUCHER 다중 연결은 허용한다.
- 거래입력 Excel·거래-전표 직접연결 Runtime의 Legacy Permission은 제거한다. `api.ledger.transaction.reorder`는 목록 순서관리 요구에 따라 Route·Permission을 복원한다. `api.import.recommend_transactions`는 Runtime 미사용을 재확인했지만 이번 삭제 승인 대상이 아니므로 유지한다.
- `transaction_item_id` 물리 컬럼 제거, 거래 Master FK 추가, 거래-전표 직접 링크 복원은 이번 기준에 포함하지 않는다.
- 2026-08-15: 전표입력은 Excel 양식·목록 다운로드·업로드·대량 Header/Line/Ref 생성을 공식 입력경로로 사용하지 않는다. 전표 전용 Excel UI, JS, Route, Controller action, Service를 제거하고 공용 Excel Manager와 다른 도메인의 Excel 기능은 유지한다. 전표 작성은 수동 입력, Evidence 기반 추천 적용, 취소전표 생성과 현재 공식 `VoucherService` 저장경로로 단일화한다. 향후 조회용 Export가 필요하면 전표 입력과 분리된 보고서 기능으로 검토한다.
- 2026-08-15: 추천 적용 당시 계정·차대·금액·Rule·라인 참조 Snapshot은 브라우저 Runtime에서만 유지한다. 최종 분개결과가 Snapshot과 다르면 `is_user_modified=1`, 다시 완전히 같아지면 `0`이며 적요 단독 변경은 제외한다. 서버 DB Snapshot이 없으므로 이 플래그는 최종 회계검증을 대체하지 않는다. 취소전표의 Rule ID는 감사 trace로 유지하되 usage/향후 learning 집계에서 제외한다. Rule usage는 POSTED commit 후 통계 후처리로 유지하고 실패는 voucher/rule context와 함께 기존 로그에 남긴다. learning/recent/client Projection writer와 DB 멱등 계약은 후속 승인사항이다.
- 2026-08-15: 전표 Feedback Loop는 최초 POSTED 확정라인 Event를 학습 SSOT로 삼고 Recent/Client Pattern을 Event aggregate Projection으로 둔다. Event는 회계 POSTED Transaction에 포함하며 Projection과 Rule usage는 commit 후 후처리한다. 신규 Event는 `(voucher_line_id,event_type)` UNIQUE와 line FK RESTRICT로 멱등·보존을 보장한다. 현재 전표와 연결할 수 없는 기존 Event 5건 및 기존 Projection 2/3건은 삭제·추측 Backfill하지 않고 Legacy baseline으로 보존한다. 취소전표는 Event/Projection/Rule usage에서 모두 제외하며, 추천 GET DML과 SYSTEM Rule 자동생성·confidence 자동조정은 계속 금지한다.
# 전표검토·전기 권한 및 상태전이 기준 (2026-08-15)

- 전표검토는 전자결재와 분리하며 `ledger_vouchers.status`를 회계 Workflow SSOT로 유지한다.
- 메뉴명은 `전표검토·전기`로 통일하고 `REVIEWED=검토완료`, `POSTED=전기완료`로 표시한다.
- 상태전이는 잠금 조회(`FOR UPDATE`) 후 현재 상태를 재검증하여 한 요청만 성공시킨다.
- 작성/검토/검토취소/전기/취소전표 작성 권한은 각각 `save`, `api.ledger.voucher.review`, `api.ledger.voucher.review_cancel`, `api.ledger.voucher.post`, `api.ledger.voucher.reverse`로 구분한다.
- 검토 화면은 직접 수정하지 않으며 오류는 반려 후 전표입력에서 수정한다. 전기완료 정정은 원전표를 유지한 채 취소전표를 생성한다.
- `CLOSED`는 향후 회계기간 마감 정책에서 결정하며 이번 Runtime에는 신규 진입 기능을 만들지 않는다.
- 검토 목록은 `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`만 대상으로 하는 DB server-side 목록으로 확정한다. `recordsTotal`은 Scope 단독 Count, `recordsFiltered`는 검색조건이 있을 때만 필요한 Join을 포함하며, 정렬 컬럼은 서버 allowlist로 제한한다.
- 상세 화면은 읽기 전용이며 저장된 Line Ref, Actor 표시명, 추천 Snapshot, 원전표·취소전표 관계만 보여준다. 별도 상태이력이 없는 검토·전기 처리자나 처리시각은 `updated_by/updated_at`으로 추정하지 않는다.

## 2026-08-15 전표 취소와 Evidence 귀속 정책

- Evidence의 직접 연결은 원전표에 유지하며 취소전표에 복제하지 않는다. 동일 Evidence의 배타적 연결 계약과 원전표 감사 추적을 함께 보존하기 위한 결정이다.
- 취소전표는 `reversal_of`로 원전표를 참조하고, 상세 조회에서만 원전표의 `ledger_evidence_links`를 따라 `원전표 증빙 (읽기 전용)`으로 제공한다. 취소전표의 직접 연결 증빙과 API 필드를 분리한다.
- Line Ref는 기존대로 역분개 Line에 복사한다. 직접 Evidence가 0건인 취소전표도 정상 검토·전기할 수 있으며, 취소전표의 Learning Event, Recent/Client Projection, Rule usage는 계속 0건을 유지한다.
# 2026-08-18 인사발령 변경명령 정책 SSOT

- `PERSONNEL_ACTION_TYPE`은 발령문서가 어떤 인사사건인지 나타내는 업무 분류이며 활성값과 표시명은 `system_codes`가 소유한다.
- `change_type_code`는 직원 Master·기간이력을 실제 변경하는 고정 시스템 실행명령이므로 코드관리에서 임의 추가·삭제·비활성화할 수 없다.
- 11개 변경명령의 저장값·한글 표시명·입력 metadata와 13개 발령유형별 `allowed`·`required_all`·`required_any` 정책은 `PersonnelActionChangePolicy`가 Runtime SSOT로 소유한다.
- 입사는 재직상태 `ACTIVE`와 입사일, 휴직은 휴직이력과 재직상태 `ON_LEAVE`, 복직은 휴직종료와 재직상태 `ACTIVE`, 퇴직은 퇴직일과 재직상태 `RETIRED`를 모두 요구한다.
- 전보는 부서·직위·직무·근무지 중 하나 이상, 기타는 11개 허용명령 중 하나 이상을 요구한다.
- `PersonnelActionService`는 입력 정규화·정책 검증·원자 저장을, `PersonnelActionApplyService`는 저장 명령 재검증과 실제 적용을 담당한다.
- DB CHECK는 11개 값과 명령별 물리컬럼 무결성을 최종 방어하며 Runtime 업무정책을 대신하지 않는다.
- UI는 서버의 Policy metadata를 소비하여 허용명령만 표시하고 필수명령 누락을 예방하지만, 최종 업무 무결성은 서버 Policy 검증이 방어한다.
- 현재 저장값과 물리컬럼을 유지하므로 DB·Migration은 변경하지 않는다.
# 2026-08-18 근로계약 목록 우선 로딩 경계

- 근로계약 목록의 초기 Critical Path는 공용 DataTable, SearchForm, 테이블 설정과 목록 표시에 필요한 코드그룹으로 제한한다.
- 2026-08-18 Closure: 근로계약 Modal 구현은 `modal-runtime.js`로 물리 분리한다. `index.js`는 목록 bootstrap과 Promise가 캐시되는 단일 `import('./modal-runtime.js')` 진입점만 보유하며, HTML Grid·AdminPicker·지급조건 계산·삭제 진행 UI와 Modal options는 최초 신규/상세 열기 시 로드한다.
- 공용 HTML Grid, AdminPicker 전체 런타임, 지급조건 계산, 시간값 편집기와 삭제 진행 패널은 신규·상세 모달을 처음 여는 시점에 동적 import하고 페이지 생명주기 동안 동일 Promise를 재사용한다.
- 모달 options와 모달 코드 초기화도 같은 최초 개방 경계에서 한 번만 수행하며, 직원·프로젝트 전체 option을 서버 렌더링하거나 별도 브라우저 저장소에 복제하지 않는다.
- 근로계약 API는 인증 완료 후 공용 `Session::write()`로 세션 쓰기 잠금을 조기에 해제하여 독립적인 목록·메타데이터·설정 요청이 세션 파일 잠금 때문에 직렬화되지 않게 한다.

## 2026-08-18 직무·배치관리 목록 우선 로딩 경계

- 최초 HTML은 권한과 화면 구조만 렌더링하며 직원·프로젝트·직무·코드 options를 포함하지 않는다. 목록 상태 한글명은 목록 Projection이 `system_codes` 표시명을 함께 반환한다.
- 직원과 프로젝트는 ERP 공용 AJAX search-picker를 사용하고 각각 `user_employees.id`, `system_projects.id`를 저장값으로 유지한다. 직무·부서·직위와 상태코드는 최초 Modal 또는 선택형 검색 사용 시 `job-assignment/options`를 한 번만 호출해 페이지 생명주기 Promise로 공유한다.
- 직무·배치 목록은 직원 1행 Projection, 기준일 Resolver, 분리된 `recordsTotal`·`recordsFiltered`를 유지한다. 삭제·휴지통·영구삭제·순서변경·Excel은 기간이력의 종료·정정 및 Audit 정책과 맞지 않아 제공하지 않는다.
- 과거 직무 이력, 비주요 프로젝트 배치, 인사발령 이동은 제목 영역에 중복 배치하지 않고 공용 DataTable Action 영역에만 둔다. 읽기 API는 인증·권한 처리 후 `Session::write()`로 세션 잠금을 조기에 해제한다.
- 직무·배치 기본 직원범위는 퇴직자를 포함한 전체 `user_employees`다. `current_only`와 `include_ended`는 사용자 의도가 있을 때만 적용하고 기본값은 각각 OFF와 ON이다. `recordsTotal`에는 검색조건을 적용하지 않으며 `current_only=1`은 지정 기준일의 `ACTIVE`·`ON_LEAVE`만 `recordsFiltered`에서 제한한다.
## 2026-08-18 직원 Current Master 수정경로와 신규 인사 Baseline

- 결정: 기존 직원의 부서, 직위·직책, 직무, 재직상태, 입·퇴사일은 직원관리에서 직접 변경하지 않고 인사발령관리만 변경 주체로 둔다.
- 결정: 신규 직원 생성에 한해 선택한 현재값과 최초 기간이력을 하나의 트랜잭션에서 생성한다.
- 결정: 최초 기간 기준일은 실입사일을 우선하고, 없으면 문서상 입사일을 사용한다.
- 결정: 입사예정은 미래 입사일, 재직은 오늘 이전 입사일과 퇴사일 없음, 퇴직은 입사일 이후의 과거 퇴사일을 요구한다. 휴직 신규 생성은 휴직 기간 원본이 필요하므로 허용하지 않는다.
- 결정: 최초 Baseline의 `created_by`는 직원 생성 Actor를 그대로 사용하고 인사발령 대상 FK는 `NULL`로 둔다. 별도 감사 테이블이나 임의 Actor 문자열은 추가하지 않는다.
- 결정: 기존 데이터 보정 기능, 스케줄러, DB/Migration은 이번 범위에 포함하지 않는다.
