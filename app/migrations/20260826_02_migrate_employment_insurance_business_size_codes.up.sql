DELIMITER $$
CREATE PROCEDURE `up_20260826_02`()
BEGIN
  DECLARE matrix_count INT DEFAULT 0;
  DECLARE invalid_count INT DEFAULT 0;

  SELECT COUNT(*) INTO matrix_count
    FROM system_statutory_standards
   WHERE standard_type_code = 'EMPLOYMENT_INSURANCE'
     AND JSON_LENGTH(JSON_EXTRACT(value_data, '$.additional_employer_rates')) > 0;

  SELECT COUNT(*) INTO invalid_count
    FROM system_statutory_standards
   WHERE standard_type_code = 'EMPLOYMENT_INSURANCE'
     AND JSON_LENGTH(JSON_EXTRACT(value_data, '$.additional_employer_rates')) > 0
     AND (
       JSON_LENGTH(JSON_EXTRACT(value_data, '$.additional_employer_rates')) <> 4
       OR BINARY JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[0].business_size_name')) <> BINARY '상시근로자 150명 미만'
       OR BINARY JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[1].business_size_name')) <> BINARY '상시근로자 150명 이상 우선지원대상기업'
       OR BINARY JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[2].business_size_name')) <> BINARY '상시근로자 150명 이상 1,000명 미만'
       OR BINARY JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[3].business_size_name')) <> BINARY '상시근로자 1,000명 이상 및 국가·지방자치단체 직접사업'
       OR ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[0].employer_rate')) AS DECIMAL(18,10)) - 0.0025) > 0.0000000001
       OR ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[1].employer_rate')) AS DECIMAL(18,10)) - 0.0045) > 0.0000000001
       OR ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[2].employer_rate')) AS DECIMAL(18,10)) - 0.0065) > 0.0000000001
       OR ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(value_data, '$.additional_employer_rates[3].employer_rate')) AS DECIMAL(18,10)) - 0.0085) > 0.0000000001
     );

  IF matrix_count = 0 OR invalid_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '고용보험 회사규모 Matrix를 안전하게 코드로 이관할 수 없습니다.';
  END IF;

  UPDATE system_statutory_standards
     SET value_data = JSON_SET(
       value_data,
       '$.additional_employer_rates[0].business_size_code', 'LESS_THAN_150',
       '$.additional_employer_rates[1].business_size_code', 'AT_LEAST_150_PRIORITY_SUPPORT',
       '$.additional_employer_rates[2].business_size_code', 'AT_LEAST_150_LESS_THAN_1000',
       '$.additional_employer_rates[3].business_size_code', 'AT_LEAST_1000_OR_GOVERNMENT'
     ),
     updated_at = NOW(),
     updated_by = 'SYSTEM:WORKPLACE_SIZE_CODE_MIGRATION'
   WHERE standard_type_code = 'EMPLOYMENT_INSURANCE'
     AND JSON_LENGTH(JSON_EXTRACT(value_data, '$.additional_employer_rates')) > 0;

  UPDATE system_codes
     SET extra_data = JSON_SET(
       extra_data,
       '$.fields[2].columns[0].code', 'business_size_code',
       '$.fields[2].columns[0].name', '법정 사업규모 코드',
       '$.fields[2].columns[0].type', 'select',
       '$.fields[2].columns[0].options', JSON_ARRAY(
         JSON_OBJECT('value','LESS_THAN_150','label','상시근로자 150명 미만'),
         JSON_OBJECT('value','AT_LEAST_150_PRIORITY_SUPPORT','label','상시근로자 150명 이상 우선지원대상기업'),
         JSON_OBJECT('value','AT_LEAST_150_LESS_THAN_1000','label','상시근로자 150명 이상 1,000명 미만'),
         JSON_OBJECT('value','AT_LEAST_1000_OR_GOVERNMENT','label','상시근로자 1,000명 이상 및 국가·지방자치단체 직접사업')
       ),
       '$.fields[2].ui.description', '계산목적별 회사규모 기간 SSOT의 안정적인 코드를 사용합니다.'
     ),
     updated_at = NOW(),
     updated_by = 'SYSTEM:WORKPLACE_SIZE_CODE_MIGRATION'
   WHERE code_group = 'STATUTORY_STANDARD_TYPE' AND code = 'EMPLOYMENT_INSURANCE';
END$$
DELIMITER ;
CALL `up_20260826_02`();
DROP PROCEDURE `up_20260826_02`;
