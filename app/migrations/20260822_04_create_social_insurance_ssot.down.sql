ALTER TABLE institution_regular_employment_income_calculation_bases
  DROP CONSTRAINT chk_regular_income_basis_type,
  ADD CONSTRAINT chk_regular_income_basis_type CHECK(basis_type_code IN('EMPLOYMENT_CONTRACT','ATTENDANCE_CLOSURE','LEAVE_USAGE','STATUTORY_STANDARD','INSURANCE_ELIGIBILITY'));
DELETE FROM system_page_registry WHERE page_key='web.institution.social_insurance';
DELETE FROM system_codes WHERE id IN('a8202204-0001-4000-8000-000000000001','a8202204-0002-4000-8000-000000000002','a8202204-0003-4000-8000-000000000003','a8202204-0004-4000-8000-000000000004','a8202204-0005-4000-8000-000000000005','a8202204-0006-4000-8000-000000000006','a8202204-0007-4000-8000-000000000007');
DROP TABLE IF EXISTS institution_social_insurance_audits;
DROP TABLE IF EXISTS institution_social_insurance_assessment_bases;
DROP TABLE IF EXISTS institution_social_insurance_coverages;
