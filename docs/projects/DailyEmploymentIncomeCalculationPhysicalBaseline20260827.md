# 일용근로소득 기관계산 물리 Baseline

## 판정

운영 DB는 변경하지 않았다. `_15·_16` 신규 SQL은 임시 MariaDB Schema에서 Up/Down을 통과했다. 다만 기존 불변 `_07·_19` 원본은 현재 MariaDB의 FK 컬럼 CHECK·GENERATED 제한으로 1901 오류가 발생하므로 원본 fresh install과 운영 통합 적용은 차단 상태다.

## Migration 실제 상태

| ID | Repository | 운영 Schema | 판정 |
|---|---|---|---|
| `_07` | Closure·Item 중심 Link 파일 존재 | Closure·Link 없음 | 미적용, 원본 CHECK MariaDB 1901 |
| `_12` | 문서번호 제거·Item 공종/작업내용 파일 존재 | 해당 구조 적용 | 적용 구조 확인 |
| `_13` | 전용 Grid 설정 삭제 파일 존재 | 업무 테이블 영향 없음 | 파일 존재 |
| `_14` | Group 작업내용 복원 파일 존재 | Group 작업내용 없음 | 미적용 |
| `_15` | Calculation Revision·Result·Allocation 신규 준비 | 관련 테이블 없음 | 운영 미적용 |
| `_16` | Reconciliation·Snapshot·Closure 정상화 신규 준비 | 관련 테이블 없음 | 운영 미적용 |
| `_17` | 파일 없음 | 관련 신규 구조 없음 | 과거 Attachment 예약, 빈 파일 미생성 |
| `_18` | 실제 근로시간 준비 | 컬럼 없음 | 운영 미적용 |
| `_19` | 비과세 Revision·Line 준비 | 테이블·컬럼 없음 | 운영 미적용, 원본 FK CHECK/GENERATED MariaDB 1901 |
| `_20` | Attachment 준비 | 테이블 없음 | 운영 미적용 |
| `_21` | Command·Audit 준비 | 비과세 확장 없음 | 운영 미적용 |

## `_15` 책임

`_14` ID가 이미 다른 불변 Migration에 점유되어 있으므로 fresh-install 번호순서를 유지하기 위해 `_15`가 Calculation Revision·Institution Result·Allocation을 함께 소유한다.

- Calculation Revision: 문서+Revision 번호, 문서+source hash UNIQUE
- 상태: DRAFT, CALCULATED, CONFIRMED, STALE, FAILED, SUPERSEDED
- Result: 세금 2종과 보험 5종
- 세금 Grain: Revision·종류·근로자·근무일·지급일·지급회차
- 보험 Grain: Revision·종류·보험사업장·근로자·적용기간
- NULL-safe UNIQUE: MariaDB 호환 물리 Scope Key 사용
- Allocation: Group·Item·Workday, 배부기초·분자·분모·절사잔액·수령행·결정순위

FK 컬럼 기반 GENERATED와 CHECK는 현재 MariaDB가 거부하므로 Scope Key는 저장 Service가 공식 FK 값과 동시에 확정하고 검증해야 한다.

## `_16` 책임

- Reconciliation Header: 문서·Calculation Revision·source hash·PASSED/FAILED/STALE·차단 건수
- Check: 세금·보험 Allocation, Item/Group/Header 합계, 실지급 대사
- `ABS(difference_amount)>=1`이면 FAILED
- Item·Header에는 결재·지급 재현용 계산 Snapshot과 최신 Revision 참조만 저장
- Closure는 Calculation Revision과 Reconciliation을 참조
- Accounting Link UNIQUE는 `(closure_id,generation_role,business_key_hash)`
- Evidence는 기관 Grain, Transaction은 작업자 지급 Grain, Voucher는 회계 Grain

## source_hash

서버 `DailyEmploymentIncomeCalculationSourceService`가 귀속연월·지급일/회차·정책 버전, Group 차원, 작업자·공종·작업내용, Workday 날짜·시간·단가, 과세 Line, 확인된 비과세 Line, 보험사업장 Resolver와 법정기준을 결정적으로 정렬해 SHA-256을 만든다. UI 상태, 표시명, 클라이언트 합계와 DRAFT 비과세는 제외한다.

## STALE와 비과세 연결

Calculation Revision과 Reconciliation의 STALE 물리상태는 마련됐다. 원천 변경 시 기존 Result·Allocation·Check를 삭제하지 않고 최신 계산 Revision과 Reconciliation을 STALE로 바꾸며 Item·Header Snapshot을 STALE로 투영해야 한다. `_21` Command 완료와 동일 Transaction에 연결할 Runtime Service는 Permission·Attachment·Storage와 전체 Migration 운영 적용 전까지 활성화하지 않는다.

## 임시 Schema 결과

- 원본 `_07`: MariaDB 1901 실패 확인
- 원본 `_19`: MariaDB 1901 실패 확인
- 임시 호환 Bootstrap 적용 후 `_07·_13~_16·_18~_21` Up 성공
- 빈 자료 Down 역순 성공
- 생성 테이블 확인 성공
- Fixture Schema `tmp_daily_calc_*`는 `finally`에서 제거
- 업무자료 Fixture와 Allocation/Reconciliation 금액 Fixture는 아직 미실행

임시 호환은 검증 도구 내부에서만 `_07` CHECK와 `_19` FK 기반 CHECK·GENERATED를 대체하며 기존 Migration 파일은 수정하지 않는다.

## 운영 적용 차단사항

1. `_07` MariaDB 호환 보완용 신규 forward-only Bootstrap 전략 승인
2. `_19` MariaDB 호환 보완용 신규 forward-only 전략 승인
3. Calculation 저장·STALE·Allocation·Reconciliation Service Transaction
4. 비과세 Command Runtime 연결
5. Permission·Attachment·Storage Runtime
6. 업무자료 통합 Fixture와 1원 차단 검증
7. 원본 fresh install 성공
8. 브라우저 시각검증

운영 DB 변경 건수는 0건이다.
