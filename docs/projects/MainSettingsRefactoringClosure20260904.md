# Main/Settings 1~7단계 리팩토링 Closure

## 최종 판정

Main/Settings 리팩토링 1~7단계를 완료했다. Synology Calendar·CalDAV 내부 구현과 기능 개발은 승인 범위에서 제외했으며, 설정 메뉴가 참조하는 공용 일정 계약은 변경하지 않았다.

## 1단계 — 현황·SSOT 조사

- Controller 24개, Service 54개, Model 30개, View 39개, JS 90개를 감사 대상으로 확정했다.
- Route, Permission, PageRegistry, Controller, Service, Model, View, JS, CSS의 도메인 명칭과 참조를 비교했다.
- 공용 DataTable, TableSettings, Picker, Excel, 휴지통 자산의 재사용 여부를 확인했다.

## 2단계 — 계층 책임 정상화

- Controller는 요청·응답과 Service 호출만 유지하고 직접 SQL·업무 로그가 없음을 확인했다.
- `SystemCodeOptionService`, `StatutoryStandardResolver`, `StatutoryStandardTemplateService`, `WorkTeamService`의 SQL을 Model 메서드로 이관했다.
- Model은 쿼리, Service는 검증·업무 흐름·로그라는 책임을 회복했다.

## 3단계 — 공용 UI·DataTable 계약 정규화

- 설정 JS의 DataTable 사용은 공용 `createDataTable` import 또는 명시적 공용 주입 경로만 허용하도록 감사했다.
- 권한부여 검색·정렬 순수 함수를 실제 ES module인 `permission-assignment/ui-helpers.js`로 분리했다.
- 공용 옵션 overwrite, 브라우저 저장소 업무상태, 문자열 실행 방식이 없음을 자동 검사한다.

## 4단계 — 파일·Route 도메인 정규화

- 설정 WEB Route는 단수 canonical 도메인으로 통일했다.
- 삭제 대상 복수형·underscore·별칭 Redirect와 Controller redirect 메서드를 제거했다.
- 사용되지 않는 View·JS·CSS alias 파일 9개를 제거했다.
- `code.view`, `work_team.view`는 WEB/API가 공유하는 기존 Permission SSOT이므로 임의 변경하지 않았다.

## 5단계 — Permission·PageRegistry 현재상태 동기화

- 12개 설정 WEB Permission key를 canonical key로 Migration했다.
- Permission ID, 역할 매핑 23건, 개인 매핑 12건을 그대로 보존했다.
- PageRegistry와 MenuRegistry의 기본 진입 URL을 canonical Route로 갱신했다.
- Route 628건과 운영 Permission 628건을 동기화했으며 삭제 후보는 0건이다.

## 6단계 — 자동 감사·Migration 안전장치

- `tools/audit_main_settings_architecture.php`를 구조 완료 게이트로 추가했다.
- `tools/apply_main_settings_domain_key_normalization.php`는 preflight, transaction rollback test, up, verify를 제공한다.
- Forward/Down Migration을 모두 제공하며 Down은 Permission key, PageRegistry, MenuRegistry를 함께 복원한다.

## 7단계 — 회귀검증·문서 폐쇄

- AGENTS 개발규칙에 Main/Settings 구조 감사와 Route/Permission 동기화 규칙을 추가했다.
- Common, Service, Route, Table Dictionary를 현재 구조로 갱신했다.
- Permission metadata 감사 기준은 누락 Page key, 미등록 Page key, 잘못된 source, 기술명, 사용자 검토 필요명, 설명 누락이 모두 0건이어야 한다.

## 운영 불변식

| 항목 | 적용 전 | 적용 후 |
| --- | ---: | ---: |
| 전체 Permission | 628 | 628 |
| 정규화 대상 Legacy key | 12 | 0 |
| Canonical key | 0 | 12 |
| 대상 Role mapping | 23 | 23 |
| 대상 User mapping | 12 | 12 |
| 설정 Legacy registry URL | 9 | 0 |
| Route/DB Permission 차이 | 0 | 0 |

## 보호 범위

- Calendar·CalDAV 내부 테이블·Service·UI: 변경 없음
- Trigger: 생성·수정·삭제 없음
- 권한 ID 및 기존 역할·개인 권한: 보존
- 확정 Migration: 수정 없음

## 최종 검증

- 수정·신규 PHP 45개 `php -l`: PASS
- 수정·신규 JS 21개 `node --check`: PASS
- `git diff --check`: PASS
- UTF-8 BOM: 0건
- CRLF: 0건
- 한글 깨짐: 0건 (`AGENTS.md`의 금지 패턴 정의 예시는 검사 대상에서 제외)
- Main/Settings 구조 감사: Controller 24, Service 54, Model 30, View 39, JS 90 / 위반 0
- Permission metadata: 누락·고아·표시 검토·설명 누락 0
- Main domain transition: `dashboard_*` DB 테이블 0, `main_*` DB 테이블 5, Legacy registry 0
- Migration Up 적용: PASS
- Migration Down→Up transaction rollback roundtrip: PASS
- 법정기준, 권한부여, 개인권한 TableSettings, 결재양식 TableSettings, 작업팀 회귀: PASS

## Dictionary 갱신

- `CommonDictionary.md`: 구조 감사 도구와 권한부여 UI helper 등록
- `ServiceDictionary.md`: SQL 이관 후 Service/Model 책임 갱신
- `RouteDictionary.md`: canonical 설정 WEB Route key와 제거된 alias 반영
- `TableDictionary.md`: 설정 Permission key 저장 계약 갱신
