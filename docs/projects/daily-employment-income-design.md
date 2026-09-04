# 일용근로소득 사전구현 설계

문서 Header 아래 Item은 작업자(거래처)·현장(프로젝트/본사)·소속팀(팀) 조합의 정산 단위다. Workday는 실제 계산 단위이며 당시 작업팀 기간 배치와 보험사업장을 참조한다. Line은 지급·공제·사용자부담 결과 및 법정기준 추적정보를 정규 보존한다.

문서와 후속 생성의 Cardinality는 다음으로 고정한다. 귀속월 문서 Header 1건 아래 Item N건을 저장하고 Item은 `project_id/scope_project_key + work_team_id + worker_client_id` 조합으로 유일하다. 사회보험 Coverage·보험사업장·회사부담과 현장 원가는 Item/Workday/Line 단위로 계산·보존한다. 결재요청은 Header 1건을 대상으로 하며 최종승인 후 세무 Evidence와 지급 Transaction Header는 동일 `income_year_month + worker_client_id`의 Item을 작업자별로 묶어 각각 1건 생성한다. 거래일은 작업자별 최종 실제 근무일이며, 프로젝트·팀별 금액과 사회보험 근거는 Evidence Detail 및 Transaction Item/Settlement가 원본 Item을 참조해 분리 보존한다.

예를 들어 작업자 A가 같은 달 프로젝트1·팀1과 프로젝트2·팀2에 근무하면 원천 Item은 2건이다. 사회보험과 현장원가는 각 Item별로 처리하지만 세무 Evidence Header와 작업자 지급 Transaction Header는 A 기준 1건이다. 그 아래 상세행은 프로젝트1과 프로젝트2로 분리되며 합계는 두 Item의 작업자 기준 합계와 일치해야 한다. 동일 작업자의 같은 근무일을 여러 Item에 중복 등록하지 않는 현재 검증도 유지한다.

## 입력 단계와 현장관리 확장 경계

현재 입력방식은 `MANUAL` 단계다. 사용자가 모달에서 프로젝트, 팀, `DAILY_WORKER` 거래처, 근무일과 금액을 직접 선택하고 서버가 작업팀 기간배치·보험사업장·법정기준을 검증한 뒤 Item/Workday/Line으로 저장한다. 자동 후보자료가 없더라도 계산 및 저장 불변식은 완전하게 동작해야 한다.

후속 `FIELD_CONFIRMED_IMPORT` 단계에서는 현장관리의 계약관리 → 기성관리 → 거래관리 → 시공기성결재 최종승인 결과를 기준으로 현재 사용자가 직접 만드는 것과 동일한 Item 초안을 생성한다. 현장자료 불러오기는 후보 생성만 담당하며 계산 결과나 Header 합계를 직접 저장하지 않는다. 사용자가 후보를 확인한 뒤 현재 `calculate()`와 `save()` 경로를 그대로 사용한다. 상용근로소득의 대상직원 불러오기와 같은 UX를 사용하되 일용근로소득은 프로젝트·팀·작업자·근무일 원천을 함께 가져오는 점이 다르다.

수동 입력 단계에서는 근무일·지급항목·근무배치가 유효하면 세금 Preview와 DRAFT 저장을 허용한다. 사회보험 사업장·Coverage·보험료 정책이 미확정인 근무일은 0원으로 확정하지 않고 `CONFIRMATION_REQUIRED`로 저장하며, 이 상태는 결재 상신 전 Preflight에서 해소해야 한다.

향후 Source 추적 컬럼은 현장관리 SSOT 도메인명, 최종승인 결과 PK, 정정·취소 계약이 확정된 뒤 신규 Migration으로 추가한다. 그 전까지 `MANUAL` 문자열이나 임의 원천 ID를 DB에 저장하여 미래 도메인을 선점하지 않는다. 도입 시에는 원천 Header뿐 아니라 각 Item/Workday가 어느 승인 결과에서 왔는지 추적하고, 재불러오기 멱등키와 이미 저장된 수동조정 보존정책을 함께 확정한다.

최종승인은 Item별 Evidence 1건, 근로자 거래 1건, 거래 Item, Settlement, Evidence Link 1건을 동일 Transaction에서 생성하는 것이 목표다. 기관 거래·지급예정·전표는 이 단계 책임이 아니다. Evidence 최초 상태는 `CORRECTION_REQUIRED`이며 업무분류 완료 후에만 `COMPLETED`가 된다.

현재 단계에서는 DDL, 조회 페이지, 선택목록, 법정기준 계산 Preview까지만 활성화했다. 저장·결재·Closure는 임시 MySQL의 실패주입 및 재호출 멱등성 검증을 통과하기 전까지 명시적으로 차단한다.

## 상용근로소득 UI 기준선 비교

상용근로소득이 사용하는 `ui-search.php`, `ui-table.php`, `SearchForm`, 서버형 `createDataTable`, TableSettings 계약을 일용근로소득도 그대로 사용한다. 일용 화면에 있던 독자 검색 Form, 직접 HTML Table 렌더링, 수동 검색 이벤트와 직접 Reload는 제거했다. 별도 Pagination·Sort·컬럼설정·Toast를 만들지 않는다.

일용근로소득 목록의 TableSettings 식별자는 `daily-employment-income` metaDomain, `institution.income-data.daily-employment` pageKey, `daily-income-table` tableKey, `institution.income-data.daily-employment.daily-income-table.v1` 저장키다. Header 물리컬럼과 Item 원본에서 동기화한 작업자 수·소속팀 수 물리 집계를 사용하고 Item 수·Evidence 상태 가상 Projection은 결합하지 않는다. 관리열은 마지막에 두며 상용근로소득과 동일한 `createDataTableFormSettings()` 계약으로 Modal 입력 라벨과 필수구분을 연결한다.

검색은 공용 검색폼이 귀속연월·제목·근무범위·프로젝트·작업팀·일용근로자·문서상태·결재상태 조건을 직렬화한다. 목록 API는 `{success,data:{rows,total,filtered},message}` 계열 공용 응답을 사용한다.

Excel Manager, 엑셀 업로드·다운로드, 공용 휴지통, 복원·영구삭제, 출력, 행 드래그 정렬은 이 화면의 책임이 아니다.
