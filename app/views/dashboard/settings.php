<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings.php'
use Core\Helpers\AssetHelper;

$cat = $cat ?? 'base-info';
$sub = $sub ?? 'company';

$fallbackLabels = [
    'base-info' => [
        'label' => '기초정보관리',
        'subs' => [
            'company' => '회사정보',
            'brand' => '브랜드',
            'cover' => '커버이미지',
            'client' => '거래처',
            'project' => '프로젝트',
            'bank-account' => '계좌',
            'card' => '카드',
            'work-team' => '팀',
        ],
    ],
    'organization' => [
        'label' => '조직관리',
        'subs' => [
            'employee' => '직원',
            'department' => '부서',
            'position' => '직책',
            'role' => '역할',
            'permission-assignment' => '권한부여',
            'approval-template' => '결재템플릿',
        ],
    ],
    'standard' => [
        'label' => '기준관리',
        'subs' => [
            'code' => '코드',
            'statutory-standards' => '법정기준',
        ],
    ],
    'system' => [
        'label' => '시스템설정',
        'subs' => [
            'site' => '사이트정보',
            'session' => '세션관리',
            'security' => '보안정책',
            'api' => '외부연동(API)',
            'external_services' => '외부서비스연동',
            'storage' => '파일저장소',
            'databasebackup' => '데이터백업',
            'logs' => '시스템로그',
        ],
    ],
];

$settingsCategoryMeta = [
    'base-info' => ['prefix' => 'settings.base_info.', 'label' => '기초정보관리'],
    'organization' => ['prefix' => 'settings.organization.', 'label' => '조직관리'],
    'standard' => ['prefix' => 'settings.standard.', 'label' => '기준관리'],
    'system' => ['prefix' => 'settings.system.', 'label' => '시스템설정'],
];

$settingsSubKeyMap = [
    'settings.base_info.company' => 'company',
    'settings.base_info.brand' => 'brand',
    'settings.base_info.cover' => 'cover',
    'settings.base_info.clients' => 'client',
    'settings.base_info.projects' => 'project',
    'settings.base_info.bank_accounts' => 'bank-account',
    'settings.base_info.cards' => 'card',
    'settings.base_info.work_teams' => 'work-team',
    'settings.organization.employees' => 'employee',
    'settings.organization.departments' => 'department',
    'settings.organization.positions' => 'position',
    'settings.organization.roles' => 'role',
    'settings.organization.role_permissions' => 'permission-assignment',
    'settings.organization.permissions' => 'permissions',
    'settings.organization.approval' => 'approval-template',
    'settings.statutory_standards.manage' => 'statutory-standards',
    'settings.system.site' => 'site',
    'settings.system.session' => 'session',
    'settings.system.security' => 'security',
    'settings.system.codes' => 'code',
    'settings.system.api' => 'api',
    'settings.system.external_services' => 'external_services',
    'settings.system.storage' => 'storage',
    'settings.system.database_backup' => 'databasebackup',
    'settings.system.logs' => 'logs',
];

$resolveSettingsCategoryKey = static function (string $pageKey) use ($settingsCategoryMeta): ?string {
    if (in_array($pageKey, ['settings.system.codes', 'settings.statutory_standards.manage'], true)) {
        return 'standard';
    }
    foreach ($settingsCategoryMeta as $categoryKey => $meta) {
        if (str_starts_with($pageKey, $meta['prefix'])) {
            return $categoryKey;
        }
    }

    return null;
};

$labels = [];
$settingsPermissionMap = [];

$settingsMenuRows = is_array($settingsMenuRows ?? null) ? $settingsMenuRows : [];
$settingsPermissionAllowed = is_array($settingsPermissionAllowed ?? null) ? $settingsPermissionAllowed : [];

foreach ($settingsMenuRows as $row) {
    $pageKey = trim((string) ($row['page_key'] ?? ''));
    if ($pageKey === '') {
        continue;
    }

    $categoryKey = $resolveSettingsCategoryKey($pageKey);
    $subKey = $settingsSubKeyMap[$pageKey] ?? null;
    if ($categoryKey === null || $subKey === null) {
        continue;
    }

    if (!isset($labels[$categoryKey])) {
        $labels[$categoryKey] = [
            'label' => $settingsCategoryMeta[$categoryKey]['label'],
            'subs' => [],
        ];
    }

    $specialLabels = [
        'settings.base_info.work_teams' => '팀',
        'settings.system.codes' => '코드',
        'settings.statutory_standards.manage' => '법정기준',
    ];
    $labels[$categoryKey]['subs'][$subKey] = $specialLabels[$pageKey]
        ?? (string) ($row['page_label'] ?? $row['menu_label'] ?? $subKey);
    $specialPermissionKeys = [
        'settings.system.codes' => 'code.view',
        'settings.statutory_standards.manage' => 'web.settings.statutory_standards.manage',
    ];
    $settingsPermissionMap[$categoryKey][$subKey] = $specialPermissionKeys[$pageKey]
        ?? trim((string) ($row['default_route_key'] ?? ''));
}

if (empty($labels)) {
    $labels = $fallbackLabels;
    $settingsPermissionMap = [
        'base-info' => [
            'work-team' => 'work_team.view',
        ],
        'organization' => [],
        'standard' => [
            'code' => 'code.view',
            'statutory-standards' => 'web.settings.statutory_standards.manage',
        ],
    ];
}

if (isset($labels['standard']['subs'])) {
    $standardSubOrder = ['code', 'statutory-standards'];
    $labels['standard']['subs'] = array_replace(
        array_fill_keys($standardSubOrder, null),
        $labels['standard']['subs']
    );
    $labels['standard']['subs'] = array_filter(
        $labels['standard']['subs'],
        static fn($label): bool => $label !== null
    );
}

// 메뉴 레지스트리 조회 순서와 무관하게 설정 카테고리의 표시 순서를 고정한다.
$orderedLabels = [];
foreach (array_keys($settingsCategoryMeta) as $categoryKey) {
    if (isset($labels[$categoryKey])) {
        $orderedLabels[$categoryKey] = $labels[$categoryKey];
    }
}
$labels = $orderedLabels;

foreach ($labels as $categoryKey => &$categoryInfo) {
    foreach ($categoryInfo['subs'] as $subKey => $label) {
        $permissionKey = trim((string) ($settingsPermissionMap[$categoryKey][$subKey] ?? ''));
        if ($permissionKey !== '' && !($settingsPermissionAllowed[$permissionKey] ?? false)) {
            unset($categoryInfo['subs'][$subKey]);
        }
    }

    if (empty($categoryInfo['subs'])) {
        unset($labels[$categoryKey]);
    }
}
unset($categoryInfo);

if (!array_key_exists($cat, $labels)) {
    $cat = array_key_first($labels) ?: 'base-info';
}

if (!isset($labels[$cat]['subs']) || !array_key_exists($sub, $labels[$cat]['subs'])) {
    $sub = array_key_first($labels[$cat]['subs']);
}

$viewFileMap = [
    'standard/code' => __DIR__ . '/settings/system/codes.php',
    'standard/statutory-standards' => __DIR__ . '/settings/statutory-standards/standards.php',
];
$viewFile = $viewFileMap[$cat . '/' . $sub] ?? (__DIR__ . "/settings/{$cat}/{$sub}.php");
if (!file_exists($viewFile)) {
    $viewFile = __DIR__ . '/settings/base-info/company.php';
}

$pageStyles = $pageStyles ?? '';
$pageScripts = $pageScripts ?? '';
$pageAssetProfile = in_array($cat . '/' . $sub, ['base-info/company', 'base-info/brand'], true)
    ? 'form-detail-light'
    : (in_array($cat . '/' . $sub, ['organization/employee', 'organization/department', 'organization/position', 'organization/role', 'organization/permission-assignment'], true)
        ? 'data-list-light'
        : ($pageAssetProfile ?? 'default'));

$pageStyles .= AssetHelper::css('/assets/css/pages/dashboard/settings.css');

$settingsPageStyleMap = [
    'base-info/company' => '/assets/css/pages/dashboard/settings/company.css',
    'base-info/brand' => '/assets/css/pages/dashboard/settings/brand.css',
    'base-info/cover' => '/assets/css/pages/dashboard/settings/cover.css',
    'base-info/client' => '/assets/css/pages/dashboard/settings/client.css',
    'base-info/project' => '/assets/css/pages/dashboard/settings/project.css',
    'base-info/bank-account' => '/assets/css/pages/dashboard/settings/bank-account.css',
    'base-info/card' => '/assets/css/pages/dashboard/settings/card.css',
    'base-info/work-team' => '/assets/css/pages/dashboard/settings/work-team.css',
    'organization/employee' => '/assets/css/pages/dashboard/settings/employee.css',
    'organization/department' => '/assets/css/pages/dashboard/settings/department.css',
    'organization/position' => '/assets/css/pages/dashboard/settings/position.css',
    'organization/role' => '/assets/css/pages/dashboard/settings/role.css',
    'organization/permission-assignment' => '/assets/css/pages/dashboard/settings/permission-assignment.css',
    'organization/approval-template' => '/assets/css/pages/dashboard/settings/approval-template.css',
    'standard/code' => '/assets/css/pages/dashboard/settings/system/code.css',
    'system/databasebackup' => '/assets/css/pages/dashboard/settings/databasebackup.css',
];

$activeSettingsStyle = $settingsPageStyleMap[$cat . '/' . $sub] ?? null;
if ($activeSettingsStyle !== null) {
    $pageStyles .= AssetHelper::css($activeSettingsStyle);
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if ($cat === 'base-info' && $sub === 'company') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/company.js');
}

if ($cat === 'base-info' && $sub === 'brand') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/base/brand.js');
}

if ($cat === 'base-info' && $sub === 'cover') {
    $coverJsVersion = file_exists(PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/cover.js')
        ? filemtime(PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/cover.js')
        : time();

    $pageScripts .= '<script type="module" src="/public/assets/js/pages/dashboard/settings/base/cover.js?v=' . $coverJsVersion . '&ym=1"></script>';
}

if ($cat === 'base-info' && $sub === 'client') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/client.js');
}

if ($cat === 'base-info' && $sub === 'project') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/project.js');
}

if ($cat === 'base-info' && $sub === 'bank-account') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/bank-account.js');
}

if ($cat === 'base-info' && $sub === 'card') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/card.js');
}

if ($cat === 'base-info' && $sub === 'work-team') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/work-team.js');
}

if ($cat === 'organization' && $sub === 'employee') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/employee.js');
}

if ($cat === 'organization' && $sub === 'department') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/department.js');
}

if ($cat === 'organization' && $sub === 'position') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/position.js');
}

if ($cat === 'organization' && $sub === 'role') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/role.js');
}

if ($cat === 'organization' && $sub === 'permission-assignment') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/permission-assignment/index.js');
}

if ($cat === 'organization' && $sub === 'approval-template') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/approval-template.js');
}

if ($cat === 'standard' && $sub === 'statutory-standards') {
    $pageStyles .= AssetHelper::css('/assets/css/components/html-grid.css');
    $pageStyles .= AssetHelper::css('/assets/css/components/structured-field-editor.css');
    $pageStyles .= AssetHelper::css('/assets/css/pages/dashboard/settings/statutory-standards.css');
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/statutory-standards/index.js');
}

if ($cat === 'system' && $sub === 'site') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/site.js');
}

if ($cat === 'system' && $sub === 'session') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/session.js');
}

if ($cat === 'system' && $sub === 'security') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/security.js');
}

if ($cat === 'standard' && $sub === 'code') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/system/code.js');
}

if ($cat === 'system' && $sub === 'api') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/api.js');
}

if ($cat === 'system' && $sub === 'external_services') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/external_services.js');
}

if ($cat === 'system' && $sub === 'storage') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/storage.js');
}

if ($cat === 'system' && $sub === 'databasebackup') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/databasebackup.js');
}

if ($cat === 'system' && $sub === 'logs') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/logs.js');
}
?>
<main class="settings-main container-fluid">
    <div class="settings-page-header">
        <h5 class="settings-title">설정</h5>
    </div>

    <div class="settings-cat-tabs-wrap">
        <ul class="nav nav-pills settings-cat-tabs">
            <?php foreach ($labels as $catKey => $catInfo): ?>
                <?php $firstSub = array_key_first($catInfo['subs']); ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($cat === $catKey) ? 'active' : '' ?>"
                       href="/dashboard/settings/<?= htmlspecialchars($catKey, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($firstSub, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($catInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="settings-sub-tabs-wrap">
            <ul class="nav nav-tabs settings-sub-tabs">
                <?php foreach ($labels[$cat]['subs'] as $key => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($sub === $key) ? 'active' : '' ?>"
                           href="/dashboard/settings/<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
    </div>

    <div class="settings-content-card">
        <?php include $viewFile; ?>
    </div>
</main>
