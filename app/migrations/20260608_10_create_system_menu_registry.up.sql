CREATE TABLE IF NOT EXISTS `system_menu_registry` (
    `menu_key` VARCHAR(191) NOT NULL COMMENT '공통 menu 식별키',
    `page_key` VARCHAR(191) NULL DEFAULT NULL COMMENT '연결된 system_page_registry.page_key',
    `module_key` VARCHAR(100) NOT NULL COMMENT '최상위 모듈 key',
    `menu_label` VARCHAR(100) NOT NULL COMMENT '메뉴 표시명',
    `module_order` INT NOT NULL DEFAULT 999 COMMENT '모듈 정렬순서',
    `menu_order` INT NOT NULL DEFAULT 999 COMMENT '메뉴 그룹 정렬순서',
    `page_order` INT NOT NULL DEFAULT 999 COMMENT '페이지 정렬순서',
    `menu_icon` VARCHAR(100) NULL DEFAULT NULL COMMENT '메뉴 아이콘 클래스',
    `default_entry` VARCHAR(255) NULL DEFAULT NULL COMMENT '대표 진입 URL',
    `is_menu` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '메뉴 여부',
    `visible_in_sidebar` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sidebar 노출 여부',
    `visible_in_settings` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Settings Menu 노출 여부',
    `visible_in_sitemap` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'SiteMap 노출 여부',
    `visible_in_navbar` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Navbar 노출 여부',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '활성 여부',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`menu_key`),
    KEY `idx_system_menu_registry_page_key` (`page_key`),
    KEY `idx_system_menu_registry_module` (`module_key`),
    KEY `idx_system_menu_registry_sidebar` (`visible_in_sidebar`),
    KEY `idx_system_menu_registry_settings` (`visible_in_settings`),
    KEY `idx_system_menu_registry_sitemap` (`visible_in_sitemap`),
    KEY `idx_system_menu_registry_navbar` (`visible_in_navbar`),
    KEY `idx_system_menu_registry_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ERP 공통 Menu Registry';

INSERT INTO `system_menu_registry` (
    `menu_key`,
    `page_key`,
    `module_key`,
    `menu_label`,
    `module_order`,
    `menu_order`,
    `page_order`,
    `menu_icon`,
    `default_entry`,
    `is_menu`,
    `visible_in_sidebar`,
    `visible_in_settings`,
    `visible_in_sitemap`,
    `visible_in_navbar`,
    `is_active`
)
SELECT
    spr.page_key AS `menu_key`,
    spr.page_key,
    spr.module_key,
    spr.page_label AS `menu_label`,
    CASE spr.module_key
        WHEN 'dashboard' THEN 10
        WHEN 'settings' THEN 20
        WHEN 'ledger' THEN 30
        WHEN 'approval' THEN 40
        WHEN 'document' THEN 50
        WHEN 'shop' THEN 60
        WHEN 'site' THEN 70
        WHEN 'notice' THEN 80
        WHEN 'institution' THEN 90
        WHEN 'public' THEN 100
        WHEN 'profile' THEN 110
        ELSE 999
    END AS `module_order`,
    CASE spr.menu_key
        WHEN 'dashboard.main' THEN 10
        WHEN 'settings.base_info' THEN 20
        WHEN 'settings.organization' THEN 30
        WHEN 'settings.system' THEN 40
        WHEN 'ledger.settings' THEN 50
        WHEN 'ledger.data' THEN 60
        WHEN 'ledger.vouchers' THEN 70
        WHEN 'ledger.funds' THEN 80
        WHEN 'ledger.book' THEN 90
        WHEN 'ledger.financial' THEN 100
        WHEN 'ledger.asset' THEN 110
        WHEN 'ledger.tax' THEN 120
        WHEN 'document.main' THEN 130
        WHEN 'approval.main' THEN 140
        WHEN 'shop.main' THEN 150
        WHEN 'site.main' THEN 160
        WHEN 'notice.main' THEN 170
        WHEN 'institution.main' THEN 180
        WHEN 'public.main' THEN 190
        WHEN 'profile.main' THEN 200
        ELSE 999
    END AS `menu_order`,
    CASE spr.page_key
        WHEN 'dashboard.main' THEN 10
        WHEN 'dashboard.report' THEN 20
        WHEN 'dashboard.activity' THEN 30
        WHEN 'dashboard.notifications' THEN 40
        WHEN 'dashboard.kpi' THEN 50
        WHEN 'dashboard.calendar' THEN 60
        WHEN 'dashboard.settings' THEN 70
        WHEN 'document.dashboard' THEN 10
        WHEN 'document.file_register' THEN 20
        WHEN 'document.view' THEN 30
        WHEN 'document.edit' THEN 40
        WHEN 'document.stats' THEN 50
        WHEN 'approval.dashboard' THEN 10
        WHEN 'ledger.dashboard' THEN 10
        WHEN 'ledger.settings.accounts' THEN 10
        WHEN 'ledger.settings.journal_rules' THEN 20
        WHEN 'ledger.settings.opening_balances' THEN 30
        WHEN 'ledger.settings.sub_accounts' THEN 40
        WHEN 'ledger.data.list' THEN 10
        WHEN 'ledger.data.create_center' THEN 20
        WHEN 'ledger.data.formats' THEN 30
        WHEN 'ledger.data.upload' THEN 40
        WHEN 'ledger.transactions' THEN 10
        WHEN 'ledger.vouchers' THEN 20
        WHEN 'ledger.vouchers.index' THEN 30
        WHEN 'ledger.funds.bank_transactions' THEN 10
        WHEN 'ledger.funds.payment_info' THEN 20
        WHEN 'ledger.funds.deposit_ledger' THEN 30
        WHEN 'ledger.funds.cash_ledger' THEN 40
        WHEN 'ledger.funds.daily_report' THEN 50
        WHEN 'ledger.funds.account_balances' THEN 60
        WHEN 'ledger.funds.unlinked_transactions' THEN 70
        WHEN 'ledger.funds.payment_schedule' THEN 80
        WHEN 'ledger.funds.reconciliation' THEN 90
        WHEN 'ledger.book.journal' THEN 10
        WHEN 'ledger.book.account' THEN 20
        WHEN 'ledger.book.general' THEN 30
        WHEN 'ledger.book.partner' THEN 40
        WHEN 'ledger.book.project' THEN 50
        WHEN 'ledger.book.daily' THEN 60
        WHEN 'ledger.book.purchase_sales' THEN 70
        WHEN 'ledger.book.vehicle_log' THEN 80
        WHEN 'ledger.financial.trial_balance' THEN 10
        WHEN 'ledger.financial.income_statement' THEN 20
        WHEN 'ledger.financial.statement_position' THEN 30
        WHEN 'ledger.financial.product_cost' THEN 40
        WHEN 'ledger.financial.construction_cost' THEN 50
        WHEN 'ledger.financial.retained_earnings' THEN 60
        WHEN 'ledger.assets.create' THEN 10
        WHEN 'ledger.assets.index' THEN 20
        WHEN 'ledger.assets.depreciation' THEN 30
        WHEN 'ledger.assets.transfer' THEN 40
        WHEN 'ledger.assets.disposal' THEN 50
        WHEN 'ledger.tax.trial_balance' THEN 10
        WHEN 'ledger.tax.income_statement' THEN 20
        WHEN 'ledger.tax.statement_position' THEN 30
        WHEN 'ledger.tax.cost_statement' THEN 40
        WHEN 'ledger.tax.retained_earnings' THEN 50
        WHEN 'ledger.tax.comparison' THEN 60
        WHEN 'site.dashboard' THEN 10
        WHEN 'site.entry_create' THEN 20
        WHEN 'site.entry_index' THEN 30
        WHEN 'shop.dashboard' THEN 10
        WHEN 'shop.products' THEN 20
        WHEN 'shop.categories' THEN 30
        WHEN 'shop.orders' THEN 40
        WHEN 'shop.payments' THEN 50
        WHEN 'shop.settlement' THEN 60
        WHEN 'institution.dashboard' THEN 10
        WHEN 'notice.index' THEN 10
        WHEN 'settings.base_info.company' THEN 10
        WHEN 'settings.base_info.brand' THEN 20
        WHEN 'settings.base_info.cover' THEN 30
        WHEN 'settings.base_info.clients' THEN 40
        WHEN 'settings.base_info.projects' THEN 50
        WHEN 'settings.base_info.bank_accounts' THEN 60
        WHEN 'settings.base_info.cards' THEN 70
        WHEN 'settings.base_info.work_teams' THEN 80
        WHEN 'settings.organization.employees' THEN 10
        WHEN 'settings.organization.departments' THEN 20
        WHEN 'settings.organization.positions' THEN 30
        WHEN 'settings.organization.roles' THEN 40
        WHEN 'settings.organization.role_permissions' THEN 50
        WHEN 'settings.organization.permissions' THEN 60
        WHEN 'settings.organization.approval' THEN 70
        WHEN 'settings.system.site' THEN 10
        WHEN 'settings.system.session' THEN 20
        WHEN 'settings.system.security' THEN 30
        WHEN 'settings.system.codes' THEN 40
        WHEN 'settings.system.api' THEN 50
        WHEN 'settings.system.external_services' THEN 60
        WHEN 'settings.system.storage' THEN 70
        WHEN 'settings.system.database_backup' THEN 80
        WHEN 'settings.system.logs' THEN 90
        ELSE 999
    END AS `page_order`,
    CASE spr.page_key
        WHEN 'dashboard.main' THEN 'bi-speedometer2'
        WHEN 'dashboard.report' THEN 'bi-bar-chart-line'
        WHEN 'dashboard.activity' THEN 'bi-activity'
        WHEN 'dashboard.notifications' THEN 'bi-megaphone'
        WHEN 'dashboard.kpi' THEN 'bi-graph-up-arrow'
        WHEN 'dashboard.calendar' THEN 'bi-calendar3'
        WHEN 'dashboard.settings' THEN 'bi-gear'
        WHEN 'document.dashboard' THEN 'bi-folder2-open'
        WHEN 'document.file_register' THEN 'bi-file-earmark-plus'
        WHEN 'document.view' THEN 'bi-file-earmark-text'
        WHEN 'document.edit' THEN 'bi-pencil-square'
        WHEN 'document.stats' THEN 'bi-bar-chart'
        WHEN 'approval.dashboard' THEN 'bi-check2-square'
        WHEN 'ledger.dashboard' THEN 'bi-journal-text'
        WHEN 'ledger.settings.accounts' THEN 'bi-list-ul'
        WHEN 'ledger.settings.journal_rules' THEN 'bi-diagram-3'
        WHEN 'ledger.settings.opening_balances' THEN 'bi-cash-stack'
        WHEN 'ledger.data.list' THEN 'bi-clipboard-data'
        WHEN 'ledger.data.create_center' THEN 'bi-database-check'
        WHEN 'ledger.transactions' THEN 'bi-pencil-square'
        WHEN 'ledger.vouchers' THEN 'bi-pencil-square'
        WHEN 'ledger.funds.bank_transactions' THEN 'bi-bank'
        WHEN 'ledger.funds.payment_info' THEN 'bi-credit-card-2-front'
        WHEN 'ledger.funds.deposit_ledger' THEN 'bi-journal-check'
        WHEN 'ledger.funds.cash_ledger' THEN 'bi-cash-stack'
        WHEN 'ledger.funds.daily_report' THEN 'bi-calendar2-check'
        WHEN 'ledger.funds.account_balances' THEN 'bi-wallet2'
        WHEN 'ledger.funds.unlinked_transactions' THEN 'bi-link-45deg'
        WHEN 'ledger.funds.payment_schedule' THEN 'bi-calendar-range'
        WHEN 'ledger.book.journal' THEN 'bi-journal'
        WHEN 'ledger.book.account' THEN 'bi-collection'
        WHEN 'ledger.book.general' THEN 'bi-bookmarks'
        WHEN 'ledger.book.partner' THEN 'bi-people'
        WHEN 'ledger.book.project' THEN 'bi-building'
        WHEN 'ledger.book.daily' THEN 'bi-calendar-week'
        WHEN 'ledger.book.purchase_sales' THEN 'bi-cash-coin'
        WHEN 'ledger.book.vehicle_log' THEN 'bi-truck'
        WHEN 'ledger.financial.trial_balance' THEN 'bi-calculator'
        WHEN 'ledger.financial.income_statement' THEN 'bi-graph-up'
        WHEN 'ledger.financial.statement_position' THEN 'bi-file-spreadsheet'
        WHEN 'ledger.financial.product_cost' THEN 'bi-box-seam'
        WHEN 'ledger.financial.construction_cost' THEN 'bi-building-gear'
        WHEN 'ledger.financial.retained_earnings' THEN 'bi-pie-chart'
        WHEN 'ledger.assets.create' THEN 'bi-plus-square'
        WHEN 'ledger.assets.index' THEN 'bi-card-list'
        WHEN 'ledger.assets.depreciation' THEN 'bi-graph-down'
        WHEN 'ledger.assets.transfer' THEN 'bi-arrow-left-right'
        WHEN 'ledger.assets.disposal' THEN 'bi-trash3'
        WHEN 'ledger.tax.trial_balance' THEN 'bi-calculator'
        WHEN 'ledger.tax.income_statement' THEN 'bi-graph-up'
        WHEN 'ledger.tax.statement_position' THEN 'bi-file-spreadsheet'
        WHEN 'ledger.tax.cost_statement' THEN 'bi-file-earmark-text'
        WHEN 'ledger.tax.retained_earnings' THEN 'bi-pie-chart'
        WHEN 'ledger.tax.comparison' THEN 'bi-arrow-left-right'
        WHEN 'institution.dashboard' THEN 'bi-building'
        WHEN 'site.dashboard' THEN 'bi-speedometer2'
        WHEN 'site.entry_create' THEN 'bi-pencil-square'
        WHEN 'shop.dashboard' THEN 'bi-bag'
        WHEN 'shop.products' THEN 'bi-box-seam'
        WHEN 'shop.categories' THEN 'bi-diagram-3'
        WHEN 'shop.orders' THEN 'bi-receipt'
        WHEN 'notice.index' THEN 'bi-megaphone'
        ELSE NULL
    END AS `menu_icon`,
    COALESCE(
        spr.default_route_url,
        CASE spr.page_key
            WHEN 'ledger.settings.opening_balances' THEN '/ledger/settings/opening-balances'
            WHEN 'ledger.settings.sub_accounts' THEN '/ledger/sub_accounts'
            WHEN 'ledger.book.journal' THEN '/ledger/book/journal'
            WHEN 'ledger.book.account' THEN '/ledger/book/account'
            WHEN 'ledger.book.general' THEN '/ledger/book/general'
            WHEN 'ledger.book.partner' THEN '/ledger/book/partner'
            WHEN 'ledger.book.project' THEN '/ledger/book/project'
            WHEN 'ledger.book.daily' THEN '/ledger/book/daily'
            WHEN 'ledger.book.purchase_sales' THEN '/ledger/book/purchase-sales'
            WHEN 'ledger.book.vehicle_log' THEN '/ledger/book/vehicle-log'
            WHEN 'ledger.financial.trial_balance' THEN '/ledger/financial/trial-balance'
            WHEN 'ledger.financial.income_statement' THEN '/ledger/financial/income-statement'
            WHEN 'ledger.financial.statement_position' THEN '/ledger/financial/statement-position'
            WHEN 'ledger.financial.product_cost' THEN '/ledger/financial/product-cost'
            WHEN 'ledger.financial.construction_cost' THEN '/ledger/financial/construction-cost'
            WHEN 'ledger.financial.retained_earnings' THEN '/ledger/financial/retained-earnings'
            WHEN 'ledger.assets.create' THEN '/ledger/assets/create'
            WHEN 'ledger.assets.index' THEN '/ledger/assets'
            WHEN 'ledger.assets.depreciation' THEN '/ledger/assets/depreciation'
            WHEN 'ledger.assets.transfer' THEN '/ledger/assets/transfer'
            WHEN 'ledger.assets.disposal' THEN '/ledger/assets/disposal'
            WHEN 'ledger.tax.trial_balance' THEN '/ledger/tax/trial-balance'
            WHEN 'ledger.tax.income_statement' THEN '/ledger/tax/income-statement'
            WHEN 'ledger.tax.statement_position' THEN '/ledger/tax/statement-position'
            WHEN 'ledger.tax.cost_statement' THEN '/ledger/tax/cost-statement'
            WHEN 'ledger.tax.retained_earnings' THEN '/ledger/tax/retained-earnings'
            WHEN 'ledger.tax.comparison' THEN '/ledger/tax/comparison'
            WHEN 'ledger.funds.deposit_ledger' THEN '/ledger/funds/deposit-ledger'
            WHEN 'ledger.funds.cash_ledger' THEN '/ledger/funds/cash-ledger'
            WHEN 'ledger.funds.daily_report' THEN '/ledger/funds/daily-report'
            WHEN 'ledger.funds.account_balances' THEN '/ledger/funds/account-balances'
            WHEN 'ledger.funds.unlinked_transactions' THEN '/ledger/funds/unlinked-transactions'
            WHEN 'ledger.funds.payment_schedule' THEN '/ledger/funds/payment-schedule'
            WHEN 'ledger.funds.reconciliation' THEN '/ledger/funds/reconciliation'
            WHEN 'ledger.vouchers.index' THEN '/ledger/vouchers/index'
            ELSE NULL
        END
    ) AS `default_entry`,
    1 AS `is_menu`,
    CASE
        WHEN spr.page_key IN (
            'dashboard.main','dashboard.report','dashboard.activity','dashboard.notifications','dashboard.kpi','dashboard.calendar','dashboard.settings',
            'document.dashboard','document.file_register','document.view','document.edit','document.stats',
            'approval.dashboard',
            'ledger.dashboard','ledger.settings.accounts','ledger.settings.journal_rules','ledger.settings.opening_balances',
            'ledger.data.list','ledger.data.create_center','ledger.transactions','ledger.vouchers',
            'ledger.funds.bank_transactions','ledger.funds.payment_info','ledger.funds.deposit_ledger','ledger.funds.cash_ledger','ledger.funds.daily_report',
            'ledger.funds.account_balances','ledger.funds.unlinked_transactions','ledger.funds.payment_schedule',
            'ledger.book.journal','ledger.book.account','ledger.book.general','ledger.book.partner','ledger.book.project','ledger.book.daily',
            'ledger.book.purchase_sales','ledger.book.vehicle_log',
            'ledger.financial.trial_balance','ledger.financial.income_statement','ledger.financial.statement_position','ledger.financial.product_cost',
            'ledger.financial.construction_cost','ledger.financial.retained_earnings',
            'ledger.assets.create','ledger.assets.index','ledger.assets.depreciation','ledger.assets.transfer','ledger.assets.disposal',
            'ledger.tax.trial_balance','ledger.tax.income_statement','ledger.tax.statement_position','ledger.tax.cost_statement','ledger.tax.retained_earnings','ledger.tax.comparison',
            'institution.dashboard','site.dashboard','site.entry_create','shop.dashboard','shop.products','shop.categories','shop.orders'
        ) THEN 1
        ELSE 0
    END AS `visible_in_sidebar`,
    CASE WHEN spr.page_key LIKE 'settings.%' THEN 1 ELSE 0 END AS `visible_in_settings`,
    1 AS `visible_in_sitemap`,
    CASE
        WHEN spr.page_key IN ('document.dashboard','approval.dashboard','ledger.dashboard','institution.dashboard','site.dashboard','shop.dashboard','notice.index') THEN 1
        ELSE 0
    END AS `visible_in_navbar`,
    1 AS `is_active`
FROM `system_page_registry` spr
WHERE spr.is_active = 1
  AND spr.page_key <> 'auth.account_lock'
  AND spr.page_key <> 'settings.organization.permissions'
ON DUPLICATE KEY UPDATE
    `page_key` = VALUES(`page_key`),
    `module_key` = VALUES(`module_key`),
    `menu_label` = VALUES(`menu_label`),
    `module_order` = VALUES(`module_order`),
    `menu_order` = VALUES(`menu_order`),
    `page_order` = VALUES(`page_order`),
    `menu_icon` = VALUES(`menu_icon`),
    `default_entry` = VALUES(`default_entry`),
    `is_menu` = VALUES(`is_menu`),
    `visible_in_sidebar` = VALUES(`visible_in_sidebar`),
    `visible_in_settings` = VALUES(`visible_in_settings`),
    `visible_in_sitemap` = VALUES(`visible_in_sitemap`),
    `visible_in_navbar` = VALUES(`visible_in_navbar`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = CURRENT_TIMESTAMP;

INSERT INTO `system_menu_registry` (
    `menu_key`,
    `page_key`,
    `module_key`,
    `menu_label`,
    `module_order`,
    `menu_order`,
    `page_order`,
    `menu_icon`,
    `default_entry`,
    `is_menu`,
    `visible_in_sidebar`,
    `visible_in_settings`,
    `visible_in_sitemap`,
    `visible_in_navbar`,
    `is_active`
) VALUES
    ('approval.write_expenditure', NULL, 'approval', '지출결의서 작성', 40, 140, 20, 'bi-receipt', '/approval/write_expenditure', 1, 1, 0, 0, 0, 1),
    ('approval.write_purchase_request', NULL, 'approval', '구매요청서', 40, 140, 30, 'bi-cart-plus', '/approval/write_purchase_request', 1, 1, 0, 0, 0, 1),
    ('approval.write_leave_request', NULL, 'approval', '휴가요청서', 40, 140, 40, 'bi-airplane', '/approval/write_leave_request', 1, 1, 0, 0, 0, 1),
    ('approval.write_trip_report', NULL, 'approval', '출장보고서', 40, 140, 50, 'bi-briefcase', '/approval/write_trip_report', 1, 1, 0, 0, 0, 1),
    ('approval.write_work_report', NULL, 'approval', '업무보고서', 40, 140, 60, 'bi-clipboard-data', '/approval/write_work_report', 1, 1, 0, 0, 0, 1),
    ('approval.status', NULL, 'approval', '결재 현황', 40, 140, 70, 'bi-hourglass-split', '/approval/status', 1, 1, 0, 0, 0, 1),
    ('ledger.vouchers.review', NULL, 'ledger', '전표검토/승인', 30, 70, 25, 'bi-check2-square', '/ledger/vouchers/review', 1, 1, 0, 0, 0, 1),
    ('institution.tax_office', NULL, 'institution', '세무서/국세청', 90, 180, 20, 'bi-receipt', '/institution/tax_office', 1, 1, 0, 0, 0, 1),
    ('institution.local_government', NULL, 'institution', '지방자치단체/지방세관', 90, 180, 30, 'bi-map', '/institution/local_government', 1, 1, 0, 0, 0, 1),
    ('institution.welfare_corp', NULL, 'institution', '근로복지공단', 90, 180, 40, 'bi-shield-check', '/institution/welfare_corp', 1, 1, 0, 0, 0, 1),
    ('institution.health_insurance', NULL, 'institution', '건강보험공단', 90, 180, 50, 'bi-heart-pulse', '/institution/health_insurance', 1, 1, 0, 0, 0, 1),
    ('institution.pension', NULL, 'institution', '국민연금공단', 90, 180, 60, 'bi-safe', '/institution/pension', 1, 1, 0, 0, 0, 1),
    ('site.estimate', NULL, 'site', '견적관리', 70, 160, 40, 'bi-file-earmark-spreadsheet', '/site/estimate', 1, 1, 0, 0, 0, 1),
    ('site.contract', NULL, 'site', '계약관리', 70, 160, 50, 'bi-file-earmark-text', '/site/contract', 1, 1, 0, 0, 0, 1),
    ('site.execution', NULL, 'site', '실행관리', 70, 160, 60, 'bi-play-circle', '/site/execution', 1, 1, 0, 0, 0, 1),
    ('site.guarantee', NULL, 'site', '보증/보험관리', 70, 160, 70, 'bi-shield-lock', '/site/guarantee', 1, 1, 0, 0, 0, 1),
    ('site.progress', NULL, 'site', '기성예정내역', 70, 160, 80, 'bi-list-task', '/site/progress', 1, 1, 0, 0, 0, 1),
    ('sitemap.index', NULL, 'sitemap', '사이트맵', 200, 200, 10, 'bi-diagram-3', '/sitemap', 1, 0, 0, 0, 1, 1)
ON DUPLICATE KEY UPDATE
    `page_key` = VALUES(`page_key`),
    `module_key` = VALUES(`module_key`),
    `menu_label` = VALUES(`menu_label`),
    `module_order` = VALUES(`module_order`),
    `menu_order` = VALUES(`menu_order`),
    `page_order` = VALUES(`page_order`),
    `menu_icon` = VALUES(`menu_icon`),
    `default_entry` = VALUES(`default_entry`),
    `is_menu` = VALUES(`is_menu`),
    `visible_in_sidebar` = VALUES(`visible_in_sidebar`),
    `visible_in_settings` = VALUES(`visible_in_settings`),
    `visible_in_sitemap` = VALUES(`visible_in_sitemap`),
    `visible_in_navbar` = VALUES(`visible_in_navbar`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = CURRENT_TIMESTAMP;
