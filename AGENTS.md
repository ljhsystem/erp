# 프로젝트 공통 규칙

1. 한글 깨짐 금지
2. UTF-8 (BOM 없음)
3. LF 사용
4. php -l 통과 필수

위 규칙 실패 시 다음 작업 진행 금지.

5. 한글 품질 검사 의무화

- 수정 파일(`php`, `js`, `css`, `md`)에는 한글 깨짐 패턴 검사를 반드시 수행한다.
- 아래 문자열이 하나라도 발견되면 작업은 실패로 처리한다.
- 패턴:
  - `?뚯`
  - `?뱀`
  - `?붿`
  - `?꾩`
  - `?대`
  - `?놁`
  - `硫`
  - `媛`
  - `湲`
  - `�`
- 발견 시 즉시 다음 작업을 중지한다.
- 발견 시 반드시 원인 분석, 문구 복구, 재검증까지 완료한 뒤에만 다음 작업을 진행한다.
- 보고 항목:
  - 깨진 문자열 개수
  - 복구 문구
  - 검사 결과
- `php -l` 통과만으로는 완료 처리할 수 없다.

범위 외 수정 금지.

DB 변경은 별도 승인 필요.

분석 요청 시 수정 금지.

수정 후 반드시:

* php -l 결과 보고
* 변경 파일 목록 보고
* 변경 전후 결과 보고

## Actor 처리 규칙

- 생성자(`created_by`), 수정자(`updated_by`), 삭제자(`deleted_by`), 승인자(`approved_by`), 반려자(`rejected_by`), 복구자(`restored_by`), 처리자(`processed_by`), 업로드자(`uploaded_by`) 등 모든 Actor 필드는 공용 규칙을 따른다.
- DB에는 Actor ID(UUID) 또는 Actor Token만 저장한다.
- 저장 시 Actor 값은 반드시 `ActorHelper::user()` 또는 `ActorHelper::system()` 등 `ActorHelper`를 통해 공급받는다.
- `AuthHelper::userId()`, `$_SESSION`, `$userId`, `$adminId` 등을 Actor 저장값으로 직접 저장하지 않는다.
- Actor 문자열(`USER:`, `SYSTEM:`, `ADMIN:`, `EMPLOYEE:` 등)의 해석과 표시명 생성은 반드시 `ActorHelper`에서만 수행한다.
- 조회/API 응답은 `ActorHelper::enrichActorNames()` 또는 `ActorHelper::enrichActorNamesRow()`를 통해 `*_by_name` 표시 필드를 제공한다.
- 표준 표시 필드명은 `created_by_name`, `updated_by_name`, `deleted_by_name`, `approved_by_name`, `rejected_by_name`, `restored_by_name`, `processed_by_name`, `uploaded_by_name` 형식을 사용한다.
- Actor 전용 표시명 필드는 신규 생성하지 않으며, 기존에 추가된 경우 제거 대상으로 분류한다.
- SQL에서 `CONCAT('USER:'`, `CONCAT('SYSTEM:'`, `CONCAT('ADMIN:'`, `CONCAT('EMPLOYEE:'` 등으로 Actor 표시명을 생성하지 않는다.
- JS(UI)는 Actor 문자열을 직접 가공하지 않는다.
- JS에서 `replace()`, `split()`, `substring()`, `trim()`, 정규식 등으로 `USER:`, `SYSTEM:`, `ADMIN:`, `EMPLOYEE:` 접두사를 제거하거나 해석하지 않는다.
- UI는 `*_by_name`을 우선 출력하고, 없을 경우에만 원본 `*_by` 값을 fallback으로 사용한다.
- 엑셀 다운로드와 공용 컬럼 렌더링도 동일한 `*_by_name` 표시 규칙을 따른다.
- 새로운 화면, 신규 기능, 리팩토링에서 `created_by`, `updated_by`, `deleted_by` 등 Actor 원본 필드를 직접 출력하지 않는다.
- 화면별 Actor 표시 로직, Actor 문자열 파싱 로직, 별도 Actor 표시 필드 생성 로직을 새로 구현하지 않는다.
- Actor 표시는 반드시 `ActorHelper`, `actorDisplay()`, `actorColumn()`, `actorExcel()`, `type: 'actor'` 공용 구조를 사용한다.
- Actor 처리 공용 구조를 사용할 수 없는 예외가 있으면 구현 전에 사유와 영향 범위를 먼저 보고한다.

## SSOT 보호 규칙

- 다음 공용 SSOT는 변경하거나 우회 구현하지 않는다.
- Actor SSOT는 `ActorHelper`, `actorDisplay()`, `actorColumn()`, `actorExcel()`, `type: 'actor'`를 기준으로 한다.
- Transaction SSOT는 `business_unit`, `transaction_direction`, `operation_type`, `import_type`, `source_type`를 기준으로 한다.
- 새로운 화면 또는 기능 개발 시 기존 SSOT를 우회하는 개별 구현을 금지한다.
- 신규 구현과 리팩토링은 공용 SSOT를 사용하도록 개발한다.
- 공용 SSOT를 사용할 수 없는 예외가 있으면 구현 전에 사유와 영향 범위를 먼저 보고한다.

## ERP 개발 표준 규칙

### 0. Architecture Guide

- 도메인 생성 또는 구조 개편 시작 전 반드시 SSOT 도메인명을 먼저 확정한다.
- SSOT 도메인명은 Route, Controller, Service, Model, View, JS, CSS, Permission, PageRegistry에서 동일하게 사용한다.
- 허용 예시: `company`, `brand`, `cover`, `client`, `project`, `bank-account`, `card`, `work-team`
- 하나의 도메인에 대해 `brand`, `brand_logo`, `brand-logo`처럼 복수 SSOT를 생성하는 것을 금지한다.
- 도메인명 표준이 확정되지 않은 상태에서 신규 파일, 신규 Route, 신규 권한, 신규 페이지 레지스트리 항목을 추가하지 않는다.
- 도메인명 변경이 필요한 경우 영향 범위를 Route, Controller, Service, Model, View, JS, CSS, Permission, PageRegistry 기준으로 먼저 조사한다.

### 1. Controller 규칙

- Controller는 요청/응답, 파라미터 수집, 권한 흐름, 트랜잭션 진입점만 담당한다.
- 업무 규칙, DB SQL, 복잡한 상태 판단은 Controller에 두지 않는다.
- API Controller는 도메인 단위로 분리한다.
- 기준 구조는 `app/Controllers/{Module}/{Domain}Controller.php` 또는 `app/Controllers/Dashboard/Settings/{Domain}Controller.php`이다.
- API 메서드는 `apiList`, `apiDetail`, `apiSave`, `apiDelete`, `apiTrashList`, `apiRestore`, `apiPurge`, `apiReorder` 형식을 사용한다.
- 화면 렌더링 메서드는 `index`, `webIndex`, `web{Domain}` 중 하나로 일관되게 사용한다.
- 하나의 Controller가 여러 메뉴의 저장/조회/삭제를 동시에 담당하지 않도록 한다.
- 비대해진 Controller는 기능 단위 Controller로 분리한다.
- Controller에서 허용되는 책임은 Request 수집, Service 호출, Response 반환으로 제한한다.
- Controller에서 업무규칙, 검증, 파일처리, 트랜잭션, 로그 기록, DB 직접 접근을 금지한다.

### 2. Service 규칙

- Service는 업무 규칙과 도메인 처리 흐름을 담당한다.
- Controller는 Service를 호출하고, Service는 Model 또는 Repository를 호출한다.
- 외부 연동, 상태 전환, 검증, 복합 저장, 트랜잭션 처리 단위는 Service에 둔다.
- Service 이름은 `{Domain}Service.php` 형식을 사용한다.
- 동일 도메인 내 복합 기능은 `{Domain}{Purpose}Service.php`로 분리할 수 있다.
- Service에서 직접 SQL을 작성하는 것은 최소화한다.
- 여러 Model을 조합하는 로직은 Controller가 아니라 Service에 둔다.
- Service는 업무의 단일 책임 주체이다.
- Service에서는 업무규칙, 검증, 저장, 수정, 삭제, 복구, 파일처리, 트랜잭션, 로그 기록을 허용한다.

### 3. Model 규칙

- Model은 단일 테이블 또는 명확한 저장소 단위를 담당한다.
- Model 이름은 `{Domain}Model.php` 형식을 사용한다.
- Model은 조회, 생성, 수정, 삭제, 정렬, 휴지통 처리 등 DB 접근을 담당한다.
- Controller에서 직접 SQL을 작성하지 않는다.
- 복수 테이블 조인이 반복되는 경우 Repository 또는 전용 Model 메서드로 분리한다.
- 테이블 존재 여부, 컬럼 존재 여부 보정 로직은 일반 런타임 CRUD와 분리한다.
- legacy 테이블명은 신규 Model에 직접 사용하지 않는다.
- Model에서 로그 기록을 금지한다.

### 4. JS 규칙

- JS는 화면 단위 entry 파일을 둔다.
- 기준 구조는 `public/assets/js/pages/{module}/{group}/{domain}.js` 또는 `public/assets/js/pages/{module}/{domain}/index.js`이다.
- API URL은 파일 상단의 `API` 객체 또는 전용 API client 파일에 모은다.
- 이벤트 바인딩, 테이블 초기화, 모달 제어, API 호출을 섞되, 반복 로직은 공통 component/helper로 분리한다.
- URL 문자열을 화면 곳곳에 흩뿌리지 않는다.
- 휴지통, 검색, picker, table, modal 등 공통 UI는 재사용 컴포넌트를 우선 사용한다.
- 한 화면 JS가 과도하게 커지면 기능별 모듈로 분리한다.
- JS 수정 시 브라우저 구문 오류가 없도록 확인한다.
- JS에서 로그 기록을 금지한다.
- JS 파일이 1000라인을 초과하면 분리 검토 대상이다.
- JS 파일이 1500라인을 초과하면 분리 계획 수립이 필수다.
- JS 파일이 2000라인을 초과하면 최우선 분리 대상으로 분류한다.
- JS 파일 줄 수를 줄이기 위한 우회 분리를 금지한다.
- `String.raw`, `eval`, `Function()`, 코드 내장용 template string 안에 기존 함수 본문이나 화면 로직을 문자열로 넣어 분리하는 방식을 금지한다.
- JS 분리는 반드시 실제 함수, 실제 파일, 실제 ES 모듈 단위로 수행한다.
- 모듈 분리 후에도 브라우저가 직접 읽는 파일에는 사람이 읽을 수 있는 실제 코드가 존재해야 한다.
- 파일 줄 수가 많은 JS를 리팩토링할 때는 원본 파일 전체를 먼저 복사해서 분리 대상 파일을 만든다.
- 대형 JS 분리는 필요한 부분만 일부 발췌해서 옮기는 방식보다 원본 전체 복사 후 각 파일에서 해당하지 않는 함수만 삭제하는 방식을 기본 원칙으로 사용한다.
- 큰 덩어리의 원본 파일을 나눌 때는 복사본 기준으로 정리하고, 부분 이동 방식은 예기치 않은 의존성 누락과 구문 오류 위험이 있으므로 기본 방식으로 사용하지 않는다.

### 4-1. 공통 옵션 병합(Merge) 규칙

- 공통 Adapter에서 기본 옵션과 화면별 옵션을 병합할 때는 공통 기본값이 spread 순서 때문에 덮어써지지 않도록 한다.
- `defaultColDef`, `defaultColGroupDef`, `components`, `frameworkComponents`, `context`, `localeText`, `statusBar`, `sideBar`, `popupParent`, `icons` 등 공통 Adapter가 제공하는 설정은 마지막 최종 객체에서 반드시 유지되어야 한다.
- 아래 구조를 금지한다.

```js
const options = {
    defaultColDef: mergedDefaultColDef,
    ...
    ...(config.gridOptions || {})
}
```

- 위 구조처럼 공통 기본값보다 뒤에 오는 spread가 동일 키를 다시 펼치면 공통 기본 설정이 overwrite 되므로 사용하지 않는다.
- 아래 두 방식을 허용한다.

```js
const options = {
    ...
    ...(config.gridOptions || {}),
    defaultColDef: mergedDefaultColDef,
}
```

```js
const options = {
    ...(config.gridOptions || {}),
}

options.defaultColDef = mergedDefaultColDef;
```

- 중첩 옵션은 먼저 병합 객체를 만든 뒤 최종 옵션에 마지막으로 적용한다.

```js
const mergedDefaultColDef = {
    ...
    ...(config.defaultColDef || {}),
    ...(config.gridOptions?.defaultColDef || {}),
}
```

- Adapter 수정 시 `createGrid()` 직전 기준으로 공통 기본값이 실제 최종 옵션에 남아 있는지 확인한다.
- 검증 기준은 `console.log(gridOptions.defaultColDef)` 또는 동등한 런타임 캡처이며, `editable`, `resizable`, `sortable`, `suppressMovable` 같은 공통 기본값이 유지되어야 한다.

### 5. View 규칙

- View는 화면 구조와 서버에서 필요한 초기 변수만 담당한다.
- View 경로는 `app/views/{module}/{group}/{domain}.php` 또는 `app/views/{module}/{domain}/index.php`를 사용한다.
- 모달, 검색 폼, 휴지통, 반복 UI는 partial로 분리한다.
- View 안에 업무 로직이나 SQL을 두지 않는다.
- View에서 사용하는 API URL은 JS의 API 객체와 일치해야 한다.
- 공통 휴지통은 `ui-modal-trash.php` 등 공통 컴포넌트를 사용한다.
- 화면별 JS/CSS는 `AssetHelper`로 명시한다.
- View에서 로그 기록을 금지한다.

### 6. API 규칙

- API URL은 `/api/{module}/{group}/{domain}/{action}` 형식을 기본으로 한다.
- 기준정보 API는 `/api/settings/base-info/{domain}/{action}`을 사용한다.
- 조직정보 API는 `/api/settings/organization/{domain}/{action}`을 사용한다.
- 시스템 기준코드는 `/api/settings/system/code/{action}`을 사용한다.
- 회계 API는 `/api/ledger/{domain}/{action}`을 사용한다.
- 목록은 `list`, 상세는 `detail`, 저장은 `save`, 삭제는 `delete`를 사용한다.
- 휴지통은 `trash`, 복구는 `restore`, 영구삭제는 `purge`를 사용한다.
- 일괄 복구/삭제는 `restore-bulk`, `purge-bulk`를 사용한다.
- 전체 복구/삭제는 `restore-all`, `purge-all`을 사용한다.
- 정렬은 `reorder`를 사용한다.
- 검색 picker는 `search-picker`를 사용한다.
- 엑셀 양식은 `template`, 다운로드는 `excel` 또는 `download`, 업로드는 `excel-upload`를 사용한다.
- API 응답은 기본적으로 `{ success, data, message }` 구조를 사용한다.

### 6-1. Route Development Guide

- 신규 Route 생성 전 동일 URL 검색을 반드시 수행한다.
- 신규 Route 생성 전 동일 key 검색을 반드시 수행한다.
- 중복 URL 생성은 금지한다.
- 서로 다른 Route에 동일 key 사용을 금지한다.
- Route 추가 또는 변경 전 `routes/api.php`, `routes/web.php`, 분리 라우트 파일 전체를 기준으로 중복 여부를 확인한다.
- Route 메타를 추가할 때는 `key`, `page`, `page_description`, `permission_name`, `permission_description`, `name`, `description`, `category`, `auth`, `permissions`, `log` 누락 여부를 함께 점검한다.

### 7. DB 네이밍 규칙

- 시스템/기준정보 테이블은 `system_*`을 사용한다.
- 사용자/조직정보 테이블은 `user_*`을 사용한다.
- 인증/권한 테이블은 `auth_*`을 사용한다.
- 회계 테이블은 `ledger_*`을 사용한다.
- 증빙 본문 테이블은 `ledger_evidence_*`를 사용한다.
- 증빙 payload는 `ledger_evidence_payloads`를 사용한다.
- 증빙 처리 상태는 `ledger_evidence_processing`을 사용한다.
- 증빙 링크는 `ledger_evidence_links`를 사용한다.
- 생성센터 처리 item은 `ledger_processing_items`를 사용한다.
- 로그 테이블은 `{domain}_logs` 형식을 사용한다.
- 이력 테이블은 `{domain}_histories` 형식을 사용한다.
- 중간 매핑 테이블은 `{source}_{target}_links` 또는 `{domain}_links` 형식을 사용한다.
- 신규 코드에서 삭제 예정 legacy 테이블을 직접 참조하지 않는다.

### 7-1. Database Guide

- 기존 Migration 수정은 금지하고 신규 Migration 생성 방식만 사용한다.
- 신규 테이블 생성 시 `TableDictionary.md` 갱신이 필수다.
- 컬럼 추가, 삭제, 타입 변경 전 `Model`, `Service`, `Controller`, `View`, `JS` 영향도 조사를 먼저 수행한다.
- 컬럼 추가 또는 변경 시 런타임 저장 흐름과 조회 흐름을 분리해서 영향 범위를 검토한다.

### 8. 파일명 규칙

- Controller: `{Domain}Controller.php`
- Service: `{Domain}Service.php`
- Model: `{Domain}Model.php`
- Repository: `{Domain}Repository.php`
- View: `{domain}.php` 또는 `{domain}/index.php`
- Partial: `{domain}_modal.php`, `{domain}_form.php`, `{domain}_table.php`
- JS 단일 파일: `{domain}.js`
- JS 모듈형: `{domain}/index.js`
- CSS 단일 파일: `{domain}.css`
- CSS 모듈형: `{domain}/index.css`
- 파일명은 도메인명을 기준으로 맞춘다.
- 같은 메뉴에서 `data`, `import`, `evidence`, `seed` 같은 다른 명칭을 혼용하지 않는다.
- 레거시 명칭은 신규 파일명에 사용하지 않는다.

### 9. 리팩토링 규칙

- 리팩토링은 기능 추가가 아니라 구조 정상화를 목적으로 한다.
- 한 번에 하나의 파일 또는 하나의 함수 범위로 제한한다.
- 승인된 범위 외 수정은 금지한다.
- 기존 동작을 바꾸지 않는 것을 원칙으로 한다.
- DB 변경은 별도 승인 없이는 금지한다.
- legacy 테이블 제거는 참조 제거, 백필 검증, 런타임 전환, 삭제 승인 순서로 진행한다.
- Controller 비대화 해소 순서는 조회, 상태, 링크, payload, 저장, 삭제/복구 순으로 진행한다.
- 먼저 읽기 전용 경로를 전환하고, 그 다음 쓰기 경로를 전환한다.
- 쓰기 전환 시 기존 저장소와 신규 저장소의 책임을 명확히 분리한다.
- 삭제/복구/purge 경로는 관련 payload, 상태, 링크, 본문 테이블을 함께 검토한다.
- 수정 후 PHP 파일은 반드시 `php -l`을 통과해야 한다.
- JS 파일 수정 시 구문 오류를 확인한다.
- UTF-8 BOM 없음, LF, 한글 깨짐 금지를 지킨다.
- 규칙 실패 시 다음 작업을 진행하지 않는다.
- 원본 제거 전 `php -l`, `node --check` 검증이 모두 통과해야 한다.

### 9-1. 리팩토링 안전규칙

대규모 파일 분리, 모듈 분리, 라우트 분리, 서비스 분리, 컨트롤러 분리 작업은
반드시 아래 절차를 따른다.

------------------------------------------------

금지

- 기존 코드를 잘라내기(Cut) 후 바로 이동하는 방식
- 원본 삭제 후 신규 파일 생성
- 검증 없이 대량 이동
- 원본 백업 없이 구조 변경

예시 금지

- `api.php` 에서 `ledger.php` 로 잘라내기
- Controller 에서 Service 로 잘라내기
- 함수에서 Helper 로 잘라내기

------------------------------------------------

필수 절차

1. 원본 유지
- 기존 파일은 먼저 수정하지 않는다.
- 예: `api.php`, `web.php` 유지

2. 대상 파일 생성
- 예: `routes/api/ledger.php` 생성

3. 복사
- 이동 대상 코드만 복사한다.
- 원본은 그대로 유지한다.
- 현재 단계에서는 중복 상태를 허용한다.

4. 검증
- 반드시 수행:
- `php -l`
- 문법 검사
- Route 수 확인
- URL 수 확인
- key 수 확인
- 기능 동작 확인

5. 원본 제거
- 검증 완료 후에만 원본 파일에서 이동 대상만 제거한다.

6. 최종 검증
- 다시 수행:
- `php -l`
- Route 수 비교
- URL 비교
- key 비교
- 기능 테스트

------------------------------------------------

적용 대상

- Route 분리
- Controller 분리
- Service 분리
- Repository 분리
- JS 모듈 분리
- 공통 함수 추출
- View 분리
- 설정 파일 분리

------------------------------------------------

핵심 원칙

- 잘라내기(Cut)보다 복사(Copy) → 검증 → 제거(Delete) 순서를 우선한다.
- 대규모 구조 변경 시 원본이 항상 살아 있어야 한다.
- "복제 → 검증 → 제거" 원칙을 기본 리팩토링 전략으로 사용한다.
- JS 모듈 분리 시 문자열 실행 기반 구조를 금지한다.
- `String.raw`, `eval`, `Function()`, template string 안에 코드를 넣고 런타임에 해석하는 방식은 리팩토링으로 인정하지 않는다.
- JS 모듈 분리는 함수 선언, export/import, 실제 파일 이동 기준으로만 수행한다.
- 파일 줄 수가 많은 대형 JS 리팩토링은 반드시 원본 파일 전체 복사로 시작한다.
- 분리 대상 파일은 원본을 복사한 뒤 파일별 책임에 맞지 않는 함수만 삭제하는 방식으로 정리한다.
- 필요한 함수만 부분 발췌해서 신규 파일로 옮기는 방식은 의존성 누락과 런타임 오류 위험이 크므로 원칙적으로 금지한다.

### 10. 파일 크기 규칙

- Controller 1,500라인 초과 금지
- Service 1,500라인 초과 금지
- Model 1,000라인 초과 금지
- JS 1,500라인 초과 금지

초과 시 분리 계획을 수립한다.

신규 기능은 기존 거대 파일에 추가하지 않고
도메인 단위로 분리한다.

### 11. 경로 구조 규칙

Controller
app/Controllers/{Module}/{Domain}Controller.php

Service
app/Services/{Module}/{Domain}Service.php

Model
app/Models/{Module}/{Domain}Model.php

View
app/views/{module}/{domain}/index.php

JS
public/assets/js/pages/{module}/{domain}/index.js

CSS
public/assets/css/pages/{module}/{domain}/index.css

신규 파일은 위 구조를 따른다.


### 12. 신규 기능 개발 규칙

신규 기능은 반드시

Controller
→ Service
→ Model

구조로 개발한다.

기존 거대 파일
(ImportController, dataCreate.js 등)

에는 신규 기능을 추가하지 않는다.

신규 기능은 신규 도메인 파일에 작성한다.


### 13. 책임 분리 규칙

하나의 Controller는 하나의 도메인만 담당한다.

하나의 Service는 하나의 업무 흐름만 담당한다.

하나의 Model은 하나의 저장소(테이블)만 담당한다.

조회, 저장, 상태변경, 삭제/복구, 업로드가 동시에 섞이기 시작하면
분리 계획을 수립한다.


### 14. 신규 파일 우선 규칙

신규 기능 추가 시

기존 거대 파일 수정보다
신규 도메인 파일 생성을 우선한다.

ImportController
LedgerController
dataCreate.js
dataStatus.js

등의 레거시 파일에는
신규 기능을 추가하지 않는다.

신규 기능은
신규 Controller
신규 Service
신규 JS
기준으로 개발한다.

### 15. 도메인 식별 규칙

파일명만 보고 해당 기능을 식별할 수 있어야 한다.

Controller
Service
Model
View
JS
CSS
Route

모두 동일한 도메인명을 사용한다.

Import
Data
Seed
Manager
Process

같은 포괄적 명칭은 신규 코드에서 사용하지 않는다.

파일명과 URL만 보고
담당 업무를 추론할 수 있어야 한다.

### 16. 공용 사전 규칙

CommonDictionary.md
ServiceDictionary.md
RouteDictionary.md
TableDictionary.md

는 프로젝트 공식 사전이다.

신규 생성
수정
삭제
이관

발생 시 반드시 사전을 갱신한다.

------------------------------------------------

함수

신규 공용 함수 생성 시

CommonDictionary.md 등록 필수

공용 함수 수정 시

CommonDictionary.md 갱신 필수

공용 함수 삭제 시

CommonDictionary.md 제거 필수

------------------------------------------------

Service

신규 Service 생성 시

ServiceDictionary.md 등록 필수

Service 책임 변경 시

ServiceDictionary.md 갱신 필수

------------------------------------------------

Route

신규 Route 생성 시

RouteDictionary.md 등록 필수

Route 변경 시

RouteDictionary.md 갱신 필수

------------------------------------------------

Table

신규 테이블 생성 시

TableDictionary.md 등록 필수

컬럼 추가/삭제 시

TableDictionary.md 갱신 필수

------------------------------------------------

사전 미갱신 상태에서는

다음 작업 진행 금지

보고 항목 필수:

- 사전 변경 여부
- 수정된 사전 파일
- 추가/수정/삭제 항목

------------------------------------------------

작업 종료 규칙

공용 함수
공용 Service
공용 Route
공용 Table

생성/수정/삭제가 발생한 경우

보고서에는 반드시 아래 항목을 포함한다.

- 사전 변경 여부
- 수정된 사전 파일
- 추가된 항목
- 수정된 항목
- 삭제된 항목

사전 변경 보고가 없으면
작업 미완료로 간주한다.

------------------------------------------------

### 16-1. Documentation Guide

- Route 추가 또는 변경 시 `RouteDictionary.md` 갱신이 필수다.
- Service 추가 또는 책임 변경 시 `ServiceDictionary.md` 갱신이 필수다.
- Table 추가 또는 컬럼 구조 변경 시 `TableDictionary.md` 갱신이 필수다.
- 공용 컴포넌트, 공용 함수, 공용 UI 자산 추가 시 `CommonDictionary.md` 갱신이 필수다.
- Dictionary 미갱신 상태에서는 작업 완료 보고를 금지한다.
- 문서 갱신 여부는 작업 종료 보고의 필수 항목이다.

------------------------------------------------

### 16-2. Error Message Guide

- 사용자 노출 메시지는 한글 표준 문구를 사용한다.
- 사용자 메시지에 깨진 한글, DB 원문, 영어 원문, Exception message 직접 노출을 금지한다.
- 저장 실패는 `저장 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 수정 실패는 `수정 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 삭제 실패는 `삭제 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 복구 실패는 `복구 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 영구삭제 실패는 `영구삭제 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 정렬 저장 실패는 `정렬 저장 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 엑셀 업로드 실패는 `엑셀 업로드 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 엑셀 다운로드 실패는 `엑셀 다운로드 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 엑셀 템플릿 다운로드 실패는 `엑셀 템플릿 다운로드 중 오류가 발생했습니다.`를 기본 표준으로 사용한다.
- 상세 원인은 Service 로그에 남기고, Controller와 API 응답에는 표준 사용자 메시지만 반환한다.

------------------------------------------------

### 16-3. SSOT Guide

- 도메인 생성 전 SSOT 도메인명을 먼저 확정한다.
- 동일 도메인에 대해 복수 표기 공존을 금지한다.
- `brand`, `brand_logo`, `brand-logo` 공존을 금지한다.
- `bank-account`, `bank.account`, `accounts` 공존을 금지한다.
- `work-team`, `work-teams`, `work_team` 공존을 금지한다.
- `cover`, `coverimage`, `cover-image`, `cover_image` 공존을 금지한다.
- SSOT 도메인명은 Route, Controller, Service, Model, View, JS, CSS, Permission, PageRegistry, Dictionary에서 동일하게 유지한다.
- 신규 구현 전 기존 도메인명 사용처를 검색해 SSOT 위반 여부를 먼저 확인한다.

------------------------------------------------

### 16-4. Common Component Guide

- 신규 구현 전 `createDataTable`, `SearchForm`, `excel-manager`, `trash-manager`, `AdminPicker`, `code-select` 존재 여부를 반드시 확인한다.
- 공용 컴포넌트가 존재하면 재사용을 우선하고 직접 구현을 금지한다.
- 직접 구현이 필요한 경우 기존 공용 컴포넌트를 사용할 수 없는 예외 사유를 보고서에 남긴다.
- 공용 Modal, 공용 Form, 공용 휴지통, 공용 엑셀관리 컴포넌트도 동일 원칙을 적용한다.
- 동일 역할 코드가 두 개 이상 도메인에서 반복되면 공용 컴포넌트 추출 후보로 분류한다.

------------------------------------------------

### 17. 사전 우선 규칙

신규 구현 전

1. CommonDictionary 검색
2. ServiceDictionary 검색
3. RouteDictionary 검색
4. TableDictionary 검색

실시

동일 기능 존재 시
재사용 우선

신규 구현 금지

사전 확인 없이
동일 기능 재구현 금지
사전 확인 결과도 보고한다.

예시

CommonDictionary 검색 결과:
재사용 가능 함수 없음

또는

CommonDictionary 검색 결과:
normalizeDataType 재사용

ServiceDictionary 검색 결과:
EvidencePayloadService 재사용

검색 결과 보고 없이
신규 구현 금지


### 18. 사전 누락 발견 규칙

개발 중 아래 항목을 발견한 경우

- 공용 함수
- 공용 Service
- 공용 Route
- 공용 Table

가 사전에 등록되어 있지 않으면

즉시 사전에 등록한다.

------------------------------------------------

예외

작업 범위와 무관한 대규모 정리는 금지.

단,

현재 수정 중인 도메인과 관련된 항목은
즉시 사전에 반영한다.

------------------------------------------------

예시

EvidenceGenerationService 수정 중

normalizeDataType 발견

CommonDictionary.md 미등록

→ CommonDictionary.md 등록 후 작업 진행

------------------------------------------------

사전 누락 발견 보고

보고 항목:

- 누락 발견 여부
- 신규 등록 항목
- 수정된 사전 파일

------------------------------------------------

사전 누락 상태에서는

신규 공용 코드 추가 금지


### 19. 공용 자산 우선 사용 규칙

신규 함수 작성 전

CommonDictionary.md 검색 필수

신규 Service 작성 전

ServiceDictionary.md 검색 필수

신규 Route 작성 전

RouteDictionary.md 검색 필수

신규 Table 작성 전

TableDictionary.md 검색 필수

------------------------------------------------

동일 기능 존재 시

신규 구현 금지

기존 함수
기존 Service
기존 Route
기존 Table

재사용 우선

------------------------------------------------

신규 구현 사유가 있는 경우

보고서에 반드시 작성

예시

기존 normalizeDataType 사용 불가

사유:
외부 연동 전용 규칙 필요

신규 함수 생성

------------------------------------------------

재사용 가능한 공용 자산이 존재하는데

신규 구현한 경우

작업 실패로 간주

### 20. 문서 분류 규칙

프로젝트 문서는 아래 5종으로 구분한다.

1. 규칙 문서

- `AGENTS.md`
- 프로젝트 전체 개발, 문서, 보고, 리팩토링 기준을 관리한다.

2. 사전 문서

- `CommonDictionary.md`
- `ServiceDictionary.md`
- `RouteDictionary.md`
- `TableDictionary.md`
- 현재 구현 및 운영 중인 영역의 공식 인벤토리를 관리한다.

3. 결정 문서

- `DecisionLog.md`
- 구조, 기술 선택, 운영 정책의 결정 이유를 기록한다.

4. 프로젝트 문서

- 마이그레이션
- 테스트
- 운영 절차
- 프로젝트 계획
- 특정 기간 또는 특정 과업 단위의 진행 문서를 관리한다.

5. 산출물 문서

- SQL
- 샘플 데이터
- 검증 스크립트
- 문서 설명용 본문이 아니라 실행·검증 보조 산출물을 관리한다.

------------------------------------------------

문서 작성 시 규칙

- 규칙 문서와 프로젝트 문서를 혼합하지 않는다.
- 사전 문서와 결정 문서를 혼합하지 않는다.
- 산출물 문서를 결정 문서처럼 작성하지 않는다.
- 프로젝트 종료 후에도 계속 유지할 기준은 규칙 문서 또는 사전 문서로 관리한다.
- 특정 작업에만 필요한 절차와 체크리스트는 프로젝트 문서로 관리한다.

### 21. UI 표준 규칙

ERP 공용 UI는 표준을 따른다.

신규 UI 구현 전 기존 UI 표준 확인은 필수다.

대상:

- DataTable
- AG Grid
- 검색영역
- 버튼영역
- 모달
- 휴지통
- 엑셀관리
- 테이블설정

------------------------------------------------

UI 구현 원칙

- 공용 UI 재사용을 우선한다.
- 페이지별 임의 구현은 금지한다.
- 기존 공용 버튼 순서와 상호작용 패턴을 먼저 확인한다.
- 동일 역할 UI를 화면마다 다른 구조와 명칭으로 다시 만들지 않는다.
- 공용 UI 확장이 가능하면 신규 구현보다 공용 확장을 우선한다.

### 22. DecisionLog 운영 규칙

`DecisionLog.md`는 작업 로그가 아니라 결정 문서다.

기록 대상은 "무엇을 수정했는가"가 아니라 "왜 그렇게 결정했는가"다.

------------------------------------------------

기록 원칙

- 결정 이유 중심으로 작성한다.
- 대안이 있었다면 왜 현재 대안을 선택했는지 기록한다.
- 영향 범위와 후속 제약이 있으면 함께 기록한다.

기록 금지

- 작업 로그 기록
- 함수 이동 내역 기록
- 서비스 분리 작업 내역 기록
- 단순 파일 수정 목록 기록

예시

- AG Grid 채택 이유
- DataTable 유지 이유
- Processing Item 채택 이유

### 23. Dictionary 역할 명확화 규칙

Dictionary는 ERP 전체 공식 사전이 아니다.

현재 구현 및 운영 중인 영역을 기준으로 관리한다.

미구현 영역은 등록하지 않는다.

------------------------------------------------

운영 원칙

- 현재 코드와 운영 경로에서 실제로 사용 중인 항목만 등록한다.
- 설계만 있고 아직 구현되지 않은 함수, Service, Route, Table은 등록하지 않는다.
- 구현 후 실제 진입점 또는 실제 사용 위치가 확인된 항목만 사전에 반영한다.
- 삭제 예정 또는 실험 단계 항목은 현행 운영 기준인지 확인 후 등록한다.

### 24. 공용 UI 자산 규칙

공용 UI 자산은 ERP 전체에서 재사용 가능한 기준 구현만 관리한다.

대상 예시

- DataTable 공용 모듈
- AG Grid 공용 모듈
- 검색영역 공용 컴포넌트
- 버튼영역 공용 컴포넌트
- 모달 공용 컴포넌트
- 휴지통 공용 컴포넌트
- 엑셀관리 공용 컴포넌트
- 테이블설정 공용 컴포넌트

------------------------------------------------

운영 원칙

- 공용 UI 자산은 업무 API를 직접 호출하지 않는다.
- 공용 UI 자산은 UI 구조, 상태 처리, 공통 이벤트 연결까지만 담당한다.
- 실제 업무 로직은 화면별 callback 또는 화면 전용 모듈에서 처리한다.
- 동일한 역할의 UI를 화면마다 새로 만들지 않는다.
- 기존 공용 자산 확장이 가능하면 신규 공용 자산 추가보다 확장을 우선한다.
- 공용 자산 이름과 책임은 화면 전용 코드와 혼합되지 않도록 유지한다.

### 25. 화면 표준 규칙

ERP 화면은 공용 표준 구조를 따른다.

화면마다 임의의 순서와 명칭으로 UI를 다시 구성하지 않는다.

------------------------------------------------

화면 구성 원칙

- 검색영역, 버튼영역, 테이블영역, 모달영역의 기본 구성을 유지한다.
- 공용 테이블 화면은 기존 버튼 순서와 상호작용 패턴을 우선 따른다.
- 같은 유형의 화면은 같은 용어와 같은 버튼 명칭을 사용한다.
- 휴지통, 엑셀관리, 테이블설정, 검색영역은 공용 위치와 공용 진입 방식을 우선 사용한다.
- 선택형 테이블 화면은 선택, 복사, 삭제, 이동, 설정 동작의 기준 흐름을 유지한다.
- 페이지별 특수 기능은 공용 영역 뒤에 추가하되 공용 영역을 깨지 않는다.

### 25-1. CSS/UI 기본 개발규칙

모든 화면은 반응형 전제를 가진다.

------------------------------------------------

레이아웃 원칙

- 고정 width, height 사용은 최소화한다.
- `width: 1200px`, `height: 800px` 같은 절대 고정 크기 신규 작성은 금지한다.
- `width: 100%`, `max-width`, `min-height` 등 유연한 크기 기준을 우선한다.
- 모바일(390), 태블릿(768), 노트북(1366), 데스크탑(1920) 기준에서 대응 가능한 구조를 유지한다.

------------------------------------------------

구현 원칙

- 신규 레이아웃은 Flex, Grid를 우선 사용한다.
- float 기반 신규 개발은 금지한다.
- JS에서 inline style 직접 수정은 최소화하고 class 기반 제어를 우선한다.
- 색상은 직접 하드코딩하지 않고 CSS 변수 사용을 우선한다.

------------------------------------------------

공용 자산 우선 원칙

- table, modal, form, button, badge, tab은 공용 컴포넌트 재사용을 우선한다.
- 공용 컴포넌트와 동일 역할의 중복 CSS 생성은 금지한다.
- 페이지 전용 CSS 생성 전 공용 CSS 재사용 가능 여부를 먼저 검토한다.

------------------------------------------------

파일 운영 원칙

- CSS 파일이 500라인을 초과하면 분리 검토 대상이다.
- 대규모 구조 변경 시에는 잘라내기보다 복제 → 검증 → 제거 순서를 따른다.

### 26. 프로젝트 문서 수명주기 규칙

프로젝트 문서는 영구 문서와 동일하게 취급하지 않는다.

프로젝트 문서는 생성, 사용, 종료, 보관 상태를 구분해서 관리한다.

------------------------------------------------

수명주기 원칙

- 프로젝트 시작 시 문서 목적과 범위를 먼저 명시한다.
- 진행 중 문서는 현재 기준으로 계속 갱신한다.
- 완료된 프로젝트 문서는 운영 기준 문서로 승격할지, 기록용으로 보관할지 구분한다.
- 운영 기준으로 계속 사용할 내용은 AGENTS 또는 사전 문서 또는 결정 문서로 이관 검토한다.
- 특정 단계에서만 필요한 체크리스트와 절차는 프로젝트 문서로 남긴다.
- 더 이상 참조하지 않는 프로젝트 문서는 후속 정리 대상으로 표시한다.

### 27. 규칙 승격 규칙

개발 중 아래 조건 중 하나를 만족하면

AGENTS 규칙 추가 또는 수정 여부를 검토한다.

1.

동일 지시가 3회 이상 반복된 경우

2.

동일 구조 문제가 2개 이상 도메인에서 발생한 경우

3.

공용 UI 또는 공용 자산이 신규 생성된 경우

4.

Controller / Service / Model / JS 분리 기준이 새로 확정된 경우

5.

프로젝트 전반에 영향을 주는 결정이 확정된 경우

------------------------------------------------

작업 완료 보고 시 아래 항목을 추가한다.

규칙 승격 검토:

- 필요 없음
- AGENTS 반영 필요

사유:
- 판단 근거를 함께 기록한다.

------------------------------------------------

규칙 승격이 필요한데 반영하지 않은 경우

후속 작업 전에 AGENTS 갱신 여부를 검토한다.

### 28. 기준정보 JS 모듈 분리 규칙

기준정보 화면 JS는 공통 구조로 모듈 분리한다.

대상 예시

- 거래처 `client`
- 프로젝트 `project`
- 계좌 `bank.account`
- 카드 `card`

------------------------------------------------

표준 폴더 구조

```text
public/assets/js/pages/dashboard/settings/base/{domain}.js
public/assets/js/pages/dashboard/settings/base/{domain}/
  index.js
  api.js
  table.js
  modal.js
  form.js
  trash.js
  excel.js
```

------------------------------------------------

표준 파일 구성

- `{domain}.js`
  - 루트 shim만 유지한다.
  - `export * from './{domain}/index.js';`
  - `import './{domain}/index.js';`
- `index.js`
  - 화면 엔트리
  - 상태 객체
  - 모듈 조립
  - DOMContentLoaded
- `api.js`
  - API URL
  - 컬럼 맵
  - 날짜 옵션
  - 화면 상수
- `table.js`
  - DataTable 생성
  - 버튼 구성
  - 행 이벤트
  - 정렬/검색/상태 토글
- `modal.js`
  - 모달 초기화
  - 상세 조회
  - 신규/수정 진입
  - 저장/삭제 submit 연결
- `form.js`
  - 폼 helper
  - 입력 검증
  - 날짜 picker
  - select2/picker
  - 파일 업로드 UI
  - 공통 DOM helper
- `trash.js`
  - 휴지통 상세 렌더
  - `window.TrashColumns.*`
- `excel.js`
  - excel dataset
  - 업로드 후 reload 이벤트

------------------------------------------------

라인 수 기준

- `index.js` 150라인 이하 권장
- `api.js` 120라인 이하 권장
- `excel.js` 80라인 이하 권장
- `trash.js` 120라인 이하 권장
- `table.js` 400라인 이하 권장
- `modal.js` 350라인 이하 권장
- `form.js` 500라인 이하 권장

500라인 초과 시 추가 하위 모듈 분리를 검토한다.

예시

- `file.js`
- `picker.js`
- `validators.js`

------------------------------------------------

공용 모듈 재사용 기준

- 테이블
  - `createDataTable`
  - `bindTableHighlight`
  - `bindRowReorder`
- 검색
  - `SearchForm`
- 휴지통
  - `trash-manager`
  - `window.TrashColumns`
- 엑셀
  - `excel-manager`
- 선택기
  - `AdminPicker`
  - `code-select`
- 포맷
  - `formatAmount`
  - `parseNumber`
  - `formatAccountNumber`
  - `unformatAccountNumber`

------------------------------------------------

금지 기준

- 거대 단일 파일에 신규 기능 추가 금지
- 화면 전용 로직을 공용 모듈에 직접 섞는 구현 금지
- API URL 문자열을 여러 파일에 분산하는 구현 금지
- 모달, 테이블, 파일업로드 로직을 한 함수에 혼합하는 구현 금지

### 29. 공용 테이블 설정 저장 규칙

공용 테이블 설정 저장값은

visibleColumns
columnOrder
columnWidths
sortSettings
pageLength

를 기준으로 관리한다.

------------------------------------------------

저장 기준

- visibleColumns 는 column key 기준으로 저장한다.
- columnOrder 는 column key 기준으로 저장한다.
- columnWidths 는 column key 기준으로 저장한다.
- sortSettings 는 column key 기준으로 저장한다.
- pageLength 는 화면별 테이블 설정 값으로 저장한다.
- title 기준 저장은 금지한다.
- index 기준 저장은 금지한다.

------------------------------------------------

columnWidths 구조

- columnWidths 는 column key -> widthPx 구조를 사용한다.
- 사용자 설정 widthPx 가 유일한 폭 기준(SSOT)이다.
- SearchForm, Sidebar, Window Resize 는 columnWidths 를 재계산하지 않는다.
- 레이아웃 변경 시 저장된 widthPx 재적용만 허용한다.
- 테이블 전체 폭은 보이는 컬럼 widthPx 합계로 계산한다.
- wrapper 보다 넓으면 scrollX 또는 가로 스크롤로 처리한다.
- 한 컬럼 폭 변경 시 해당 컬럼만 변경한다.
- 다른 컬럼 폭 자동 재분배는 금지한다.

------------------------------------------------

컬럼 숨김 규칙

- 컬럼을 숨겨도 columnWidths 정보는 유지한다.
- 컬럼 숨김 시 columnWidths 항목 삭제는 금지한다.
- 다시 표시될 때 마지막 사용자 폭 정보를 복원할 수 있어야 한다.


### 30. 수정 전 인코딩 정상화 규칙

파일 수정 작업 시작 전

대상 파일의 인코딩 상태를 먼저 확인한다.

---

수정 전 검사

대상:

* php
* js
* css
* md

---

확인 항목

1.

UTF-8 BOM 없음

2.

LF 사용

3.

한글 깨짐 패턴 존재 여부

패턴 예시

?뚯
?뱀
?붿
?꾩
?대
?놁
硫
媛
湲
�

---

판정

정상

* UTF-8 No BOM
* LF
* 한글 깨짐 없음

→ 수정 진행 가능

---

비정상

* UTF-8 아님
* BOM 존재
* CRLF 존재
* 한글 깨짐 존재

→ 기능 수정 금지

---

우선 수행

1.

인코딩 정상화

2.

한글 복구

3.

php -l 확인

4.

정상 상태 확인

---

그 이후에만

기능 수정

리팩토링

버그 수정

진행 가능

---

예외

사용자가

"현재 상태 그대로 두고 기능만 수정"

을 명시 승인한 경우

---

보고 필수

수정 시작 전

* UTF-8 상태
* LF 상태
* 한글 품질 검사 결과

수정 종료 후

* UTF-8 상태
* LF 상태
* 한글 품질 검사 결과

---

비정상 파일을 수정한 경우

작업 실패로 간주한다.

### 31. 증빙원본 Body Builder 규칙

- 모든 자료유형은 공용 저장 프레임을 사용한다.
- 저장 프레임은 다음 순서를 따른다.
  - Form
  - collectEditPayload()
  - parsed_json
  - mapped_payload_json
  - syncFromLegacyRow()
  - buildXXXPayload()
  - Body Table
- 자료유형별 차이는 `buildXXXPayload`에서만 처리한다.
- SaveService, Controller, DualWrite 실행 구조, payload 생성 구조를 자료유형별로 새로 만들지 않는다.
- Body Builder의 역할은 Form SSOT 기준 데이터만을 해석해 Body Table에 반영하는 것이다.