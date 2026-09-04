# 개인경비 역할형 분개규칙 Seed Closure

## Migration 계약

- `20260825_02_seed_personal_expense_role_based_journal_rules`는 기존 Migration 10·11·14와 비용분류 Migration을 수정하거나 재실행하지 않는다.
- 대상은 USER Rule 6건과 각 Rule의 revision 1 CREATE Snapshot 6건이다.
- `rule_code`와 Service 동일 `condition_hash`, 전체 Payload로 신규·멱등·충돌을 판정한다. 일부 Rule, Revision 누락, Snapshot 불일치 상태에서는 전체를 차단한다.
- Migration Actor는 `ActorHelper::system('PERSONAL_EXPENSE_ROLE_RULE_SEED')`이며 사용자 UUID를 가장하지 않는다.
- `request_key`는 API 명령 멱등 키이므로 이번 Seed에 사용하거나 숨겨 저장하지 않는다. 향후 API 명령 멱등성이 필요하면 Rule Header가 아닌 공용 멱등 요청 구조로 별도 설계한다.
- 운영 사용 이후 안전한 삭제를 증명할 수 없으므로 Down은 데이터를 삭제하지 않고 forward-only 오류를 반환한다.

## 추천조회 계약

- 전표 작성 화면의 `linkedEvidences`에는 저장된 Link와 저장 전 `추가 예정` 증빙이 함께 있으며 추천 요청은 이 identity 목록을 전송한다.
- 서버는 증빙 테이블에서 존재·상태·공식 비용분류를 다시 확인하고 중복 identity를 한 번만 처리한다.
- 개인경비 차변은 비용분류별 EXPENSE 규칙, 대변은 `item_code=NULL`인 EMPLOYEE_ACCRUED_EXPENSE 공통 규칙을 사용한다.
- 미선택, 증빙 부재·미완료, Source Context 없음, 비용분류 없음, 조건 불일치, API 오류를 구분해 표시한다.
