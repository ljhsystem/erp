# 근로계약 5개 테이블 최종 123컬럼 정합성 감사

## 결재함 금액 정합성

- 월 지급합계 SSOT는 `institution_employment_contracts_components` 활성 행의 `SUM(amount)`다.
- 결재함 공용 `총금액`은 근로계약 문서에서 월 지급합계를 뜻하며, 연 환산액은 `월 지급합계 × 12` 파생값으로 상세에만 함께 표시한다.
- 목록은 계약별 지급조건 사전 집계 projection을 사용하고, 상세는 `EmploymentContractApprovalAdapter`가 같은 components 조회 결과로 `total_amount`, `monthly_total_amount`, `annualized_amount`를 제공한다.
- 삭제된 지급항목은 목록·상세 합계에서 제외하며 헤더 또는 결재요청에 중복 합계를 저장하지 않는다.

## 기준

- 실행 DB: `SUKHYANGNAS:3307/sukhyang`
- DBMS: MariaDB `10.11.11`
- 조사일: 2026-08-03
- 실제 `SHOW CREATE TABLE`, `INFORMATION_SCHEMA.COLUMNS`, `STATISTICS`, `TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE` 결과를 기준으로 했다.
- 전체 컬럼별 11항목 매트릭스는 `php tools/audit_employment_contract_schema.php --matrix`로 재현한다. COMMENT와 필수 여부는 실행 DB에서 직접 읽으므로 문서 복제값이 아니다.

## 전수검사 결과

| 테이블 | 컬럼 수 | COMMENT 누락 | 모달·업무 표현 책임 |
|---|---:|---:|---|
| `institution_employment_contracts` | 46 | 0 | 계약 헤더, 분류, 기간, 근무형태, 지급기준, 결재상태 |
| `institution_employment_contracts_components` | 31 | 0 | 지급항목, 정형 계산요소, 확정금액, 마스터 정책 스냅샷 |
| `institution_employment_contracts_pay_components` | 21 | 0 | 계약 모달에서 편집하지 않는 급여항목 공용 마스터 |
| `institution_employment_contracts_weekly_schedules` | 12 | 0 | `NORMAL`·`NIGHT` 반복 일정 및 `FLEXIBLE`·`OTHER` 기준 반복 일정의 월~일 7행 SSOT |
| `institution_employment_contracts_work_schedule_policies` | 13 | 0 | 비고정 근무형태의 계약당 1행 상세 SSOT |

## 표현 판정

- 직접입력: 직원, 계약분류 3축, 계약기간, 기간제 사유, 근무장소·프로젝트·직무, 근무형태, 급여 지급조건, 수습, 비고.
- 자동계산: 주근무일수, 주근무시간, 평균 일근무시간, 기준 출퇴근·휴게시간, 주휴일. `weekly_schedules`에서 Service가 계산한다.
- 자동생성: ID, 순번, 계약번호, 개정차수, 당사자 스냅샷, Actor·일시, 결재요청 참조.
- 읽기전용: 계약번호, 이전계약, 개정정보, 계약상태, 승인·종료 정보, Actor 표시명.
- 조건부입력: 종료일·기간제 사유, 비고정 근무형태 정책, 근무일 시간, 수당 계산요소.
- 화면 비노출: PK·FK 원본, 암호화 식별정보, Actor 원본 토큰, 계산·과세·임금판정 스냅샷 원본 코드. 필요한 정책은 한글 읽기전용 요약으로 표시한다.
- 마스터 전용: 급여항목 마스터의 활성기간·과세기본값·임금 산입정책·정렬·삭제·감사 컬럼.

## SSOT 결정

- 근무시간 요약은 요일별 일정에서 파생하며 계약 헤더에 저장하지 않는다.
- 일반·야간은 주간 반복 일정만, 선택·교대는 비고정 정책만 사용한다. 탄력·기타는 기준 반복 일정과 추가 정책을 함께 사용한다.
- 선택·탄력·교대·기타의 상세 SSOT는 `institution_employment_contracts_work_schedule_policies`이다.
- 지급조건 금액 SSOT는 각 component의 `amount`이며 합계·환산액은 저장하지 않는다.
- 기본급 산식은 `quantity`를 기준시간으로 사용한 `기준시간 × 기준단가`다.
- 계약대상 법률판정, 계약별 법정 휴일·연차, 출력·체결·교부·서면보관은 근로계약관리 저장 책임에서 제외한다.

## 검증 명령

```text
php tools/audit_employment_contract_schema.php --counts
php tools/audit_employment_contract_schema.php --matrix
php tools/audit_employment_contract_schema.php --codes
php tools/audit_employment_contract_schema.php --pay-components
php tools/audit_employment_contract_schema.php --integrity
```

`--integrity`는 component·weekly schedule·policy 고아행, 헤더와 정책의 근무형태 불일치, 선택·교대 계약의 weekly schedule 잔존을 검사한다.
