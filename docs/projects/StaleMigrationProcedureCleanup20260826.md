# 잔존 Migration PROCEDURE 정리 운영 절차

대상 Migration은 `20260826_04_remove_stale_migration_procedures`이며 다음 두 임시 실행객체만 제거한다.

- `migrate_20260824_05_extend_journal_rule_learning_ssot`
- `migrate_20260825_04_regular_income_generation`

운영 적용 전 Repository 직접 참조를 재검색하고 `php tools/apply_stale_migration_procedure_cleanup.php preflight`로 객체 종류, DEFINER, 본문 해시와 업무자료 기준선을 확인한다. 격리 테스트는 `php tools/test_stale_migration_procedure_cleanup.php`로 수행한다.

적용은 `php tools/apply_stale_migration_procedure_cleanup.php apply`만 사용한다. 이 도구는 최신 전체 백업을 만든 뒤 파일 크기와 SHA-256을 기록하고, 두 객체의 `SHOW CREATE` 원문 및 해시와 관련 스키마·행 수·요청 상태를 `storage/db_backup` 감사 JSON에 보존한다. 예상 기준선이 다르면 Migration을 실행하지 않는다.

적용 후 `verify`로 대상 Routine 0건을 확인한다. 다른 Routine, 관련 TABLE DDL·행 수, 요청 #8, 급여문서, Evidence·거래·Link·지급예정·전표 기준선은 적용 전과 같아야 한다. Down은 폐기된 실행객체를 복원하지 않고 `SIGNAL`로 중단한다.
