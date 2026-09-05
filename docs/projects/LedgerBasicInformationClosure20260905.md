# 회계관리 기초정보관리 Closure

## 최종 판정

회계관리 > 기초정보관리의 계정과목, 분개규칙, 기초금액, 재고관리는 자산관리 개발을 시작할 수 있는 기준선으로 검증 완료했다.

## 검증 범위

- 계정과목: 계정 계층, 전기 가능 계정, 보조원장 정책, 공용 검색·테이블·TableSettings·휴지통
- 분개규칙: 결과 계정, 업무 코드, 역할 Projection, 공용 검색·테이블·TableSettings·휴지통
- 기초금액: 회사·회계연도 유일성, 회계기간, 0원 개시, 기초전표 연결, 차대변 균형, 전체 Rollback
- 재고관리: 기초재고·당기증가·당기감소·기말재고, 사업구분·프로젝트, 산출근거·증빙근거, 확정·확정취소, 전체 Rollback

## 운영 데이터 감사 결과

| 항목 | 결과 |
| --- | ---: |
| 계정과목 | 297개 |
| 전기 가능 계정 | 183개 |
| 보조원장 정책 | 306개 |
| 분개규칙 | 16개 |
| 기초금액 | 1건 |
| 재고관리 문서 | 0건 |
| 계정·코드 중복 및 고아 참조 | 0건 |
| 관련 DB Comment 누락 | 0건 |
| 관련 Trigger | 0건 |

## 회귀검증

- `journal_rule_management_role_projection_runtime.php`: PASS
- `ledger_opening_balance_contract.php`: PASS
- `ledger_opening_balance_runtime.php`: PASS 및 전체 Rollback
- `ledger_inventory_balance_runtime.php`: PASS 및 전체 Rollback
- Route·Permission Metadata 감사: PASS
- Service Logging 감사: PASS
- PHP 문법검사 및 JS `node --check`: PASS
- UTF-8 BOM: 0건
- CRLF: 0건
- 한글 깨짐: 0건
- `git diff --check`: PASS

## 확정 경계

- 기초금액은 전표와 연결하며, 0원 개시는 전표 없이 확정 상태로 관리한다.
- 재고관리 문서는 수불부를 대체하지 않는다. 기초재고와 당기 증감의 산출근거·증빙근거를 보존하여 기말재고를 확정한다.
- 재고의 결산조정 전표 및 재무제표 반영은 자산관리 완료 후 결산 Closure에서 연결한다.
- 분개규칙 사전점검의 복합규칙 자동분해 경고는 운영 데이터 오류가 아니라 자동 Migration 금지 조건이다. 현재 16개 규칙의 계정·코드·유효성은 정상이다.
- 브라우저 화면 검증은 인증된 세션이 없어 수행하지 못했다. 서버 Runtime과 정적 UI 계약으로 대체 검증했으며, 최초 사용자 화면 점검에서 시각적 결함이 발견되면 Closure 보완 대상으로 처리한다.

## 재현 명령

```text
php tools/audit_ledger_basic_information.php
php tests/regression/journal_rule_management_role_projection_runtime.php
php tests/regression/ledger_opening_balance_contract.php
php tests/regression/ledger_opening_balance_runtime.php
php tests/regression/ledger_inventory_balance_runtime.php
php tools/audit_route_permission_metadata.php
php tools/audit_service_logging.php
```
