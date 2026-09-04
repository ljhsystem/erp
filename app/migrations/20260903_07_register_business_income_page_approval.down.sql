-- 운영 적용 후 자동 Down 실행 금지. 승인된 복구 절차에서만 사용한다.
DELETE step_row FROM user_approval_template_steps step_row JOIN user_approval_templates template_row ON template_row.id=step_row.template_id WHERE template_row.template_key='BUSINESS_INCOME_DEFAULT';
DELETE FROM user_approval_templates WHERE template_key='BUSINESS_INCOME_DEFAULT';
DELETE role_permission FROM auth_role_permissions role_permission JOIN auth_permissions permission_row ON permission_row.id=role_permission.permission_id WHERE permission_row.page_key='web.institution.income_data.business_income';
DELETE FROM auth_permissions WHERE page_key='web.institution.income_data.business_income';
UPDATE system_page_registry SET page_description='사업소득 Placeholder',source_description='Placeholder',updated_at=NOW() WHERE page_key='web.institution.income_data.business_income';
