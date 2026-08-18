UPDATE system_page_registry
SET menu_key='settings.standard',
    menu_label='기준관리',
    page_label='코드관리',
    page_description='ERP 공통 코드 관리',
    breadcrumb='설정 > 기준관리 > 코드관리',
    default_route_key='code.view',
    default_route_url='/dashboard/settings/standard/code',
    updated_at=NOW()
WHERE page_key='settings.system.codes';

UPDATE system_page_registry
SET menu_key='settings.standard',
    menu_label='기준관리',
    page_label='법정기준관리',
    page_description='법정기준 적용기간·기준값·관련근거 관리',
    breadcrumb='설정 > 기준관리 > 법정기준관리',
    default_route_key='web.settings.statutory_standards.manage',
    default_route_url='/dashboard/settings/standard/statutory-standards',
    updated_at=NOW()
WHERE page_key='settings.statutory_standards.manage';

UPDATE system_menu_registry
SET menu_label='코드관리',
    menu_order=30,
    page_order=1,
    default_entry='/dashboard/settings/standard/code',
    updated_at=NOW()
WHERE page_key='settings.system.codes';

UPDATE system_menu_registry
SET menu_label='법정기준관리',
    menu_order=30,
    page_order=2,
    default_entry='/dashboard/settings/standard/statutory-standards',
    updated_at=NOW()
WHERE page_key='settings.statutory_standards.manage';
