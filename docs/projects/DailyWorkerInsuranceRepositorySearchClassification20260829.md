# 일용근로 보험 Closure Repository Search 분류

## 검색 기준

`480`, `actual_work_minutes`, `calculate`, `InsuranceEligibilityResolver`, `CONFIRMATION_REQUIRED`, `application_status_code`, `supersedes_`, `workday_scope_key`, `revision_scope_key`, `period_scope_key`, `workplace_group`, Snapshot, SearchForm, DataTable, Excel, Attachment를 `app`, `public`, `routes`, `tests`, `tools`, `docs` 전체에서 확인했다.

## A. 정상 사용

- 공식 계산 Call Chain: `DailyEmploymentIncomeService::calculate` → `calculateItemInsuranceLines` → `DailyEmploymentIncomeInsuranceEligibilityService::resolveItem` → `InsuranceEligibilityResolver::resolve`.
- `actual_work_minutes`: Workday 입력·저장·월 집계·계산 source hash·Excel Projection에서 사용한다. Runtime 기본값은 두지 않는다.
- `480`: 현재 잔존 39개 파일 중 일용근로 관련 값은 정순옥·정책 경계·시각 Fixture의 명시적 입력과 기대값이다. 사용자 신규입력 기본값이 아니다.
- `CONFIRMATION_REQUIRED`, `application_status_code`: Resolver 결과, 계산 Line, Preflight, UI 카드의 정상 상태 계약이다.
- `supersedes_*`: 고용기간·경과조치 Fact·Group·Member의 append-only 정정 계보다.
- `workday_scope_key`, `revision_scope_key`, `period_scope_key`: 기존 Line/Revision의 NULL-safe Grain과 읽기 전용 Snapshot 호환키다.
- `workplace_group`: 2025-07-01 국민연금 건설일용 현장 합산의 Model·Service·Migration·Result Snapshot에서 사용한다.
- SearchForm·DataTable·Excel·Attachment: 기존 공용 컴포넌트와 공용 Attachment Storage Adapter를 재사용한다.

## B. 제거 완료

- 상세 열기 `calculate`: `openDetail`은 저장 Snapshot만 읽고 `calculateDocument`를 호출하지 않는다. `daily_employment_income_detail_autocalculation_guard_contract.mjs`가 이를 고정한다.
- 근로시간 480분 Runtime 기본값: 제거 완료. 남은 480은 Fixture 명시값이다.
- Workday 지급 `adjustment_amount`와 `PAY_ADJUSTMENT`: Runtime 생성·저장·복사·집계에서 제거했고 운영 미적용 Migration `_08`로 물리 컬럼 제거를 준비했다.
- 2025 합산 후처리: 각 기능별 재조합 없이 Eligibility Service 결과 DTO를 Calculation Result Snapshot에 직접 전달한다.
- 중복 Workday와 중복 지급 Line: Workday ID는 한 번만 합산하고 지급 Line 중복 참조는 서버에서 차단한다.

## C. 잔존 차단항목

- 실제 `DailyEmploymentIncomeService` 공개 `calculate` 경로를 통한 2025 Group 21개 격리 Fixture는 아직 완결 검증 대상이다. 현재 21개는 공식 Eligibility Service→Resolver 경로이며 최종 공개 Calculate Payload Fixture가 추가로 필요하다.
- Group·Member 두 Connection 동시성 Runtime Fixture, Snapshot 전후 전체행 Hash Runtime Fixture, 상태별 승인 불변 Runtime Fixture가 남아 있다.
- API 인증·권한 Matrix는 Route의 `view/save/correct` 분리까지 반영했으나 실제 역할별 세션 통합 Fixture가 남아 있다.
- 운영 DB에는 `_01~_08` 준비 Migration이 적용되지 않아 운영 보험사업장·Fact·Group·Member ID가 없다. 브라우저 사실관리 시나리오는 승인된 운영 적용 전 실행할 수 없다.
