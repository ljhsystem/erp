UPDATE auth_permissions
SET is_active=0,
    updated_at=NOW(),
    updated_by='SYSTEM:MIGRATION'
WHERE permission_key LIKE 'api.settings.statutory_standards.%'
  AND permission_key NOT IN (
      'api.settings.statutory_standards.view',
      'api.settings.statutory_standards.detail',
      'api.settings.statutory_standards.options',
      'api.settings.statutory_standards.save',
      'api.settings.statutory_standards.delete',
      'api.settings.statutory_standards.resolve',
      'api.settings.statutory_standards.source_file'
  );

UPDATE system_page_registry
SET page_label='법정기준관리',
    page_description='법정기준 적용기간·기준값·관련근거 관리',
    source_description='법정기준관리',
    updated_at=NOW()
WHERE page_key='settings.statutory_standards.manage';

UPDATE system_menu_registry
SET menu_label='법정기준관리',updated_at=NOW()
WHERE menu_key='settings.statutory_standards';
