UPDATE system_codes
SET extra_data=JSON_REMOVE(extra_data,'$.scope_fields'),
    updated_at=NOW(),
    updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE'
  AND deleted_at IS NULL
  AND JSON_VALID(extra_data);

SET @industrial_accident_field = JSON_OBJECT(
    'code','industry_rates',
    'name','사업종류별 산재보험료율',
    'type','matrix',
    'required',TRUE,
    'columns',JSON_ARRAY(
        JSON_OBJECT(
            'code','industry_name',
            'name','사업종류',
            'type','text',
            'required',TRUE,
            'key_part',TRUE,
            'width',240
        ),
        JSON_OBJECT(
            'code','employer_rate',
            'name','사업주 부담률',
            'type','rate',
            'required',TRUE,
            'min',0,
            'max',1,
            'width',180
        )
    ),
    'ui',JSON_OBJECT(
        'collapsible',TRUE,
        'default_expanded',TRUE,
        'allow_paste',FALSE,
        'title','사업종류별 산재보험료율',
        'description','공식 사업종류명과 해당 사업주의 산재보험 부담률을 행 단위로 관리합니다.'
    )
);

UPDATE system_codes
SET extra_data=JSON_OBJECT(
        'fields',JSON_ARRAY(JSON_EXTRACT(@industrial_accident_field,'$')),
        'calculation_policy',JSON_OBJECT('fields',JSON_ARRAY()),
        'preserve_schema_in_value',TRUE
    ),
    updated_at=NOW(),
    updated_by='SYSTEM:MIGRATION'
WHERE code_group='STATUTORY_STANDARD_TYPE'
  AND code='INDUSTRIAL_ACCIDENT'
  AND deleted_at IS NULL;

UPDATE system_statutory_standards
SET value_data=JSON_OBJECT(
        'industry_rates',JSON_ARRAY(JSON_OBJECT(
            'industry_name',JSON_UNQUOTE(JSON_EXTRACT(scope_data,'$.industry_code')),
            'employer_rate',CAST(JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.employer_rate')) AS DECIMAL(18,12))
        )),
        '_schema',JSON_OBJECT(
            'version',1,
            'fields',JSON_ARRAY(JSON_EXTRACT(@industrial_accident_field,'$')),
            'calculation_policy',JSON_OBJECT('fields',JSON_ARRAY())
        )
    ),
    updated_at=NOW(),
    updated_by='SYSTEM:MIGRATION'
WHERE standard_type_code='INDUSTRIAL_ACCIDENT';

ALTER TABLE system_statutory_standards
    DROP COLUMN IF EXISTS scope_data;
