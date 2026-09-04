# Service Dictionary

> Evidence Service의 책임 경계는 [EvidenceOriginalContract.md](EvidenceOriginalContract.md)를 따른다. 이 문서는 구현 Service 인벤토리이며 Evidence의 공통 의미를 별도로 정의하지 않는다. `EvidenceTypePolicyService`는 유형별 필수 공통 분류와 정책을 판정하고, 화면 필드 메타데이터를 소유하지 않는다.

## 2026-09-04 시스템 로그관리 Service 경계

- `SystemLogService`: 시스템 `.log` 파일의 목록·용량 집계·제한된 Tail 조회·단건/전체 삭제·다운로드 경로 검증을 소유한다. View와 Controller의 직접 파일 접근을 금지하고 삭제 감사사건을 `service-system-log-management`에 기록한다. 업무 Service 로그 생성, DB 감사로그, 웹서버 접근로그와 캘린더 로그 구조는 범위 밖이다.
- `DatabaseSyncService` / `DatabaseRestoreService`: DB 동기화·복원의 실행 상태는 상태 JSON과 Runner Lock을 운영 상태 SSOT로 유지하고, 시작·진행·완료·실패 사건은 `LoggerFactory`의 Service 채널로 기록한다. 별도 `*_trace.log` 누적 파일은 생성하지 않는다.
  - Verification: `tests/regression/system_log_service_contract.php`가 목록 집계, 사용자용 요약의 기술상세 비노출, 경로 이탈 차단을 검증한다.

## 2026-09-02 내부 승인형 Evidence P1 호환 저장

- `RegularEmploymentIncomeAccountingGenerationService`: 신규 승인에서 표준 급여 raw 금액과 `work_team_id`/`team_id` 호환값을 함께 전달하며 Model의 실제 컬럼 필터로 구·신규 Schema를 지원한다.
- `PersonalExpenseApprovalService`: 신청일·원 Item 프로젝트·거래처 raw 값과 `work_team_id`/`team_id` 호환값을 저장한다. 공용 거래처는 정규화 결과이고 `raw_client_id`는 신청 Item 원본이다.
- `DailyEmploymentIncomeAccountingGenerationService`: 기존 Header raw·Raw Line 생성은 유지하고 공통 Envelope와 Group raw 분류를 추가한다. Canonical `DAILY_EMPLOYMENT_INCOME`만 신규 저장한다.
- `EvidenceGenerationService`: 내부 승인형 Evidence의 공용 응답은 `work_team_id`를 SSOT로 제공하고 `team_id` Alias를 신규 응답에서 제거한다. 목록 응답에서는 Snapshot·Source Hash·멱등키 Hash를 제거한다.
- `audit_evidence_p1_normalization.php`: 운영 DDL/DML 없이 내부 승인형 3종의 컬럼·backfill·원 업무·금액·Link·TableSettings 예상치를 집계한다.

## 2026-09-01 내부 업무형 Evidence Forward 정규화

- `DailyEmploymentIncomeAccountingGenerationService`: 최종승인 Transaction 안에서 일용 Evidence Header, 0원·제외·사용자부담을 포함한 Raw Line 전체, 근로자 지급 Transaction, Evidence Link, Registry, Closure를 생성한다. Header는 신규 `raw_*`와 구 컬럼을 한시적으로 dual-write하고 Callback 재사용 시 Raw Line Grain·Revision·금액까지 대사한다. 사용자부담 Line은 Evidence에 보존하되 지급 Settlement에서는 제외한다.
- `RegularEmploymentIncomeAccountingGenerationService`: 신규 상용 Evidence에 구 금액 컬럼과 정규화 금액 컬럼을 dual-write하고 승인 시점 Snapshot을 `APPROVAL_CAPTURED`로 저장한다. `raw_employee_count` 신규 쓰기는 중단한다.
- `PersonalExpenseApprovalService`: 신규 개인경비 Evidence에 현재 승인요청 ID, 최종승인 시각, Actor를 함께 저장한다. 구 스키마에서는 Model의 물리컬럼 필터로 기존 저장계약을 유지한다.
- `SalaryReportEvidenceModel`, `EmployeePersonalExpenseEvidenceModel`: `information_schema`의 실제 컬럼으로 INSERT payload를 제한하여 Forward 코드와 구·신규 스키마의 배포 순서 호환성을 제공한다.

## 2026-08-27 일용근로소득 엑셀 Preview

- `DailyEmploymentIncomeExcelService`: 일용근로소득 다중 근무그룹 입력양식·현재 문서 다운로드·업로드 Preview를 담당한다. 업로드는 DB에 저장하지 않고 그룹조건 일치, 일용근로자 ID, `WORK_TYPE`, 귀속월 날짜를 검증한 뒤 Group Grid 적용 후보만 반환한다. 날짜별 산정내역은 Trim한 500자 이하 선택값으로 업로드·다운로드하며 길이 초과를 자동절단하지 않는다.
- `DailyEmploymentIncomeModel::resolveDailyInsuranceContext`: 회사·사업구분·프로젝트·근무일로 유효 보험사업장을 먼저 확정하고, 명시 선택값은 같은 Scope의 자동 후보가 없을 때만 검증하여 사용한다. 보험사업장이 없거나 중복이면 계산 확인사항이지만, 확정 사업장에서 개인 Coverage가 없는 경우는 법정 가입자격 차단이 아니라 `PENDING_REPORT`로 투영한다. 중복 Coverage만 정정 필요사항으로 차단한다.

## 2026-08-24 역할형 분개규칙 저장

- `JournalRuleService`: 신규 저장을 역할형 규칙으로 제한하고 회사 범위, 공식 조건·회계역할·개인경비 분류 코드, 전기 가능한 계정, 적용기간, 서버 생성 `condition_hash`, 동일 역할·차대변 조건 충돌을 검증한다. 일반 저장 API는 SYSTEM 규칙의 생성과 수정을 모두 차단한다. Rule과 불변 Revision은 동일 트랜잭션에서 저장하며 신규 생성도 revision 1의 CREATE를 남긴다.
- `JournalRuleEvaluationService`: 역할형 개인경비 조건을 Service와 동일하게 정규화하며 회사 UUID는 대소문자를 변경하지 않는다. 비용분류별 정확 조건 hash와 `item_code=NULL` 공통 조건 hash를 함께 조회해 EXPENSE 차변과 EMPLOYEE_ACCRUED_EXPENSE 대변을 역할별로 해석하고, Source 조건 또는 적용일이 다르면 선택하지 않는다.
- `VoucherEvidenceRecommendationService`: 전표에 저장된 Link뿐 아니라 작성 화면의 `추가 예정` 증빙 identity도 동일 입력으로 조회한다. 서버가 증빙 존재·상태를 다시 검증하고 중복 identity를 제거한다. 개인경비 비용분류는 `PersonalExpenseClassificationProjectionService`의 최신 정정 Revision 우선·승인 Item 원본분류 fallback인 `effective_expense_category`만 사용한다. 개인경비 차변은 Item identity별 1 Line을 유지하고, 대변은 같은 직원·회사·통화·회계일·계정·정책 범위에서만 합산하되 모든 원천 Source Ref를 보존한다. 건수·금액·identity·보조정보 Coverage가 모두 `COMPLETE`일 때만 적용 가능한 추천안을 반환한다.
- `JournalRuleRevisionService`: Rule 행을 잠근 뒤 연속 Revision 번호와 before/after snapshot을 같은 트랜잭션에서 기록한다.
- 운영 Closure 적용 순서는 `20260824_11` 역할 Registry·EMPLOYEE 조건정책 → `20260825_01` 공식 지급수수료 분류 → `20260824_14` 역할형 조건 DDL이다. 프로젝트에는 Migration 이력 테이블이 없으므로 적용 도구가 각 단계의 구조·Seed 상태를 Preflight하고 완료 파일 목록과 전후 Snapshot을 출력한다.

## EmploymentContractService project boundary

- `work_location_type = PROJECT` means that the contractual workplace characteristic is a company site and does not require `project_id`.
- `project_id` is required only for a fixed-term contract whose `fixed_term_reason_code` is `PROJECT_COMPLETION`; it is a contract snapshot, not the employee's current project assignment.
- Current and historical project assignment changes remain owned by `JobAssignmentService` and the personnel-action application workflow, and do not revise an ordinary employment contract.

## 2026-08-20 공용 DataTable VIEW 설정

- `UserSettingService`는 기존 `system_user_settings`의 `(user_id, page_key, setting_type)` 계약을 유지한다. 공용 TableSettings 통합 후에도 TABLE과 VIEW는 각각 detail/save/delete되며 Controller·Route·DB 구조는 추가하지 않는다.
- TABLE은 `visibleColumns`, `columnOrder`, `columnDisplayName`, `columnRequirementPolicy`, VIEW는 `columnWidths`, `sortSettings`, `pageLength`, `searchFormExpanded`, `currentPage`, `searchFormState`를 소유한다. TableSettings가 노출하는 VIEW 네 항목도 동일 VIEW JSON을 사용하며 Excel 설정은 별도 setting_type과 공용 기능으로 유지한다.

## 2026-08-12 상용근로소득

- `RegularEmploymentIncomeService`: 귀속월 재직기간과 유효 근로계약의 교집합으로 대상직원을 배치 선정하고 계약 누락·중복 제외 사유를 반환하며, Header와 직원별 Item의 원자 저장, Item 합계 기반 Header 집계, 공용 결재 상신·회수, 공용 휴지통 삭제·복원·완전삭제, 최종승인 시 급여(신고) 증빙과 거래 및 Evidence Link의 멱등 생성을 담당한다. 계산 가능성은 대상선정과 분리하며 법정요율을 하드코딩하지 않는다.
- `RegularEmploymentIncomeFieldPolicyService`: 상용근로소득 사용자별 TABLE 설정의 `columnDisplayName`과 `columnRequirementPolicy`를 읽어 Header 입력필드의 저장 필수값을 검증하고 동일한 사용자 지정 컬럼명을 오류 문구에 사용한다.
- `RegularEmploymentIncomeApprovalAdapter`: `REGULAR_EMPLOYMENT_INCOME` 문서의 공용 결재함 상세와 승인·반려 처리를 위 Service로 연결한다.
- 재사용: `ApprovalWorkflowService`, `TransactionCrudService`, `EvidenceExternalKeyService`, `EvidenceLinkModel`, `ActorHelper`.

## 2026-08-11 코드관리 영구삭제

- `CodeService`: `system_codes` 코드 목록·상세·저장·그룹명 동기화·정렬과 단건 Hard Delete 흐름을 담당한다. 식별키 변경과 삭제는 `CodeReferenceService`의 동일한 fail-closed 판정을 사용하고, 참조 중 코드의 비활성화와 표시명 변경은 허용한다. 휴지통·복구·purge 및 Excel 입출력 책임은 제공하지 않는다.
- `CodeReferenceService`: `CodeReferenceRegistry`에 등록된 일반 물리 컬럼과 JSON 참조를 `CodeModel`로 검사해 사용자용 업무명·참조건수를 반환한다. 운영 코드그룹 전체를 등록 대상으로 하며 미등록 그룹, 존재하지 않는 대상, 조회 오류는 삭제·식별키 변경을 허용하지 않는다.
- `CodeReferenceRegistry`: 코드그룹별 참조 물리 위치의 단일 Registry다. 코드 삭제, `code_group`/`code` 변경, 참조내역 조회가 같은 정의를 사용한다. 신규 코드그룹은 참조 없음까지 명시적으로 등록하기 전 삭제할 수 없다.

## 2026-08-11 법정기준 Metadata 연계

- `StatutoryStandardService`는 `DataTableColumnMetaService`의 `statutory-standard`, `statutory-standard-source` 메타데이터를 options 응답과 물리 컬럼 필수값 검증에 재사용한다. DB NOT NULL은 완화할 수 없고, 사용자가 Table Setting에서 선택 컬럼을 필수로 강화한 정책은 저장 요청에도 적용한다.

## 2026-08-11 법정기준관리

- `StatutoryStandardService`: 한 행=한 적용기간 법정기준 CRUD, `SequenceHelper` 기반 신규 순번 채번, 동적 값 검증, NULL 종료일을 무한 종료로 보는 동일 종류 기간중복 방지, 관련근거와 원본파일의 트랜잭션 저장 및 영구삭제를 담당한다. 개정 등록은 기존 열린 기간을 사용자가 먼저 종료하는 방식 B를 사용하며 신규 저장이 기존 행을 자동 변경하지 않는다. 현재 적용 중인 행은 삭제할 수 없고 선택삭제는 전건 사전검증 후 하나의 DB Transaction에서 처리한다. `rate` 기준값은 Resolver용 소수 비율을 유지하되 저장 전에 12자리 정밀도로 정규화해 JSON 부동소수점 꼬리를 제거한다. 관련근거 파일은 파일저장소의 `public_document` 정책을 SSOT로 사용하며 options 응답에도 허용 확장자·MIME·용량·활성 상태를 제공해 파일 선택 UI와 서버 검증을 일치시킨다. 저장소 내부 생성명은 `file_path`에만 사용하고 `file_name`에는 사용자가 업로드한 원본 파일명을 보존한다. 본체 적용기간은 `effective_from`/`effective_to`, 근거자료 공표일은 Source의 `published_at`만 사용한다. Web/API는 `ActorHelper::user()`를 기본으로 사용하며 승인된 CLI 데이터 정비는 생성자에 `ActorHelper::system(context)`를 주입해 같은 검증·트랜잭션 경로를 사용한다.
- `StatutoryStandardTemplateService`: 운영 DB `system_codes.STATUTORY_STANDARD_TYPE.extra_data`를 직접 JSON decode하는 유일한 입력 Template 파서다. 보험 Type은 `component_templates`에서 정책 구성요소·고용형태·업무 Scope가 정확히 일치하는 카드 계약을 선택하고 중첩 `value_path`, 그룹, nullable, 선택지를 검증한다. 목록 Projection은 `summaryFields()`로 첫 표시필드 정의만 요청당 한 번 읽고 상세 편집용 선택코드 options를 조립하지 않는다. 상세·저장 경로는 한 번 정규화한 Template 컬렉션을 `findFrom()`으로 재사용한다. `SYSTEM_CODES` Select는 `option_code_group`과 `allowed_codes`를 검증한 뒤 `SystemCodeOptionService`로 선택지를 투영한다. 원시 JSON 입력이나 보험별 PHP·JS 필드 하드코딩은 사용하지 않는다. 기존 Matrix·Bracket 계약과 실제 법정 숫자의 `value_data` 책임은 유지한다.
- `SystemCodeOptionService`: 법정기준 Header·기준값 카드가 지정한 코드그룹을 `sort_no`, `code_name`, `id` 순으로 조회하는 공용 선택지 경계다. Template이 선언한 `allowed_codes`의 누락을 차단하고, 기존값 조회에서는 비활성 코드를 `disabled`로 표시하며 신규 저장은 활성 허용코드만 통과시킨다. 동일 Service 요청 안에서는 코드그룹·허용코드·비활성 포함 여부가 같은 조회 결과를 재사용해 Template별 중복 SQL을 방지한다.
- `StatutoryStandardService`는 보험 Header 조합을 `StatutoryStandardTemplateService`의 정확 일치 Template과 명시적인 Dimension 조합 계약으로 서버 검증하고, 목록·상세에 사용자용 `standard_combination_name`을 제공한다. 공통 `PREMIUM`은 `ALL/ALL`, `ELIGIBILITY`는 `REGULAR/HEAD_OFFICE`, `DAILY/HEAD_OFFICE`, `DAILY/CONSTRUCTION_SITE`만 허용하며 이전 UI 값이나 지원하지 않는 조합을 자동 보정하거나 저장하지 않는다.
- `StatutoryStandardPeriodStatusProjection`: 법정기준의 `effective_from`, `effective_to`, 서버 회사 업무일 기준으로 `period_status`(`SCHEDULED`, `CURRENT`, `ENDED`) SQL Projection과 검색어 정규화를 제공한다. 물리 Workflow 상태나 DB 저장값을 만들지 않는다.
- `StatutoryStandardTemplateService`의 `type=matrix`는 비어 있지 않은 기본 `columns`와 선택적인 `dynamic_dimension`/`object_storage` 계약을 검증한다. `StatutoryStandardService`는 같은 계약으로 동적 Map과 숨김 컬럼의 시스템 기본값까지 정규화하며 `dash_as_zero`는 대시만 0으로, `blank_as_zero`는 대시와 공란을 0으로 구분한다. 행 필수값, 숫자·선택값, 음수·rate 범위, 중복키, 구간 역전·중첩·마지막 무제한 구간 검증은 유지한다. `connects_after.rows_key`가 선언된 구조화 규칙은 self-describing Matrix의 최종 상한과 첫 규칙 구간의 연결도 검증한다. `preserve_schema_in_value` 유형은 신규 저장 시 `_schema.fields`를 찍고 수정 시 DB에 저장된 구조 계약을 신뢰하되 현재 Template의 표시명·접힘 UI·숨김 기본값만 code 기준으로 병합한다. 따라서 과거 계산 의미는 유지하면서 사용자용 표현 개선은 과거 수정화면에도 적용된다. 특정 가족수나 초과구간 개수는 Service에 두지 않는다.
- `StatutoryStandardResolver`: 일반 조회는 보험 Type의 `PREMIUM/ALL/ALL`, 구성요소 조회는 요청한 정책 구성요소·고용형태·업무 Scope·추가 Dimension과 기준일이 정확히 일치하는 한 행만 반환한다. 고용형태·Scope fallback은 없으며 0건은 없음, 2건 이상은 Timeline 중복 오류다.
- `StatutoryStandardSourceModel::replace`는 관련근거 전체를 삭제후 재삽입하지 않고 ID 기반 diff를 적용한다. 존재 ID는 수정하여 생성 감사값과 미교체 파일을 보존하고, 신규 ID는 삽입하며, 요청에서 제외된 ID만 삭제한다. 이 처리와 본체 저장은 같은 DB Transaction을 사용한다.
- `StatutoryStandardService::list`는 목록 projection에 `value_data` 전체를 노출하지 않고 `StatutoryStandardValueSummaryService`가 생성한 `value_summary`와 표시형식을 제공한다. 목록 요청당 경량 Summary 필드 Map을 한 번만 만들며 상세 모달용 선택코드 options는 조회하지 않는다. 전역검색 SQL은 PDO native prepare 계약에 맞춰 검색 대상별 고유 named placeholder를 사용하며 동일 placeholder를 반복하지 않는다. 보험료 등 일반 기준은 Type Template 첫 필드의 금액·요율·Matrix 형식을 유지하고, 가입자격은 정책 버전 같은 첫 숫자 필드를 대표값으로 사용하지 않으며 고용형태·업무 Scope·종속 보험 필드로 결정적인 가입자격 요약을 생성한다. Matrix·Bracket·`_schema`·`calculation_policy`·Source 상세는 `detail`에서만 조회한다.
- `StatutoryStandardValueSummaryService`: 법정기준 목록의 표시 전용 `value_summary` Projection을 담당한다. `AMOUNT`, `RATE`, `MATRIX`, `ELIGIBILITY_SUMMARY`, `TEXT` 표시계약을 구분하며 원본 `value_data`를 변경하거나 Resolver 계산에 관여하지 않는다.
## 2026-08-06 취업규칙·인사규정

| Service | Responsibility | Reuse |
|---|---|---|
| `EmploymentRuleService` | 취업규칙·인사규정 초안·개정·결재·시행예정·시행·폐지·감사 | `ApprovalWorkflowService`, `EmploymentRuleResolver`, `ActorHelper`, `system_codes` |
| `EmploymentRuleResolver` | 회사·규정종류·기준일에 맞는 단일 시행 개정본 해석과 중복기간 방어 | 규정 문서 조회 경계. 근태·휴가·급여 계산정책 SSOT로 사용하지 않는다. |
| `EmploymentRuleApprovalAdapter` | `EMPLOYMENT_RULE_REVISION` 결재 상세와 승인·반려 | 공용 결재 Adapter registry |

## 2026-08-04 인사발령관리

- `PersonnelActionChangePolicy`: 변경구분 11개 저장값·한글 표시명·입력 metadata와 발령유형 13개의 `allowed`·`required_all`·`required_any`·필수 상태값을 소유하는 Runtime 정책 SSOT다. 변경구분은 코드관리 대상이 아닌 고정 시스템 실행명령이며 실제 DB 쓰기는 담당하지 않는다.
- `PersonnelActionService`: 인사발령 목록·상세, Modal 지연 입력옵션, 작성중 헤더/복수 대상자/복수 변경행의 원자 저장, 공용 목록 순서변경, 결재요청·회수·결재결과 투영과 휴지통 조회·복원·단건/선택/전체 완전삭제 정책을 담당한다. 변경 전 값은 클라이언트 입력을 신뢰하지 않고 직원 현재 SSOT에서 생성하며 변경명령 집합은 `PersonnelActionChangePolicy`로 검증한다.
- `PersonnelActionApplyService`: 승인되고 효력일이 도래한 인사발령만 행 잠금과 단일 트랜잭션으로 직원 현재값 및 기간 이력에 적용한다. 적용 직전 저장된 명령집합을 `PersonnelActionChangePolicy`로 재검증하고 미지원 명령을 명시적으로 거부한다. 이미 적용된 발령은 성공적으로 무시하고 변경 전 스냅샷 충돌은 대상자 `FAILED`와 `application_error`로 남긴다.
- `PersonnelActionApprovalAdapter`: 공용 결재함에 `PERSONNEL_ACTION` 상세·대상자·변경행을 제공하고 결재 처리를 `PersonnelActionService`로 위임한다. 결재선은 템플릿 SSOT의 `SUBMIT → FINAL_APPROVAL` 2단계를 그대로 사용한다.
- `AttendanceService`: 불변 출퇴근 등록, 일별 근태 재계산, 관리자 직접 정정, 월 마감 revision 생성과 재오픈을 처리한다. 출퇴근 원천 등록과 일별 계산은 별도 트랜잭션 경계로 분리해 계산 실패가 이미 저장된 원천 사실을 롤백하지 않는다. 본인 출퇴근 API는 로그인 사용자에 연결된 직원과 서버 현재시각만 사용하지만 관리자 근태관리 화면에서는 노출하지 않는다. 관리자용 `출퇴근 기록 보정`만 직원·과거 발생일시·사유를 입력받는다. 시간 분류 규칙은 `AttendanceCalculationPolicy`에 위임하고, 유효한 `CLOCK_IN → CLOCK_OUT` 순서쌍의 자동 WORK와 출퇴근 범위 안 계약상 고정 휴게의 자동 BREAK 구간을 만든다. 월별 현황과 월 마감 목록은 Service가 동일 Model projection을 호출하되, 각각 읽기 전용 누계와 마감 액션이라는 별도 API 계약을 유지한다.
- `AttendanceCalculationPolicy`: 출퇴근 사건의 계약 일정 기반 `work_date` 귀속, WORK 합집합에서 BREAK/OUTSIDE 교집합 차감, 최초 WORK 시작부터 최종 WORK 종료 범위 안의 실제 BREAK/OUTSIDE 시간, 승인 휴가, 계약 예정초과, 일·주 법정 연장 중복 방지, 기준일 야간구간, 법정공휴일 근로와 지원 근무형태를 판정하는 근태 계산정책 SSOT다. 계약상 휴게가 있으면 Service가 자동 BREAK를 공급하므로 정상 고정휴게는 별도 출퇴근 사건을 요구하지 않는다. `StatutoryStandardResolver`가 제공한 기준일 revision만 소비하고 DB I/O와 급여 금액 계산은 담당하지 않는다. 법정기준 또는 공휴일 calendar가 없거나 근무형태가 미지원이거나 필수 휴게구간이 전혀 없으면 `NEEDS_CONFIRMATION`으로 마감을 차단한다.
- `AttendanceService` 월 마감 Guard: 출퇴근 누락, 계약·일정 충돌, 휴직기간 충돌, 연속 출퇴근 중복, `NEEDS_CONFIRMATION`을 서버에서 차단하고 원인별 건수를 사용자 메시지로 제공한다.
- `DataTableColumnMetaService`는 단일 도메인의 물리컬럼과 복합 도메인에 등록된 복수 원본테이블의 모든 물리컬럼을 `information_schema`에서 등록순서 + `ORDINAL_POSITION` 순으로 제공한다. `ordinal_position`은 등록 테이블 전체의 누적 순번이고 `source_ordinal_position`은 원본 테이블 내부 순번이다. 증빙원본 `evidence-*` 도메인은 기본값 복원용 원본 컬럼명·사용컬럼명·필수구분을 각각 현재 물리 DB의 `COLUMN_NAME`, `COLUMN_COMMENT`(공백이면 `COLUMN_NAME`), `IS_NULLABLE`에서만 만든다. 복합 도메인은 필요 시 모든 key를 `table.column`으로 고정해 Projection 별칭과 충돌을 차단한다. `job-assignment`는 `auth_users` 같은 JOIN 표시 전용 참조 테이블을 제외하고 직원 Master와 6개 기간이력 테이블 전체를 qualified key로 제공하며 COMMENT와 NULL 허용 계약을 그대로 사용한다. `individual-permission-users`는 직원 1행을 기준으로 `user_employees` 전체 물리컬럼을 qualified key로 제공한다. 역할·계정상태·권한방식 조회값은 명시적 기타 가상컬럼, 개인권한 수 집계값은 계산 가상컬럼으로 화면에서 등록해 물리 metadata를 오염시키지 않으면서 동일 TABLE/VIEW 설정의 보기·순서·표시명·너비·정렬 적용 대상에 포함한다. 상용근로소득은 물리 문서상태와 별도로 현재 결재요청 상태를 `approval_status` JOIN Projection으로 정식 등록하며 요청이 없으면 미상신으로 표시한다. 전표입력의 `voucher-evidence-selection`과 거래입력의 `transaction-evidence-selection`은 여러 증빙 원본을 합친 동일한 선택 Projection 메타데이터를 제공하고, 직원·거래처·프로젝트·계좌·카드·팀 표시값은 원본 FK를 해석한 JOIN 컬럼으로 등록한다. 증빙원본의 급여(신고)는 `evidence-payroll-report`를 `ledger_evidence_salary_report`에 연결해 다른 증빙원본과 동일한 물리 컬럼 기반 테이블설정·상세모달 계약을 사용한다. 근태 메타 도메인 `attendance-daily`, `attendance-monthly`, `attendance-exceptions`, `attendance-closures`와 근로계약 모달의 `employment-contract-pay-component` 급여항목 관리 도메인도 같은 물리 metadata 계약을 사용한다.
- 권한부여의 역할별·개인별 권한목록은 실제 순서변경 열을 공용 `__reorder`, 권한 체크 열을 공용 `__actions`로 등록한다. 페이지 전용 `handle`, `role_permission_id`, `user_permission_id` 설정 key를 만들지 않으며 공용 시스템 가상컬럼 placeholder와 실제 기능 열이 중복되지 않게 한다.
- `AttendanceWeeklyRecalculationService`: `WORKING_TIME_STANDARD.week_start_day`로 법정 주간을 결정하고 해당 직원의 주 Daily를 잠근 일괄 조회해 일 초과와 주 추가 초과를 중복 없이 발생 날짜에 재배분한다. 월 경계 마감 전에는 마감 Transaction 내에서 다음 달 closure와 Daily를 잠그고 근무일·명시적 일정예외의 Daily 준비, `NEEDS_CONFIRMATION`, blocking exception, CLOSED 상태를 최종 재검증한다. 마감은 다음 달 Daily를 즉시 생성하지 않으며 CLOSED 월은 재오픈을 요구한다.
- `StatutoryStandardService` 근태 Revision Guard: Daily가 참조하는 근로시간·공휴일 row의 종류, 시작일, `value_data`, 근거 Source와 참조 날짜를 훼손하는 종료일 수정을 차단한다. 정정은 기존 row 보존 후 신규 Revision/Correction INSERT로 처리한다.
- `AttendanceScheduleService`: 근무일에 유효한 근로계약과 주간 일정으로 예정 근무 스냅샷과 야간 출퇴근의 근무일을 결정한다. 상세 휴게구간이 없으면 `break_minutes` 총량만 예정근로에서 차감하고 이를 오류로 보지 않는다. 상세구간이 존재할 때의 범위오류·합계 불일치는 원천 출퇴근을 거부하지 않고 `calculation_issue_code`로 반환해 `NEEDS_CONFIRMATION`으로 투영한다.

## 2026-07-28 자금 실제·회계 잔액 분리

- `FundsOverviewService`: 증빙원본 입금-출금의 실제잔액과 POSTED/CLOSED 전표의 BANK_ACCOUNT 참조 라인 차변-대변 회계잔액을 계좌별로 분리하고 차이를 제공한다.
- `DailyFundsReportService`: 동일 기준일의 실제잔액, 회계잔액, 차이 및 지급의무 전망을 서로 다른 영역으로 제공하며 확정 내부이체를 외부 입출금과 분리한다.
- `FundsBalanceModel`: POSTED/CLOSED 전표라인과 `ledger_voucher_line_refs`의 `ACCOUNT/BANK/BANK_ACCOUNT` 참조를 사용해 계좌별 회계잔액을 조회한다.
- `BankTransactionReportModel`: 은행 원본 한 행에서 전표연결, PAYMENT 지급배분, 확정 내부이체 방향·상대 자사계좌·전표·금액을 독립 필드로 조회한다.
- `InternalTransferRepository`: 확정 전표, 활성 은행 Evidence Link 2건, 서로 다른 자사계좌, canonical `ACCOUNT` 라인 참조와 정확한 차변·대변 금액을 단일 계약으로 검증한다. 별도 내부이체 테이블, 문자열 추론, legacy fallback을 사용하지 않는다.

## 2026-07 Funds information architecture

- `FundsOverviewService`
  - Responsibility: 사용 중인 계좌 마스터와 증빙원본 입출금(은행) 거래를 조합해 자금유형별 계좌, 입금·출금 계산잔액, 최종거래일, 총잔액을 구성하고 `BANK` 코드의 저장 코드·기존 코드명을 표준 코드명으로 정규화한다.
  - Controllers: `FundsOverviewController`
  - Models: `BankAccountModel`, `BankTransactionReportModel`, `CodeModel`
  - Out of scope: 거래 저장·수정, 계좌 마스터 저장, 전표 연결, DB 구조 변경
- `BankTransactionReportService`
  - Expanded responsibility: 선택한 활성 계좌의 화면 진입 컨텍스트를 제공하고 해당 계좌 필터가 적용된 기존 거래 조회·요약·수정 흐름을 유지한다.
  - Controllers: `BankTransactionReportController`
  - Models: `BankAccountModel`, `BankTransactionReportModel`
  - Out of scope: 자금유형 분류와 전체 자금현황 집계

## 2026-07 Evidence read/policy SQL boundary

- Body Read Models use `EvidenceSchemaModel` directly for validated physical-column expressions; Models do not depend on a Service for schema access.
- `EvidenceBankHelperService`: preserves BANK_TRANSACTION validation/normalization and delegates legacy claim reset to `EvidenceImportModel`.
- `EvidenceReferenceResolverService`: preserves lookup order, normalization, caches, and fallback while delegating reads to `EvidenceReferenceModel`, `ClientModel`, and `ProjectModel`.
- `EvidenceStatusHelperService`: preserves status/readiness interpretation and detects active outputs only through `EvidenceLinkModel` using canonical `(import_type, evidence_id)` identity.
- `EvidenceSummarySearchService`: preserves JSON summary aggregation and ordering while `EvidenceImportModel` reads candidate rows.
- `EvidenceTemplateDropdownService`: preserves spreadsheet labels, ordering, empty fallback, and validation composition while `EvidenceDropdownModel` and `CodeModel` perform reads.
- `EvidenceTypePolicyService`: preserves type-policy caches and fallback while `CodeModel` and `EvidenceSchemaModel` perform code/schema reads.
- `EvidenceDownloadService`: preserves column selection, workbook formatting, filters, and order while `EvidenceImportModel` and `EvidenceSchemaModel` perform reads.

## 2026-06 Auth

- `Mailer` / `MailService`
  - Responsibility: 필수 업무 발신자 프로필 코드로 From·기본 Reply-To와 SMTP Transport를 함께 결정한다. `SUKHYANG_APP_ADMIN`은 `DAUM_SMTP_MAIN`(`DAUM_SMTP_MAIN.password`)을 사용하는 ERP 자동·보안·관리자 메일이고, `SUKHYANG_REPRESENTATIVE`는 `GOOGLE_SMTP_MAIN`(`GOOGLE_SMTP_MAIN.password`)을 사용하는 향후 대표자 직접 작성 공식메일 전용이다. Transport 공개 연결정보는 `SmtpSettings.Transports`에 두고 Secret은 공용 `SecretResolver`로만 조회한다. 프로필·Transport 누락, 오타, 불완전 설정, Transport와 CredentialCode 불일치는 다른 SMTP로 fallback하지 않고 차단한다. SMTP 접수 결과·오류코드·Message ID·TransportCode를 반환하며 Secret이 비어 있으면 연결 전에 `SMTP_SECRET_NOT_CONFIGURED`로 차단한다. `AdminEmail`은 관리자 알림 수신주소로만 사용한다.
  - Callers: `TwoFactorMail`, `AdminApprovalMail`, `ContactMail`
  - Out of scope: SMTP Secret 저장·표시, Gmail 웹 기본 발신주소 변경, 대표자 공식메일 업무화면, Queue/Worker

- `TwoFactorMail` / `AdminApprovalMail` / `ContactMail`
  - Responsibility: 모두 `SUKHYANG_APP_ADMIN` 프로필을 명시한다. 문의 전달만 검증된 문의자 이메일을 호출별 Reply-To Override로 사용한다. 전체 이메일·OTP·승인 Token·SMTP Secret은 로그에 기록하지 않고 request ID, 오류코드, 발신 프로필, 수신 도메인, 성공 여부만 기록한다.
  - Callers: `AuthService`, `RegisterService`, `ContactController`
  - Out of scope: 대표자 명의 발송, 인앱 알림의 이메일 전환, 비밀번호 찾기

- `ExternalIntegrationService`
  - Responsibility: `BusinessApi.BaseUrl` 공개 설정과 `BusinessApi.CredentialCode`를 분리하고, `BUSINESS_STATUS_API.service_key`는 공용 `SecretResolver`로만 조회한다. 실제 요청 URL은 Service 내부에서 조립하며 Service Key를 로그·오류메시지·브라우저 응답에 반환하지 않는다. Secret 누락 시 외부 호출 전에 차단한다.
  - Callers: `ExternalIntegrationController`
  - Out of scope: Secret 저장·회전·화면 표시

- `Crypto`
  - Responsibility: `Security.RRNCredentialCode`의 `SECURITY_RRN_KEY.secret`을 공용 `SecretResolver`로 조회하여 기존 AES-256-CBC 암호화·복호화 계약을 유지한다. 기존 암호자료 호환을 위해 Key 변경과 무계획 재암호화를 금지한다.
  - Callers: 직원·거래처·일용근로 보험 판정·엑셀 표시 Service
  - Out of scope: Key 생성·회전·로그·브라우저 반환

- `ApprovalService`
  - Responsibility: approval token verification, approval request view-data assembly, user approval execution, approval audit logging
  - Controllers: `UserApprovalController`
  - Out of scope: HTML rendering, direct view DB access, approval result page rendering

- `AuthService`
  - Expanded responsibility: login, SMTP-accepted delivery 확인 후에만 2FA Challenge를 생성하는 실패 폐쇄형 인증메일 흐름, 2FA verification, password change, password recovery result assembly, and current-user session refresh after password changes. SMTP 실패 시 pending Challenge를 폐기하고 실패 인증로그와 역할별 안전 문구를 반환한다.
  - Controllers: `LoginController`, `PasswordController`, `TwoFactorController`
  - Out of scope: direct view DB access, HTML rendering

- `PermissionService`
  - Responsibility: permission catalog query, approved/active user and active-role validation, `ROLE`/`EXTEND`/`REPLACE` final effective permission evaluation through `auth_user_permission_profiles`, `auth_user_permissions`, `auth_role_permissions`, request-scope decision caching, permission create/update/delete/reorder orchestration, and current Route permission registry synchronization through `PermissionModel`
  - Controllers: `PermissionController`, `PasswordController`, `TwoFactorController`, route/middleware permission checks
  - Construction: existing explicit PDO injection remains supported; core middleware may use the optional default connection without acquiring PDO itself
  - Out of scope: direct HTML rendering, role-permission bulk assignment UI state

- `SettingService` / `SessionConfigService`
  - Responsibility: system setting lookup and session timeout/alert setting interpretation through `SettingConfigModel`
  - Callers: settings Controllers, `ConfigHelper`, API access middleware, session Controller
  - Construction: existing explicit PDO injection remains supported; core callers may use the optional default connection
  - Out of scope: direct View DB access, core SQL, arbitrary configuration SQL

- `SettingsNavigationService`
  - Responsibility: settings menu registry query, current-user permission evaluation, and settings View data assembly
  - Controllers: `MainController`
  - Out of scope: HTML rendering, direct View DB access, calendar navigation

- `SystemCodeOptionService`
  - Responsibility: 허용 코드그룹의 활성 선택지 Projection과 단일 코드 유효성 확인
  - Model: `CodeModel::getAllowedOptions()`, `CodeModel::isActiveCode()`
  - Out of scope: direct SQL, 코드 CRUD, 화면 정렬 재구성

- `StatutoryStandardResolver` / `StatutoryStandardTemplateService`
  - Responsibility: 기준일에 유효한 법정기준 후보 Resolve와 법정기준 Type Template Projection
  - Models: `StatutoryStandardModel::effectiveComponentCandidates()`, `StatutoryStandardModel::effectiveLegacyCandidates()`, `CodeModel::getStatutoryStandardTemplates()`
  - Out of scope: direct SQL, 최신행 추정, 임의 숫자 fallback

- `WorkTeamService`
  - Responsibility: 작업팀 업무규칙과 저장 흐름, `BUSINESS_UNIT` 코드 유효성 검증
  - Model: 업무 저장은 `WorkTeamModel`, 코드 판정은 `CodeModel::isActiveCode()`
  - Out of scope: direct SQL, Controller 검증 중복

- `PageRegistryQueryService`
  - Responsibility: page registry lookup for `PageKeyResolver` while preserving resolver cache and legacy key interpretation contracts
  - Callers: `PageKeyResolver`
  - Out of scope: page-key interpretation, legacy alias policy, DB schema change
  - 활성 Permission은 반드시 활성 PageRegistry 원본에 연결되어야 하며 API Permission은 소유 WEB 화면 Page Key를 공유한다.

- `RolePermissionService`
  - 권한부여 트리는 `system_page_registry.page_label`과 `breadcrumb`를 페이지·카테고리 표시 SSOT로 사용한다.
  - 개별 Permission은 `PermissionPresentationHelper`를 통해 사용자용 권한명·설명·기능그룹으로 정규화하며 Permission ID·Key와 기존 역할·개인 Mapping은 변경하지 않는다.
  - PageRegistry 미연결 Permission은 운영 정규화 오류로 간주한다. `virtual.*` 그룹은 방어적 표시에만 사용하며 정상 완료 상태에서는 0개여야 한다.
  - HTTP Runtime에서는 현재 `PermissionRegistry`에 등록된 Route Permission만 신규 부여 트리에 포함한다. Route가 사라진 과거 Permission과 역할·개인 Mapping은 물리삭제해 현재 Route Set과 일치시킨다.
- `PermissionService`
  - PermissionRegistry의 삭제 Route 정리는 Service 트랜잭션에서 수행한다. `auth_role_permissions`, `auth_user_permissions`, `auth_permissions` 순서로 물리삭제하고, 감사이력은 FK `SET NULL`과 Snapshot으로 보존한다.
  - Responsibility: reusable Permission Master tree and compact role-selection mapping reads, reorder orchestration, plus differential batch saving of a role's complete selected permission set in one transaction using `auth_role_permissions`, `auth_permissions`, and `system_page_registry`; protects the `super_admin` management permissions, preserves at least one active approved recovery administrator using effective ROLE/EXTEND/REPLACE results, and records before/after sets in the security log

- `UserPermissionService`
  - Responsibility: 사용자별 `ROLE`/`EXTEND`/`REPLACE` Mode와 직접 Permission 전체 Set 조회·차집합 저장, canonical-hash `state_version`, 자기권한·admin·super_admin·권한상승·마지막 복구관리자 Guard, Profile·Mapping·Audit 단일 트랜잭션 처리
  - Models/Repository: `UserPermissionModel`, `UserPermissionRepository`
  - Out of scope: 역할 Permission 저장, 직원 퇴사 시 계정 비활성화, 기간제한 권한
  - Controllers: `RolePermissionController`
  - Batch policy: validates the active role and every active permission ID, computes add/remove differences against current mappings, then uses one bulk insert and one bulk delete at most; it never issues one HTTP request per checkbox.
  - Out of scope: DB schema change, direct HTML rendering
  - Domain note: canonical organization Route domain and Permission key are `permission-assignment`; `RolePermission*` class names describe the persisted role-to-permission relation only and are not Route aliases.

- `TemplateService`
  - Responsibility: approval-template list/save/delete/reorder orchestration for `user_approval_templates`; creates inactive drafts, validates the complete executable step flow and one-active-template-per-document-type policy before activation, guards request dependencies before hard delete, and reorders the locked complete template set with collision-safe sequences
  - Controllers: `ApprovalTemplateController`
  - Out of scope: step persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval`, `approval/template`, and `TemplateService`

- `TemplateStepService`
  - Responsibility: approval-template step list/save/delete/reorder orchestration, active-template structural edit guard, role/approver assignment and eligibility validation, template-row locking, template-scoped `MAX(sort_no) + 1`, complete step-set validation, and continuous collision-safe renumbering for `user_approval_template_steps`
  - Controllers: `ApprovalTemplateController`
  - Out of scope: template row persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval/step` and `TemplateStepService`

## 2026-06 Additions

- `ChartAccountService`
  - Responsibility: chart-account validation, hierarchy policy, transaction boundaries, Actor selection, and orchestration through `ChartAccountModel` and `SubChartAccountModel`
  - Controllers: `ChartAccountController`, `SubChartAccountController`
  - Out of scope: direct SQL, sub-account persistence SQL, Excel parsing

- `CustomSubAccountService`
  - Responsibility: `ledger_accounts_sub`의 계정별 `ref_target` 허용·필수 정책 CRUD, `REF_TARGET` 기준코드 검증, 계정 소유권 검증, `allow_sub_account` 동기화. 일괄 저장은 기존 허용행 PK를 보존하고 제거 대상만 삭제하며 `ledger_account_context_ref_policies` 참조행은 삭제 전에 차단한다.
  - Controllers: `SubChartAccountController`; 전표의 실제 선택값 저장은 `VoucherLineRefService` 책임으로 분리 유지
  - Out of scope: direct SQL, legacy `ref_type` fallback, voucher-line reference persistence

- `ChartAccountReferenceGuardService`
  - Responsibility: 휴지통 계정의 단건·선택·전체 영구삭제 전 실제 물리 참조와 대상 외 하위계정을 일괄 검증
  - Models: `ChartAccountReferenceModel`; 실제 삭제는 `ChartAccountService`의 단일 트랜잭션에서 수행
  - Out of scope: 계정 삭제 SQL, 보조계정 정책 삭제, FK 또는 DB schema 변경

- `EvidenceImportBusinessService`
  - Responsibility: Import own-company profile assembly, client existence lookup, and client company-name update orchestration through CompanyModel and ClientModel
  - Controllers: shared Ledger Import Controller traits
  - Out of scope: HTTP handling, raw SQL, evidence persistence, transaction/voucher creation

- `EvidenceImportBatchService`
  - Responsibility: normalize an Import batch-date key and query deletable evidence source row IDs through EvidenceImportModel
  - Controllers: shared Ledger Import Controller upload trait
  - Out of scope: batch deletion, status transition, transaction handling

- `EvidenceSchemaService`
  - Responsibility: cached evidence/import table and column existence lookup through EvidenceSchemaModel
  - Controllers: shared Ledger Import Controller utility trait
  - Out of scope: schema mutation, arbitrary SQL, user-provided identifier interpolation

- `DatabaseSyncService`
  - Responsibility: latest SQL backup selection, current Active/Standby DB pair resolution, Standby DB snapshot creation, Standby DB PDO sync execution, sync heartbeat/status/log persistence, failed-sync automatic rollback
  - Controllers: `DatabaseSyncController`
  - Out of scope: Primary backup generation, Primary restore, mysql CLI execution

- `DatabaseRestoreService`
  - Responsibility: selected SQL backup validation, current Active/Standby DB pair resolution, Active DB PDO restore execution, restore heartbeat/status/log persistence, sync-running guard
  - Controllers: `DatabaseRestoreController`
  - Out of scope: backup generation, Standby sync execution, mysql CLI execution

- `DatabaseActiveSwitchService`
  - Responsibility: Active DB switch guard validation, `db_replication.php` active_target update, target DB connection validation, latest switch status persistence, and switch log persistence
  - Controllers: `DatabaseActiveController`
  - Out of scope: DB connection recovery, automatic failover, automatic promotion

- `DatabaseActiveSwitchService` update
  - Updated responsibility: Active DB switch guard validation for running sync/restore status, `db_replication.php` active_target switch, target DB connection validation, latest switch status persistence, switch log persistence
  - Updated out of scope: DB connection recovery, automatic failover, automatic promotion

- `DataTableColumnMetaService`
  - Responsibility: shared DataTable physical-column meta assembly, canonical domain-alias resolution, table-comment exposure, and formal JOIN/Projection/virtual source descriptions. Composite domains preserve source table, original column, source ordinal, cumulative domain ordinal, database default, nullable/required state, and a collision-safe public key. The `job-assignment` domain exposes all columns from `user_employees` plus six physical assignment-history tables with qualified `table.column` keys, while excluding JOIN-only `auth_users` and reference masters. The `personnel-action` domain exposes the physical header/target tables while retaining approved display projection compatibility. The `leave-status` and `leave-request` domains expose the seven tables actually read by the request-list SQL plus formal display projections; `leave-balance` exposes the three tables actually read by the balance SQL and identifies `base_year` and Ledger SUM as projections. The `statutory-standard` domain maps directly to final `system_statutory_standards`; related-source metadata maps to `system_statutory_standard_sources`.
  - Controllers: `SystemController`
  - Out of scope: page-specific column rendering, DataTable state persistence, direct modal UI rendering

- `DatabaseReplicationStatusService`
  - Responsibility: replication topology configuration normalization and primary/secondary status interpretation. Fixed server identity and replica status queries are delegated to `DatabaseReplicationStatusModel`.
  - Controllers: `DatabaseActiveController`, `SystemController`
  - Out of scope: backup, restore, synchronization, failover, and arbitrary SQL execution

- `LayoutService`
  - Responsibility: layout UI/session/user/brand data composition. Employee and setting reads are delegated to `EmployeeModel` and `SettingService`.
  - Controllers: `LayoutController`
  - Out of scope: layout persistence, UI rendering, and direct DB access

- `NotificationService`
  - Responsibility: notification validation and orchestration for the navbar feed, stored-notification read-state updates, creation, administrator recipient resolution, and live aggregation of the logged-in user's currently actionable approval steps. Stored persistence is delegated to `NotificationModel`, approval projection reads to `ApprovalInboxModel`, and administrator lookup to `UserModel`.
  - Controllers: `NotificationController`; also used by ledger notification flows
  - Out of scope: notification UI rendering, direct DB access, approval-state persistence, and treating notification read state as approval workflow state

- `JournalRuleService`
  - 신규 저장은 단일 `accounting_role_code + debit_credit + account_id` 역할형 계약만 허용한다. 서버가 업무·자료·원천 Line·품목 조건을 정규화해 `condition_hash`를 만들고 규칙 저장과 `ledger_journal_rule_revisions` 생성을 같은 트랜잭션에서 처리한다. 레거시 차변·대변 묶음 Payload는 신규 저장에서 차단한다.
- `JournalRuleRevisionService`는 Rule 행을 `FOR UPDATE`로 잠근 트랜잭션 안에서 서버 확정 before/after Snapshot을 기록하고 `revision_no`를 원자적으로 증가시킨다. 신규등록, 편집, 상태, 잠금, 자동적용, 우선순위, 휴지통, 복원 변경을 모두 같은 계약으로 처리한다.
- `VoucherLineSourceRefService`는 회사 범위, 증빙 원천 Line, Rule Revision을 서버에서 재검증하고 안정적인 `source_ref_key`를 생성한다. 전표 Line별 배분합계와 원천별 ORIGINAL 균형을 대사하고, 상세 재조회 시 Source Ref를 복원하며 취소전표에는 반대 방향·동일 금액의 REVERSAL Ref와 `original_source_ref_id`를 생성한다.
- `AccountContextRefPolicyService`는 `ledger_accounts_sub`의 허용범위 안에서 회사·Operation Type·회계역할·기준일별 활성 정책행을 조건부 필수 참조유형으로 판정하고 적용기간 중복·복원·허용행 삭제 보호를 담당한다. 정책행 존재가 필수를 뜻하며 계정 전체 허용/필수 정책은 변경하지 않는다.
- `VoucherSubAccountValidationService`는 계정 전체 `is_required=1` 참조유형과 전표 계획의 회계역할 Context 조건부 필수 참조유형을 OR 합성하여 모두 검증한다. 조건부 정책으로 전역 필수를 약화하지 않는다.
- `JournalLearningPolicyService`는 전역 `journal_learning_policy.default` Baseline과 회사별 `journal_learning_policy.{company_id}` Override를 기존 SettingService를 통해 병합하고 정책 Revision·유형별 Guard와 전체 Snapshot을 후보·학습 판단에 제공한다.
- `JournalPostedLearningService`는 POSTED 비취소 전표의 Source Ref별 안정적인 Event Key로 학습 Event를 멱등 생성하고 상태·결정코드·재시도·최종오류를 관리한다. Source Ref 없는 Legacy Event와 비활성·전기불가 계정은 학습에서 제외한다.
- `JournalRuleEvaluationService`는 ACTIVE USER/SYSTEM 규칙을 우선 평가하고 미결정 역할에만 회사·증빙·업무·방향·원천 Line 조건이 같은 최근 POSTED 근거를 설명 가능한 수동 후보로 제공한다. 급여 3개 유형은 추천과 적용 양쪽에서 서버 Guard한다.
- `JournalRecommendationGuardService`는 급여 고정 Guard와 학습정책의 `operation_type`·`import_type`·`workflow`별 Guard를 추천 조회와 추천 Snapshot 적용 저장에서 검사한다. 비활성 도메인의 수동 전표 저장 자체는 차단하지 않는다.
  - Responsibility: 분개규칙 CRUD, 코드·계정 유효성, 거래처유형 wildcard, 동일조건 중복·충돌 차단, 정렬, 휴지통, 과거 전표에서 사용된 Rule 영구삭제 방어
  - Controllers: `JournalRuleController`
  - Out of scope: Excel 입출력, 추천 점수 계산, 시스템 Rule 자동생성

- `ClientService`
  - Expanded responsibility: client save request payload normalization, save validation, file-attached save orchestration, duplicate business-number save message mapping, and active client picker projection including the `CLIENT_TYPE` code and display name
  - Controllers: `ClientController`
  - Out of scope: direct HTML rendering, controller response output

- `ClientPayloadService`
  - Responsibility: client request payload normalization, save validation, duplicate business-number save message mapping, nullable field normalization
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: DB persistence, file upload/delete, controller response output

- `ClientFileService`
  - Responsibility: client file-attached save orchestration helper, upload error mapping, attachment upload/delete/retain/rollback helper
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: DB persistence commit/rollback ownership, controller response output

- `ClientTrashService`
  - Responsibility: client delete, trash list, restore, dependency-guarded partial-success purge, reorder orchestration including attachment cleanup
  - Purge policy: `ClientDependencyRepository` checks physical and logical ID references immediately before hard delete; referenced clients remain in trash while unreferenced clients are deleted
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: HTML rendering, controller response output

- `ClientExcelService`
  - Expanded responsibility: client template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, and required-column validation. Client/account dropdown reads are delegated to `ClientModel` and `ChartAccountModel`.
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: controller response output, DB schema change

- `ProjectExcelService`
  - Responsibility: project template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, and required-column validation. Client/employee dropdown reads are delegated to their domain Models.
  - Controllers: `ProjectController` via `ProjectService`
  - Out of scope: controller response output, DB schema change

- `ProjectService`
  - Responsibility: project query/save orchestration; the project search Picker is ordered by `system_projects.sort_no` before its limit is applied and preserves `sort_no` for the common Picker contract. Payload normalization and business validation delegate to `ProjectPayloadService`, client/employee resolution delegates to `ProjectReferenceResolver`, trash lifecycle delegates to `ProjectTrashService`, and Excel delegates to `ProjectExcelService`.
  - Controllers: `ProjectController`
  - Out of scope: direct HTML rendering, controller response output

- `ProjectPayloadService`
  - Responsibility: project request normalization, nullable-field normalization, and Korean business validation; `project_name` remains an invariant business-required field
  - Consumers: `ProjectService`, `ProjectExcelService` through the normal save path
  - Out of scope: DB persistence, user-specific Table Setting storage, controller response output

- `ProjectTrashService`
  - Responsibility: project soft delete, trash list, restore, ordering, and dependency-guarded partial-success purge
  - Purge policy: `ProjectDependencyRepository` checks current physical and logical ID references immediately before hard delete; referenced projects remain in trash
  - Consumers: `ProjectService`
  - Out of scope: controller response output, schema mutation

- `BankAccountService`
  - Responsibility: bank-account CRUD, explicit business validation, reorder, Excel orchestration, commit-safe bank-copy lifecycle, and dependency-guarded partial-success purge
  - Repository: `BankAccountDependencyRepository` checks cards, client default accounts, payment schedules, evidence bodies, transactions, voucher summaries, and polymorphic voucher-line account references immediately before hard delete; referenced accounts remain in trash
  - File policy: new upload is compensating-deleted when DB save fails; replaced or purged files are deleted only after DB commit
  - Controllers: `BankAccountController`
  - Out of scope: direct HTML rendering, controller response output, runtime schema mutation

- `BrandService`
  - Responsibility: brand asset list/detail/save/status/purge orchestration for the settings brand page
  - Controllers: `BrandController`
  - Out of scope: direct HTML rendering, controller response output

- `CoverService`
  - Responsibility: cover list/public-list/save/delete/trash/reorder orchestration for `cover` domain, with legacy `CoverImageService` isolated as compatibility wrapper
  - Controllers: `CoverController`, `AboutController`
  - Out of scope: direct HTML rendering, controller response output

- `WorkTeamService`
  - Responsibility: work-team list/detail/save/delete/trash/reorder/excel orchestration, shared modal/Excel save validation, and transaction-safe dependency-guarded partial-success purge; team-leader and Excel dropdown reads are delegated to `ClientModel`
  - Repository: `WorkTeamDependencyRepository` checks member, evidence, transaction, and polymorphic voucher-line TEAM references by team ID immediately before hard delete; referenced teams remain in trash
  - Purge policy: only rows with `deleted_at IS NOT NULL` can be hard-deleted; bulk purge classifies blocked rows before deleting eligible rows in one transaction
  - Controllers: `WorkTeamController`
  - Out of scope: direct HTML rendering, controller response output

- `CardService`
  - Responsibility: card list/detail/save/delete/trash/reorder/excel orchestration, DB-column-aligned business validation, commit-safe card-image lifecycle, and dependency-guarded partial-success purge; client and bank-account dropdown reads are delegated to their Models
  - Repository: `CardDependencyRepository` checks evidence bodies, transactions, voucher summaries, and polymorphic voucher-line card references by card ID immediately before hard delete; referenced cards remain in trash
  - File policy: new uploads are compensating-deleted when DB save fails; replaced, explicitly removed, or purged images are deleted only after DB commit
  - Controllers: `CardController`
  - Out of scope: direct SQL, card-number logging, controller response output

- `ProjectReferenceResolver`
  - Responsibility: preserve client/employee name normalization and nullable resolution contracts used by project import while delegating DB lookup to `ClientModel` and `EmployeeModel`
  - Consumers: `ProjectService`
  - Out of scope: direct PDO/SQL, project persistence, generic master-data lookup

- `EmployeeService`
  - Responsibility: employee list/detail/search/save/status/delete/reorder orchestration across employee SSOT `user_employees` and login-account SSOT `auth_users`, including representative qualification registration/update/delete, qualification/education count summaries, and server-side enforcement of the current user's employee Table Setting required-field policy. The employee search Picker is ordered by `user_employees.sort_no` before its limit is applied and preserves both `sort_no` and `position_name`, so contract and personnel consumers retain the employee screen order and can display the selected employee's current position without creating another position SSOT. The representative qualification data remains in `institution_qualifications_employee_records`; `user_employees` stores only its connection ID. Employees are referenced by `employee_id`; the Service does not resolve, validate, or persist a client link because `system_clients` is an independent client SSOT.
  - Controllers: `EmployeeController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `employee`; runtime compatibility alias remains `employees`

- `DepartmentService`
  - Responsibility: department list/detail/save/reorder orchestration, DB-bound and user Table Setting required-field validation, selectable `auth_users.id` manager validation, and guarded hard delete through `DepartmentDependencyRepository`. A department referenced by current employees, assignment histories, personnel-action changes, or employment-rule ownership cannot be deleted; organizational closure uses `is_active = 0`.
  - Controllers: `DepartmentController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash
  - Domain note: canonical organization domain is `department`; runtime compatibility aliases remain `departments` and `dept`

- `PositionService`
  - Responsibility: integrated position/title master list/detail/save/reorder orchestration, DB-bound and user Table Setting required-field validation, and guarded hard delete through `PositionDependencyRepository`. A row referenced by current employees, position histories, or personnel-action changes cannot be deleted; discontinued use is represented by `is_active = 0`.
  - Controllers: `PositionController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash, independent grade/title domain design
  - Domain note: canonical organization domain is `position`; runtime compatibility aliases remain `positions` and `positions_modal`

- `RoleService`
  - Responsibility: `auth_roles` list/detail/save/reorder, protected-role policy, Table Setting required-field validation, dependency-guarded transactional hard delete of role permissions and the role
  - Controllers: `RoleController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash
  - Domain note: canonical organization domain is `role`; `super_admin` and `admin` are protected, and runtime compatibility is limited to the plural URL redirect

- `EvidenceReferenceResolverService`
  - Responsibility: base reference ID/name resolution, bank account lookup, voucher ref lookup
  - Controllers: `ImportController`
  - Out of scope: payload wrapper, readiness, voucher policy, transaction orchestration
- `EvidenceTransactionContextService`
  - Responsibility: upload transaction context resolution, transaction type mapping
  - Controllers: `ImportController`
  - Out of scope: readiness, rule engine, payload merge, reference resolution
- `EvidenceRuleEngineService`
  - Responsibility: business-required readiness evaluation, system readiness evaluation, readiness result assembly, transaction create error formatting
  - Controllers: `ImportController`
  - Out of scope: reference resolution, transaction context resolution, payload normalization
- `EvidenceTypePolicyService`
  - Responsibility: evidence import/source type normalization, legacy type alias policy, upload/business data type allow policy, transaction direction policy, manual tax invoice type detection, upload business unit policy
  - Controllers: `EvidenceController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceStatusController`, `EvidenceUploadController`
  - Out of scope: page ready/planned rollout policy, field/meta domain policy, excel manager domain policy, field alias/display policy, modal preset policy, processing plan policy, upload save, voucher create, DB schema change
- `SystemFieldService`
  - Responsibility: body-table physical column discovery, data-type to target-table resolution, source field option generation, field ordering/default visibility/default required policy generation, field-group metadata assembly for table setting, excel upload/download, and modal rendering preparation
  - Controllers: `EvidenceImportController`, `EvidenceUploadController`
  - Out of scope: import/source type normalization, runtime page-ready policy, processing plan, badge/color/icon UI policy, DB schema change
- The deprecated processing-policy compatibility service was removed after Body reads moved to `EvidenceBodyStatusProjectionModel` and `body.evidence_status`.
  - Responsibility: legacy compatibility boundary while generation-processing state is retired; Body reads must derive transaction/voucher linkage from `ledger_evidence_links` and must not restore a separate processing table.
  - Controllers: none directly; used by `EvidenceGenerationService`
  - Out of scope: processing-state persistence, import/source type normalization, field/meta generation, upload/save/delete orchestration, and DB schema change
- `EvidenceExternalKeyService`
  - Responsibility: canonical external-source normalization and deterministic `external_key` generation for every evidence upload type. It uses stable provider identifiers first and otherwise hashes an ordered allowlist of raw source fields; ERP IDs, sort/order, file metadata, Actor, processing state, links, and corrected business references are excluded.
  - Controllers: none; used by `EvidenceUploadService`
  - Out of scope: DB reads/writes, duplicate decisions, upload counters, backfill, and UI messages- `EvidenceUploadService`
  - Expanded responsibility: upload runtime/cancel/trace, preview session store/load/clear, file column validation/header-only read, deterministic external-key build through `EvidenceExternalKeyService`, duplicate annotation, required-missing summary/confirmation, upload source key policy, preview confirm orchestration, upload file path orchestration, trace payload build, validation response build, preview confirm response build, chunk upload progress/result build. Duplicate and batch reads delegate to `EvidenceBodyStorageModel` over the type-specific Evidence Body SSOT.
  - Controllers: `ImportController`
  - Out of scope: transaction create, voucher create, DB schema change
- `EvidenceUploadParserService`
  - Responsibility: spreadsheet load, upload row parse, bank workbook parse, upload header/column mapping, upload payload key resolve
  - Controllers: `ImportController`
  - Out of scope: upload batch save, preview validation, transaction create, voucher create
- `EvidenceBatchSaveService`
  - Responsibility: upload batch save helper, Excel-upload validation result handling, new Evidence default `evidence_status=CORRECTION_REQUIRED`, duplicate/deleted-duplicate/conflict counters and details, payload build, persist parameter assembly, per-Body `sort_no` allocation, and chunk commit. TableSettings requirement policy never determines company-wide Evidence status.
  - Controllers: `ImportController`
  - Out of scope: batch upsert orchestration entrypoint, transaction boundary, bank side effect, transaction create, voucher create, DB schema change
- `VoucherPolicyService`
  - Responsibility: voucher ref policy lookup, voucher ref type/account normalization, evidence ref apply, required ref validation, ledger account resolve
  - Controllers: `ImportController`
  - Out of scope: voucher create orchestration, bundled voucher create, learning, link/update side effects
- `VoucherCreateService`
  - Responsibility: bank voucher create orchestration, existing voucher check, voucher line build, voucher link/status update
  - Controllers: `ImportController`
  - Out of scope: bundled voucher create, voucher policy lookup, learning helper, DB schema change
- `EvidenceTemplateService`
  - Expanded responsibility: template download orchestration, template sheet fill, sample generation, and selected-column header rendering for evidence source templates.
  - Controllers: `EvidenceImportController`
  - Out of scope: dropdown option/query lookup, evidence type field SSOT decisions, upload parse/save, DB schema change
- `EvidenceDownloadService`
  - Expanded responsibility: evidence source type spreadsheet download generation, selected-column filtering, and DB column header rendering based on `information_schema.COLUMNS`
  - Controllers: `EvidenceDownloadController`
  - Out of scope: page rendering, DB schema change, controller response output
- `EvidenceTemplateDropdownService`
  - Responsibility: template dropdown build/apply, dropdown option lookup, bank template dropdown composition
  - Controllers: `ImportController`
  - Out of scope: template file download/write, format mapping/query, upload save, DB schema change
- `EvidenceFormatMappingService`
  - Responsibility: format lookup, format column lookup, legacy/canonical system field mapping, template field grouping
  - Controllers: `ImportController`
  - Out of scope: template file generation, dropdown apply, upload parse/save, DB schema change
- `EvidenceBusinessRefService`
  - Responsibility: business reference resolve helper, payload business reference normalize, business reference candidate extraction
  - Controllers: `ImportController`
  - Out of scope: client sync, bank helper, DB schema change
- `EvidenceClientSyncService`
  - Responsibility: import client sync, tax invoice client sync, client upsert/update helper, import party normalization
  - Controllers: `ImportController`
  - Out of scope: business reference helper, bank helper, DB schema change
- `EvidenceBankHelperService`
  - Responsibility: bank payload normalize, bank evidence sync, bank transaction upsert, bank voucher line validation helper
  - Controllers: `ImportController`
  - Out of scope: evidence status helper, separate payment-domain orchestration, DB schema change
- `EvidenceStatusHelperService`
  - Responsibility: evidence status/readiness interpretation and canonical `(import_type, evidence_id)` active-link detection through `EvidenceLinkModel`
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, sort helper, DB schema change
- `VoucherQueryService`
  - Responsibility: voucher list/detail/trash/picker result assembly, `전표검토·전기` server-side page/count orchestration, direct `linked_evidences` reads, and reversal-only read-only `original_linked_evidences` projection through `reversal_of`
  - Controllers: `VoucherController`
  - Out of scope: HTTP handling, voucher state transitions, persistence orchestration, direct SQL, DB schema change

- `EvidencePayloadHelperService`
  - Responsibility: evidence payload scalar normalization, storage JSON encode helper, seed row id extraction, evidence total amount calculation, blank value detection
  - Controllers: `ImportController`
  - Out of scope: link helper, sort helper, DB schema change
- `EvidenceDeleteRestoreService`
  - Responsibility: 자료유형별 Evidence Body의 소프트삭제·복구 DB 변경을 담당한다. 처리상태 테이블이나 링크 삭제는 수행하지 않는다.
  - Responsibility: evidence soft delete helper, evidence restore helper, evidence body delete/restore helper
  - Controllers: `ImportController`
  - Out of scope: evidence lifecycle purge orchestration, bundled voucher, upload save, DB schema change
- `EvidenceLifecycleService`
  - Responsibility: 삭제된 Body의 영구삭제를 조정하며 canonical `(import_type, evidence_id)` 링크와 분할 계보가 없을 때만 수행한다.
  - Logging: 요청 건수, 실제 영구삭제 건수, 자료유형을 `service-ledger-evidence-lifecycle` 채널의 단일 경계 로그로 기록한다.
  - Responsibility: evidence purge lifecycle, evidence processing delete lifecycle, bank transaction sync lifecycle, evidence hard delete lifecycle
  - Controllers: `ImportController`
  - Out of scope: bundled voucher, upload save, template/format, DB schema change
- `EvidenceUploadValidationService`
  - Responsibility: upload row enrichment, upload amount normalization, preview validation, business/project validation, upload validation error assertion
  - Controllers: `ImportController`
  - Out of scope: upload batch save, parser, transaction create, voucher create, DB schema change
- `EvidenceSummarySearchService`
  - Responsibility: evidence summary text search, evidence summary ranking, `mapped_payload_json` decode/search helper
  - Controllers: `EvidenceController`
  - Out of scope: request/response output, page rendering, upload save, DB schema change
- `EvidenceUploadPersistService`
- Responsibility: upload batch persistence entrypoint, type-specific Body Model-call orchestration, upload transaction boundary, file-internal/DB/concurrent duplicate skipping, and batch result assembly. Existing rows are never updated by upload when `external_key` already exists. Upload acceptance remains governed by `EXCEL_UPLOAD`, while the persisted Body `evidence_status` is calculated independently from the current evidence-type TABLE requirement policy. SQL execution delegates to `EvidenceBodyStorageModel`, `EvidenceSchemaModel`, and the type-specific Evidence Models on the same PDO transaction connection; removed integrated-source and generation-processing tables are not fallback targets.
- P1 Source trace: 신규 추적컬럼이 존재하면 Canonical EvidenceType, `EXTERNAL_FILE_IMPORT`, 원천시스템·기관, 외부키, Batch와 원 행 번호를 기존 Envelope와 함께 dual-write한다. 영속 File SSOT가 없는 경우 `source_file_id`를 임의 생성하지 않는다.
  - Controllers: `EvidenceUploadController`
  - Out of scope: upload file parse, preview validation, request payload collection, DB schema change
- `EvidenceLinkHelperService`
  - Responsibility: `EvidenceLinkModel`을 통해 canonical 증빙 Identity의 링크 존재 확인과 영구삭제 의존성 정리를 제공한다. 소프트삭제 시 링크는 유지한다.
  - Responsibility: evidence link purge helper, evidence source reference detach, evidence link soft-delete/delete helper
  - Controllers: `ImportController`
  - Out of scope: upload helper, transaction helper, voucher helper, DB schema change
- `EvidenceSortHelperService`
  - Responsibility: per-Body `sort_no` payload value helper and compatibility no-op for existing sort-column initialization calls
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, DB schema change
## Legacy Ledger Evidence Service Notes

This section preserves the older evidence-service split reference in readable form until the active service inventory above fully replaces it.

| Service | Responsibility | Used By | Out of Scope |
| --- | --- | --- | --- |
| EvidencePayloadService | Owns evidence payload read/save, payload normalization entrypoints, and payload-oriented lookup helpers. | `ImportController`, `EvidencePayloadController` | Readiness, transaction creation, voucher creation, and DB schema change |
| EvidenceStatusService | Owns final Body `evidence_status` (`COMPLETED`, `CORRECTION_REQUIRED`) changes and type-specific Body `sort_no` reorder. Linked evidence is protected from status mutation. 상태변경·정렬 성공은 요청/처리 건수와 Actor를 `service-ledger-evidence-status`에 기록한다. | `EvidenceStatusController` | Processing/generation state persistence, payload save orchestration, transaction/voucher creation, and DB schema change |
| EvidenceTrashService | Owns Body trash, restore, and purge orchestration. Soft delete retains `ledger_evidence_links`; linked evidence is blocked, and purge accepts deleted Body rows only. | `EvidenceLifecycleController` | Upload parse/save, processing-state persistence, transaction/voucher creation, and DB schema change |
| EvidenceUploadService | Owns upload flow orchestration, parsed row handling, and upload batch save coordination. | `ImportController`, `EvidenceUploadController` | Transaction creation, voucher creation, and DB schema change |
| EvidenceGenerationService | Owns evidence-original runtime read orchestration, body-row assembly, and shared Actor display-name enrichment (`created_by_name`, `updated_by_name`, `deleted_by_name`). Transaction/voucher linkage is projected only from `ledger_evidence_links`. | `EvidenceListController`, `EvidenceLifecycleController` | Type policy, field meta, upload/save/delete orchestration, transaction/voucher creation, and DB schema change |
| EvidenceBodyReadService | Owns the canonical supported body-reader list and body-table read/count orchestration, including approved daily-employment-income and business-income-report evidence. The counter and paged list use the same immutable Body reader; business-income detail includes approved work-line and statutory calculation Raw Line projections. Concrete SQL access is delegated to domain-specific read models. | `EvidenceGenerationService` | Direct SQL ownership, type normalization policy, page policy, field meta policy, upload/save/delete flows, UI policy, and DB schema change |
| EvidenceSourceRepository | Metadata-allowlisted integrated evidence-source repository. Builds server-side DataTable identity counts, filtered counts and bounded page projections, validates requested projection/search/sort fields against actual source-table columns, and batch-loads body/reference/link data for the current page. The immutable daily-employment-income evidence projection supports reverse transaction-to-evidence lookup without mutating the approved source. | `EvidenceGenerationService`, evidence reference consumers | Type normalization policy, UI policy, writes, schema mutation, permission decisions |
| EvidencePayloadNormalizeService | Owns payload value normalization, field-level cleanup, and format-column-based payload normalization helpers. | `ImportController`, `EvidenceGenerationSaveService`, `EvidenceGenerationService`, `EvidenceUploadService` | Readiness, reference resolution, transaction helpers, and voucher orchestration |
| EvidenceRuleEngineService | Owns evidence required-field validation and evidence-state result assembly. | `ImportController`, `EvidenceGenerationService` | Reference resolution, transaction creation, and payload normalization |
| EvidenceTypePolicyService | Owns import/source type normalization, legacy alias resolution, upload/business allow lists, source/import query helper policy, transaction direction policy, manual tax invoice type detection, and the active evidence-type UI policy consumed by the original evidence editor. Approval-generated types such as `BUSINESS_INCOME_REPORT` declare their physical TableSettings meta domain and immutable read-only lifecycle here; controllers reuse that policy as a server-side mutation guard. | `EvidenceController`, `JournalController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceSaveController`, `EvidenceStatusController`, `EvidenceUploadController`, `EvidenceGenerationService`, `EvidenceStatusService`, `EvidenceTrashService` | Payload persistence, transaction/voucher creation, and DB schema change |
| SystemFieldService | Owns body-table physical-column metadata lookup, data-type target-table resolution, source-column option generation, field-group ordering/default visibility/default required policy assembly, and the field-meta baseline that table settings, excel upload/download, and detail modal can converge on. Runtime default field-meta generation must start from `targetTableForDataType() -> information_schema.COLUMNS`; transitional `mapped_payload_json` helpers remain classified as legacy and are not the default path. | `EvidenceImportController`, `EvidenceUploadController`, `EvidenceDownloadService`, `EvidenceListController`, `EvidenceLifecycleController`, `EvidenceSaveController` | Import/source type normalization, UI policy, and DB schema change |
| EvidenceGenerationSaveService | 기존 API/DI 명칭을 유지하는 Body 저장 진입점이다. 일반 모달 수정은 사용자가 선택한 `COMPLETED/CORRECTION_REQUIRED`만 저장하고 신규 Evidence가 상태를 명시하지 않으면 `CORRECTION_REQUIRED`를 사용한다. 사용자별 TableSettings 필수정책으로 상태를 계산하지 않는다. 활성 거래·전표에 연결된 Evidence 수정은 전체 요청을 차단한다. 급여 화면의 UI 자료유형 `PAYROLL`은 물리 Body·링크 식별자 `PAYROLL_REPORT`로 변환하되 생성 원천을 보존한다. 저장과 확인은 하나의 트랜잭션으로 처리한다. | `EvidenceSaveController` | 상태 자동판정, Generation Center 상태, transaction/voucher 생성, DB 직접 SQL |
| JournalCandidateEngineService | Owns journal candidate collection, canonical account-set merge, request-scope cache reuse, score calculation, deterministic ranking, balanced-line construction, and Top-N response assembly. P0 안전정책으로 공식 분개규칙 신호가 없는 후보는 반환하지 않으며 Legacy 최근패턴은 사용하지 않는다. It does not select a candidate for the user or write learning data. | Voucher evidence recommendation flow | Candidate-source SQL ownership, learning writes, voucher persistence, workflow state, and UI candidate selection |
| VoucherEvidenceRecommendationService | Builds canonical recommendation contexts from all evidence currently linked in the voucher modal, recognizes a uniquely matching DATA/FUND pair, invokes `JournalCandidateEngineService`, and assembles selectable voucher-level recommendation sets only when every Line has an account and the combined debit/credit totals balance. It returns explicit applicability, balance, difference, and unresolved-Line metadata. For every recommended account it reads `ledger_accounts_sub` policy and maps unambiguous Evidence SSOT base references into canonical `line.refs[]`. It never applies a set or persists temporary selection. | `VoucherController::apiEvidenceRecommendations()` | Journal rule CRUD, user candidate selection, voucher persistence, evidence-link persistence, and temporary UI state |
| JournalRuleCandidateProvider | Produces unscored debit/credit/VAT account-set candidates from active `ledger_journal_rules` rows matching the current business context. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalClientPatternCandidateProvider | Produces unscored debit/credit candidates from the logical client's existing direction/line patterns and, when configured, combines the client master `default_account_id` with recent opposite-side accounts. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalRecentPatternCandidateProvider | P0 안전정책에 따라 공식 POSTED 근거와 증빙유형 격리를 보장하지 못하는 Legacy 최근패턴을 추천하지 않는 차단 Provider다. 역할 기반 최근분개 정책 승인 전까지 항상 빈 후보를 반환한다. | `JournalCandidateEngineService` | 최근분개 신규정책, candidate merge, scoring, learning writes, and persistence |
| JournalLearningCandidateProvider | Produces unscored final-account candidates only from `POSTED_CONFIRMATION` events whose source voucher is non-reversal, non-deleted, and `POSTED` or `CLOSED`; it cannot originate a final candidate without an official journal-rule signal. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalLearningFeedbackService | 최초 POSTED 전표의 확정 라인을 서버 SSOT에서 재구성해 멱등 Event로 기록하고, Event 전체 aggregate로 Recent/Client Projection을 deterministic 재계산한다. 단일 Evidence Context 또는 공통 Line Ref가 명확할 때만 Context Projection을 만든다. | `VoucherService` POSTED transition, rollback 검증 tool | 추천 GET 쓰기, Rule usage/confidence, SYSTEM Rule 생성, 거래↔전표 직접 연결 |
| JournalLearningFeedbackRepository | `ledger_journal_learning_events` Event insert/duplicate 판정과 Recent/Client Projection upsert SQL을 소유한다. | `JournalLearningFeedbackService` | Context 판정, workflow, 사용자 응답, Rule usage |
| EvidenceBodyWriteService | Owns metadata-compatible evidence Body payload mapping, Body Model dispatch, persistence verification, and physical-column filtering through `information_schema.COLUMNS`. It dispatches both `PAYROLL` and `PAYROLL_REPORT` to `ledger_evidence_salary_report`, preserving the approval source while storing the canonical payroll-report import type. It enforces server-managed canonical identity columns for manual tax invoices (`source_type=MANUAL`, `import_type=TAX_INVOICE_MANUAL`) for both modal and Excel-upload writes, regardless of client payload values. It does not expose a legacy synchronization API or persist a second evidence SSOT. | `EvidenceGenerationSaveService`, `EvidenceUploadPersistService` | Evidence workflow state, recommendation state, transaction/voucher creation, and controller request handling |
| VoucherCreateService | 은행 증빙 전표라인을 정규화하고 기존 전표·중복 지문을 Model로 확인한 뒤 `VoucherService` 저장 흐름을 호출한다. 기존 전표 자동연결은 canonical `(import_type, evidence_id)`로 `EvidenceLinkModel`에 위임한다. | Evidence import/save controllers | SQL 실행, 범용 링크 저장, processing 상태 |

## Legacy Service Principles

- Controllers collect requests, delegate orchestration to services, and return responses.
- Shared helpers should stay focused on reusable domain behavior rather than controller callbacks.
- Each service should own one business flow clearly enough that state transitions and side effects remain traceable.
- If a service split changes architecture decisions, record the reason in `DecisionLog.md`.
## 2026-06-27 Addendum

| Service | Responsibility | Used By | Notes |
| --- | --- | --- | --- |
| VoucherService | 전표·분개·보조계정·증빙링크·Line Source Ref 저장과 상태 workflow 및 소프트삭제·복원을 조정한다. 추천 Line 저장 시 Source identity와 Rule Revision을 함께 검증하고 한 트랜잭션으로 저장한다. 취소전표는 Header·Line·Line Ref와 `reversal_of` 및 원 Source Ref를 가리키는 REVERSAL Ref를 생성하되 원전표 Evidence 링크는 복제하지 않는다. | VoucherController, VoucherCreateService callbacks | 거래 직접 FK 조회 및 동일 증빙의 거래 연결 강제 금지. `linked_evidences[]`, `line.refs[]`, `line.source_refs[]`가 runtime 계약이다. |
| VoucherPostingValidationService | POSTED 직전 전표 라인의 계정 사용 가능 여부, Type별 Ref Master, 연결 Evidence Body 유효성을 Set 기반으로 재검증한다. Evidence 0건과 거래 직접 Link 0건은 허용한다. | VoucherService REVIEWED→POSTED transition | 상태전이·후속처리는 담당하지 않으며 Posting readiness만 검증한다. |
| VoucherPurgeService | 단건·선택·전체 전표 영구삭제의 트랜잭션을 소유한다. 전달받은 ID 전체가 실제 휴지통 상태인지 재검증하고 ref→line→VOUCHER link→header 순서로 삭제하며, 하나라도 실패하면 전체 롤백한다. | VoucherController | Controller 트랜잭션, 중첩 트랜잭션, 소프트삭제·복원, 거래 영구삭제 |
| VoucherStatus | Defines the canonical voucher workflow values, transitions, normalization, editable/locked groups, picker values, and the review-list status set `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`. | VoucherController, VoucherService, VoucherModel, VoucherReviewQueryModel | Keeps status filtering and workflow decisions on the shared status SSOT; `CLOSED` is reserved for the future accounting-close policy and is excluded from the current review list. |
| VoucherLineRefService | Owns voucher-line ref replace, detail hydration, and validation-shape reconstruction for voucher `line.refs[]` using `ledger_voucher_line_refs`. | VoucherController, VoucherService | Keeps voucher controller/service code focused on voucher orchestration while centralizing voucher multi-ref read/write mapping in one voucher-only service. |
| VoucherNumberService | 전표번호 형식·수정 가능 여부·중복 방지 정책과 트랜잭션을 소유하며 조회·중복검사·번호 갱신은 `VoucherModel`에 위임한다. | VoucherController | 현행 날짜별 `MAX 성격의 최신번호 + 1` 방식과 반환 계약을 유지하며 별도 번호이력 테이블은 사용하지 않는다. |
| VoucherPolicyService | 증빙 기반 라인 보조계정 보강과 필수 `ref_target` 정책을 판정한다. 계정과 정책 조회는 `ChartAccountModel`, `SubAccountPolicyModel`에 위임한다. | Evidence import/save controllers | SQL 실행, `ref_type` fallback, 업무 데이터 저장 |
| TransactionCrudService | Owns transaction validation and atomic header/item/settlement/evidence-link orchestration, file flow, active transaction list ordering, trash/restore/purge policy, and header total recomputation. User CRUD may save and revise both `draft` and `completed`; only `closed` and `cancelled` block modification, while soft delete remains limited to `draft`. It joins an existing PDO transaction without committing or rolling it back, allowing final approval to atomically include the transaction flow. `TransactionModel`, `TransactionItemModel`, `TransactionSettlementModel`, and `EvidenceLinkModel` own every SQL operation. List ordering validates unique transaction IDs and positive `sort_no` values, then updates active rows transactionally. Soft delete and restore retain evidence links; purge accepts deleted transactions only and removes items, settlements, TRANSACTION target links, and the header in one transaction. Application conflict checks are backed by the DB conditional unique key for active TRANSACTION evidence, and DB duplicate races are normalized to `이미 다른 거래에 연결된 증빙입니다.`. Settlement ownership is backed by the header FK cascade. | TransactionController, VoucherController | Transaction evidence identity uses the Evidence Body ID; the transaction header has no evidence identity column, and transaction-to-voucher direct linking is outside this service. |
| RegularEmploymentIncomeAccountingGenerationService | 상용근로소득 FINAL_APPROVAL의 직원별 급여 증빙·거래 생성 단일 책임 Service다. 저장된 불변 급여 Line과 합계, 직원별 Evidence·직원 거래·원본추적 Link 생성 가능성 및 중복을 Preflight한다. 통과한 계획만 바깥 PDO Transaction에서 Item별 `CORRECTION_REQUIRED` Evidence와 1:1 직원 Transaction·Item·Settlement, `PAYROLL_REPORT_EVIDENCE`·`EMPLOYEE_PAYROLL` Registry로 생성한다. 기관 발생거래·지급예정·전표·분개는 생성하지 않는다. 급여 증빙 조회는 원본 문서제목·귀속연월, 결재요청 순번, 직원별 급여 Item명, 직원명과 공용 Actor 표시명을 FK의 사용자 표시값으로 제공한다. | RegularEmploymentIncomeService | 결재상태 전환과 Transaction commit은 소유하지 않는다. 사용자부담은 급여 Line과 직원 Evidence 합계가 보존하고 후속 전표추천이 집계한다. |
| EvidenceWorkflowPolicyService | Evidence 상태와 사용 목적을 분리하는 공용 정책 SSOT다. 런타임 상태는 `CORRECTION_REQUIRED`·`COMPLETED` 두 개뿐이다. `SOURCE_TRACE`는 두 상태 모두 허용하고, `ACCOUNTING_READY` 및 전표 후보는 `COMPLETED`만 허용한다. | TransactionCrudService, EvidenceStatusService, Voucher Service 계열 | Evidence 저장, Link 저장, 전표 생성 자체는 담당하지 않는다. |
| TransactionEvidenceReferenceService | Projects transaction-connectable `DATA`/`BOTH` Evidence SSOT rows for selection and detail restoration, including the common evidence-selection TableSettings display projection (source/type/status, business classification, employee/client/project/account/card/team names, summary, amount and timestamps), and converts evidence policy semantics into independently applicable business-classification, transaction-overview, one-unit pretax item, and directional settlement recommendation candidates. Settlement candidates must resolve to an active `SETTLEMENT_TYPE` code; their descriptions use the evidence policy `DESCRIPTION` value rather than physical source-column names. Each line candidate carries the canonical `(import_type, evidence_id)` source identity; conflicting scalar candidates remain selectable review data and are never chosen automatically. | TransactionController, TransactionCrudService, transaction evidence-selection DataTable, transaction recommendation card | Does not apply recommendations, save transactions or links, expose `FUND` evidence, or own evidence validation, recommendation-history persistence, and metadata persistence. |
| TransactionReferenceValidatorService | 거래 및 전표 Posting의 Master ID와 코드 필드를 SSOT 기준으로 검증한다. `validateGroupedIds()`는 Ref Type별 ID Set을 한 번의 Master 조회로 검증한다. 일반 거래의 직원 참조는 현재 ACTIVE 정책을 유지하고, 승인된 상용근로소득 Projection은 명시적 `REGULAR_EMPLOYMENT_INCOME_EFFECTIVE_SNAPSHOT` Context로 원천 문서·직원행·승인 계약·귀속기간을 함께 검증한다. | TransactionCrudService, VoucherPostingValidationService | 거래처·프로젝트·직원·계좌·카드·팀의 공용 서버 검증 책임이다. 과거 급여 검증도 검증 생략이 아니라 등록된 원천 정책을 사용한다. |
| EvidenceMetadataService | Owns the Evidence Metadata Registry and Semantic Mapping contract: header validation and CRUD, read-only health projection, centralized delete/purge reference guards, atomic restore conflict checks, transactional row ordering, schema-based recommendations, physical-column validation, duplicate prevention, and Actor enrichment. The controlled basis policy includes `BASE_DATE`, `DESCRIPTION`, amount meanings, and repeatable `ADJUST_AMOUNT`. | EvidenceMetadataController | Metadata supplies the body location, DATA/FUND/BOTH usage area, and semantic values; it does not select transaction handlers or journal accounts. Active runtime types and rows referenced by Evidence Body or `ledger_evidence_links` cannot be deleted. Header and detail saves are transactional. |
| UserSettingService | Owns per-user page setting detail/save/delete orchestration for `system_user_settings`, including page-key and setting-type validation, current-user scoping, `exists` response metadata, and DB-backed persistence for `TABLE`, `VIEW`, `EXCEL_UPLOAD`, and `EXCEL_DOWNLOAD`. The service stays storage-type agnostic while the shared client bridge decides when a single UI state should be split across separate `TABLE` and `VIEW` rows. | UserSettingController | Keeps browser-storage replacement logic out of page JS while leaving screen-specific setting-key selection to common client modules. |

## Personal expense approval

- `ApprovalInboxService`
  - Responsibility: authenticated-user approval-box classification, authorized document detail assembly, current-request and immutable resubmission-history projection, actionable-step policy, mandatory rejection reason validation, and document-type action delegation. `recordsTotal` is the box/user scope before keyword search and `recordsFiltered` is the same scope after search. Count uses a display-independent access projection, while history loads every related request step once and groups it by `request_id`.
  - Controller: `ApprovalInboxController`
  - Model: `ApprovalInboxModel`
  - Summary projection: `ApprovalDocumentSummaryResolver`가 목록 조회 결과를 문서유형별로 일괄 Resolve한다. 상용근로소득은 Header 총지급액, 귀속월 말일과 Item 정렬순서 기반 대표 직원을 사용한다. 일용근로소득은 Header 총지급액·귀속월·작업자별 최종 근무일과 Group·Item 정렬순서 기반 대표 작업자를 일괄 투영한다. 행별 조회와 별도 결재요청 Snapshot은 만들지 않는다.
  - Amount projection: 근로계약 문서의 공용 `total_amount`는 `institution_employment_contracts_components` 활성 행의 `SUM(amount)`를 사전 집계한 월 지급합계다. 결재단계 JOIN과 분리하여 행 증폭을 방지하고 결재함 전체 구분에서 동일 projection을 사용한다.
  - Current document adapter: `PERSONAL_EXPENSE` delegates mutations to `PersonalExpenseApprovalService`; future document types add an adapter without duplicating request/step workflow.
  - 역할 공동결재 단계는 활성·승인·재직 상태이며 활성 역할을 가진 사용자에게만 노출하고, 처리 시 원자적 조건 갱신으로 동시 승인을 한 명으로 제한한다.
- `PersonalExpenseService`
  - 상세 Item에 승인 원본분류와 최신 정정 Revision 기반 유효 회계분류 Projection을 병합한다. Evidence raw 분류는 원본 재현용이며 추천의 SSOT로 사용하지 않는다.
- `PersonalExpenseClassificationCorrectionService`
  - 최종승인 문서·Item·Evidence·분류코드·금액을 잠금 검증하고 일괄 정정 Revision을 원자적으로 추가한다. 동일 요청키·동일 Payload는 기존 결과를 반환하며 다른 Payload 재사용은 차단한다.
- `PersonalExpenseClassificationProjectionService`
  - 최신 정정 Revision을 우선하고 없으면 승인 Item 분류를 사용하는 유효분류 ReadModel을 제공하며 처리 Actor 표시명은 `ActorHelper` 공용 Projection을 사용한다.
  - Responsibility: validates and saves employee-owned personal-expense headers/items and recalculates the four stored header aggregates from active items in the same transaction after every supported item mutation. Submission validation locks active items, recalculates the header, requires at least one active item, and verifies persisted values before workflow processing. Personal-expense list rows also receive the status-aware virtual approval projection (`approval_stage_name`, `approval_actor_name`, `approval_actor_type`, `approval_action_result`): only pending/in-progress work exposes the actual current step name, all draft and terminal states expose `-`, pending work uses the assigned approver/role, approved or rejected work uses the latest actual action, and withdrawn/cancelled work uses the request actor and timestamp without falling back to a stale approver. Detail timelines separately project scheduled users for waiting/pending steps, actual `acted_by` users for approved/rejected steps, no actor fallback for cancelled steps, and request-level withdrawal/cancellation actors and timestamps.
  - Responsibility: current-login employee resolution, header-based owner-scoped search/list/detail, atomic header and multi-item create/update/delete synchronization, owner-scoped trash listing and restore, guarded transactional item-first permanent deletion, server-side amount recalculation, active code resolution, employee-scoped header sequence allocation and row reordering, and document-local item ordering.
  - Controller: `PersonalExpenseController`
  - Models: `PersonalExpenseModel`, `PersonalExpenseItemModel`, `EmployeeModel`, `ProjectModel`, `CodeModel`
  - Performance: 일반 목록은 서버 페이징 최대 500건으로 제한하고 필터 건수는 헤더 테이블만 조회한다. 결재 처리자 상관 조회를 윈도 함수 파생테이블로 바꾸는 안은 운영 데이터 실측에서 더 느려 기존 요청별 인덱스 조회를 유지한다. 휴지통 아이템은 `listForHeaders()` 한 번으로 일괄 조회한 뒤 신청서 ID로 그룹화한다.
  - Delete policy: soft delete is limited to draft/rejected/withdrawn current requests. Purge requires a trashed header, no pending/in-progress/approved approval history, no generated evidence reference, and deletes all items before the header while preserving non-final approval audit rows.
- `PersonalExpenseApprovalService`
  - 개인경비 신청서 상신과 결재 상태 전환을 담당한다. 최종승인 시 신청 아이템마다 증빙원본 1건, 거래헤더 1건, 거래내역 1건, 필요 시 VAT 정산 1건과 증빙–거래 링크 1건을 동일 DB 트랜잭션에서 생성한다.
  - Responsibility: active `PERSONAL_EXPENSE` template resolution, immutable header-level request/step snapshot creation, header status-projection and current-request-pointer synchronization, approver resolution, locked approve/reject/withdraw transitions, resubmission history preservation, and atomic final approval that creates one evidence and one independent transaction per item with merchant-client updates and shared evidence links.
  - Controllers: `PersonalExpenseController`, `ApprovalInboxController` through `ApprovalInboxService`
  - Final approval: creates one evidence and one independent transaction with one transaction item per application item, resolves external keys through `EvidenceExternalKeyService`, resolves new merchants through `EvidenceClientSyncService`, and links each evidence to its transaction without creating a voucher.
  - SSOT: `approval_personal_expenses`, `approval_personal_expense_items`, `user_approval_requests`, `user_approval_request_steps`, `ledger_evidence_employee_personal_expense`, `ledger_evidence_links`

## Employment contract approval

- `ApprovalWorkflowService`
  - Responsibility: 문서유형 공통 결재템플릿 해석, 불변 요청·단계 스냅샷 생성, 지정결재자·역할 적격성 검증, 승인·반려·회수 및 다음 단계 활성화를 담당한다.
  - Logging: 결재요청 생성, 단계 승인·반려·진행, 회수의 최종 성공 결과를 `service-approval-workflow` 채널에 문서·요청·단계 식별자와 함께 한 번 기록한다.
  - SSOT: `user_approval_requests`, `user_approval_request_steps`
  - Out of scope: 업무문서 검증·상태 projection·최종승인 후속처리
  - Transaction contract: Workflow는 독립 트랜잭션을 소유하지 않는다. Adapter의 승인·반려 callback과 최종승인 후속처리는 반드시 도메인 Service가 소유한 동일 트랜잭션에서 실행한다.
- `ApprovalDocumentAdapterRegistry`
  - Responsibility: 결재함의 문서유형별 상세 projection과 mutation 위임 경계를 등록하고 표시명·상세 섹션·아이템/합계 필드·첨부 지원·최종승인 확인문으로 제한된 UI metadata를 제공한다.
  - Adapters: `PersonalExpenseApprovalAdapter`, `EmploymentContractApprovalAdapter`, `PersonnelActionApprovalAdapter`, `LeaveApprovalAdapter`, `EmploymentRuleApprovalAdapter`, `RegularEmploymentIncomeApprovalAdapter`, `DailyEmploymentIncomeApprovalAdapter`
  - Rule: 결재함에 문서유형 하드코딩 분기를 추가하지 않고 Adapter를 등록한다.
- `EmploymentContractService`
- `PayComponentService`: `institution_employment_contracts_pay_components`의 적용일 기준 활성 지급항목을 `sort_no ASC, component_name ASC, id ASC`로 조회하고, 근로계약·상용근로소득에 동일 옵션 Projection과 서버 과세정책 판정을 제공한다. 근로계약 지급조건의 급여항목 관리 모달에서 목록·상세·신규·수정·소프트삭제와 선택행 순서변경을 처리하며 신규 표시순서는 서버가 마지막 순서 다음 값으로 자동 부여하고, 순서변경은 전체 대상의 중복 없는 `sort_no`를 트랜잭션으로 저장한다. 코드·유효기간·계산/과세/임금정책도 서버에서 검증한다. 상용근로소득 증감행은 이 Service가 마스터 PK·유효기간·활성상태를 재검증한 뒤 코드·명칭·과세 Snapshot을 확정한다. `RegularEmploymentIncomePayLineService`는 일반 지급항목의 중복 증액을 차단하고 항목별 감액 잔액을 검증하며, `OTHER_PAY` 복수 증액과 과세 지급 잔액 범위의 기타 감액을 처리한다.
- `EmploymentContractService` 보험 적용계약: DRAFT에서는 고용보험·산재보험 상태 NULL을 허용하고 `EXCLUDED` 사유를 검증하며 결재요청 전 두 상태를 필수화한다. 별도 보험기간 대신 계약기간·승인·Revision 이력을 재사용한다.
- `DailyEmploymentIncomeService` 고용·산재 회사부담 정책: 본사·통신판매는 `BUSINESS_DIVISION_POLICY`로 자동 부담하고 건설은 Group의 `DAILY_GROUP_MANUAL_SETTING`으로 우리 회사 부담 여부를 선택한다. 설정사유 입력과 보험사업장 조회를 요구하지 않으며, 회사부담 상태·설정 출처를 계산 Snapshot에 보존하되 공식 ELIGIBILITY Resolver 판정으로 표시하지 않는다. 건설 Group이 미선택일 때만 `CONFIRMATION_REQUIRED`로 저장·상신을 차단한다.
- `DailyEmploymentIncomeApprovalAdapter`: 공용 결재함의 일용근로소득 상세를 `request_id`·신청번호·요청시각·문서제목·대표 작업자·사업구분 표시명과 Group×작업자 Item으로 정규화한다. 합계는 Item DOM 재합산이 아니라 Header의 지급액·원천징수·실지급액·회사부담 Snapshot Projection을 사용하며 승인·반려 처리는 `DailyEmploymentIncomeService`에 위임한다.
- `EmploymentContractFieldPolicyService`: 공용 TableSettings의 사용자별 사용컬럼명·정적 필수구분을 근로계약 저장 및 결재요청 Server Validation에 적용한다. 조건부 필수 업무정책은 `EmploymentContractService`가 stable code로 합성하며, 이 Service는 별도 설정 저장소를 만들지 않고 기존 `UserSettingService`의 TABLE 계약만 읽는다.
- `EmploymentContractStatutoryProjectionService`: 근로계약 Snapshot을 계약 시작일의 `StatutoryStandardResolver` 결과와 비교하는 읽기 전용 Projection이다. 최저임금 판정과 공식 Source를 제공하며 계약금액을 변경하지 않는다. SSOT가 없는 휴게·주 근로시간·연장근로·수습 기준은 `NOT_VERIFIABLE`로 반환하고, 현재 신규계약의 명백한 최저임금 미달만 결재요청 Guard에 제공한다.
  - Responsibility: 실제 코드관리 SSOT에 따른 근로계약 Validation, 명시적 목록 projection과 검색 전/후 건수, 공용 목록 순서변경, Modal 최초 개방 시 입력정책·통합 직위·직책·급여항목 option 조회, 계약 시작일 기준 `MINIMUM_WAGE` 법정기준의 읽기 전용 입력안내, 계약 헤더·요일별 소정근로 일정·계약 당시 지급조건의 원자적 저장·상세조회·작성중 수정, 결재상태 projection, 종료·해지, 소프트삭제·복원·안전한 완전삭제를 조정한다. 최저임금 안내는 기준단가를 자동 저장하거나 덮어쓰지 않는다. 계약 당시 직위·직책은 활성 `user_positions` 참조목록에서 선택한 명칭을 `job_title_snapshot`에 보존하며, 직원 현재값은 신규 작성 기본값일 뿐 저장 시 강제하지 않는다. 개정 초안은 승인 원본의 Snapshot을 계승한다. 계약기간은 `EMPLOYMENT_CONTRACT_PERIOD_TYPE`, 고용구분은 `EMPLOYMENT_CATEGORY`, 근로시간은 `EMPLOYMENT_WORKING_TIME_TYPE`을 SSOT로 사용한다. `contract_period_type = FIXED_TERM`일 때만 종료일과 기간제 사유를 저장하고, `INDEFINITE`는 해당 값을 `NULL`로 정규화한다. 기간제는 종료일을 필수로 하며 사유별 상세·프로젝트 조건, `REVIEW_REQUIRED` 결재 차단 및 일반 기간제 계속근로 2년 초과 결재 차단을 담당한다. 결재요청 직전 활성 지급조건과 0원 초과 월 지급합계를 다시 검증한다.
- `EmploymentContractValidityService`
  - Responsibility: 승인 계약의 기준일·기간 유효성, 종료일 경계, 종료 처리일, 개정 계보의 최신본 선택을 단일 SSOT로 해석한다. 결재요청과 최종 승인 직전에는 직원별 계약행을 잠그고 서로 다른 계약 계보의 기간 중복을 차단하며, 근태·휴가·상용근로소득이 동일 Resolver 결과를 사용한다.
- `EmploymentContractAuditService`
  - Responsibility: 계약 헤더·요일별 일정과 휴게구간·비고정 근무정책·지급조건을 업무 필드 중심 Snapshot으로 구성하고, Actor·사유·결재요청·요청키와 함께 불변 감사 원장에 멱등 기록한다. 계약 ID 기준 감사이력 조회를 제공하며 SQL 저장은 `EmploymentContractAuditModel`에 위임한다.
  - Models: `EmploymentContractModel`, `EmploymentContractWeeklyScheduleModel`, `EmploymentContractWorkSchedulePolicyModel`, `EmploymentContractComponentModel`, `PayComponentModel`
  - `NORMAL`·`NIGHT`는 주간 반복 일정만, `SELECTIVE`·`SHIFT`는 계약당 1행의 비고정 근무정책만 사용한다. `FLEXIBLE`·`OTHER`는 기준 반복일정과 추가 정책을 함께 저장한다. 근무형태는 계약 헤더만 SSOT이며 정책행에는 유형을 중복 저장하지 않는다. 주간·월평균·통상임금 기준시간은 요일별 일정에서 조회 시 파생한다.
  - 지급조건 Grid는 `quantity`, `rate`, 선택적 `premium_rate`, 확정 `amount`를 계산 SSOT로 사용한다. 기본급·연차수당은 수량과 기준단가, 근로수당은 수량·기준단가·가산배율로 계산하고 문자열 산식은 저장하지 않는다. 지급합계는 활성 components의 `amount` 합계로 파생한다.
  - 요일별 `break_minutes`는 계약상 총 휴게시간이고 `break_schedules`는 선택적 예정 시각이다. 상세구간 입력 시 공용 Time Picker를 사용하고 합계가 총량과 일치하도록 검증하며, 미입력 과거 계약을 자동 보정하지 않는다.
  - 승인·시행 계약은 직접 수정하지 않는다. 실제 조건 변경은 `CHANGE` 개정 초안과 새 적용 시작일로 처리하고, 당시 원계약과 ERP 입력이 달랐던 입력누락은 `CORRECTION` 정정 초안과 `CREATE_CORRECTION` 감사행으로 원본을 보존한다.
  - Approval: `ApprovalWorkflowService`를 사용하며 승인된 계약은 직접 수정하지 않는다.
  - Attachment boundary: 공용 파일 메타데이터 구조가 사용자 직접 DB 생성 절차로 확정될 때까지 첨부 기능을 구현하지 않는다.
  - 발의자는 `requester_id`/`requested_at`, 실제 결재 처리자는 단계의 `acted_by`/`action_at`을 사용한다. `SUBMIT`은 상신 즉시 완료하고 첫 실제 승인단계만 `pending`으로 활성화한다.
  - 역할 단계는 첫 사용자를 자동배정하지 않으며 `approver_id = NULL`인 불변 스냅샷으로 생성한다. 최종승인은 기존 증빙·거래·VAT·링크 생성 진입점을 재사용한다.
  - 상신과 재상신은 동일한 `submit()` 요청 생성 경로를 사용한다. 템플릿 첫 단계는 `SUBMIT`으로 정규화되어 현재 로그인 사용자가 즉시 `acted_by`로 완료하며, 직접 삭제로 남은 신청서의 고아 요청 포인터는 새 요청 생성 트랜잭션 안에서 정리한다.
  - Attachment boundary: document attachments are not handled until a shared business-document attachment store exists.
- `PersonalExpenseExcelService`
  - Responsibility: shared Excel Manager domain adapter for the personal-expense item grid. It creates the 23-field item template/download workbook through `ExcelValueFormatterHelper`, parses uploads, strips all injected IDs, and delegates code/reference/amount validation to `PersonalExpenseService`. Upload returns replacement grid rows only and never persists an application or registers a merchant client.
  - Controller: `PersonalExpenseController`
  - Settings: page key `approval.personal_expense`, metadata domain `personal-expense-item`, keys `excel.template.personal-expense-item.v1` (`EXCEL_UPLOAD`) and `excel.download.personal-expense-item.v1` (`EXCEL_DOWNLOAD`).
- `DailyFundsReportService`
  - `InternalTransferRepository`가 확정한 기존 증빙·전표 SSOT 관계만 내부입금·내부출금으로 분리하고 미확정 후보는 일반 입출금에 유지한다.
  - Responsibility: 기준일 은행·현금 자금수단의 전일잔액, 당일 입출금, 마감잔액, 미연결 요약, 지급예정 잔여액과 보고서 엑셀을 기존 은행 증빙·계좌·연결·지급예정 SSOT에서 파생한다.
  - Controller: `DailyFundsReportController`
  - Models/Repository: `BankTransactionReportModel`, `PaymentScheduleModel`, `BankAccountModel`, `CodeModel`, `CompanyModel`, `InternalTransferRepository`
  - Constraint: `REVIEWED/POSTED/CLOSED` 전표만 확정하며 문자열·날짜·유사금액으로 내부이체를 추정하지 않는다.
- `PaymentScheduleService`
  - Responsibility: 자동 생성된 지급예정의 목록·요약·상세·계획정보 수정·보류·해제·소프트삭제·복원·엑셀과 은행 출금 검색·배분·해제, 계산 상태 투영, 참조 무결성 검증 및 지급이력 기록을 단일 업무 흐름으로 조정한다. 수동 지급의무 생성은 허용하지 않는다.
  - Models: `PaymentScheduleModel`, `PaymentScheduleHistoryModel`, `BankPaymentEvidenceModel`, `EvidenceLinkModel`
  - Integrity: 전표 원천과 참조값은 활성 원본·마스터로 검증한다. 지급예정행·은행원본행·관련 활성 링크를 `FOR UPDATE`로 잠근 뒤 출금액과 예정액을 재검증한다. 보류·취소·검토필요 상태의 신규 연결은 금지하며 현재 KRW `BANK_TRANSACTION` 출금만 지원한다. 예정계좌와 실제 출금계좌가 다르면 경고·이력을 남기되 계획계좌를 덮어쓰지 않는다.

- `PaymentObligationService`
  - Responsibility: 전표가 `REVIEWED → POSTED`로 최초 전환되는 동일 트랜잭션에서 지급의무 계정의 대변 순증액만 지급예정으로 생성하고, `reversal_of`가 있는 역분개 승인 시 원전표 지급의무를 취소 또는 검토필요로 전환한다.
  - SSOT: 계정 판정은 `ledger_accounts.creates_payment_obligation/payment_obligation_type`, 원천 Identity는 `VOUCHER_LINE/voucher_id/voucher_line_id`, 귀속값은 전표라인 참조 후 전표 전체 단일 참조 순서만 사용한다.
  - Integrity: 후보·초안·차변 감소·0원·삭제 전표는 생성하지 않는다. 이미 지급 연결된 원지급의무는 역분개 시 링크를 보존하고 `REVIEW_REQUIRED`로 전환한다.

## 직무·배치 조회

- `EmployeeAssignmentResolver`
  - Responsibility: 명시적 `as_of_date`와 양끝 포함 기간정책으로 직무·프로젝트·근무지 등 배치의 effective 상태를 `PLANNED`, `ACTIVE`, `ENDED`, `CANCELLED`로 판정하고, 단건 Service와 일괄 목록 SQL이 동일 정책을 사용하도록 SQL 식을 제공한다.
  - Boundary: DB 조회와 HTTP API를 소유하지 않는다. 목록은 직원별 호출 없이 `JobAssignmentModel`의 단일 batch SQL에서 Resolver 식을 사용한다.
- `JobAssignmentService`
  - Responsibility: 기준일 직무·배치 조회와 함께 종료된 과거 직무 이력, 비주요 프로젝트 배치, 직접 등록 프로젝트 종료 및 관리자 정정을 검증·처리한다. 입력 options는 Modal 또는 선택형 검색의 최초 사용 시 지연 조회하며 코드·직무·부서·직위만 제공하고, 직원과 프로젝트는 공용 AJAX search-picker가 조회한다. 직접 쓰기는 입퇴사·휴직·프로젝트 기간, 중복기간, 인사발령 원본 금지, `request_key` 멱등성을 동일 트랜잭션에서 강제하며 직원 마스터를 변경하지 않는다.
  - Models: `JobAssignmentModel`, `EmployeeAssignmentAuditModel`
  - List ordering: `JobAssignmentModel`은 공용 DataTables가 전달한 실제 목록 표시 컬럼 key를 고정 SQL 표현식 허용목록으로 변환하고, 허용되지 않은 key는 직원 순번으로 복귀한다. 목록에서 제외한 `기타 프로젝트` 요약과 대표 `수정일시` Projection은 정렬 대상으로 허용하지 않는다.
  - SSOT: 상태 조회는 `institution_job_assignments_job_histories`, `institution_job_assignments_project_histories`, `institution_job_assignments_workplace_histories`; 감사 증적은 `institution_job_assignments_audits`다.
  - SSOT: 직원 현재값은 `user_employees`, 기간 재현은 `institution_job_assignments_*` 기간이력 테이블, 변경 근거는 `institution_personnel_actions`다.
  - List scope: 기본 목록과 `recordsTotal`은 전체 `user_employees`를 직원범위로 사용한다. `current_only=1`은 지정 기준일의 `ACTIVE`·`ON_LEAVE`만 `recordsFiltered`에 적용하는 명시적 검색조건이며 기본값은 OFF다. 퇴직자의 목록 상태는 `RETIRED`로 유지하고 기준일 이력이 없으면 Current Master의 마지막 부서·직위를 표시한다.
  - Mutation boundary: 공식 주 직무·주 프로젝트·근무지는 `PersonnelActionService`와 `PersonnelActionApplyService`가 변경한다. 이 Service는 도입 전 종료 직무와 직접 생성 비주요 프로젝트만 등록·종료·Audit 정정하며 Current Master를 변경하지 않는다.
- `LeaveRequestService`: 전자결재 휴가신청의 직원 Application 경계다. 로그인 사용자와 연결된 `user_employees.id`를 강제하고 목록·상세·저장·상신·회수·취소요청을 기존 휴가 SSOT와 `LeaveService` 정책에 위임한다. 직원 선택값과 관리자 대행을 허용하지 않는다.
- `LeaveService`: 일반 휴가의 종류 정책, 출처별 부여·관리자 조정, Grant별 분 단위 불변 원장, 신청 계산, 전자결재 최종 적용·전체 취소, 근태 재계산을 단일 트랜잭션으로 조정한다. 전일·반차는 계약 휴게 총량만으로 계산할 수 있고, 시간차는 휴게구간과 신청구간의 교차가 필요하므로 총량만 있고 상세구간이 없으면 확인을 요구한다. 차감 시 사용 가능한 Grant를 `expires_on NULL 후순위 → expires_on → usable_to → usable_from → created_at → id`로 잠그고 소비하며, 취소는 원래 USAGE 원장의 Grant 배분을 그대로 복원한다. 직원 소유권 방어와 Approval lifecycle은 유지하며 전자결재 Application 진입은 `LeaveRequestService`를 사용한다. 부여·잔액 목록은 공용 DataTable 서버 페이징을 사용하고 초기 옵션에는 직원 전체 목록을 포함하지 않는다. 자동발생·자동이월·자동소멸은 이 Service의 현재 책임이 아니며 장기 휴직 SSOT와 분리한다.
- `LeaveApprovalAdapter`: 공용 결재함에 `LEAVE_REQUEST` 상세를 제공하고 승인·반려를 `LeaveService`에 위임한다.
## 2026-08-06 — 자격·교육관리

| Service | Responsibility | Reuse |
|---|---|---|
| App\Services\Institution\QualificationEducationService | 자격·교육 목록, 등록·수정·삭제·검증·갱신, 교육과정 기준정보, 첨부, 감사, Excel | 직원관리와 자격·교육관리의 단일 업무 Service다. 대표 자격증의 직원 변경을 막고 삭제 시 대표 연결을 함께 해제한다. FileService, ActorHelper, system_codes를 재사용한다. |

### 2026-08-21 책임 보강

`QualificationEducationService`가 자격 종류, 교육과정 재교육 정책, 직무별 요구조건, 검증·갱신·무효화 및 통합 정책감사의 단일 업무 흐름을 담당한다. DB 접근은 `QualificationModel`, `EducationModel`, `QualificationEducationPolicyModel`에 위임하며 Actor는 `ActorHelper`만 사용한다. 직접 Excel 다운로드 책임은 제거했다.
### EmployeeHrBaselineService

- 경로: `app/Services/Institution/EmployeeHrBaselineService.php`
- 책임: 신규 직원 Master 생성 트랜잭션 안에서 부서·직위·직무·재직상태 최초 기간이력을 원자적으로 생성한다.
- 기준일: `real_hire_date` 우선, 없으면 `doc_hire_date`를 사용한다.
- 제약: 기존 직원의 이력 보정에는 사용하지 않으며, 인사발령 원본이 없는 최초 Baseline만 생성한다.
### EducationSessionService

- 경로: `app/Services/Institution/EducationSessionService.php`
- 책임: 교육 Session/Target 일정 확정, 대상 Snapshot, 확인, 참석·이수, 완료 시 직원 교육이력 확정, Audit 및 공용 Notification Core의 `TRAINING_ASSIGNED/UPDATED/CANCELLED` Event 원자 생성.
- 저장소: `EducationSessionModel`, `EducationModel`.
- 알림 채널 전송은 책임에 포함하지 않으며 직원의 `user_id`가 없는 대상은 임의 매핑하지 않는다.

### NotificationService

- 경로: `app/Services/System/NotificationService.php`
- 책임: Event 멱등 생성, Recipient·IN_APP Delivery 원자 생성, MANDATORY/OPTIONAL Preference 판정, 본인 Feed/Paging/읽음, 안전한 내부 action target 해석.
- 저장소: `NotificationModel`; Legacy `system_notifications`를 읽거나 쓰지 않는다.
- 경계: 외부 Provider, Web Push, SMS, Kakao, 업무 acknowledgement를 처리하지 않는다. 결재 actionable 항목은 현재 단계 정확성을 위해 `ApprovalInboxModel` Projection을 유지하되 저장형 Core 알림과 다른 종류로 병합한다.
# 2026-08-22 상용근로소득 계산·회계연계

- `RegularEmploymentIncomeCalculationService`: 현재·과거 모드 구분 없이 귀속연월의 승인 근로계약과 법정기준 Revision, 지급일의 간이세액표·지방소득세 Revision을 소비하는 단일 계산 Service다. `TRACE_COLUMNS`는 적용상태·계산기초·요율·끝수처리 전 금액·끝수처리 정책과 법정기준/Coverage/회사규모 FK 9개의 유일한 계산 추적 계약이며 계산 Projection과 모든 저장경로가 함께 사용한다. 사회보험 계산기초는 사용자가 확인한 Item Snapshot, 확정 Coverage/Basis, 법정기준의 `automatic_fallback_base_value_code`·`pay_item_basis_rule_code`에 따른 지급항목 자동제안 순으로 결정하며 Coverage/Basis 미등록 자체는 차단하지 않는다. 국민연금은 `stage=ASSESSMENT_BASE`일 때 신고 소득월액의 단위처리를 요율 적용 전에 수행하고, 건강보험·고용보험은 비과세 제외 보수를 자동 제안한다. 존재하지 않는 기타공제 Line은 생성하지 않으며 사용자가 명시적으로 추가한 `SETTLEMENT` 공제 Line만 검증·재계산·저장·결재 재검증 흐름에서 보존한다. `calculated_amount + adjustment_amount = final_amount` 계약으로 실제값 차이를 보존하고, `HISTORICAL_IMPORT`는 Header 모드가 아니라 검증할 수 없는 Line 원본값의 provenance로만 허용한다.
- `RegularEmploymentIncomeCalculationService` 기관별 카드 Projection: 운영 Header의 `calculation_version VARCHAR(30)` 계약에 맞춘 `REGULAR_INCOME_V4_CARDS`부터 상용 계산결과에도 고용안정·직업능력개발과 산재보험 사용자부담 Line을 포함해 공통 8개 카드 계약을 충족한다. 산재보험은 공식 업종·보험관계 연결 SSOT가 없으므로 금액을 임의 계산하지 않고 `NEEDS_CONFIRMATION` Line과 확인 안내를 반환하며 근로자 공제·실지급액에는 포함하지 않는다. 미확정 Line도 DB 허용 계산원천인 `CALCULATED`와 별도 계산상태로 구분해 저장한다.
- `RegularEmploymentIncomeCalculationService` 미확정 구분: 근로소득세 가족수처럼 계산 입력만 대기 중인 경우에도 적용 간이세액표 Revision과 과세대상 계산기초를 Projection하고, 지방소득세는 선행 소득세가 대기 중이어도 법정 10%·끝수처리 Revision을 Projection한다. 확정 고용보험 Coverage가 적용제외이면 근로자 실업급여·사용자 실업급여·고용안정·직업능력개발 Line을 모두 `EXCLUDED` 0원으로 반환하되 일반 미확정으로 표시하지 않는다. 저장 상세는 `RegularEmploymentIncomeModel::lineItems()`가 `statutory_standard_id`로 적용기간을 조회 Projection하며 법정기준 기간을 Line에 중복 저장하지 않는다.
- `RegularEmploymentIncomeCalculationService` 근로계약 보험판정: 귀속기간의 유효 승인 근로계약에서 고용보험이 `EXCLUDED`이면 고용보험 근로자·사용자부담과 고용안정·직업능력개발을 모두 적용 제외하고 계약의 미적용 사유를 Line의 계산 메시지와 저장 가능한 업무사유에 보존한다. 산재보험도 계약 적용상태를 먼저 확정하며, `APPLICABLE`이면 해당 연도 산재 Revision의 사업종류 요율이 단일 확정될 때 계산기초·요율·계산 전 금액을 Projection한다. 법정 끝수처리가 미등록이면 적용 여부를 다시 미확정으로 돌리지 않고 `APPLICABLE/NEEDS_CONFIRMATION`으로 분리해 상신을 차단한다. 2013년 건설업 Revision은 비과세 근로소득을 제외한 피보험자별 보수에 3.7%를 적용한 뒤 10원 미만을 절사하는 법정 계산정책을 사용한다.
- `RegularEmploymentIncomeCalculationService` 공제대상 가족수 기본계약: 동일 귀속월의 저장 Snapshot과 요청값이 모두 없으면 간이세액표의 법정 최소 단위인 본인 1명을 신규 Preview 기본값으로 사용한다. 사용자가 배우자·부양가족을 반영해 변경하면 그 Snapshot으로 즉시 재계산하며, 기본값은 별도 가족관계 SSOT를 추정하지 않는다.
- `RegularEmploymentIncomeDeductionLineService`: 상용근로소득 공제 정산 Line의 SSOT다. 기존 Line Item의 `source_key=SETTLEMENT|공제코드|정산유형|대상기간|멱등토큰` 계약을 해석하고, 양수 저장된 `ADDITIONAL_COLLECTION/REFUND`를 각각 공제 증가·감소 방향으로 환산하며 Transaction Settlement의 `*_CURRENT`, `*_SETTLEMENT`, `*_REFUND` 유형과 `MINUS/PLUS` 방향을 결정한다. 정산 Projection에는 확정 적용상태 `APPLICABLE`을 명시해 저장·상세·회계 생성의 상태기반 합계 계약을 유지한다.
- `RegularEmploymentIncomePayLineService`: PAY Line의 `CONTRACT_BASE/INCREASE/DECREASE` 효과, 수동 증감 사유·Actor·처리시각, 지급·과세·공제·실지급 합계 및 Transaction 지급항목 Projection을 검증하는 순수 정책 Service다. 공제와 회사부담을 PAY 효과에 혼합하지 않는다.
- `EmploymentIncomeTaxTableService`: 지급일에 Resolve된 근로소득 간이세액표 `value_data`에서 공제대상 가족수 문자열/숫자 키를 정규화하고, 과세대상 월급여 구간·가족수 열·표 세액·소액부징수 결과를 반환하는 순수 정책 Service다. 지원 가족수나 급여구간을 PHP 상수로 복제하지 않는다.
- `RegularEmploymentIncomeHistoricalService`: 과거 실제 급여의 보험 계산기초 Snapshot과 Line Item의 자동계산·조정·최종값 계약을 검증한다. 법정계산 가능 항목은 `CALCULATED/WARNING`, 불가능하지만 실제값이 확정된 항목은 `NOT_VERIFIABLE`로 분리한다. 공제 정산 Line의 업무원천·원천키·처리 Actor를 보존하고 `RegularEmploymentIncomeDeductionLineService`의 추징·환급 방향으로 지급총액·공제총액·실지급액을 재현한다.
- `RegularEmploymentIncomeService`: 동적 지급·공제·회사부담 항목, 조정 Audit, 승인 Snapshot, 급여 Evidence 1건, 직원별 거래·지급예정 N건 및 Report Dataset을 조정하며 Voucher를 생성하지 않는다. 상세 재조회에서는 직원 Item이 참조한 승인 근로계약의 보험 적용 제외 사유를 저장 Line에 표시 Projection하여 계약 Snapshot과 카드 상태를 일치시킨다. 계산 저장의 `request_key`는 문서 잠금 안에서 Audit의 정규 Payload Hash와 비교하여 동일 Payload를 멱등 반환하고 다른 Payload 재사용을 저장 전에 차단한다. DRAFT 직원행 저장은 요청의 유효한 Item PK를 우선하고 PK 누락 시 동일 문서의 `employee_id`로 기존행을 식별하며, 다른 문서 PK·존재하지 않는 PK·직원/PK 중복을 저장 전에 차단한다. 저장과 결재요청 직전에는 직원별 최종 Line을 SSOT로 직원 Snapshot 및 문서 Header의 지급·공제·실지급 합계를 같은 트랜잭션에서 동기화하고 Line→직원→Header 불변식을 재검증한다. 최종 승인 회계 Projection은 승인 Line의 `final_amount`만 사용해 지급항목을 Transaction Item, 직원부담 공제를 MINUS Settlement로 투영하고 회사부담·VAT를 제외하며, 지급·공제·실지급 합계가 1원 미만까지 일치할 때만 생성한다.
- `RegularEmploymentIncomeCalculationService` 보험료 Override: 당월 법정기준 자동계산 후 동일 직원·보험의 이전 월 중 가장 최근 `INSURANCE_OVERRIDE` 또는 `INSURANCE_OVERRIDE_RESET` Snapshot을 조회한다. 수동 설정은 적용금액·사유와 원본 Line/귀속월을 계승하고, RESET은 오래된 수동 설정 계승을 중단한다. 저장·재계산·결재 직전 경로는 정상 보험 Line Override와 정산 Line을 분리해 전달하며 `final_amount`를 직원·문서 합계 SSOT로 사용한다. 별도 사회보험 자격·가족 이력은 이 책임에 포함하지 않는다.
- `RegularEmploymentIncomeLineSnapshotValidationService`: 최종 승인 회계자료 생성 전에 적용 사용자부담 Line의 공통 계산 Snapshot과 보험별 Coverage·회사규모 요구조건을 검증한다. 적용 제외는 0원·사유만 검증하고 미확정 상태는 차단하며, 산재보험은 공식 사업종류·보험관계 Scope 연결 전까지 불완전한 적용 Line의 회계 생성을 차단한다. 저장 Line을 기본값으로 보정하지 않는다.
# social-insurance (2026-08-22)

- `RegularEmploymentIncomeInsuranceProjectionService`: 상용근로소득 보험 Line을 본사·통신판매 사업구분 회사부담 정책, 건설 근로계약 설정, 개인 Coverage, 장기요양 건강보험 종속, 과거 계산 Snapshot의 실제 출처 순서로 읽기 전용 표시 Projection에 결합한다. 공식 가입자격 Resolver 결과로 위장하지 않으며 보험료 Revision과 적용기간·회사부담 출처·가입상태를 공용 Badge DTO에 제공한다.

- `SocialInsuranceService`: 상용근로소득 계산과 직원정보가 공유하는 Coverage/Basis SSOT Service다. 직원 잠금, 기간중복 방지, 장기요양 건강보험 기간 Guard, Audit 기록, 귀속월 배치 조회를 담당하며 `4대보험업무` Placeholder에서는 직접 관리 UI나 API로 노출하지 않는다.
- `SocialInsuranceService::currentSummary`: 직원 상세의 공식 소비자를 위해 현재 보험상태만 읽기 전용으로 제공하며 개발예정인 4대보험업무 관리 URL은 반환하지 않는다.
- `RegularEmploymentIncomeCalculationService`: 사회보험 배치 결과와 법정기준 Revision을 결합하고 미확정·잠정 정책 누락을 공제 항목별 `NEEDS_CONFIRMATION`으로 반환한다. 사회보험 Revision은 귀속월 말일, 원천세 Revision은 실제 지급일을 기준으로 Resolve하며 현재일 fallback을 사용하지 않는다. 고용보험은 Revision의 근로자 부담률과 계산정책을 함께 소비해 보수×요율 후 10원 미만 버림을 수행하고 계산기초·요율·계산 전 금액·끝수처리를 Line Projection에 제공한다. 필수 공제 하나라도 미확정이면 공제총액·실지급액을 숫자 0으로 확정하지 않고 `NULL` Projection과 확정공제 부분합을 제공한다.
- `RegularEmploymentIncomeCalculationService`: `EmploymentContractValidityService`가 Revision 계보별로 정규화한 최신 유효 근로계약의 활성 components `amount` 합계를 기본 지급 원천으로 사용한다. 동일 직원에게 독립된 유효 계약 계보가 여러 건이면 합산하지 않고 확인 대상으로 차단한다. 근태·휴가는 금액 변동에 실제 사용될 때만 확정성과 계산근거를 요구하며, 근태 미마감 자체를 전 직원 공통 차단조건으로 사용하지 않는다.
- `WorkplaceSizePeriodService`: 회사·계산목적별 회사규모 기간을 등록하고 같은 목적의 현재 leaf 기간중복을 차단한다. 회사 범위의 동일 요청키는 정규화된 동일 Payload에만 같은 결과를 반환하며 다른 Payload는 충돌로 차단한다. 회사 행 `FOR UPDATE`로 등록과 정정을 직렬화하고, 정정·기간종료는 기존 확정행 UPDATE 없이 이전 Revision을 참조하는 신규 불변 Revision으로 처리한다. Actor는 `ActorHelper` 또는 격리검증에 주입된 `ActorHelper::system()` 공급자만 사용한다.
- `WorkplaceSizeRateResolver`: 법정기준의 `additional_employer_rates` Matrix를 표시명이 아닌 `business_size_code`로 해석한다. 현재 목적은 `EMPLOYMENT_INSURANCE_VOCATIONAL`이며 표시명 변경은 계산에 영향을 주지 않는다.
# 일용근로소득 사전구현 (2026-08-26)

- `IncomeInsurancePremiumCalculationService`: 상용·일용 소득계산이 함께 사용하는 보험료 법정계산 정책이다. 계산기초 × 요율, 법정기준의 끝수처리 단계·단위, 결과 상·하한 적용을 단일 구현으로 제공하며 고용보험 근로자·사용자부담, 고용안정·직업능력개발 및 산재보험 계산 Line이 같은 산식을 사용한다.

- `DailyEmploymentIncomeService`: 일용근로소득 서버 Paging 목록·상세·근무그룹 AJAX 선택목록·근무일별 계산 Preview·Command 기반 Aggregate 저장과 공용 휴지통 삭제·복원·완전삭제를 담당한다. 상신 Preflight는 DRAFT·REJECTED·WITHDRAWN만 대상으로 저장 원천의 현재 해시, 서버 재계산, Header·Item·Workday·Line 합계, 법정기준 ID, 보험 확인상태, 비과세 적용사유, 같은 회사의 작업자·근무일·사업구분·프로젝트·작업팀 중복을 검사하며 상태를 변경하지 않는다. 근무그룹 선택목록은 사업구분·프로젝트·작업팀을 검색어와 페이지 기준 최대 20건씩 조회하고 `has_more`를 제공한다. 사업구분 저장·정책판정값은 `system_codes.BUSINESS_UNIT.code`, 표시값은 `code_name`이며 프로젝트와 작업팀 목록은 선택한 사업구분 코드 범위의 원본 활성 마스터만 반환한다. 입력 Aggregate는 `Header → Group → Item → Workday → Line`이며 사업구분·프로젝트·작업팀·그룹 작업내용은 Group SSOT로, 작업자 공종·작업내용은 Item SSOT로 저장한다. 금액 입력은 Workday가 SSOT이며 Excel 과세·비과세 증감도 첫 실제 Workday에 명시적으로 반영해 화면·계산·저장 Payload가 같은 값을 사용한다. 목록 노출은 저장 적합성 확정을 대신하지 않으며, 저장 시 클라이언트 선택조건을 신뢰하지 않고 회사·사업구분·프로젝트·작업팀·작업자·근무일 배정과 유효기간을 다시 검증한다. Workday는 실제 존재하는 날짜이면서 Header 귀속연월과 일치해야 하고 같은 Item·날짜는 중복될 수 없다. 실제근로시간과 비과세 적용사유는 Workday에 저장하고, 비과세증감이 0원이 아니면 적용사유를 필수검증하며 별도 비과세 근거자료 문자열은 사용하지 않는다. 같은 작업자의 복사 카드들은 계산 Preview에서 카드별 `client_key`·계산 source·요청 version으로 독립 계산하지만, 같은 Group의 중복 작업자는 Aggregate 저장 전에 서버가 차단한다. 다른 Group에는 같은 작업자를 반복 등록할 수 있다. Header의 `worker_count`, `work_team_count`는 계산 완료 Item과 Group을 기준으로 중복 제거해 산출한다. 고용·산재 회사부담은 본사·통신판매 사업구분 정책 또는 건설 Group 선택으로 확정하며 보험사업장 미등록은 차단하지 않는다. 공식 가입자격·보험료 정책 자체가 미확정이면 임의 0원 확정 없이 `CONFIRMATION_REQUIRED`로 보존한다. 직원과 일용 작업자를 연결하는 공식 개인 식별 SSOT가 없으므로 상용·일용 간 중복귀속은 이름 추정으로 차단하지 않고 경고한다. 기관별 확정 Revision·배부·대사는 승인 대기 저장구조가 마련되기 전 확정값으로 표시하지 않는다.
- `DailyEmploymentIncomeService` 기관별 계산 상세: 소득세·지방소득세는 Workday Grain, 국민연금·건강보험·장기요양보험·고용보험과 사용자부담은 Item Grain으로 계산한다. 국민연금·건강보험은 보험 활성화와 가입자격 판정을 분리하고, 귀속시점·근무범위의 공식 가입자격 Revision이 `ELIGIBLE`로 판정한 뒤에만 요율·상하한·끝수처리를 적용한다. `NOT_ELIGIBLE`은 적용 제외 0원, 입력자료 부족은 `CONFIRMATION_REQUIRED`로 투영하여 확정 합계에서 제외하고 저장·상신을 차단한다. 장기요양보험 자격은 건강보험 판정에 종속한다. 보험사업장·Coverage 미등록만으로 미확정 처리하지 않지만, 가입자격 판정 없이 지급액에 요율을 직접 적용하지 않는다.
- `DailyEmploymentIncomeFieldPolicyService`: 일용근로소득 TableSettings의 `columnDisplayName`, `columnRequirementPolicy`를 같은 페이지키로 읽어 Header 입력 라벨과 서버 저장 필수검증에 적용한다.
- `DailyEmploymentIncomeCalculationService`: `DAILY_WORKER_INCOME_TAX`와 `LOCAL_INCOME_TAX_WITHHOLDING` 법정기준을 적용일로 Resolve하여 근무일별 소득세·지방소득세 및 계산 Snapshot을 산출한다. 각 세금 Line은 계산 당시 법정기준 ID와 적용 시작일·종료일을 함께 Projection한다. 일용근로소득공제는 과세금액 한도, 과세표준·산출세액·근로소득세액공제 후 세액·결정 소득세·지방소득세는 0원 하한을 적용하며 각 계산단계를 Workday 결과로 반환한다.
- `DailyEmploymentIncomeService` 실제근로시간·보험 Preflight 보강: 신규 Workday의 휴게 제외 실제근로시간 1~1,440분을 필수검증하고 동일 근로자·동일 날짜의 문서 전체 Group 합계가 1,440분을 넘으면 차단한다. 복수 Group은 확인 경고로 반환한다. 국민연금·건강보험·장기요양은 공식 ELIGIBILITY Resolver를 사용하고, 고용·산재는 사업구분 또는 건설 Group의 회사부담 선택과 PREMIUM 계산 Line 일치를 검증한다. 회사부담과 금액 조정사유는 독립 검증한다.
- `DailyEmploymentIncomeService` Workday 산정내역: `calculation_note`를 자유 설명 선택값으로 받아 Trim 후 500자 이하를 검증하고 Preview·Aggregate 저장·상세 재조회·Command 및 업무 Snapshot에 반영한다. 산정내역은 금액·세액·보험료 계산 source hash와 Calculation Revision에서 제외하므로 산정내역만 변경해 계산을 STALE 처리하지 않으며, 자동계산액과 실제 적용액 차이의 조정사유를 대신하지 않는다.
- `DailyEmploymentIncomeService` Workday 비과세 적용사유: 비과세증감이 0원이 아니면 Trim된 500자 이하 `non_taxable_reason`을 필수로 검증하고 Workday에 저장한다. 별도 비과세 근거자료 문자열은 화면·Excel·Payload·DB 저장계약에서 사용하지 않는다.
- `IncomeCalculationCodeService`: 소득계산의 법정 계산원천·실제 적용원천·지급확인 상태·법정 계산상태를 `system_codes`에서 해석하는 공용 SSOT 경계다. Line FK의 ID가 지정된 `code_group`에 속한 활성 코드인지 서버에서 재검증하며 화면별 코드 배열을 만들지 않는다.
- `DailyEmploymentIncomeLineContractService`: 일용근로소득 PAY와 세금 Workday Line, 보험 공제·사용자부담 Item Line의 Grain, `ITEM` Scope Key, 자동계산액과 실제 적용액 차이 사유를 검증하는 단일 정책 책임이다. Generated `adjustment_amount`를 직접 저장하지 않는다.
- `DailyEmploymentIncomeBusinessUnitPolicyService`: 코드관리 `BUSINESS_UNIT.extra_data.daily_employment_income`의 프로젝트·작업팀 적용정책을 해석한다. 근로자 Master는 `DAILY`만 사용하고, 계산 당시 사업구분 정책과 프로젝트 귀속으로 가입자격 Scope·필수 귀속자료·파생근거를 한 곳에서 결정한다. 가입자격 `HEAD_OFFICE`는 물리적 본사 ID가 아니라 기존 Revision의 비건설 법정기준 Scope이며, `business_unit_code`와 `HQ_NON_CONSTRUCTION_POLICY`/`ECOMMERCE_NON_CONSTRUCTION_POLICY` 파생근거를 별도 Snapshot으로 보존한다. 작업명이나 근로자 고정 유형으로 건설 Scope를 결정하지 않는다.
- `AttachmentStorageService`: `system_file_upload_policies`를 기준으로 공용 비공개 Attachment를 Stage하고 SHA-256을 검증한다. DB 저장 실패 시 Stage 객체를 보상 삭제하며, 공개 URL을 만들지 않고 권한검증 후 다운로드할 절대경로만 해석한다. `20260827_20_create_common_attachment_ssot` 운영 적용 전에는 일용근로소득에서 호출하지 않는다.
  - 지방소득세율은 클라이언트 입력을 받지 않고 같은 근무일의 `LOCAL_INCOME_TAX_WITHHOLDING`을 Resolve한다. 보험사업장·보험종류별 Coverage가 하나로 확정되지 않거나 APPLICABLE 보험료 계산정책이 준비되지 않으면 임의 0원 처리 없이 Preview와 저장을 차단한다.
## DailyEmploymentIncomeNonTaxPayloadService

- 경로: `app/Services/Institution/DailyEmploymentIncomeNonTaxPayloadService.php`
- 책임: 일용근로소득 비과세 명령의 공식 Payload 필드만 정규화하고, 순서 비의존 Attachment 식별목록과 금액 표현을 고정하여 서버 SHA-256을 생성한다.
- 제외: 클라이언트 hash 신뢰, 화면표시명·임시 URL·요청시각의 hash 포함, Command·Revision DB 저장.

## DailyEmploymentIncomeCalculationSourceService

- 경로: `app/Services/Institution/DailyEmploymentIncomeCalculationSourceService.php`
- 책임: Header·Group·Item·Workday·공식 Line·보험 Resolver·정책 버전을 결정적으로 정렬·정규화하여 기관계산 Revision의 서버 `source_hash`를 생성한다. 보험 5종의 근로자·사용자 Line과 고용·산재 회사부담 상태·`BUSINESS_DIVISION_POLICY`/`DAILY_GROUP_MANUAL_SETTING` 출처·PREMIUM Revision을 포함해 부담설정 변경도 새 계산 Revision을 발생시킨다.
- 제외: UI 상태와 표시명, 클라이언트 합계, DRAFT 비과세 Line, 임시 URL.

## 2026-08-29 보험 가입자격 Closure Service

- `InsuranceEligibilityPolicyValidator`: 가입자격 JSON의 필수 구조, 결합방식, 결과코드와 구조화 조건계약을 검증한다.
- `InsuranceEligibilityConditionEvaluator`: 구조화된 가입자격 조건의 `TRUE`·`FALSE`·`UNKNOWN`을 ALL·ANY·NONE 3값 논리로 결합한다. 확정값이 UNKNOWN보다 우선하는 결합 계약을 공용으로 제공한다.
- `InsuranceEligibilityDecisionModelEvaluator`: ELIGIBILITY 정책계약 v2의 `COMPONENT_ELIGIBILITY`와 `BUSINESS_AND_WORKER_ELIGIBILITY`를 데이터 기반 구조화 규칙으로 평가한다. 고용보험 구성요소별 적용·부분 적용·임의가입 신청사실과 산재보험 사업장 적용성·근로자성·실제 근로 단계를 공용 `component_results`로 투영하며 보험별 법령조건은 Service에 하드코딩하지 않는다.
- `InsuranceEligibilityResolver`: 귀속일·보험·근로형태·근무범위 Revision과 생년월일 기준 연령, 고용기간, 월 일수·시간·소득을 평가한다. 각 조건을 3값으로 먼저 평가한 뒤 `InsuranceEligibilityConditionEvaluator`로 결합하므로 ALL의 확정 FALSE를 누락 입력 UNKNOWN이 덮어쓰지 않는다. 장기요양은 건강보험 결과에 종속하며 건강보험 정책을 국민연금 정책으로 대체하지 않는다.
- `InsuranceEligibilityReasonProjectionService`: Resolver가 생성한 `reason_code`를 선택된 ELIGIBILITY Revision의 `reason_codes` 메타데이터와 결합해 한글 사유명·상세설명을 투영한다. 확정 실패조건, 누락 사실, 구성요소별 결과를 공용 Projection으로 보존하며 기존 Calculation Snapshot은 수정하지 않고 조회 시에도 동일 계약을 재사용한다.
- `IncomeCalculationModeProjectionService`: 상용·일용 세금 계산 Line의 법정기준 적용기간, 계산기초, 요율, 끝수처리, 자동계산 결과를 공용 `CALCULATION_MODE` 표시 Projection으로 구성한다. 원본 계산값을 변경하거나 저장하지 않는다.
- `DailyEmploymentIncomeInsuranceEligibilityService`: 일용근로소득 Group·Item·Workday·지급 Line을 고용관계 분석 SSOT로 사용해 국민연금·건강보험·장기요양보험 가입자격을 판정한다. 과거 종료 귀속월은 인접 확정 Workday가 없을 때만 해당 Item의 최초·최종 근무일을 단기 고용 분석값으로 사용하고, 현재월·인접월 자료가 있거나 모호하면 `CONFIRMATION_REQUIRED`를 반환한다. 건설 경과조치는 프로젝트 계약일·입찰공고일, 2025년 합산은 확정 보험사업장의 정규 관리번호와 유효 프로젝트 관계를 분석한다. 고용·산재는 공용 보험사업장 기능 완성 전 이 Service에서 판정하지 않는다.
- `DailyEmploymentIncomeGroupInsurancePolicyService`: 일용 고용보험·산재보험의 회사부담 계약을 담당한다. 본사·통신판매는 자동 부담, 건설은 Group 2값 수동설정으로 정규화하며 외부 출처 `BUSINESS_DIVISION_POLICY`·`DAILY_GROUP_MANUAL_SETTING`을 기존 물리 원천코드 FK에 호환 저장한다. 설정사유 입력이나 보험사업장 조회 없이 Preview용 회사부담 Projection을 생성하고 공식 ELIGIBILITY Revision ID를 위조하지 않는다.
- `DailyEmploymentIncomeCalculationResultService`: 문서 저장 시 공식 계산 source hash가 달라진 경우 기존 `CALCULATED` Revision만 `STALE`로 전이하고 새 계산 Revision과 보험 5종의 불변 Result를 INSERT한다. 확정·처리 중 Revision은 서버에서 재계산을 차단한다. Item 물리 ID, 사업구분 기반 Scope, 가입자격·보험료 Revision, Workday·지급 Line ID, 고용관계 분석과 계산값을 고정 key 순서 JSON으로 보존한다. 일용 고용·산재 회사부담은 `eligibility_revision_id=NULL`, 회사부담 상태와 `BUSINESS_DIVISION_POLICY`/`DAILY_GROUP_MANUAL_SETTING` 출처, 실제 PREMIUM Revision을 Snapshot에 분리 보존한다. 상세 조회에서는 저장된 코드와 Snapshot을 결합해 한글 상태·사유·설명 표시 Projection을 생성하며 정책 원본 JSON은 API에 노출하지 않는다.
- `DailyEmploymentIncomeAccountingGenerationService`: 일용근로소득 최종승인 Transaction 안에서 공식 계산 Revision을 확인하고 Document×Group×Worker Item Grain의 `INTERNAL_APPROVAL`·`APPROVED` Evidence, 근로자 지급 Transaction, Canonical Evidence Link를 생성한다. Evidence 금액 SSOT는 `raw_*`이며 구 `total_*` 컬럼은 운영 호환 전환기간에만 dual-write한다. 지급 Transaction은 공제 전 지급액 Item 정확히 1건과 비영 근로자 공제 Line별 MINUS Settlement로 구성한다. Callback 재사용은 Canonical `DAILY_EMPLOYMENT_INCOME`, `DAILY_WORKER`, Evidence Link 1건, Item 1건, 공식금액, Settlement 원 Line·Revision·Source Hash 추적, 사용자부담 제외, Registry·Closure 일치를 모두 검증하며 하나라도 다르면 차단한다. 실패주입을 포함한 전체 생성은 바깥 승인 Transaction에서 Rollback된다.
- `SocialInsuranceAttachmentService`: 공용 `AttachmentStorageService`와 `AttachmentModel`을 재사용하여 경과조치 근거자료를 `private://attachment`에 Stage하고 무결성 SHA-256 확인 후 `system_attachments`에 저장한다. 저장 실패 시 Stage 파일을 보상 삭제하며 전용 파일 저장소나 공개 URL을 만들지 않는다.
- 계산 source hash에는 Workday 세금 원천뿐 아니라 Item별 국민연금·건강보험·장기요양 가입자격 상태·사유·가입자격 Revision·Workday 분석·프로젝트 경과근거·관리번호 합산근거·보험료 Revision·확정금액을 포함한다. 원천자료나 정책이 정정되면 기존 계산 Revision을 변경하지 않고 새 계산 Revision을 생성한다.
### DailyEmploymentIncomeTransactionRepairService

- 최종승인으로 생성된 일용근로소득 Transaction Projection을 승인 원천과 Evidence를 변경하지 않고 정정한다.
- 대상 전수 잠금, 사전대사, 기존 Item ID 유지, signed Settlement 생성, 공용 감사행 기록과 사후대사를 단일 DB Transaction으로 수행한다.
- `TransactionProjectionRepairRepository`는 완료 감사행 INSERT와 request key·Transaction별 읽기만 제공하며 UPDATE·DELETE 기능을 제공하지 않는다.

## 사업소득 P1 Service (2026-09-03)

- `BusinessIncomeService`: `business-income` Header → Group → 소득자 Item → 외주 작업내역 N건 Aggregate의 목록·상세·선택지·계산 Preview·저장·결재요청 사전검증·기안회수·최종승인 진입점을 담당한다. 작업내역별 `수량×단가+증감액=확정금액`을 서버에서 재계산하고 합계를 소득자 총지급액으로 확정한 뒤 사업소득세와 개인지방소득세만 원천징수한다. 원천징수 법정기준 조회일은 귀속연월 말일이며 귀속연월을 계산 Source Hash에 포함해 월 변경 시 재계산을 강제한다. 거래일은 소득자 세무 Profile과 프로젝트·작업팀 유효성 판정에 사용한다. 외주 작업 단위는 코드관리 `UNIT`의 활성 코드/한글명만 허용하고 표준 한글명으로 저장한다. 품명부터 단가까지가 산정근거이므로 별도 산정내역 문자열은 저장하지 않으며 증감이 있으면 사유가 필수다.
- `BusinessIncomeExcelService`: 공용 Excel Manager를 통해 사업소득 Header·지급그룹·소득자·외주 작업내역 N건을 한 행 Grain으로 다운로드한다. 업로드 시 그룹번호와 소득자번호로 N건을 재구성하고 `BusinessIncomeService` 계산을 통과한 Preview만 Modal에 반환하며 DB는 변경하지 않는다.
- `BusinessIncomeTaxProfileService`: Item 거래일에 유효한 `system_client_tax_profiles`를 Resolve하고 거주자·개인·프리랜서·사업소득 원천징수·검증완료 조건을 모두 충족한 경우에만 불변 판정 Snapshot을 제공한다.
- `BusinessIncomeCalculationService`: 귀속연월 말일을 법정기준 조회일로 받아 `BUSINESS_INCOME_WITHHOLDING`과 `LOCAL_INCOME_TAX_WITHHOLDING` Revision을 Resolve한다. 지방소득세는 확정 소득세를 계산기초로 하며 세율·끝수처리·적용단계·계산기초·집계단위·적용순서·소액부징수 정책이 없으면 `CALCULATION_POLICY_NOT_READY`로 계산 확정과 결재요청을 차단한다.
- `BusinessIncomeTransactionGenerationService`: 최종승인 DB Transaction 안에서 소득자 Item별 Evidence Header·계산 Raw Line·외주 작업내역 원본 N건·지급 Transaction·거래품목 N건·양수 공제 Settlement·Canonical Evidence↔Transaction Link 1건과 Closure를 생성한다. Evidence는 세금계산서형 공급가액·부가세 계약을 사용하지 않고 승인된 총지급액·원천징수·최종지급액 `raw_*`만 보존한다. 거래품목은 작업내역의 품명·규격·단위·수량·단가·확정금액을 보존하며 전표·분개·Posting은 생성하지 않는다.
- `RegularEmploymentIncomeAccountingGenerationService`: 상용근로소득 최종승인 Transaction 안에서 직원별 `INTERNAL_APPROVAL`·`APPROVED` Evidence와 지급 Transaction·Link를 생성한다. Header 금액 Snapshot뿐 아니라 지급·공제·사용자부담 계산 Line을 `ledger_evidence_salary_report_lines`에 복제하고 Snapshot JSON·SHA-256으로 승인 원본을 고정한다.
- `ApprovalDocumentSummaryResolver`: 기존 상용·일용 요약에 사업소득 귀속연월·소득자 지급 건수·총지급액 요약을 추가해 결재함에서 UUID 대신 업무 제목과 금액을 표시한다.
# 2026-09-03 법정기준 Revision Supersession

## 2026-09-04 기초금액 Service

- `OpeningBalanceService`: 회사·회계연도별 기초금액 문서의 저장, 검토요청, 검토완료, 전기, 삭제 및 취소전표 생성을 조정한다. 금액 원본과 상태전이는 `VoucherService`를 재사용하며 관계행과 전표 저장을 하나의 DB Transaction으로 묶는다.
- `OpeningBalanceModel`: `ledger_opening_balances` 단일 저장소 CRUD와 기초전표 Header·Line 조회를 담당한다. 업무검증과 로그는 수행하지 않는다.
- `VoucherService`: 호출자가 시작한 DB Transaction이 있으면 소유권을 침범하지 않고 참여한다. 독립 호출일 때만 직접 Begin·Commit·Rollback하여 기초금액 관계행과 전표의 원자성을 보장한다.

- `StatutoryStandardService`: 확정 Revision 직접 수정을 금지하고 `createRevisionCorrection()`에서 신규 Revision·Source·Supersession 관계를 하나의 DB Transaction으로 생성한다. `revisionChain()`은 관리화면 감사 Projection을 제공한다.
- `StatutoryStandardResolver`: 기준일과 동일 Scope의 기간 후보 중 유효한 descendant가 존재하는 ancestor를 제외하고 최종 leaf 1건만 반환한다. 0건은 `POLICY_NOT_FOUND`, 복수 독립 leaf는 `AMBIGUOUS_POLICY`이며 생성시각·ID·수정시각 fallback은 사용하지 않는다.
- `StatutoryStandardSupersessionModel`: 불변 선형 관계 INSERT와 연결 chain 조회를 담당한다.
# Trigger 제거 무결성 책임 (2026-09-03)

- `BusinessIncomeTaxProfileService`: 거래처 행을 `FOR UPDATE`로 잠근 동일 Transaction에서 코드·기간·경계일·자기 제외 중복을 검증하고 저장한다.
- `BusinessIncomeEvidenceCanonicalPolicy`: 사업소득 Evidence의 `INTERNAL_APPROVAL / BUSINESS_INCOME_REPORT / OUT / BUSINESS_INCOME` 고정 계약을 저장 전에 검증한다.
- `StatutoryStandardService` 및 `StatutoryStandardSupersessionModel`: Correction 생성 Transaction에서 Revision 잠금, 동일 Type·Scope, 선형 관계, 분기·cycle 금지를 검증한다. 확정 Revision·Source·Supersession 변경은 차단한다.
# 2026-09-03 사업소득 Evidence 금액 계약

- `BusinessIncomeTransactionGenerationService`: 최종승인 Item별 Evidence·Transaction·Settlement·Link·Closure를 원자적으로 생성하며 Evidence/Transaction 공급금액은 총지급액, Transaction Final은 최종지급액으로 대사한다.
- `BusinessIncomeEvidenceCanonicalPolicy`: Canonical 코드값과 Evidence 총액·원본 총액·공제·최종지급액 불변식을 저장 전에 검증한다.
