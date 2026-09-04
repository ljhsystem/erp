DROP PROCEDURE IF EXISTS cleanup_20260826_04_stale_migration_procedures;

DELIMITER $$
CREATE PROCEDURE cleanup_20260826_04_stale_migration_procedures()
BEGIN
    DECLARE v_named_tables INT DEFAULT 0;
    DECLARE v_unexpected_routines INT DEFAULT 0;
    DECLARE v_expected_procedures INT DEFAULT 0;

    SELECT COUNT(*) INTO v_named_tables
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN (
           'migrate_20260824_05_extend_journal_rule_learning_ssot',
           'migrate_20260825_04_regular_income_generation'
       );

    IF v_named_tables <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '잔존 Routine 정리 대상 이름으로 TABLE 또는 VIEW가 존재합니다.';
    END IF;

    SELECT COUNT(*) INTO v_unexpected_routines
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE()
       AND ROUTINE_NAME IN (
           'migrate_20260824_05_extend_journal_rule_learning_ssot',
           'migrate_20260825_04_regular_income_generation'
       )
       AND (
           ROUTINE_TYPE <> 'PROCEDURE'
           OR DEFINER <> 'sukhyang@%'
           OR (ROUTINE_NAME = 'migrate_20260824_05_extend_journal_rule_learning_ssot'
               AND SHA2(ROUTINE_DEFINITION, 256) <> '21be8dfaffb4c55ceb356fcbb13ed3a39f1af8a49317ef68dcd93d04af21512f')
           OR (ROUTINE_NAME = 'migrate_20260825_04_regular_income_generation'
               AND SHA2(ROUTINE_DEFINITION, 256) <> '08f4fb84fc054bff064906508266e3f393378c152551e8419b45e8c1e8741224')
       );

    IF v_unexpected_routines <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '잔존 Migration PROCEDURE의 종류, DEFINER 또는 본문 해시가 기준선과 다릅니다.';
    END IF;

    SELECT COUNT(*) INTO v_expected_procedures
      FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = DATABASE()
       AND ROUTINE_TYPE = 'PROCEDURE'
       AND DEFINER = 'sukhyang@%'
       AND (
           (ROUTINE_NAME = 'migrate_20260824_05_extend_journal_rule_learning_ssot'
            AND SHA2(ROUTINE_DEFINITION, 256) = '21be8dfaffb4c55ceb356fcbb13ed3a39f1af8a49317ef68dcd93d04af21512f')
           OR
           (ROUTINE_NAME = 'migrate_20260825_04_regular_income_generation'
            AND SHA2(ROUTINE_DEFINITION, 256) = '08f4fb84fc054bff064906508266e3f393378c152551e8419b45e8c1e8741224')
       );

    IF v_expected_procedures NOT IN (0, 1, 2) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '잔존 Migration PROCEDURE 확인 결과가 유효하지 않습니다.';
    END IF;

END$$
DELIMITER ;

CALL cleanup_20260826_04_stale_migration_procedures();
DROP PROCEDURE cleanup_20260826_04_stale_migration_procedures;
DROP PROCEDURE IF EXISTS migrate_20260824_05_extend_journal_rule_learning_ssot;
DROP PROCEDURE IF EXISTS migrate_20260825_04_regular_income_generation;
