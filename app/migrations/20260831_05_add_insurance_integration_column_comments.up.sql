DELIMITER $$
CREATE PROCEDURE migrate_20260831_05_up()
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'
           AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')
           AND COLUMN_COMMENT='') <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='법정기준 통합 컬럼 COMMENT 기준선이 다릅니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results'
           AND COLUMN_NAME IN('daily_employment_income_item_id','eligibility_status_code','eligibility_reason_code','missing_inputs','snapshot_schema_version')
           AND COLUMN_COMMENT='') <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='가입자격 Result 컬럼 COMMENT 기준선이 다릅니다.';
    END IF;

    ALTER TABLE system_statutory_standards
      MODIFY COLUMN policy_component_code VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '정책 구성요소',
      MODIFY COLUMN employment_type_code VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '고용형태',
      MODIFY COLUMN work_scope_code VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '업무 Scope',
      MODIFY COLUMN additional_dimension_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL COMMENT '추가 차원정보',
      MODIFY COLUMN additional_dimension_key CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '추가 차원키';

    ALTER TABLE institution_daily_employment_income_calculation_results
      MODIFY COLUMN daily_employment_income_item_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '일용근로소득 항목 ID',
      MODIFY COLUMN eligibility_status_code VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '가입자격 판정상태',
      MODIFY COLUMN eligibility_reason_code VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '가입자격 판정사유',
      MODIFY COLUMN missing_inputs LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '가입자격 누락입력',
      MODIFY COLUMN snapshot_schema_version VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Snapshot Schema 버전';
END$$
CALL migrate_20260831_05_up()$$
DROP PROCEDURE migrate_20260831_05_up$$
DELIMITER ;
