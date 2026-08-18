# 대외기관업무 인사·노무 테이블 네이밍 최종 정리

## 확정 규칙

- 페이지 소유 업무 테이블: `institution_{복수형 page_domain}_*`
- 전사 공용 직원·조직 마스터: 기존 `user_employees`, `user_departments`, `user_positions` 유지
- 상태 전환: 신규 Rename Migration의 `RENAME TABLE`만 사용
- 금지: DROP/CREATE, 호환 View, 구형명 fallback, 이중 읽기/쓰기

## 근로계약관리 하위 테이블

| 기존 테이블 | 신규 테이블 | 책임 | Master/Transaction/Snapshot | 변경 결과 |
|---|---|---|---|---|
| `institution_employment_contracts` | `institution_employment_contracts` | 근로계약 헤더 | Transaction | 유지 |
| `institution_employment_pay_components` | `institution_employment_contracts_pay_components` | 근로계약에서 선택하는 급여항목 기준정보 | Master | Rename |
| `institution_employment_contract_components` | `institution_employment_contracts_components` | 계약별 급여·수당·공제 및 계산정책·금액 | Snapshot | Rename |
| `institution_employment_contract_weekly_schedules` | `institution_employment_contracts_weekly_schedules` | 계약별 주간 반복 소정근로 일정 | Snapshot | Rename |
| `institution_employment_contract_work_schedule_policies` | `institution_employment_contracts_work_schedule_policies` | 계약별 비고정 근로형태 정책 | Snapshot | Rename |

급여항목 마스터는 근로계약 전용이며 전사 급여대장·보상 지급항목을 책임지지 않는다. 계약별 구성행은 계약 당시 항목 코드·명칭, 수당/공제 구분, 계산방식, 과세 여부, 통상임금·평균임금·최저임금 반영 정책과 확정 금액을 스냅샷으로 보존한다. 근태는 실제 출퇴근·일별 계산·월마감만 책임지며 계약 금액을 소유하지 않는다.

## 적용 Migration

- Up: `app/migrations/20260805_04_rename_institution_hr_page_tables.up.sql`
- Down: `app/migrations/20260805_04_rename_institution_hr_page_tables.down.sql`
- 22개 업무 테이블을 하나의 `RENAME TABLE` 문으로 전환한다.
- 기존 적용 Migration은 역사 기록으로 수정하지 않는다.
