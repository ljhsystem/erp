DELIMITER $$
CREATE PROCEDURE migrate_20260831_05_down()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'
           AND ((COLUMN_NAME='policy_component_code' AND COLUMN_COMMENT='정책 구성요소')
             OR (COLUMN_NAME='employment_type_code' AND COLUMN_COMMENT='고용형태')
             OR (COLUMN_NAME='work_scope_code' AND COLUMN_COMMENT='업무 Scope')
             OR (COLUMN_NAME='additional_dimension_data' AND COLUMN_COMMENT='추가 차원정보')
             OR (COLUMN_NAME='additional_dimension_key' AND COLUMN_COMMENT='추가 차원키'))) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 통합 컬럼 COMMENT Down 기준선이 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results'
           AND ((COLUMN_NAME='daily_employment_income_item_id' AND COLUMN_COMMENT='일용근로소득 항목 ID')
             OR (COLUMN_NAME='eligibility_status_code' AND COLUMN_COMMENT='가입자격 판정상태')
             OR (COLUMN_NAME='eligibility_reason_code' AND COLUMN_COMMENT='가입자격 판정사유')
             OR (COLUMN_NAME='missing_inputs' AND COLUMN_COMMENT='가입자격 누락입력')
             OR (COLUMN_NAME='snapshot_schema_version' AND COLUMN_COMMENT='Snapshot Schema 버전'))) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='가입자격 Result 컬럼 COMMENT Down 기준선이 다릅니다.';
    END IF;

    ALTER TABLE system_statutory_standards
      MODIFY COLUMN policy_component_code VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN employment_type_code VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN work_scope_code VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN additional_dimension_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN additional_dimension_key CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '';

    ALTER TABLE institution_daily_employment_income_calculation_results
      MODIFY COLUMN daily_employment_income_item_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN eligibility_status_code VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN eligibility_reason_code VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN missing_inputs LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '',
      MODIFY COLUMN snapshot_schema_version VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '';
END$$
CALL migrate_20260831_05_down()$$
DROP PROCEDURE migrate_20260831_05_down$$
DELIMITER ;
