DELIMITER $$
BEGIN NOT ATOMIC
IF @statutory_revision_actor IS NULL OR @statutory_revision_actor NOT LIKE 'SYSTEM:%' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ActorHelper::system()으로 공급한 법정기준 Revision Actor가 필요합니다.';
END IF;

IF EXISTS(SELECT 1 FROM system_statutory_standards WHERE id IN(
    'b15c0001-2026-0903-0000-000000000001','b15c0001-2026-0903-0000-000000000002',
    '10ca1001-2026-0903-0000-000000000001','10ca1001-2026-0903-0000-000000000002'
)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 법정기준 정정 Revision ID가 이미 존재합니다.';
END IF;

UPDATE system_codes
SET extra_data=JSON_SET(
    extra_data,
    '$.calculation_policy.fields',
    JSON_ARRAY(
        JSON_OBJECT('code','method','name','계산 처리방법','type','rounding','required',TRUE),
        JSON_OBJECT('code','discard_below_unit','name','버림 기준단위','type','number','required',TRUE,'min',1,'unit_label','원'),
        JSON_OBJECT('code','stage','name','적용단계','type','text','required',TRUE),
        JSON_OBJECT('code','base_value_code','name','계산기초','type','text','required',TRUE),
        JSON_OBJECT('code','aggregation_unit','name','집계단위','type','text','required',TRUE),
        JSON_OBJECT('code','application_order','name','적용순서','type','number','required',TRUE,'min',0),
        JSON_OBJECT('code','threshold','name','소액부징수 기준액','type','amount','required',TRUE,'min',0),
        JSON_OBJECT('code','threshold_comparison','name','소액부징수 비교방식','type','text','required',TRUE)
    ),
    '$.preserve_schema_in_value', TRUE
),updated_at=NOW(),updated_by=@statutory_revision_actor
WHERE code_group='STATUTORY_STANDARD_TYPE'
  AND code IN('BUSINESS_INCOME_WITHHOLDING','LOCAL_INCOME_TAX_WITHHOLDING');

IF ROW_COUNT()<>2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='사업소득 법정기준 Type Metadata 2건을 갱신하지 못했습니다.';
END IF;

INSERT INTO system_statutory_standards(
    id,sort_no,standard_type_code,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by
) VALUES
('b15c0001-2026-0903-0000-000000000001',900001,'BUSINESS_INCOME_WITHHOLDING','2013-01-01','2024-06-30',
 JSON_OBJECT('rate_value',0.03,'calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10,'stage','AFTER_RATE_CALCULATION','base_value_code','GROSS_PAYMENT','aggregation_unit','WITHHOLDING_AGENT_RECIPIENT_PAYMENT','application_order',1,'threshold',1000,'threshold_comparison','LT')),
 '사업소득 원천징수 3% 및 2024-06-30 이전 소액부징수 정책 정정 Revision',NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('b15c0001-2026-0903-0000-000000000002',900002,'BUSINESS_INCOME_WITHHOLDING','2024-07-01',NULL,
 JSON_OBJECT('rate_value',0.03,'calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10,'stage','AFTER_RATE_CALCULATION','base_value_code','GROSS_PAYMENT','aggregation_unit','WITHHOLDING_AGENT_RECIPIENT_PAYMENT','application_order',1,'threshold',0,'threshold_comparison','NONE')),
 '2024-07-01 이후 인적용역 사업소득 소액부징수 제외 정정 Revision',NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('10ca1001-2026-0903-0000-000000000001',900003,'LOCAL_INCOME_TAX_WITHHOLDING','2013-01-01','2013-12-31',
 JSON_OBJECT('rate_value',0.1,'calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10,'stage','AFTER_INCOME_TAX','base_value_code','INCOME_TAX','aggregation_unit','WITHHOLDING_AGENT_RECIPIENT_PAYMENT','application_order',2,'threshold',0,'threshold_comparison','NONE')),
 '2013년 사업소득 지방소득세 완전 계산정책 정정 Revision',NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('10ca1001-2026-0903-0000-000000000002',900004,'LOCAL_INCOME_TAX_WITHHOLDING','2014-01-01',NULL,
 JSON_OBJECT('rate_value',0.1,'calculation_policy',JSON_OBJECT('method','TRUNCATE','discard_below_unit',10,'stage','AFTER_INCOME_TAX','base_value_code','INCOME_TAX','aggregation_unit','WITHHOLDING_AGENT_RECIPIENT_PAYMENT','application_order',2,'threshold',0,'threshold_comparison','NONE')),
 '2014년 이후 개인지방소득세 완전 계산정책 정정 Revision',NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor);

INSERT INTO system_statutory_standard_sources(
    id,standard_id,organization_name,law_name,notice_no,source_name,source_url,published_at,file_path,file_name,file_size,mime_type,note,sort_no,created_at,created_by,updated_at,updated_by
) VALUES
('b15c1001-2026-0903-0000-000000000001','b15c0001-2026-0903-0000-000000000001','국세청','소득세법 제86조·제129조',NULL,'사업소득 원천징수 세율 및 소액부징수','https://www.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7701&mi=2413',NULL,NULL,NULL,NULL,NULL,'사업소득 3%와 2024-07-01 전후 소액부징수 정책',1,NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('b15c1001-2026-0903-0000-000000000002','b15c0001-2026-0903-0000-000000000002','국세청','소득세법 제86조·제129조',NULL,'인적용역 사업소득 소액부징수 제외','https://www.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7701&mi=2413',NULL,NULL,NULL,NULL,NULL,'2024-07-01 이후 지급분 경계',1,NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('10ca1101-2026-0903-0000-000000000001','10ca1001-2026-0903-0000-000000000001','국가법령정보센터','지방세법 제103조의13',NULL,'지방소득세 특별징수','https://www.law.go.kr/법령/지방세법/제103조의13',NULL,NULL,NULL,NULL,NULL,'원천징수 소득세의 10%',1,NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor),
('10ca1101-2026-0903-0000-000000000002','10ca1001-2026-0903-0000-000000000002','국가법령정보센터','지방세법 제103조의13',NULL,'개인지방소득세 특별징수','https://www.law.go.kr/법령/지방세법/제103조의13',NULL,NULL,NULL,NULL,NULL,'2014년 이후 원천징수 소득세의 10%',1,NOW(),@statutory_revision_actor,NOW(),@statutory_revision_actor);

INSERT INTO system_statutory_standard_supersessions(
    id,predecessor_revision_id,successor_revision_id,correction_reason,created_at,created_by
) VALUES
('5a5e0001-2026-0903-0000-000000000001','021889f4-3c43-466d-8e33-80f9f39455bc','b15c0001-2026-0903-0000-000000000001','불완전한 사업소득 계산정책을 기간별 완전 정책으로 정정',NOW(),@statutory_revision_actor),
('5a5e0001-2026-0903-0000-000000000002','b15c0001-2026-0903-0000-000000000001','b15c0001-2026-0903-0000-000000000002','2024-07-01 인적용역 사업소득 소액부징수 제외 시행 경계 반영',NOW(),@statutory_revision_actor),
('5a5e0001-2026-0903-0000-000000000003','7255f865-08d0-4fd9-b9d6-85361f93fe0a','10ca1001-2026-0903-0000-000000000001','2013년 지방소득세 계산정책 필수항목 보완',NOW(),@statutory_revision_actor),
('5a5e0001-2026-0903-0000-000000000004','7af041bd-74b5-4d85-a489-f8dc703c5a06','10ca1001-2026-0903-0000-000000000002','2014년 이후 개인지방소득세 계산정책 필수항목 보완',NOW(),@statutory_revision_actor);
END$$
DELIMITER ;
