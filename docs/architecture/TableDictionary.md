# Table Dictionary

## 2026-09-04 Main 캘린더

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| `main_calendar_list` | Main Calendar | 시놀로지 Calendar·Task Collection 목록과 ERP 관리속성을 보존한다. | `main_calendar_events`, `main_calendar_tasks`, `main_calendar_visibility`의 부모 테이블이다. |
| `main_calendar_events` | Main Calendar | 시놀로지 VEVENT 원본과 ERP 일정 확장정보를 보존한다. | `calendar_id → main_calendar_list.id` |
| `main_calendar_tasks` | Main Calendar | 시놀로지 VTODO 원본과 ERP 할 일 확장정보를 보존한다. | `calendar_id → main_calendar_list.id` |
| `main_calendar_visibility` | Main Calendar | 사용자·시놀로지 로그인별 캘린더 표시 여부를 보존한다. | `calendar_id → main_calendar_list.id` |
| `main_calendar_sync_state` | Main Calendar | 시놀로지 로그인별 캘린더 동기화 실행상태를 보존한다. | 독립 실행상태 테이블 |

기존 `dashboard_calendar_*` 물리명은 `20260904_01_rename_dashboard_calendar_tables_to_main` Migration에서 `main_calendar_*`로 원자적 변경한다. 업무 데이터와 FK·Index는 유지하며 Trigger와 View는 생성하지 않는다.

> 증빙원본 테이블의 공통 목적·영역·컬럼명·`raw_*` 불변성에 관한 최종 SSOT는 [EvidenceOriginalContract.md](EvidenceOriginalContract.md)이다. 이 문서는 물리 테이블 인벤토리와 전환 상태를 기록하며, 아래의 과거 일반화 문구가 SSOT와 충돌하면 폐기된 것으로 보고 SSOT를 우선한다.

## 2026-09-02 내부 승인형 Evidence P1 Forward 전환

- `ledger_evidence_salary_report`: `work_team_id`, 표준 지급·근로자공제 raw 금액, Source 문서·Item·멱등키 및 신규 승인 Snapshot 계약을 `_41~42`에서 추가한다. 기존 `team_id`, `raw_gross_amount`, `raw_deduction_amount`는 Legacy 호환으로 유지한다.
- `ledger_evidence_employee_personal_expense`: `work_team_id`, 승인추적, `raw_application_date`, `raw_project_id`, `raw_client_id`와 신규 승인 Snapshot 계약을 `_43~44`에서 추가한다. 원 업무에 없는 사업구분 raw 값은 만들지 않는다.
- `ledger_evidence_daily_employment_income`: 기존 Header raw·Raw Line을 유지하고 누락 공통 Envelope와 Group의 `raw_business_unit`, `raw_project_id`, `raw_work_team_id`만 `_45~46`에서 보강한다.
- 업로드형 Source 추적·세금계산서 Raw Line·상태 다중분리 Migration은 미적용 산출물에서 제거했다.

## 2026-09-01 내부 업무형 Evidence Forward 정규화

- `ledger_evidence_daily_employment_income`: 승인문서 × 근무그룹 × 근로자 Item Grain이다. 공용 업무분류와 `raw_income_year_month`, 근무일수·금액 합계, 사용자 검토상태를 소유한다. 거래일은 연결 Transaction 또는 작업자별 최종 실제 근무일로 투영한다.
- `ledger_evidence_daily_employment_income_lines`: Evidence × 원 계산 Line Grain의 불변 Projection이다. 0원·`EXCLUDED` Line과 사용자부담 Line도 보존하며 `(evidence_id, source_calculation_line_id)`가 유일하다. Source Hash는 Header가 소유하고 Line은 Calculation Revision을 참조한다.
- `ledger_evidence_tax_invoice_lines`: `TAX_INVOICE` Evidence × 원 품목 Line Grain이다. 품목 순서·Sheet·행과 품목 원천 사실을 보존하며 Header 대표 `raw_item_*` Projection의 Forward 대체 저장소다.
- `ledger_evidence_tax_invoice_manual_lines`: `TAX_INVOICE_MANUAL` Evidence × 수기 품목 Line Grain이다. 수기 작성 품목을 순서대로 보존하며 Header 합계와 품목 상세 책임을 분리한다.
- 외부 업로드형 Evidence 5종의 P1 Source 추적컬럼은 `evidence_type_code`, `acquisition_method_code`, `source_system_code`, `source_institution_code`, `source_external_key`, `source_file_id`, `import_batch_id`, `source_sheet_name`, `source_row_no`다. 기존 `source_type`, `import_type`, `external_key`는 소비자 전환기간의 Legacy Alias다.
- `ledger_evidence_salary_report`: 직원 Item Grain을 유지한다. `raw_gross_payment_amount`, `raw_worker_deduction_amount`, `snapshot_json`, `snapshot_version`, `snapshot_origin_code`, `source_hash`, `reconstruction_hash`, `calculation_version`을 Forward 추가한다. 신규 승인은 `source_hash`, 기존 2건은 `LEGACY_RECONSTRUCTED`와 `reconstruction_hash`를 사용하며 사후 Hash를 승인 당시 Source Hash로 표현하지 않는다.
- `ledger_evidence_employee_personal_expense`: 개인경비 Item Grain을 유지한다. `approval_request_id`, `approved_at`, `approved_by`를 승인 추적으로 추가하며 기존 행은 원 Item·Header·최종 승인단계가 유일하게 대사될 때만 backfill한다.
- 일용 Transaction 구조 Repair 감사 저장소는 아직 존재하지 않는다. 불변 before/after Snapshot, Hash, 결정적 `request_key`, 처리 Actor·시각을 갖춘 전용 저장소가 별도 승인으로 설치되기 전에는 운영 Repair를 차단한다.

## 2026-08-24 역할형 분개규칙 조건 확장

- `ledger_journal_rules`: 회계역할별 단일 결과 규칙 SSOT다. 결과는 `accounting_role_code + debit_credit + account_id + amount_policy_code`, 조건은 기존 업무·자료·거래처 조건과 `source_type + source_line_type + item_code + 적용기간`으로 저장한다. 신규 역할형 저장에서는 `debit_account_id`, `credit_account_id`, `vat_account_id`를 모두 NULL로 유지한다.
- 개인경비 Source 계약은 `import_type=EMPLOYEE_EXPENSE_PERSONAL`, `source_type=PERSONAL_EXPENSE_ITEM`, `source_line_type=ITEM`이다. `item_code`는 `PERSONAL_EXPENSE_CATEGORY` 활성 코드이며 Evidence 자료유형을 Source 유형으로 중복 저장하지 않는다.
- Migration `20260824_14_extend_role_based_journal_rule_conditions`는 기존 Rule 0건을 Preflight하고 `credit_account_id`를 NULL 허용으로 변경하며 세 조건 컬럼과 평가용 최소 인덱스를 추가한다. 기존 Rule·Revision 또는 역할형 데이터가 있으면 Down을 거부한다.
- `PERSONAL_EXPENSE_CATEGORY.FEES_AND_COMMISSIONS`의 공식명은 `지급수수료`이며 설명은 `수수료, 이용료 및 각종 업무처리 대가로 지급한 개인경비`다. `TAXES_AND_DUES` 다음, `SUPPLIES` 이전에 위치하고 Migration `20260825_01_seed_personal_expense_fees_category`로만 등록한다. 공식 코드이므로 Down은 지원하지 않는다.
- Migration `20260825_02_seed_personal_expense_role_based_journal_rules`는 회사 초기 기준데이터인 역할형 USER 규칙 6건과 CREATE Revision 6건을 원자적으로 등록한다. `rule_code`가 공식 식별자이고 `condition_hash`가 조건 중복 SSOT이며, 전체 Payload가 같은 완전한 6건 상태만 멱등 성공한다. `request_key`는 API 명령 식별자이므로 Seed·Rule Header·Revision에 저장하지 않는다. 운영 사용 이후 안전한 자동삭제를 보장할 수 없어 Down은 `SIGNAL`로 차단하는 forward-only 계약이다.
- Migration `20260825_03_seed_personal_expense_category_coverage_rules`는 활성 개인경비 공식 분류 15개 중 누락된 10개(FUEL, PARKING, TOLL, ACCOMMODATION, COMMUNICATION, ENTERTAINMENT, OFFICE_SUPPLIES, FREIGHT, EQUIPMENT_RENTAL, OTHER)의 기본 EXPENSE Rule과 CREATE Revision을 추가한다. OTHER는 `item_code=OTHER`에만 대응하며 NULL·미등록 분류의 범용 Fallback이 아니다. 동일 Payload만 멱등 성공하고 부분 적용·충돌을 차단하며 Down은 forward-only다.

## Employment contract project boundary

- `institution_employment_contracts.work_location_type` stores the contractual workplace characteristic (`HEAD_OFFICE`, `PROJECT`, `REMOTE`, `HYBRID`, `OTHER`).
- Nullable `institution_employment_contracts.project_id` stores only a specific-project-limited contract snapshot. Site work alone never makes it required; `FIXED_TERM + PROJECT_COMPLETION` does.
- Actual dated project assignments are stored only in `institution_job_assignments_project_histories` and are not derived from or written back to the employment-contract snapshot.

## 프로젝트 기준정보

| Table | Responsibility | SSOT contract |
|---|---|---|
| `system_projects` | 전사 프로젝트 기준정보와 표시순서·Soft Delete 상태 | `id`가 Consumer 참조 SSOT이며 작은 `sort_no`가 위에 표시된다. 삭제 생명주기는 Soft Delete → 복원 또는 실제 ID 참조를 검사하는 guarded purge다. |

## 2026-08-12 상용근로소득·급여(신고)

| Table | Responsibility | SSOT contract |
|---|---|---|
| `institution_regular_employment_incomes` | 귀속월별 상용근로소득 Header와 결재 연결 | 한 귀속월=한 문서, 집계값은 활성 Item 합계. 현재 Runtime이 생성·소비하지 않던 `correction_of_id`, `revision_no`, Header `request_key`, `snapshot_at`은 `20260824_04`에서 제거했다. 정정·개정 기능이 필요하면 승인된 업무흐름과 함께 신규 Migration으로 다시 설계한다. |
  - `withholding_date`는 기관 신고와 근로소득세·개인지방소득세 법정기준 Revision 조회일이다. 지급예정일·실제지급일이 아니며 급여 Transaction 거래일과 분리한다.
| `institution_regular_employment_income_items` | 직원별 확정 소득·공제와 당시 인사·계산기초 Snapshot | 직원·근로계약 FK, 승인 당시 표시값, 귀속월 공제대상 `dependent_count_snapshot`, 실제 급여 계산에 사용한 국민연금 기준소득월액·건강보험 보수월액·고용보험 산정대상 보수 Snapshot을 보존한다. 동일 문서의 직원 순번은 `(regular_employment_income_id, sort_no)` UNIQUE를 유지하며, 재배치는 `INT UNSIGNED` 범위의 현재 최댓값 뒤 임시영역으로 기존 활성행을 이동한 후 요청 배열 순서의 1부터 연속번호를 적용한다. 보험 Snapshot은 Coverage/Basis 기간이력 SSOT가 아니며 확정 기간이력이 없을 때 법정기준의 지급항목 포함·제외 정책으로 자동 제안한다. 국민연금은 자동 제안 소득월액의 1,000원 미만을 기준소득월액 확정 단계에서 처리하고, 건강보험·고용보험은 비과세 제외 보수를 자동 제안한다. `employment_contract_id`는 급여·소득 근거 보존을 위해 계약 삭제를 `RESTRICT`한다. |
| `institution_regular_employment_income_line_items` | 직원별 지급·공제·회사부담 최종 Line SSOT | PAY는 양수 금액과 `CONTRACT_BASE/INCREASE/DECREASE` 효과를 분리 저장한다. 수동 증감은 `source_reference_id`에 급여항목 마스터 PK, `source_key`에 `PAY_COMPONENT|지급항목코드|증감구분|멱등토큰`, `item_name_snapshot`·`taxable_flag`에 계산 당시 명칭·과세정책을 보존하며 업무원천·사유·처리 Actor/시각을 요구한다. 보험료 Override는 `calculated_amount` 자동액, `final_amount` 적용액, `adjustment_amount` 차이, `adjustment_reason` 적용사유를 보존하고 `source_key=INSURANCE_OVERRIDE|보험코드|원본귀속월`, `source_reference_id=직전 Line ID`로 계승한다. 명시적 자동계산 복귀는 `INSURANCE_OVERRIDE_RESET|보험코드|귀속월`로 저장해 더 오래된 Override 계승을 종료한다. 별도 자격이력은 만들지 않고 월별 Snapshot을 연속 이력으로 사용한다. 공제 정산은 별도 테이블 없이 양수 `final_amount`와 `source_key=SETTLEMENT|공제코드|ADDITIONAL_COLLECTION 또는 REFUND|대상기간|멱등토큰`으로 당월 정상공제와 분리한다. `(regular_employment_income_item_id, business_source_code, source_key)` UNIQUE로 동일 원천 중복을 차단한다. Line `HISTORICAL_IMPORT`는 Header 모드가 아니라 실제 원본값 provenance다. |
| `ledger_evidence_salary_report` | 최종승인 당시 직원별 급여(신고) 증빙 Snapshot | Migration `20260826_05_enable_employee_salary_report_evidence` 적용 후 원본 Header가 아니라 `(source_regular_employment_income_id, regular_employment_income_item_id)` 직원별 1:1이다. 결재요청·직원·귀속월·직원별 지급/공제/실지급/사용자부담·최종승인 Actor/시각을 물리 저장하고 직원 거래와 `ledger_evidence_links`로 1:1 연결한다. 기준일은 귀속월 말일이다. |

- 기존 3개 운영 테이블을 그대로 사용하며 신규 급여 상세·상태·연결 테이블은 만들지 않는다.
- `ledger_evidence_metadata`의 `PAYROLL_REPORT`는 귀속월 말일을 기준일로 투영하고 기준금액 `raw_gross_amount`, 원본 테이블을 선언한다.

## 2026-08-11 법정기준 Metadata 계약

- `system_statutory_standards`, `system_statutory_standard_sources`의 `COLUMN_COMMENT`와 `IS_NULLABLE`은 공용 Table Setting 기본 `label`/`required`의 DB Metadata SSOT다. 시스템 자동입력 컬럼은 입력 대상 판정에서 제외하며, `value_data` 내부 필드의 표시명과 필수 여부는 `STATUTORY_STANDARD_TYPE.extra_data`가 담당한다.

## 2026-08-11 법정기준관리

| Table | Responsibility |
|---|---|
| `system_statutory_standards` | 한 행=한 종류·정책 구성요소·고용형태·업무 Scope·추가 Dimension·적용기간 법정기준 SSOT |
| `system_statutory_standard_sources` | 법정기준 한 행에 연결되는 0~N개 공식 법령·고시·URL·원본파일 근거 |

- `system_codes.STATUTORY_STANDARD_TYPE`의 활성 13개 행은 법정기준 종류와 동적 값·계산정책 입력계약의 SSOT다. `calculation_policy.fields`는 공식 근거가 확인된 Type만 선언한다. 현재 국민연금, 건강보험, 장기요양보험, 일용근로소득, 근로소득 간이세액표, 지방소득세 특별징수는 계산기초·계산단계·끝수처리·집계단위·판정단위·적용순서 중 필요한 항목만 선언한다. 건강보험은 계산기초 상·하한과 구분되는 계산결과 `minimum_result_amount`·`maximum_result_amount`, 공식 선후관계를 위한 `result_limit_application_stage`, 월중 자격변동 정책을 위한 `qualification_month_rule_code` 공용 계약을 사용한다. 건강보험과 장기요양보험의 2013년 및 2026년 공식 끝수정책은 각각 `TRUNCATE`와 `discard_below_unit=10`을 저장한다. 실제 기준값과 정책은 같은 `system_statutory_standards.value_data`, 당시 계약은 같은 `_schema`가 소유하며 전용 rounding 테이블은 사용하지 않는다.
- `system_codes`는 코드관리에서 단건 Hard Delete만 사용한다. 휴지통 상태를 저장하지 않으며 `deleted_at`, `deleted_by` 컬럼을 두지 않는다. `is_active=0`은 과거 참조를 유지하면서 신규 선택에서 제외하는 상태이며 애플리케이션은 0/1만 저장한다. Hard Delete는 `CodeReferenceRegistry`의 일반 컬럼·JSON 참조 검사가 완료되고 참조가 없을 때만 허용한다. `sort_no`는 현재 전역 순번을 유지하며 정렬 변경 시 `updated_at`, `updated_by`도 갱신한다. `extra_data`는 JSON 문자열 확장정보다.
- 운영 법정기준의 기간은 공표일이 아닌 실제 법령 적용일로 분할한다. 2013년 법인지방소득세 독립세 이전의 법인세분은 `CORPORATE_LOCAL_INCOME_TAX`에 저장하지 않으며, 2014년 독립세 전환 이후 구간만 관리한다.
  - `INDUSTRIAL_ACCIDENT.value_data.industry_rates`는 같은 적용기간의 사업종류별 공식 보험료율을 `[{industry_name,employer_rate}]`로 저장한다. 별도 공식 사업종류 코드 SSOT가 없으므로 현재는 `industry_name` 텍스트를 중복키로 사용하며 회사 업종과 자동 연결하지 않는다.
  - `EMPLOYMENT_INSURANCE.value_data`는 `employee_rate`, `employer_rate`, `additional_employer_rates[{business_size_name,employer_rate}]`와 같은 적용기간의 `calculation_policy`를 저장한다. 앞의 두 값은 실업급여 노사 부담률이고 Matrix는 법령상 사업규모별 고용안정·직업능력개발사업 사업주 부담률이다. 근로자 부담금은 비과세 근로소득을 제외한 보수에 요율을 적용한 뒤 피보험자별 지급 건마다 10원 미만을 버린다. 확정된 Coverage 적용제외 정보가 있으면 계산에서 제외한다. 회사 규모를 법정기준에 하드코딩하지 않으며 실제 Consumer가 업무 SSOT의 사업규모와 대조한다.
  - `INDUSTRIAL_ACCIDENT`의 2018년 이후 건설업 `employer_rate`는 사업종류별 요율과 전 업종 출퇴근재해 요율의 합계다. 개별실적요율 같은 사업장별 가감은 공통 법정기준 값이 아니라 향후 해당 Consumer의 판정 책임이다.
- `system_statutory_standards`의 보험 Type은 `policy_component_code`, `employment_type_code`, `work_scope_code`, `additional_dimension_data/key`로 보험료와 가입자격 Timeline을 분리한다. `additional_dimension_key`는 정규화 JSON의 SHA-256이며 Resolver는 정확히 같은 Grain만 선택한다. 별도 `INSURANCE_ELIGIBILITY` Type은 통합 완료 후 물리 제거한다.
- 보험 Dimension의 DB COMMENT는 각각 `정책 구성요소`, `고용형태`, `업무 Scope`, `추가 차원정보`, `추가 차원키`이며 공용 TableSettings·Modal·Excel metadata의 한글 표시명 SSOT다. 가입자격 Result 계약의 항목 ID·판정상태·판정사유·누락입력·Snapshot 버전도 같은 DB COMMENT 계약을 사용한다.
- 각 `system_statutory_standards` 행 자체가 불변 Revision이다. 별도 Revision 복제 테이블이나 Draft·Confirm 상태는 만들지 않으며, 확정 오류의 정정 계보는 공용 `system_statutory_standard_supersessions`만 사용한다.
- `EMPLOYMENT_INCOME_TAX_TABLE.value_data.table`은 `salary_unit`, `dependent_counts`, `rows`를 함께 보존한다. 각 행은 `salary_from`, `salary_to`, 가족수 문자열 키 Map인 `tax_by_dependents`를 가지므로 현재 1~11명과 미래의 다른 가족수 범위를 같은 JSON 계약으로 저장한다. `value_data.excess_rules`는 가변 개수의 시작·종료, 기준급여, 기준세액 참조방법, 초과반영률, 세율, 고정가산액을 저장하고 `adjustment_rules`는 연령조건 자녀수별 공제 같은 표 외 조정을 구조화한다. `_schema.fields`에는 저장 당시 렌더링·검증 계약을 스냅샷으로 보존한다. Global `extra_data`는 신규 입력 UI의 기본 계약만 제공하며 저장된 `value_data`의 차원·규칙·스키마가 과거 기준 재현 SSOT다. 공식 HWP/PDF/XLS는 Source 원본파일로 보존하며 구형 `matrix_cells`, 가족수별 물리 컬럼, 문자열 수식은 사용하지 않는다.
- `EMPLOYMENT_INCOME_TAX_TABLE.extra_data.fields[].ui`는 공식표·표 상한 초과 계산기준·별도 세액 조정기준의 제목, 도움말, 기본 접힘 상태와 `allow_paste`를 제공한다. 대량 공식표만 `allow_paste=true`이며 두 소규모 규칙은 직접 행 입력만 허용한다. `excess_rules`와 `adjustment_rules`는 Metadata의 `required=false` 계약에 따라 0건 저장할 수 있다. 가족수 원시 배열은 기본 화면에서 숨기고 현재 `dependent_counts` 범위를 요약하며, `base_tax_reference=TABLE`과 `rule_type=CHILD_COUNT_DEDUCTION`은 숨김 `default_value`로 자동 저장한다. 이는 표시 Adapter이며 `value_data` 계산 SSOT를 대체하지 않는다.
- 공식 간이세액표의 가족수별 세액 동적 컬럼은 `dash_as_zero=true`로 대시만 0원 처리한다. `salary_to`는 마지막 상한행의 공란을 보존하도록 nullable이며, 공란은 0이 아니라 JSON `null`로 저장한다.
- Resolver 조회 인덱스는 `idx_statutory_standard_resolve(standard_type_code, effective_from, effective_to)`를 유지한다. 동일 종류의 기간중복은 시작일 단일 UNIQUE 제약이 아니라 Service의 전체 기간 교차검증으로 차단한다.
- `system_statutory_standards`에는 공표일 컬럼을 두지 않는다. `effective_from`, `effective_to`는 법정기준 본체의 적용기간이다.
- 공표일 SSOT는 `system_statutory_standard_sources.published_at`이며, 1:N으로 연결된 각 근거자료가 자기 공표일을 소유한다.
- `system_statutory_standard_sources.id`는 근거자료 식별자로 유지된다. 기준 수정 시 ID 기반 diff 동기화를 사용하여 존재 근거의 `created_at`, `created_by`, 미교체 파일 참조를 보존한다.
- 법정기준 종류명과 동적 입력계약은 `system_codes.STATUTORY_STANDARD_TYPE`에서 조회하며 이름을 업무 테이블에 중복 저장하지 않는다.
- 법정기준관리 업무 테이블은 Standard, Source, Supersession 세 개다. 적용연도와 유효 여부는 `effective_from`, `effective_to` 및 Resolver의 leaf 선택 결과에서 파생한다.

## 2026-08-06 취업규칙·인사규정

| Table | Responsibility | SSOT contract |
|---|---|---|
| `institution_employment_rules` | 회사별 취업규칙·인사규정 문서 헤더 | 회사, 규정 코드, 규정 종류, 제목과 소관 부서를 식별한다. 현재 개정본 포인터는 두지 않는다. |
| `institution_employment_rules_revisions` | 불변 규정 개정본과 결재·시행기간 | 초안만 직접 수정하며 승인 이후 변경은 새 revision으로 만든다. 기준일 해석은 시행기간과 상태로 결정한다. |
| `institution_employment_rules_audits` | 규정·개정본 단위 감사 | 처리자·사유·요청 키·변경 전후 값을 기록하며 정책값 또는 적용범위를 저장하지 않는다. |

## System

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| system_page_registry | PageRegistry | Stores canonical ERP page rows keyed by `page_key`, including module/menu/page labels, breadcrumb, and representative WEB route information promoted from the current `PAGE_MAPPING`. | Prepared for future permission, breadcrumb, sitemap, and menu consumers through `page_key`, `default_route_key`, and `default_route_url`. |
| system_menu_registry | MenuRegistry | Stores ERP menu display-policy rows keyed by `menu_key`, with nullable `page_key` links to `system_page_registry`, per-surface visibility flags, ordering, icon, and representative entry URL. | Seeded from current Sidebar, Settings Menu, SiteMap, and Navbar structures; prepared for future menu consumers through `page_key`, `visible_in_sidebar`, `visible_in_settings`, `visible_in_sitemap`, and `visible_in_navbar`. |
| system_user_settings | UserSetting | Stores per-user page preference JSON such as DataTable and view-state payloads, keyed by page and setting type for DB-backed UI preference restore. | Scoped to the current authenticated user, logically keyed by `user_id + page_key + setting_type`, and consumed by `UserSettingService` plus shared client-side settings storage bridges. |
| system_clients | Client | 거래처 기본정보, 업무분류, 연락처, 정산정보, 파일 참조와 soft-delete 상태의 SSOT. | 프로젝트·카드·거래·전표·증빙·지급예정·개인경비 등에서 FK 또는 논리참조한다. 직원은 거래처가 아니므로 `user_employees`와 직접 연결하지 않는다. 참조 안전성을 보장할 수 없어 영구삭제를 금지하고 soft delete와 복원만 허용한다. |
| system_bank_accounts | BankAccount | 전사 은행계좌 기준정보, 통장사본 참조, 표시순서, 활성상태와 soft-delete 상태의 SSOT. | `id`가 카드·거래처 기본계좌·증빙·거래·전표·지급예정의 물리 또는 논리참조 SSOT다. 삭제 생명주기는 Soft Delete → 복원 또는 ID 참조를 검사하는 guarded purge이며, 통장사본 물리파일은 DB commit 이후 정리한다. |
| system_cards | Card | 카드명, 전체 카드번호, 유효기간 년·월, 한도, 카드 이미지, 카드사·결제계좌 참조, 표시순서와 soft-delete 상태의 SSOT. CVC/CVV 및 금융 비밀번호는 저장하지 않는다. | 카드사는 `system_clients.id`, 결제계좌는 `system_bank_accounts.id`를 사용한다. 증빙·거래·전표의 카드 ID 참조 및 `ledger_voucher_line_refs(ref_target='CARD')`를 검사하는 guarded purge를 적용하며 이미지 물리파일은 DB commit 이후 정리한다. |
| system_client_histories | ClientHistory | 증빙 동기화로 변경된 거래처 필드의 변경 전후값과 원본 증빙을 보존한다. | `client_id`는 `system_clients.id` FK이며 기존 `20260526_02_create_system_client_histories` Migration이 소유한다. CRUD 런타임에서 Schema를 생성하지 않는다. |
| system_settings_config | SettingConfig | Stores DB-backed system configuration values such as layout branding and UI policy. | Read through `SettingConfigModel`/`SettingService`; layout composition does not query this table directly. |
| system_notifications | Notification | Stores user-targeted notifications, read state, actor reference, action type, optional domain reference, and message content. | Owned by `NotificationModel`; `recipient_user_id` and `actor_user_id` refer to authentication users. Actionable approval navbar entries are live projections from approval request/step SSOT and are not duplicated in this table. |

## Auth

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| auth_permissions | Permission | Stores ERP permission master rows including `permission_key`, `permission_name`, `description`, `category`, `page_key`, `page`, `permission_source`, and `is_active`. | Referenced by `auth_role_permissions.permission_id`, synchronized by `PermissionRegistry`, logically linked to `system_page_registry.page_key`. |
| auth_roles | Role | Organization role master SSOT. Stores one role key/name, ordering, active state, description, and Actor audit values. The physical update Actor column is the legacy `updated_bY` column and is mapped to standard `updated_by` API output. | Referenced by `auth_users.role_id`, `auth_role_permissions.role_id`, `user_approval_template_steps.role_id`, and `user_approval_request_steps.role_id`. |
| auth_role_permissions | RolePermission | Stores role-to-permission mappings keyed by `role_id + permission_id` and acts as the runtime permission source of truth. | Links `auth_roles.id` to `auth_permissions.id`. |
| auth_user_permission_profiles | UserPermissionProfile | 사용자별 Permission 적용방식 `ROLE`/`EXTEND`/`REPLACE` SSOT. | 사용자당 PK 1건이며 기존 사용자는 `ROLE`로 초기화한다. 전체 컬럼은 공용 테이블 설정에 사용할 한글 업무 코멘트를 제공한다. |
| auth_user_permissions | UserPermission | 사용자별 직접 Permission Mapping. | `UNIQUE(user_id, permission_id)`, Permission 인덱스와 사용자·Permission CASCADE FK를 사용하며 권한부여 일시·Actor 코멘트를 제공한다. |
| auth_user_permission_audits | UserPermissionAudit | Mode 및 개인 Permission Set 변경을 `MODE`/`GRANT`/`REVOKE`와 `batch_id`로 영구 기록한다. | 사용자·Permission 삭제 시 FK는 SET NULL이고 식별 Snapshot은 유지하며 전체 감사 컬럼에 한글 업무 코멘트를 제공한다. |

## Ledger

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| ledger_accounts | ChartAccount | Stores the canonical chart of accounts, hierarchy, normal-balance policy, posting availability, payment-obligation policy, sub-account allowance, ordering, and soft-delete state. | `creates_payment_obligation=1` requires a posting account and one checked `payment_obligation_type`; voucher posting uses only these columns and never account-name heuristics. Parent hierarchy uses `parent_id`; referenced by journal rules and voucher lines through account IDs. |
| ledger_accounts_sub | SubAccountTargetPolicy | Stores allowed sub-account targets per account using canonical `ref_target`, plus `is_required` and ordering. | Child of `ledger_accounts.id`; voucher-line values remain normalized separately in `ledger_voucher_line_refs`. No `ref_type` fallback is used. |
| ledger_account_context_ref_policies | AccountContextRefPolicy | 회사·Operation Type·회계역할·적용기간별 조건부 보조계정 필수정책 SSOT. 활성·유효 정책행의 존재 자체가 해당 참조유형의 필수를 뜻하며 계정 전체 허용정책을 새로 만들지 않는다. | `account_sub_policy_id`는 `ledger_accounts_sub.id`를 RESTRICT 참조하고 `account_id`·`ref_target`·`is_required`를 중복 저장하지 않는다. 최종 필수집합은 계정 전체 `is_required=1`과 조건부 정책의 합집합이며 같은 Context의 적용기간 중복은 Service가 잠금 검증한다. |
| ledger_journal_rules | JournalRule | 회사 범위, 증빙 `import_type`, 조건 Hash, 회계 역할, 차대변, 전기 가능 계정을 한 행으로 저장하는 분개추천 공식 SSOT. | 추천 가능 조건은 `rule_status=ACTIVE`, 미삭제, 적용기간 유효, 활성·전기 가능 계정이다. `is_active`는 상태 호환값이며 독립 판정에 사용하지 않는다. 동일 조건·역할의 복수 계정 후보는 충돌 후보로 함께 보존한다. |
| ledger_journal_rule_revisions | JournalRuleRevision | 분개규칙의 신규등록·업무값·상태·출처·잠금·자동적용·우선순위·휴지통 변경 전후 전체 서버 Snapshot을 불변 보존한다. | `(rule_id,revision_no)` UNIQUE를 사용하되 Rule 영구삭제 후에도 보존하기 위해 Rule FK는 두지 않는다. 회사 범위와 Actor를 Snapshot 및 정규 컬럼에 보존한다. |
| ledger_voucher_line_source_refs | VoucherLineSourceRef | 전표 Line과 증빙 원천 Line의 다대다 배분 및 추천근거를 연결하는 SSOT. 개인경비 차변은 Item당 1 Ref, 합산 대변은 포함된 모든 Item Ref를 가진다. | 서버 생성 `source_ref_key`가 행 멱등성을 보장한다. `source_amount`와 `allocated_amount`는 양수 절대금액이며 방향은 `debit_credit`, 원본·역분개·정정은 `reference_action_code`로만 표현한다. 취소전표 REVERSAL은 `original_source_ref_id`로 ORIGINAL을 역추적한다. Rule ID와 Revision 번호는 둘 다 NULL 또는 둘 다 NOT NULL이다. |
| ledger_journal_learning_events | JournalLearningEvent | 회사별 POSTED 확정 결과와 정책 Snapshot을 보존하는 지속학습 Event SSOT. | 신규 학습 Event는 Source Ref 필수이며 안정적인 `event_key` UNIQUE로 재전기·중복호출을 차단한다. Source Ref 없는 Legacy Event는 `IGNORED/LEGACY_EVENT_EXCLUDED`로 보존하고 학습에서 제외한다. |
| ledger_journal_recent_patterns | JournalFeedback | 학습 Event에서 재계산되는 최근 확정 분개 조합 Projection. `pattern_hash=SHA1(debit|credit|vat)`가 key다. | `legacy_usage_count`는 Writer 도입 전 2건의 통계를 보존하고 신규 Event aggregate를 더해 deterministic SET한다. 회계 SSOT가 아니다. |
| ledger_journal_client_account_patterns | JournalFeedback | 명확한 단일 거래처 Context의 확정 계정 Projection. `(client_id,transaction_direction,line_type,account_id)`가 key다. | `legacy_usage_count/legacy_recent_score`로 기존 3건을 보존하고 신규 Event aggregate를 deterministic SET한다. 애매한 거래처 Context는 제외한다. |
| ledger_data_formats | EvidenceFormat | Stores saved evidence-import format headers grouped by `source_type` and `format_name`. | Parent of `ledger_data_format_columns`; referenced by evidence upload format flows. |
| ledger_data_format_columns | EvidenceFormat | Stores per-format column order, visibility, requirement, normalized field mapping, and source-column SSOT key through `original_column_key`. | Child of `ledger_data_formats.id`; unique by `(format_id, system_field_name)` for normalized mapping and `(format_id, original_column_key)` for source-column settings. |
| ledger_evidence_links | EvidenceLink | Evidence와 거래·전표·지급예정의 유일한 DB 연결 SSOT. | `(evidence_type, evidence_id, target_type, target_id, link_type)`가 동일 링크 중복을 방지한다. 활성 `TRANSACTION` 링크는 생성 컬럼 `active_transaction_evidence_type`, `active_transaction_evidence_id`와 `uk_evl_active_transaction_evidence`로 canonical 증빙 Identity당 하나만 허용한다. soft-delete 링크와 `VOUCHER`·`PAYMENT_SCHEDULE` 링크는 이 조건부 유일성 대상이 아니다. 확정 내부이체는 동일 확정 전표의 활성 `BANK_TRANSACTION` 2건과 canonical `ACCOUNT` 라인 참조를 함께 검증해 파생한다. `PAYMENT_SCHEDULE`은 `BANK_TRANSACTION`·`PAYMENT`·양수 `amount`만 허용하며, 한 출금을 여러 지급예정에 배분할 때 활성 링크 합계가 출금 원본액을 초과하지 않도록 Service가 잠금 검증한다. |
| ledger_payment_schedules | PaymentSchedule | 지급의무와 지급계획의 단일 SSOT. | 전표 승인 생성 원천은 `VOUCHER_LINE`, `source_id=voucher_id`, `source_line_key=voucher_line_id`이며 UNIQUE로 재생성을 차단한다. `payment_due_date`와 지급계좌는 미확정 시 NULL이다. `obligation_lifecycle_status`는 `ACTIVE/CANCELLED/REVIEW_REQUIRED`만 허용하고 역분개 시 취소 Actor·일시·사유를 보존한다. 지급상태·기지급액·잔여액·연체정보는 저장하지 않고 활성 PAYMENT 링크 합계와 기준일로 계산한다. |
| ledger_payment_schedule_histories | PaymentScheduleHistory | 지급예정 생성·변경·보류·삭제·복원 및 실제 지급 연결·해제 업무이력. | JSON 전후값과 Actor token을 보존한다. 지급예정 운영행의 물리 삭제를 허용하지 않아 FK는 RESTRICT다. |
| ledger_evidence_metadata | EvidenceMetadata | Stores one Registry header per `import_type`: Evidence Body `source_table`, DATA/FUND/BOTH `evidence_type`, ordering, Actor, and soft-delete state. The retained `process_role` column is legacy compatibility data and is not a Runtime handler selector. | Parent SSOT for `ledger_evidence_metadata_columns`. Runtime-required or referenced headers are protected from delete/purge. No revision, rule-engine, handler, or matching responsibility is stored here. |
| ledger_evidence_metadata_columns | EvidenceMetadata | Stores semantic accounting meaning to actual source-table column mapping as `(metadata_id, semantic_key, physical_column)`, including `BASE_DATE`, representative `DESCRIPTION`, amount meanings, and optional `adjustment_direction`. `adjustment_direction` accepts `ADD` or `DEDUCT` only for `ADJUST_AMOUNT`; every other semantic uses `NULL`. The retained `is_required` column is compatibility data fixed to `N`, not Evidence Body validation SSOT. | Child of `ledger_evidence_metadata.id`; unique by `(metadata_id, semantic_key, physical_column)` and server-validated against the Header source table. Detail replacement remains inside the Header save transaction. |
| ledger_evidence_bank_transaction | EvidenceBody | Stores `BANK_TRANSACTION` evidence body rows with raw bank transaction fields. | `evidence_status` is the user-confirmed business-classification state and runtime writes use only `COMPLETED` or `CORRECTION_REQUIRED`; deletion lifecycle uses `deleted_at/deleted_by`. `id` is the evidence body key; non-empty `external_key` is unique within the Body table and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `BANK_TRANSACTION`. |
| ledger_evidence_tax_invoice | EvidenceBody | Stores `TAX_INVOICE` evidence body rows with raw HomeTax tax-invoice fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_tax_invoice_manual | EvidenceBody | Stores `TAX_INVOICE_MANUAL` evidence body rows with raw manual tax-invoice fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_cash_receipt | EvidenceBody | Stores `CASH_RECEIPT` evidence body rows with raw cash-receipt fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to client and project references when mapped. |
| ledger_evidence_card_hometax | EvidenceBody | Stores `CARD_HOMETAX` evidence body rows with raw HomeTax card fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `CARD_HOMETAX`. |
| ledger_evidence_card_statement | EvidenceBody | Stores `CARD_STATEMENT` and `CARD_APPROVAL` evidence body rows with raw card-company fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and card-company evidence types resolve here. |
| ledger_evidence_employee_expense | EvidenceBody | Stores employee-expense source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_payroll | EvidenceBody | Stores payroll and payroll-withholding source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_daily_worker | EvidenceBody | Stores construction daily-worker source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_business_income | EvidenceBody | Stores business-income source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_cash_sales | EvidenceBody | Stores business/cash-sales source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_business_data | EvidenceBody | Stores shopping-order and import-invoice source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_bank_transactions | FundsLegacy | Legacy bank transaction storage used by historical funds flows and migration/backfill paths. | Referenced by bank-evidence generation, processing-item backfill, and legacy funds screens. |
| ledger_transactions | Transaction | Stores ERP common transaction header data including classification, base references, and the SSOT header amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | Linked from evidence links using `target_type='TRANSACTION'`; parent of `ledger_transaction_items`; no direct voucher relation is stored. |
| ledger_transaction_items | Transaction | Stores transaction item rows with SSOT fields such as `sort_no`, `item_date`, `item_name`, `item_specification`, `item_unit_name`, `item_quantity`, `item_unit_price`, `item_foreign_unit_price`, `item_foreign_amount`, `item_supply_amount`, and `item_description`. Item tax type is not stored; evidence keeps its own tax semantics and transaction VAT remains a settlement. | Child of `ledger_transactions.id`; removed with the header during transaction purge. |
| ledger_transaction_settlements | TransactionSettlement | Stores transaction-level settlement adjustments with SSOT fields `sort_no`, `settlement_type`, `amount_sign`, `amount`, and `settlement_description`. | `transaction_id` is normalized to `varchar(36) utf8mb4_general_ci NOT NULL` and protected by `fk_transaction_settlement_transaction` to `ledger_transactions.id` with `ON DELETE/UPDATE CASCADE`. The legacy `transaction_item_id` column remains physical compatibility only. |
| ledger_vouchers | Voucher | Stores voucher header read-model data for list/search/sort flows, including `debit_total`, `credit_total`, line count, representative summary refs, and the workflow status (`DRAFT`, `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`, `CLOSED`, `DELETED`) derived from voucher runtime operations. | Linked from evidence links using `target_type='VOUCHER'`; no direct transaction relation is stored. Totals and summary columns are recalculated from `ledger_voucher_lines` and `ledger_voucher_line_refs`. The balance difference is calculated at runtime and is not stored. |
| ledger_voucher_lines | Voucher | Stores voucher journal lines with voucher-local `line_no` as the UI, drag-and-drop, save, and query order SSOT; global `sort_no` remains internal-only. It also stores account, amount, line summary, journal rule, user-modification fields, and the original recommended account/side/amount needed by POSTED feedback. | Child of `ledger_vouchers.id`; parent of `ledger_voucher_line_refs`; `line_no` is contiguous from 1 within each voucher. `recommended_*` is nullable for manual lines. |
| ledger_voucher_line_refs | Voucher | Stores all voucher-line sub-account refs from `line.refs[]` as normalized `(voucher_line_id, ref_target, ref_id)` rows, where `ref_id` uses the shared UUID key shape `CHAR(36)`. | Child of `ledger_voucher_lines.id`; the only SSOT for Voucher sub-account save/detail/reopen flows. |

## Notes

- Register new runtime tables here before shipping code that depends on them.
- When a table references another table logically rather than with an FK, document that relation in the Relations column.
- Keep payload, status, link, and body responsibilities separated rather than overloading one table.
- Evidence Body ordering is local to each type-specific table through `sort_no`. The retired generation-center-wide `evidence_sort_no`, `ledger_evidence_number_sequences`, and `ledger_evidence_number_histories` are not part of the runtime schema or API contract.

## Personal expense approval tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `approval_personal_expenses` | PersonalExpense | Employee-owned personal-expense application header containing application date, title, whole-document description, memo, stored active-item aggregates (`item_count`, `supply_amount`, `vat_amount`, `total_amount`), current document-status projection, current approval-request pointer, soft-delete audit fields, and other audit fields. | Aggregate columns are server-calculated from non-deleted `approval_personal_expense_items` in the same transaction as item mutation; client header totals are never trusted. Ownership is forced by the authenticated user's `user_employees.id`; `UNIQUE(employee_id, sort_no)` keeps employee-local numbering. Header `deleted_at` is the sole application-trash state, so items are retained unchanged during soft delete. `user_approval_requests.status` remains the workflow SSOT. |
| `approval_personal_expense_items` | PersonalExpenseItem | Stores multiple expense lines for one personal-expense header, including expense, merchant snapshot, project, optional exact existing-client reference, item, amount, and audit fields. | Actual DB FK `personal_expense_id → approval_personal_expenses.id` has `ON DELETE CASCADE`; purge nevertheless deletes items explicitly before the header for count/error verification. Generated `ledger_evidence_employee_personal_expense.source_personal_expense_item_id` uses `ON DELETE RESTRICT`, so any evidence reference blocks purge. Nullable `client_id` uses `ON DELETE SET NULL`; `UNIQUE(personal_expense_id, sort_no)` keeps document-local ordering. |
| `approval_personal_expense_item_classification_corrections` | PersonalExpenseClassificationCorrection | 최종승인 개인경비 Item의 회계분류를 원본 변경 없이 보존하는 불변 Revision SSOT. | Item별 `revision_no`가 연속 증가하며 최신 Revision의 `corrected_category`가 유효분류다. 정정이 없으면 승인 Item 원본분류를 사용한다. 변경·삭제·활성 컬럼은 없고 오류는 다음 Revision으로 Forward Fix한다. |
| `user_approval_requests` | ApprovalRequest | Approval execution header, document link, current step, and overall workflow status SSOT. | `document_type=PERSONAL_EXPENSE`, `document_id=approval_personal_expenses.id`; resubmission creates a new immutable-history row and the document pointer selects the current request. |
| `user_approval_request_steps` | ApprovalRequestStep | 결재요청 단계의 불변 스냅샷 SSOT. `step_type`은 `SUBMIT`·`APPROVAL`·`FINAL_APPROVAL`이며, `approver_id`가 있으면 지정결재, 없고 `role_id`가 있으면 역할 공동결재다. | `SUBMIT`은 상신 시 `approved`로 완료되고 `requester_id`/`requested_at`이 발의 SSOT다. 역할 공동결재의 `approver_id`는 NULL이며 실제 처리자는 `acted_by`에 기록한다. `(role_id,status,is_active)` 조회 인덱스를 사용한다. |
| `user_approval_templates` | ApprovalTemplate | 미래 결재요청의 단계 설계도 Header SSOT. 신규 행은 비활성 초안으로 생성하며, 활성화 시 전체 단계 완결성과 동일 `document_type` 활성 템플릿 단일성을 Service에서 검증한다. | `user_approval_template_steps.template_id`, `user_approval_requests.template_id`가 참조한다. 요청 생성 시 단계 Snapshot을 만들며 이후 템플릿 변경은 기존 요청에 영향을 주지 않는다. |
| `user_approval_template_steps` | ApprovalTemplateStep | 결재템플릿 단계 정의 SSOT. 순번, 단계명, `step_type`, 역할, 특정 결재자, 활성 여부를 관리한다. | `approver_id`가 있으면 역할은 지정 사용자 적격성 검증 조건이고, `approver_id`가 없으면 역할 보유자 공동결재 정책을 요청 스냅샷으로 복사한다. |
| `ledger_evidence_employee_personal_expense` | EvidenceBody | Final-approved employee personal-expense item evidence body, including separate raw merchant base and detailed addresses. | `source_personal_expense_item_id` references `approval_personal_expense_items.id` with one unique evidence per item; identity uses `EMPLOYEE_EXPENSE_PERSONAL + evidence_id`. |

## Employment contract tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `institution_employment_contracts` | EmploymentContract | 근로계약 헤더, 당사자 스냅샷, 계약 당시 통합 직위·직책 스냅샷, 기간·근무형태·급여지급·결재상태와 감사정보의 SSOT다. | `contract_date`는 실제 계약 체결일, `contract_start_date`는 근로조건 적용 시작일, `contract_end_date`는 적용 종료일, `created_at`은 ERP 등록시각이다. 기존 계약일 미확정 행 보존을 위해 물리컬럼은 NULL을 허용하지만 신규 DRAFT는 Service에서 필수다. `contract_no`는 최초 생성 시 계약일의 `YYYYMMDD`와 6자리 UUID suffix로 확정한다. 같은 계약일의 DRAFT 재저장·재오픈에는 유지하고 계약일이 실제 변경된 DRAFT만 새 날짜로 재생성하며, CORRECTION과 승인계약에서는 계약일·번호를 변경하지 않는다. `job_title_snapshot`은 `user_positions` 참조목록에서 사용자가 선택한 계약 당시 명칭을 문자열로 보존하며 직원 현재 `position_id` 변화로 갱신하지 않는다. 계약기간은 `contract_period_type`, 고용구분은 `employment_category`, 근로시간은 `working_time_type`이 각각 단일 SSOT다. `FIXED_TERM`일 때만 종료일과 기간제 사유를 저장하며 근무시간 요약, 법정 휴일·연차, 출력·교부 상태는 저장하지 않는다. |
- `institution_employment_contracts` 보험 적용계약 (`20260828_07`): 계약기간과 Revision을 보험 적용기간으로 재사용한다. 고용보험·산재보험은 각각 `*_application_status_code`(`APPLICABLE`, `EXCLUDED`, DRAFT/레거시 `NULL`)와 `*_exclusion_reason`만 저장하며 별도 보험기간·확인자·확인시각·근거 컬럼을 두지 않는다. `EXCLUDED` 사유는 MariaDB 3값 논리와 공백 비교를 막는 `COALESCE(...,FALSE)`·`BINARY ...=BINARY TRIM(...)` CHECK로 보호한다.
| `institution_employment_contracts_audits` | EmploymentContractAudit | 근로계약의 법적 의미가 있는 액션과 전체 계약조건 before/after JSON을 보존하는 불변 감사 원장이다. | 계약 ID는 Draft purge 후에도 물리 식별값을 보존하기 위해 FK 없이 저장하고 인덱스로 조회한다. 결재요청은 `RESTRICT` FK로 보존하며 `(contract_id, action_type, request_key)`가 멱등성을 보장한다. |
| `institution_employment_contracts_weekly_schedules` | EmploymentContractWeeklySchedule | `NORMAL`, `NIGHT`의 반복 일정과 `FLEXIBLE`, `OTHER`의 기준 반복 일정 SSOT다. | 계약별 7행과 `WEEKLY_HOLIDAY` 정확히 1행을 Service가 강제한다. 주간·월평균·통상임금 기준시간은 이 일정에서 조회 시 파생하며 헤더에 중복 저장하지 않는다. |
| `institution_employment_contracts_work_schedule_policies` | EmploymentContractWorkSchedulePolicy | `SELECTIVE`, `SHIFT`, `FLEXIBLE`, `OTHER` 계약의 근무형태별 정책 SSOT다. | 계약당 최대 1행이다. 선택근무는 기준시간·선택/의무시간대, 탄력근무는 정산기간·기준시간, 교대근무는 기준시간, 기타근무는 전체 정책 필드를 유형별로 저장한다. `NORMAL`·`NIGHT`에는 행을 두지 않는다. |
| `institution_employment_contracts_components` | EmploymentContractComponent | 계약 당시 급여 구성항목과 연장·야간·휴일근로수당 약정을 계약별로 저장한다. | 계약금액 SSOT는 활성 행(`deleted_at IS NULL`)의 `amount`다. 월 지급합계와 결재함 `total_amount`는 계약별 `SUM(amount)`, 연 환산액은 그 합계의 `× 12` 파생값이다. 기본급·연차수당은 `quantity × rate`, 근로수당은 `quantity × rate × premium_rate`로 계산하고 문자열 산식이나 헤더·결재 전용 합계는 저장하지 않는다. 정액수당은 `amount`를 직접 확정한다. |
| `institution_employment_contracts_pay_components` | PayComponent | 상시근로자 근로계약용 급여구성항목 마스터다. | Employment 도메인 전용이며 계약시작일에 활성인 항목만 참조한다. `OTHER_PAY`는 2013-01-01부터 유효한 과세 기타 지급항목으로, 상용근로소득에서 복수 증액과 과세 지급 잔액 범위의 기타 감액에 사용한다. ERP 범용 지급항목으로 사용하지 않는다. |

## Human-resources common SSOT tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `user_departments` | Department | 전사 공용 부서 마스터 SSOT다. | 실제 10개 물리 컬럼만 사용하며 계층 구조와 휴지통은 없다. `manager_id`는 직원과 연결된 선택 가능한 `auth_users.id`를 저장한다. 참조가 없는 오등록 건만 Guarded Hard Delete하고 조직개편·폐지는 `is_active = 0`으로 처리한다. |
| `user_positions` | Position | 전사 공용 직위·직책 통합 마스터 SSOT다. | 실제 10개 물리 컬럼만 사용하며 독립 직급 Master나 휴지통은 없다. `sort_no`는 표시순서, nullable `level_rank`는 업무상 등급 숫자이며 별도 SSOT가 아니다. 현재 직원·기간이력·인사발령·취업규칙 참조가 없는 오등록 건만 Guarded Hard Delete하고 미사용은 `is_active = 0`으로 처리한다. |
| `user_employees` | Employee | 직원 기본정보와 부서·직위·재직상태·현재 직무의 현재상태 SSOT다. | `employment_status`는 명시적 현재 재직상태이고 `job_id`는 현재 직무 캐시다. 입퇴사일은 문서상·실제 날짜를 보존한다. 직원 참조는 `user_employees.id`인 `employee_id`를 사용하며 `system_clients`와 직접 연결하지 않는다. `representative_qualification_id`는 복수 자격 원장 중 직원 모달에서 관리할 대표 자격증 한 건만 연결하며 이름과 파일은 원장에 저장한다. 재직·직무 현재값과 기간이력의 동기화는 인사발령 적용 Service만 담당하며 로그인 활성상태와 분리한다. `client_id` 제거는 forward-only Migration `20260814_01_remove_employee_client_link`가 소유한다. |
| `auth_users` | AuthUser | 로그인 계정, 인증·알림·승인·역할·잠금·접속상태와 계정 감사정보의 SSOT다. | 직원 화면은 `user_employees.user_id = auth_users.id`로 결합한다. 공용 직원 Table Setting 메타는 `user_employees` 25개 다음에 `auth_users` 23개 물리 컬럼을 원본 순서대로 합성하며, 두 테이블의 `id`는 각각 `id`, `auth_user_id` 공개 키로 구분한다. |
| `institution_job_assignments_jobs` | Job | 인사·노무 전체에서 재사용하는 직무 마스터다. | 직무별 자격·교육·프로젝트 적격성은 향후 별도 관계 테이블이 이 ID를 참조한다. 동일 직무 목록을 `system_codes`에 중복하지 않는다. |
| `institution_job_assignments_employment_status_histories` | EmployeeEmploymentStatus | 특정 날짜의 입사예정·재직·휴직·퇴직 상태를 재현하는 기간 이력 SSOT다. | `user_employees.employment_status`는 현재상태이며 인사발령 적용 Service가 동일 트랜잭션에서 이력과 함께 갱신한다. 로그인 활성상태와 독립이다. |
| `institution_job_assignments_department_histories` | EmployeeDepartmentAssignment | 직원 부서의 유효기간 이력 SSOT다. | 종료일 NULL은 현재 유효행이며 직원별 한 건만 허용한다. 시작·종료 인사발령 대상자를 보존하고 직원 마스터 현재 부서와 적용 Service가 원자적으로 동기화한다. |
| `institution_job_assignments_position_histories` | EmployeePositionAssignment | 통합 `user_positions`를 기준으로 직원 직위·직책의 유효기간 이력을 보존한다. | 별도 직책 마스터를 만들지 않는다. 종료일 NULL은 현재 유효행이며 적용 Service가 직원 마스터와 원자적으로 동기화한다. |
| `institution_job_assignments_leave_periods` | EmployeeLeave | 휴가와 분리된 휴직·복직 기간 SSOT다. | 직원별 활성 휴직은 최대 1건이다. 휴직·복직 원본 발령 대상자를 보존한다. |
| `institution_job_assignments_job_histories` | EmployeeJobAssignment | 직원 직무의 기간 이력 SSOT다. | `user_employees.job_id`는 현재 직무 캐시이며 발령 적용 Service만 이력과 원자적으로 동기화한다. 기간 중복은 Service 잠금 검증을 병행한다. |
| `institution_job_assignments_audits` | EmployeeAssignmentAudit | 직무·프로젝트·근무지 배치의 직접 등록·종료·정정 감사 증적이다. | 업무 상태 SSOT가 아니며 현재·기준일·목록 상태 계산에 사용하지 않는다. 도메인별 FK 하나, 물리 작업·출처·사유·Actor, JSON 전후값과 전역 UNIQUE `request_key`를 보존한다. 감사행 수정·삭제 API는 제공하지 않는다. |
| `institution_job_assignments_project_histories` | EmployeeProjectAssignment | 직원의 다중 프로젝트 기간배치 SSOT다. | 동일 프로젝트 재배치를 허용하고 시작일로 차수를 구분한다. `status_code`는 저장 당시 상태와 명시적 `CANCELLED`를 보존하며 기준일 표시는 기간에서 파생한다. 현재 주배치는 직원별 최대 1건이며 전체 기간 중복은 Service 잠금 검증을 병행한다. |
| `institution_job_assignments_workplace_histories` | EmployeeWorkplaceAssignment | 프로젝트 배치와 독립된 직원 근무지 기간이력 SSOT다. | `HEAD_OFFICE`, `PROJECT`, `BUSINESS_TRIP`, `REMOTE`, `OTHER`를 표현하고 특정 날짜의 근무지를 재현한다. 프로젝트 유형만 `project_id`를 필수로 하며 현재 활성 근무지는 직원별 최대 1건이다. |
| `institution_personnel_actions` | PersonnelAction | 인사발령 문서, 업무상태, 결재 포인터, 미래 적용, 정정·취소 원본의 SSOT다. | `issued_date`는 발령일, `action_date`는 효력일이다. 결재상태는 `user_approval_requests`가 소유하며 적용완료 문서는 직접 수정하지 않는다. |
| `institution_attendance_clock_events` | AttendanceClockEvent | 수정·삭제하지 않는 실제 출퇴근 원본 SSOT다. | 내부 `request_key`, 외부 `source_type_code + external_key`로 중복을 차단하고 오류 원본은 무효 표시한다. 휴게 상세나 법정기준 부족 등 계산 조건은 원천 INSERT를 롤백하지 않는다. |
| `institution_attendance_daily_records` | AttendanceDailyRecord | 직원·근무일별 재계산 가능한 근태 Projection이며 미마감·재오픈 월의 월별 실시간 누계 원천이다. 계약 일정, 실제 구간, 승인 휴가, `working_time_standard_id`, `public_holiday_standard_id`와 계산정책 version을 적용한 최신 결과를 보관한다. | `employee_id + work_date`가 유일하다. 계약 상세 휴게구간이 없어도 계약 예정근로는 `break_minutes` 총량을 차감한다. 실제근로는 실제 WORK/BREAK 원천으로 계산하며 실제 휴게 누락은 별도 확인 대상이다. `contract_excess_seconds`는 계약 예정초과, `calculated_overtime_seconds`는 주간 전체 재평가로 발생 날짜에 귀속한 일·주 법정 연장근로다. |
| `institution_attendance_work_segments` | AttendanceWorkSegment | 일별 WORK·BREAK·OUTSIDE 실제 시간 구간이다. CLOCK_IN→CLOCK_OUT은 자동 WORK, 해당 출퇴근 범위 안의 계약상 고정 휴게는 자동 BREAK로 생성하며 관리자는 실제 변경·추가 휴게와 외출을 정정할 수 있다. | 반개구간을 사용한다. WORK와 BREAK·OUTSIDE의 겹침은 차감 표현으로 허용하고, 동일 성격 구간끼리의 중복은 Service가 차단한다. |
| `institution_attendance_daily_exceptions` | AttendanceDailyException | 지각·조퇴·결근·누락·일정충돌·연속 출퇴근 중복 등 복수 예외 SSOT다. | `DUPLICATE_CLOCK_IN/OUT`은 서로 다른 시각의 연속 동일 원본을 보존한 채 표시한다. `daily_record_id + exception_type_code + source_type_code` 단일 행을 재사용한다. |
| `institution_attendance_monthly_closures` | AttendanceMonthlyClosure | 직원·월별 현재 마감상태와 현재 revision 포인터 SSOT다. | 집계 스냅샷은 저장하지 않고 현재 history만 참조한다. |
| `institution_attendance_monthly_closure_histories` | AttendanceMonthlyClosureHistory | 월 마감 revision별 불변 집계 스냅샷 SSOT이며 급여·원가·통계와 마감된 월별 현황의 확정 근태 소비 경계다. | 귀속월 마지막 법정 주간이 종료·재평가된 후에만 생성한다. `monthly_closure_id + revision` 및 `source_request_key`가 유일하고 과거 revision을 수정하지 않는다. 마감 상태에서는 현재 revision snapshot을 표시하고, 재오픈 이후에는 Daily 실시간 누계로 되돌아가며 새 revision 재마감 전까지 확정 소비를 중단한다. |
| `institution_attendance_audits` | AttendanceAudit | 근태 등록·재계산·정정·마감·재오픈의 변경 전후 감사 증적이다. | 현재 상태나 월 집계를 계산하는 SSOT로 사용하지 않는다. |

### TableSettings DB 원본 기본값 계약

- Migration `20260820_01_add_korean_column_comments`는 누락되어 있던 27개 업무 테이블의 421개 `COLUMN_COMMENT`를 한글 업무명으로 보강한다.
- 공용 TableSettings 기본값 복원은 물리 컬럼의 `COLUMN_COMMENT`를 사용컬럼명으로, `IS_NULLABLE`을 필수구분으로 사용한다.
- 이 Migration은 컬럼 타입, NULL 허용 여부, 기본값, 정렬순서와 저장 데이터는 변경하지 않는다.

| `institution_personnel_actions_targets` | PersonnelActionTarget | 발령 문서의 대상 직원과 대상별 적용결과·적용 Actor를 저장한다. | 문서 내 직원 중복을 금지하며 `application_status`, `application_error`, `applied_at`, `applied_by`가 대상별 적용 감사를 구성한다. |
| `institution_personnel_actions_changes` | PersonnelActionChange | 대상 직원별 타입 안전 변경명령과 변경 전후 스냅샷을 저장한다. | `change_type_code`는 일반 코드가 아니라 `PersonnelActionChangePolicy`가 소유하는 고정 시스템 실행명령이다. 범용 문자열·JSON EAV를 사용하지 않으며 FK·날짜·코드 물리 컬럼과 DB CHECK는 명령별 물리 저장 무결성을 방어한다. |
- `institution_leave_types`: 휴가 종류와 허용 신청단위·시간차 최소분·유급·잔액·증빙·발생방식·이월정책 SSOT. `LIMITED` 이월만 양수 제한분을 가지며 시간차 최소분은 `HOURLY` 허용 종류에만 설정한다.
- `institution_leave_grants`: 직원별 휴가 부여 업무행. 수동·계산확정·이월·과거이관 출처를 구분하고 계산확정만 구조화 계산 근거 JSON을 가진다.
- `institution_leave_requests`: 일반 휴가 및 승인 후 전체 취소 신청 헤더.
- `institution_leave_request_items`: 날짜별 신청구간과 근로계약·예정근로·휴게구간 계산 스냅샷.
- `institution_leave_usages`: 최종 승인된 휴가 사용 SSOT. 근태가 직접 조회한다.
- `institution_leave_ledger_entries`: 분 단위 휴가 잔액 증감 불변 원장. 부여·사용·복원·이월·소멸은 `grant_id`로 원 Grant에 귀속하고 관리자 조정만 `grant_id=NULL`이다. 잔액은 `SUM(amount_minutes)`로 계산한다.
- `institution_leave_audits`: 휴가 업무 변경 전용 감사 증적. 잔액과 현재상태 조회에는 사용하지 않는다.
- `institution_employment_contracts_break_schedules`: 근로계약 주간 일정별 선택적 예정 휴게구간이다. `break_minutes`가 계약상 총 휴게시간 SSOT이며 상세구간을 입력한 경우에만 그 합계가 총량과 일치해야 한다. 상세행 0건은 휴게 없음이 아니라 시각 미지정을 뜻한다.
## 2026-08-06 — 자격·교육관리

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| institution_qualifications_employee_records | Qualification | 직원별 자격·면허 원장 SSOT | 직원관리의 대표 자격증과 자격·교육관리가 동일 행을 조회·수정한다. `user_employees.representative_qualification_id`가 대표 행 하나만 연결하며, 만료·갱신일, 첨부, 검증 상태를 보존한다. |

## 2026-08-21 — 자격·교육 정책 SSOT 보강

| 테이블 | 도메인 | 책임 |
|---|---|---|
| `institution_qualifications_types` | Qualification | 자격 종류·유효기간·갱신 정책 원본 |
| `institution_qualifications_job_requirements` | Qualification | 직무별 자격 요구조건과 적용기간 |
| `institution_educations_job_requirements` | Education | 직무별 교육 요구조건과 적용기간 |
| `institution_qualification_education_policy_audits` | QualificationEducation | 자격·교육 정책 변경 통합 감사원장 |

`institution_qualifications_employee_records.qualification_type_id`는 자격 종류 원본을 참조한다. `institution_educations_courses`의 `recurrence_policy_code`, 주기·이벤트·법정기준 컬럼은 재교육 정책 SSOT이며 기존 `validity_months`는 호환 조회용으로만 남긴다.
| institution_qualifications_audits | QualificationAudit | 자격 등록·수정·삭제·검증·갱신 감사 | 상태 조회에 사용하지 않고 request_key 멱등성과 전후 스냅샷만 보존한다. |
| institution_educations_courses | EducationCourse | 교육과정 기준정보 SSOT | 교육 종류는 system_codes를 참조하고 법정·필수·수료증 정책을 관리한다. |
| institution_educations_employee_records | Education | 직원별 교육 이수 원장 SSOT | 교육기관·일시·시간·수료번호·유효기간·첨부를 보존한다. |
| institution_educations_audits | EducationAudit | 교육 이력 변경 감사 | 상태 조회에 사용하지 않고 request_key와 전후 스냅샷을 보존한다. |
### `system_work_teams`

- SSOT: 거래처 기반 작업팀 Master (`work-team`)
- 관계: 팀장은 `system_clients.id`, 정렬은 `sort_no ASC`
- 삭제 정책: soft delete → trash → restore 또는 ID 기반 dependency-guarded purge
- Actor: `created_by`, `updated_by`, `deleted_by` 원본을 `ActorHelper`로 표시명 확장

### `system_work_team_members`

- SSOT: 거래처 기반 작업팀 구성원
- 관계: `team_id` → `system_work_teams.id`, `client_id` → `system_clients.id`
- 작업팀 purge 시 직접 Dependency Consumer로 보호
### 신규 직원 인사 Baseline 기록 정책

- `user_employees`: 신규 직원의 현재 부서·직위·직무·재직상태 Master를 저장한다.
- `institution_job_assignments_department_histories`: 신규 직원의 최초 부서 기간이력을 저장한다.
- `institution_job_assignments_position_histories`: 신규 직원의 최초 직위·직책 기간이력을 저장한다.
- `institution_job_assignments_job_histories`: 직무가 지정된 신규 직원의 최초 직무 기간이력을 저장한다.
- `institution_job_assignments_employment_status_histories`: 신규 직원의 최초 재직상태 기간이력을 저장한다.
- 위 기록은 동일 직원 생성 트랜잭션에서 함께 생성되며 이번 작업에서 테이블·컬럼 변경은 없다.
### institution_educations_sessions

- 실제 한 번의 교육 일정 SSOT. 교육과정, 일정, 장소, 담당자, 강사와 `DRAFT/SCHEDULED/CANCELLED/COMPLETED` 상태를 저장한다.
- Migration: `20260821_05_create_education_sessions`.

### institution_educations_session_targets

- 교육 Session의 직원 단위 대상 Snapshot SSOT.
- `(session_id, employee_id)` UK로 중복을 차단하며 확인·참석·이수 및 제외 Audit 상태를 보존한다.

### institution_educations_employee_records.session_id

- 회사 교육 Session 결과는 nullable FK로 연결한다. 외부 개별교육 및 과거이력은 `NULL`을 유지한다.
- `(session_id, employee_id)` UK로 같은 Session 결과의 중복 교육이력을 차단한다.

## 2026-08-21 — 공용 Notification Core

| 테이블 | 책임 | 핵심 계약 |
|---|---|---|
| `system_notification_events` | 채널과 무관한 업무 사실 Event SSOT | `event_key` UK, 업무 원천·제목·내용·최소 JSON payload·Actor 보존 |
| `system_notification_recipients` | Event 수신자와 IN_APP 읽음 SSOT | `(event_id, recipient_user_id)` UK, 본인 읽음, Page Registry action target |
| `system_notification_deliveries` | 채널별 전달 상태 SSOT 및 향후 Durable Queue | `(recipient_id, channel_code)` UK, IN_APP은 즉시 `SENT` |
| `system_notification_channel_policies` | Event 유형별 채널·필수성·재시도·보존 정책 | 이번 Closure는 IN_APP 정책만 등록 |
| `system_notification_user_preferences` | 사용자별 선택 채널 설정 | MANDATORY IN_APP은 Preference보다 우선 |

`system_notifications`는 59건을 Core로 Backfill한 Legacy/Read-only 테이블이다. 삭제하지 않으며 신규 Runtime INSERT는 금지한다. Web Push Subscription과 외부 Provider 정보는 이번 Core에 포함하지 않는다.
# 2026-08-22 Evidence Link N:M·상용근로소득 보강

- `ledger_evidence_links`: Evidence↔Transaction 물리 Cardinality는 N:M이다. 활성 동일 `(evidence_type,evidence_id,target_type,target_id)` Pair만 `uk_evl_active_evidence_target_pair`로 차단하고 soft-delete Pair는 재연결할 수 있다.
- `ledger_evidence_metadata.transaction_cardinality`: Evidence Type별 `SINGLE_TRANSACTION`/`MULTI_TRANSACTION` 업무 Cardinality SSOT다. `PAYROLL_REPORT`는 `MULTI_TRANSACTION`이다.
- `institution_regular_employment_income_line_items`: 지급·공제·회사부담별 법정 자동계산값, 조정값, 실제 확정값과 조정사유 Snapshot. 법정검증 불가 시 `calculated_amount`·`adjustment_amount`는 `NULL`이고 확인된 `final_amount`만 보존할 수 있다.
- `institution_regular_employment_income_calculation_bases`: 계약·근태마감·휴가·법정기준·보험판정 계산근거 참조.
- `institution_regular_employment_income_audits`: 계산·조정·결재·회계반영·정정·취소 감사원장.
- `institution_regular_employment_income_accounting_links`: 상용근로소득 문서의 직원별 Evidence와 직원 급여 Transaction을 연결하는 멱등 Registry다. 최종 역할은 `PAYROLL_REPORT_EVIDENCE`, `EMPLOYEE_PAYROLL` 두 가지이며 두 역할 모두 급여 Item과 직원 Evidence를 필수로 참조하고 `payment_schedule_id`는 NULL이다. 기관별 채무는 최종승인 단계에서 거래로 생성하지 않고 직원 Evidence의 사용자부담 Line을 전표추천 단계에서 집계한다.
- `institution_regular_income_accounting_schedules`: 2026-08-26 책임분리 Migration에서 폐기한다. 지급예정은 급여 Closure Registry에 연결하지 않고 자금관리 SSOT가 독립적으로 소유한다.
- `ledger_transaction_items.regular_employment_income_line_item_id`, `ledger_transaction_settlements.regular_employment_income_line_item_id`: 상용근로소득 지급·공제·환급·회사부담 결과와 원천 Line의 명시적 FK다. 각 결과의 `statutory_standard_revision_id`와 `calculation_basis_id`가 적용 법정기준과 계산기초를 보존한다.
# social-insurance SSOT (2026-08-22)

- `institution_social_insurance_coverages`: 직원별 국민연금·건강보험·고용보험 실제 취득/적용제외 기간 SSOT. 장기요양은 건강보험에서 파생하고 예외만 저장한다.
- `institution_social_insurance_assessment_bases`: Coverage별 기준소득월액·보수월액·공식 보험료 산정대상 보수의 기간이력.
- `institution_social_insurance_audits`: Coverage와 산정기준 생성·변경·종료·정정 감사원장.

# workplace-size-period SSOT (2026-08-26)

- `institution_workplace_size_periods`: 회사·계산목적별 회사규모 적용기간의 불변 Revision SSOT. `business_size_code`가 법정 Matrix key이고 표시명은 Snapshot이다. 정정은 기존 행 UPDATE 없이 `previous_period_id`를 참조하는 새 Revision으로 보존하며 현재행은 후속 Revision이 없는 leaf다. `MANUAL_CONFIRMED`와 `HISTORICAL_IMPORT`는 공식자료와 구분해 근거설명·확정 Actor·확정일을 보존한다. 같은 `company_id + calculation_purpose_code`의 현재 leaf 기간은 중복할 수 없고 회사 범위 `request_key`로 멱등 처리한다. 데이터 존재 시 Down은 차단한다.
- `institution_regular_employment_income_line_items` 계산추적 확장: `application_status_code`는 `APPLICABLE/EXCLUDED/NOT_APPLICABLE`을 구분하고 계산기초·요율·끝수처리 전 금액·끝수처리 정책 및 법정기준/Coverage/회사규모 기간 FK를 보존한다. 비법정 지급항목은 관련 컬럼과 FK가 `NULL`일 수 있다. 산재보험은 상용근로소득 직원별 월 급여 Line 생성대상이 아니다.
- Migration `20260826_02_migrate_employment_insurance_business_size_codes`는 기존 고용보험 Matrix의 정확히 일치하는 4개 표시명 행에 안정적인 `business_size_code`를 추가하고 동적 입력 Schema를 코드 Select로 전환한다. 대응 표시명이나 행 수가 다르면 임의 이관하지 않고 `SIGNAL`로 중단하며 Down은 forward-only로 차단한다.
# 일용근로소득 사전구현 (2026-08-26)

- 적용 Migration은 `20260827_02_enable_daily_employment_income_with_company_ssot`이다. 상용근로소득과 동일하게 `company_id`만 `system_company.id`의 `utf8mb3_general_ci` 정의를 명시적으로 따르며, DB 적용 전 FK 형식 불일치가 확인된 `20260826_06`은 실행 기준으로 사용하지 않는다.

- `system_work_team_assignments`: `DAILY_WORKER` 거래처와 작업팀·프로젝트/본사 범위의 기간 배치 SSOT. 기간 중복은 Service의 잠금 조회와 시작일 UNIQUE로 차단한다.
- `institution_social_insurance_workplaces`: 프로젝트 또는 본사의 사회보험 사업장과 공식 관리번호 확인상태를 기간형으로 보존한다.
- `institution_daily_worker_social_insurance_coverages`: 일용근로자·보험사업장·보험종류별 `APPLICABLE`/`EXCLUDED`/`NOT_APPLICABLE` 기간 판정이다.
- `system_work_teams.business_unit`: 코드관리 `BUSINESS_UNIT`을 참조하는 팀의 기본 사업구분이다. 기존 작업팀은 전문건설업 업무자료였으므로 `CONSTRUCTION`으로 백필한다. 일용근로소득 수동 입력은 팀페이지의 활성 팀 전체를 참조하며 이 기본값으로 기존 팀을 숨기지 않는다.
- `institution_daily_employment_income_items.business_unit`: 일용근로소득 Item의 사업구분 Snapshot이다. 입력 순서는 사업구분 → 소속팀 → 작업자이며 소속팀은 팀페이지, 작업자는 거래처페이지의 활성 기준정보 전체를 참조한다. 수동 DRAFT 입력은 기존 작업팀 배치가 없어도 허용하므로 Workday의 `work_team_assignment_id`는 NULL을 허용하고, 배치가 유일하게 확인될 때만 ID를 보존한다.
- `institution_daily_employment_incomes`: 귀속연월·제목 기준 일용근로소득 문서 Header다. 지급예정일은 저장하지 않으며 거래일은 작업자별 실제 Workday의 최종일을 사용한다. 상용근로소득과 동일하게 별도 문서번호 컬럼을 사용하지 않는다. `id` 다음의 전역 `sort_no BIGINT UNSIGNED`를 목록 순번 SSOT로 사용하며 UNIQUE를 유지한다. `20260827_05_add_daily_employment_income_trash`에서 공용 휴지통용 `deleted_at`, `deleted_by`와 삭제일 인덱스를 추가한다. `20260827_06_add_daily_employment_income_counts`에서 Item 원본을 중복 제거해 계산하는 `worker_count`, `work_team_count` 물리 집계를 추가하며 클라이언트 입력값은 저장하지 않는다.
- `institution_daily_employment_income_items`: 작업자(거래처 `system_clients.DAILY_WORKER`)·현장(`system_projects` 프로젝트/본사)·소속팀(`system_work_teams` 팀)별 정산 및 증빙 생성 단위다. `sort_no`가 문서 안 작업자 정산행 순서의 SSOT다.
- `institution_daily_employment_income_workdays`: 근무일별 지급·세액·보험 계산 결과와 적용 배치·사업장 FK를 보존한다.
- `institution_daily_employment_income_lines`: `PAY`/`DEDUCTION`/`EMPLOYER_BURDEN` 계산 Snapshot과 법정기준·Coverage·사업장 FK를 보존한다. 상세 재조회는 저장된 `statutory_standard_id`로 법정기준을 JOIN하여 당시 적용 시작일·종료일을 Projection하며 현재 활성 Revision으로 과거 Snapshot을 덮어쓰지 않는다.
- `institution_daily_employment_income_lines` A-1 보강 (`20260828_04`): Workday UUID 또는 `ITEM`인 `workday_scope_key`로 Item Grain NULL UNIQUE 공백을 제거한다. `calculated_amount`와 nullable 실제 적용액 `final_amount`, DB Generated Stored `adjustment_amount`, 조정사유, 법정 계산원천·실제 적용원천 `system_codes.id` FK, 처리 Actor·시각을 보존한다. 기존 30건은 Scope만 Backfill하고 계산액·원천·처리정보는 추정하지 않는다.
- `system_codes` 소득계산 공용 SSOT (`20260828_03`): `INCOME_STATUTORY_CALCULATION_SOURCE`, `INCOME_ACTUAL_APPLICATION_SOURCE`, `INCOME_PAYMENT_CONFIRMATION_STATUS`, `INCOME_STATUTORY_CALCULATION_STATUS`의 중립 공용 코드를 보존한다.
- `ledger_evidence_daily_employment_income`: 최종승인 생성 Grain은 `승인문서 Document + Group + Worker Item`이다. 공용 업무분류, 귀속연월·근무일수·금액 합계와 사용자 검토상태를 보존하고, 지급예정일 및 그 raw 복제 컬럼은 `20260903_16`에서 제거했다. 거래일은 연결 Transaction 또는 작업자별 최종 실제 근무일이다.
- `institution_daily_employment_income_commands`: 저장·수정·삭제·상신·회수·Closure 재처리와 비과세 생성·확인·정정·근거자료 연결 명령의 전역 `request_key`와 서버 Payload hash를 보존하는 불변 멱등 원장이다. 문서 삭제 후에도 결과 재호출을 판정해야 하므로 문서 ID는 FK 없이 식별값으로 보존한다. 최종 소유 Migration은 실제 DB에 적용된 `20260827_04_create_daily_employment_income_commands`이며, 비과세 명령과 대상·결과 Revision 참조는 준비·운영 미적용 `20260827_21`이 확장한다.
- `institution_daily_employment_income_closures`: 최종승인 요청과 공식 계산 Revision·Source Hash·Payload Hash를 묶어 `PROCESSING/COMPLETED`를 보존하는 멱등 원장이다. MariaDB 10.11에서 적용할 수 없는 과거 복합 CHECK에 의존하지 않으며 최종 소유 Forward Migration은 `20260831_09_close_daily_employment_income_approval_accounting`이다.
- `institution_daily_employment_income_accounting_links`: Group×근로자 Item 1건당 Evidence와 근로자 지급 Transaction을 `EVIDENCE`·`WORKER_PAYMENT` 역할 및 결정적 Hash로 멱등 연결한다. 같은 근로자라도 Group이 다르면 합치지 않는다. 기관 Counterparty·납부고지 Grain이 확정되기 전에는 기관부담 Transaction 역할을 만들지 않는다.
- `institution_daily_employment_income_calculation_results`: 계산 Revision 안의 보험별 결과 Grain은 근로자뿐 아니라 `daily_employment_income_item_id`를 포함한다. 따라서 동일 근로자가 복수 Group에 속해도 Group별 Item 계산결과와 Snapshot을 독립 보존한다. `20260831_11_close_daily_calculation_result_group_worker_grain`은 데이터 변경 없이 고유키 계약만 교정한다.
- `system_codes.BUSINESS_UNIT.extra_data.daily_employment_income`: 사업구분별 `uses_project`, `requires_project`, `uses_work_team`, `requires_work_team` 정책 SSOT다. 본사·통신판매업은 프로젝트/팀 미사용, 전문건설업은 프로젝트/팀 필수로 시작한다.
- `system_projects.business_unit`: 프로젝트가 속한 사업구분이다. 기존 프로젝트는 전문건설업으로 백필하고 Item 사업구분과의 일치를 서버에서 검증한다.
- `institution_daily_employment_income_items.scope_project_key`, `work_team_scope_key`: 프로젝트·작업팀 NULL을 각각 `NO_PROJECT`, `NO_WORK_TEAM`으로 정규화하는 서버 생성키다. 문서·사업구분·두 범위키·작업자 UNIQUE로 최종 Item Grain을 보장한다. `work_scope_code`는 사용자 입력이 아니라 서버 파생 기술 Projection이다.

### 일용근로소득 독립 근무그룹 (`20260827_10`)

- `institution_daily_employment_income_groups`: 문서 안의 독립 카드형 업무입력 SSOT다. 사업구분·프로젝트·작업팀·그룹 작업내용과 프로젝트 현장용 고용보험·산재보험 임시 판정 상태·사유·보험별 공용 판정원천 FK를 보존한다. 본사 Group은 이 수동값을 사용하지 않는다. 판정원천 FK는 `UPDATE/DELETE RESTRICT`이고 상태·사유·원천 CHECK는 `COALESCE(...,FALSE)`와 바이너리 Trim 비교를 사용한다.
- `institution_daily_employment_income_items.daily_employment_income_group_id`: Item은 Group FK와 작업자 FK의 조합으로 유일하다. 사업구분·프로젝트·작업팀은 Group에서만 입력하며 Item에 중복 저장하지 않는다.
- `institution_daily_employment_income_items.work_type_code`, `work_description`: 그룹 작업내용과 별도로 작업자별 공종과 작업내용 원본을 보존한다. 동일 Group 안에서도 작업자마다 다른 공종·작업내용을 가질 수 있다.
- `20260827_14_restore_daily_income_group_description`: `_12`에서 제거했던 `institution_daily_employment_income_groups.work_description VARCHAR(500) NOT NULL`과 공백 금지 CHECK만 카드형 입력계약에 맞춰 forward-only로 복원한다. Header 문서번호는 복원하지 않는다.
- `institution_daily_employment_income_workdays`: 실제 근무일과 날짜별 단가 및 현재 계산내역을 보존한다. 기관별 확정 Revision·배부·대사 SSOT를 대신하지 않는다.
- 기관별 확정 계산 저장구조는 `docs/projects/DailyEmploymentIncomeInstitutionProjection20260827.md`의 승인 대기 설계를 따른다. 승인 전 신규 테이블을 운영 DB에 만들지 않는다.
- `20260827_18_add_daily_income_actual_work_minutes` (2026-08-28 운영 적용): Workday에 휴게시간을 제외한 실제 근로 분 `actual_work_minutes SMALLINT UNSIGNED NULL`을 저장한다. NULL은 과거자료 미확인 호환값이며 신규 Workday는 1~1440 정수 분이 필수다. 문서 전체 동일 근로자·동일 날짜의 Group 합계도 1,440분을 초과할 수 없다.
- `20260828_01_add_daily_income_calculation_note` (2026-08-28 운영 적용): Workday에 선택 입력 산정내역 `calculation_note VARCHAR(500) NULL`을 저장한다. 값은 Trim 후 저장하며 공백 문자열과 500자 초과를 차단한다. 기존 Workday는 Backfill 없이 NULL을 유지하고 별도 인덱스를 만들지 않는다.
- `20260829_08_remove_daily_workday_adjustment_amount` (운영 미적용): 과세증감·비과세증감과 중복되는 Workday 지급 `adjustment_amount` 및 `PAY_ADJUSTMENT` Line을 제거한다. 적용 전 0원이 아닌 값이 있으면 Migration을 차단한다. 보험·세액 Line의 자동계산액과 최종 적용액 차이를 보존하는 `institution_daily_employment_income_lines.adjustment_amount`는 별도 감사 필드이므로 유지한다.
- `20260828_02_add_daily_income_non_taxable_reason`: Workday의 비과세증감에 필요한 적용사유 `non_taxable_reason VARCHAR(500) NULL`을 저장한다. 비과세금액이 0원이 아니면 Trim된 적용사유가 필수이며 별도 비과세 근거자료 문자열 컬럼은 만들지 않는다.
- `institution_daily_employment_income_non_taxable_revisions` (준비·운영 미적용, `20260827_19`): 양수 `applied_amount`, Workday 또는 기간 범위, 법정기준 Revision, 확인·정정 계보를 보존하는 비과세 근거 SSOT다. 확정행은 직접 수정하지 않고 후속 Revision으로 정정한다.
- `institution_daily_employment_income_non_taxable_audits` (준비·운영 미적용, `20260827_21`): 비과세 Command·Revision별 상태변경과 전후 JSON Snapshot, Actor Token, request key, 서버 Payload hash를 보존하는 추가전용 감사원장이다. Snapshot은 감사증적이며 Revision·Line 업무 SSOT를 대체하지 않는다.
- `institution_daily_employment_incomes` Header의 `description VARCHAR(500)`과 `memo TEXT`는 문서제목 다음의 비고·내부 메모를 보존한다. 추가 소유자는 `20260827_22_add_daily_income_header_description_memo`다.
- `institution_daily_employment_income_calculation_revisions` (준비·운영 미적용, `20260827_15`): 문서별 계산 Revision 번호와 서버 source hash, 계산정책 버전, 계산·확정·STALE·실패·대체 상태를 보존하는 기관계산 불변원장이다.
- `institution_daily_employment_income_calculation_results`: 세금은 근로자·근무일·지급회차, 보험은 Item·보험사업장·근로자·적용기간 Grain으로 법정기준과 자동·확정 근로자/사용자 금액을 보존한다. 지급예정일은 Grain에서 제거했다. 보험 Result는 가입자격 상태·사유·부족입력·가입자격 Revision·고용기간·경과조치 Fact·합산 Group과 버전 Snapshot을 함께 저장한다. `CONFIRMATION_REQUIRED` 금액은 NULL이어야 하며 숫자 0으로 확정하지 않는다. 현재 MariaDB가 FK 컬럼 기반 GENERATED를 거부하므로 NULL-safe UNIQUE용 물리 Scope Key를 Service가 공식값과 함께 확정한다.
- `institution_daily_employment_income_allocations` (준비·운영 미적용, `20260827_15`): 기관 확정액을 Group·Item·Workday에 결정적으로 배부하고 배부기초·분자·분모·잔액 수령행·결정순위를 보존한다.
- `institution_daily_employment_income_reconciliations` (준비·운영 미적용, `20260827_16`): 계산 Revision별 source hash와 대사 통과·실패·STALE 및 차단 오류 수를 보존한다.
- `institution_daily_employment_income_reconciliation_checks` (준비·운영 미적용, `20260827_16`): 세금·보험 Allocation, Item·Group·Header 합계와 실지급액의 원천·대상·차이 및 차단 여부를 보존한다.
- `institution_daily_employment_income_lines` 보강 (준비·운영 미적용, `20260827_19`): PAY Line의 과세구분, 비과세 Revision FK와 Workday/기간 범위키를 추가해 Item 전체·기간·특정 Workday 범위의 NULL UNIQUE 공백을 제거한다.
- `system_attachments`, `institution_daily_income_non_tax_revision_attachments` (준비·운영 미적용, `20260827_20`): 공용 파일 메타데이터·SHA-256·비공개 객체키와 비과세 Revision 증빙 Link 상태를 분리 보존한다. 활성 DRAFT/LOCKED Link가 있는 원본은 삭제할 수 없다.
- `institution_social_insurance_workplaces.business_unit`: 본사와 통신판매업처럼 프로젝트가 없는 사업구분도 서로 다른 보험사업장을 Resolve할 수 있도록 회사·사업구분·프로젝트 범위를 기간형으로 구분한다.

## 2026-08-29 일용근로자 보험 가입자격 Closure

| Table | Grain | Responsibility |
|---|---|---|
| `institution_daily_employment_income_line_backfill_audits` | Migration × 원 Line 식별값 | 기존 Line의 호환 백필 전후 Snapshot·결정규칙·행 해시·검증결과를 독립 보존하며, 가변 Line과 물리 FK를 맺지 않음 |

- 프로젝트 일반 날짜, Workday, Coverage는 판정 사실로 자동 전환하지 않으며 자동 Backfill은 0건이다.
- Item과 계산 Result는 사용한 ID 및 불변 Snapshot만 소유한다. 확정행은 UPDATE하지 않고 하나의 leaf를 추가한다.
# 2026-08-31 법정기준 선택코드 SSOT

- `system_codes`: 법정기준 Header와 보험 가입자격 기준값 카드의 선택형 업무코드를 소유한다. Forward Migration `20260831_06_unify_statutory_standard_select_codes`는 기간상태를 포함한 15개 그룹·34개 결정적 코드행을 등록하고, 보험 5종 `STATUTORY_STANDARD_TYPE.extra_data.field_sets.eligibility`의 Select 17개를 `SYSTEM_CODES` 참조계약으로 전환한다. Revision·Source·Calculation Result 업무행은 변경하지 않는다.
- 신규 그룹: `STATUTORY_POLICY_COMPONENT`, `STATUTORY_EMPLOYMENT_TYPE`, `STATUTORY_WORK_SCOPE`, `STATUTORY_CONDITION_COMBINATION`, `INSURANCE_ELIGIBILITY_DECISION`, `INSURANCE_ELIGIBILITY_RESULT`, `INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE`, `INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY`, `INSURANCE_ELIGIBILITY_MONTH_JUDGMENT`, `INSURANCE_ELIGIBILITY_INCOME_BASIS`, `INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE`, `INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD`, `INSURANCE_ELIGIBILITY_TRANSITION_POLICY`, `INSURANCE_ELIGIBILITY_TRANSITION_STATUS`, `STATUTORY_STANDARD_PERIOD_STATUS`.
- 기존 `STATUTORY_STANDARD_TYPE`, `STATUTORY_ROUNDING_METHOD`, `SOCIAL_INSURANCE_CONFIRMATION_STATUS`는 같은 책임으로 재사용하며 중복 그룹을 만들지 않는다.
### ledger_transaction_projection_repairs

- 승인 후 생성된 Transaction Projection 정정의 변경 전·후와 사유를 보존하는 INSERT 전용 감사 SSOT이다.
- 승인 원천, 결재이력, Evidence Revision과 분리하며 업무자료 삭제와 연쇄 삭제되지 않도록 FK를 두지 않는다.
- `request_key`는 동일 정정의 멱등키이며 완료된 감사행의 UPDATE·DELETE는 허용하지 않는다.
- Snapshot에는 거래 구조 식별값과 금액만 저장하고 개인정보·Evidence 전체 Snapshot·Secret은 저장하지 않는다.

## 사업소득 P1 Table (2026-09-03)

| Table | Grain | Responsibility |
|---|---|---|
| `system_client_tax_profiles` | 거래처 × 비중복 유효기간 | 소득자 세무 적격성 코드와 검증 Actor |
| `institution_business_incomes` | 귀속연월 문서 | 제목·비고·메모, 업무·계산·결재·지급·신고·제출 상태와 Group·Item 기준 건수·지급·공제·실지급 확정 합계 Snapshot Header. `memo`는 `20260903_17`에서 추가한다. |
| `institution_business_income_groups` | 사업구분 × 프로젝트 × 작업팀 | 동일 업무귀속 Group. 지급예정일은 소유하지 않는다. |
| `institution_business_income_items` | 소득자 × 개별 용역 정산 건 | 실제 거래일, 작업내역 확정금액 합계, 지급·공제·실지급액과 세무 프로필 불변 Snapshot. `gross_payment_amount`는 직접입력값이 아니라 활성 작업내역 `final_amount` 합계다. |
| `institution_business_income_work_lines` | 소득자 정산 Item × 외주 작업내역 | 품명·규격·단위·수량·단가·계산금액·증감액/사유·확정금액. `item_unit_name`은 코드관리 `UNIT`의 활성 코드 또는 한글명을 서버에서 확인해 표준 한글명으로 저장한다. 소득자 한 명에 N건을 허용하며 중복 설명인 `calculation_note`는 `20260903_20`에서 제거한다. |
| `institution_business_income_calculation_revisions` | 문서 × 계산 Revision | 계산 source hash와 정책 준비상태 |
  - `institution_business_incomes.withholding_date`는 문서 전체의 신고·법정기준일이며 사업소득세·개인지방소득세 Revision 조회일로 사용한다. 소득자 Item의 `transaction_date`는 외주 용역 거래일과 지급 Transaction 거래일로 유지한다.
  - `institution_daily_employment_incomes.withholding_date`는 문서 Header의 신고·법정기준일이다. 소득세·개인지방소득세 Revision은 이 날짜를 사용하고, 근무사실·최저임금·지급 Transaction은 `work_date` 계약을 유지한다.
  - `ledger_evidence_salary_report.raw_withholding_date`, `ledger_evidence_daily_employment_income.raw_withholding_date`, `ledger_evidence_business_income.raw_withholding_date`는 승인 당시 Header 원천징수일 Snapshot이다.
| `institution_business_income_calculation_lines` | Revision × Item × 계산 Line | 총지급·사업소득세·개인지방소득세 계산원본 |
| `institution_business_income_commands` | 멱등 request key | 쓰기 Command 결과 추적 |
| `institution_business_income_closures` | 문서 × 승인요청 | 최종승인 후속처리 Closure |
| `institution_business_income_artifact_links` | 사업소득 Item | 최종승인으로 생성된 Evidence와 Transaction ID의 멱등 산출물 원장. 전표·분개 Registry가 아니다. |
| `ledger_evidence_business_income` | 승인된 사업소득 Item | 공용 Evidence Header와 승인 원천 raw·hash Snapshot |
| `ledger_evidence_business_income_raw_lines` | Evidence × 원 Calculation Line | 승인 당시 계산 Line 전체 불변 보존 |
| `ledger_evidence_business_income_work_lines` | Evidence × 원 외주 작업내역 | 승인 당시 작업내역 N건과 산정값·원 Source ID·Hash 불변 보존 |
| `ledger_evidence_salary_report_lines` | 급여 Evidence × 승인 급여 Line | 승인 당시 지급·공제·사용자부담 Line의 계산기초·요율·끝수처리·조정·확정금액과 법정기준을 불변 보존 |

- `ledger_evidence_business_income`은 `raw_gross_payment_amount`, `raw_income_tax_amount`, `raw_local_income_tax_amount`, `raw_total_deduction_amount`, `raw_net_payment_amount`를 승인 금액 SSOT로 사용한다. 세금계산서형 `provider_*`, `supply_amount`, `vat_amount`, `service_amount`, `total_amount`는 `20260903_24_align_income_evidence_originals`에서 제거한다.
- 신규 승인행은 세 유형 모두 `INTERNAL_APPROVAL` 원본이며 공용 증빙 검토상태 `COMPLETED`로 저장한다. 결재 승인 사실은 승인요청·승인 Actor·승인시각 필드가 별도 보존한다. 급여·일용의 전환기 구 금액 컬럼은 운영 호환을 위해 유지하지만 신규 조회·상세·검증은 `raw_*`만 SSOT로 사용한다.
- 운영 기준선에는 `ledger_evidence_business_income`가 존재하지 않아 P1 Migration이 신규 생성한다. 전역 Evidence 순번은 폐기된 구조이므로 신규 테이블은 로컬 `sort_no`만 사용한다.
- Forward Migration `20260903_18_create_business_income_work_lines`는 운영 사업소득 Item·Evidence 0건을 Preflight한 뒤 원천/증빙 작업내역 테이블과 기타공제 사유 컬럼을 추가한다. Trigger나 데이터 보정 DML은 만들지 않는다.
- Forward Migration `20260903_21_remove_business_income_other_deduction`은 운영 사업소득 자료 0건을 Preflight한 뒤 Header·Item·Evidence의 기타공제 컬럼 5개를 제거하고 계산 Line을 총지급·사업소득세·개인지방소득세로 제한한다. Trigger나 데이터 보정 DML은 만들지 않는다.
# 2026-09-03 법정기준 Revision Supersession SSOT

- `system_statutory_standard_supersessions`: `system_statutory_standards` Revision 사이의 불변 선형 정정·대체 관계를 보존한다. `predecessor_revision_id`와 `successor_revision_id`는 각각 UNIQUE이며 원 Revision FK를 변경하지 않는다. 동일 Type·정책 구성요소·고용형태·업무 Scope·추가 Dimension만 연결할 수 있다.
- 자기참조·분기·병합·cycle 차단, 확정 Standard·Source의 UPDATE/DELETE 금지, 동시성 잠금은 명시적 Application Service가 담당한다. 계산정책 정정은 신규 Revision·신규 Source INSERT와 Supersession 관계 INSERT만 허용하며 DB Trigger를 사용하지 않는다.
# Trigger 제거 정책 (2026-09-03)

- 법정기준·사업소득 관련 Trigger 10개는 `20260903_13_remove_statutory_and_business_income_triggers`로 제거한다.
- 기존 FK·UNIQUE·CHECK는 유지하며, 업무 검증과 동시성 잠금은 명시적 Application Service가 담당한다.
- 운영 DB Trigger 기준 수는 0이다. Trigger 재도입은 사용자 사전승인이 필요하다.
# 2026-09-03 Comment SSOT 보완

- `20260903_14_complete_database_korean_comments`: 캘린더 보호영역을 제외한 Application BASE TABLE 163개 중 문제 Table Comment 3건과 문제 Column Comment 476건을 한글 SSOT로 보완한다. 컬럼 추가·삭제·타입 변경과 DML은 없다.
- 전체 변경 Manifest: `docs/projects/DatabaseCommentSsotManifest20260903.json`
- 사업소득 Evidence 금액은 `raw_gross_payment_amount`, `raw_total_deduction_amount`, `raw_net_payment_amount`가 SSOT이며 세금계산서형 합계 컬럼은 사용하지 않는다.
