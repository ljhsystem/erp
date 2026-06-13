<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings.php'
use App\Models\System\MenuRegistryModel;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use Core\Database;
use Core\Helpers\AssetHelper;

$hasSettingsPermission = static function (string $permissionKey): bool {
    try {
        $user = (new AuthSessionService())->getCurrentUser();
        if (!$user || empty($user['id'])) {
            return false;
        }

        return (new PermissionService(Database::getInstance()->getConnection()))
            ->hasPermission((string) $user['id'], $permissionKey);
    } catch (\Throwable $e) {
        return false;
    }
};

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
    'system' => [
        'label' => '시스템설정',
        'subs' => [
            'site' => '사이트정보',
            'session' => '세션관리',
            'security' => '보안정책',
            'codes' => '기준정보',
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
    'settings.system.site' => 'site',
    'settings.system.session' => 'session',
    'settings.system.security' => 'security',
    'settings.system.codes' => 'codes',
    'settings.system.api' => 'api',
    'settings.system.external_services' => 'external_services',
    'settings.system.storage' => 'storage',
    'settings.system.database_backup' => 'databasebackup',
    'settings.system.logs' => 'logs',
];

$resolveSettingsCategoryKey = static function (string $pageKey) use ($settingsCategoryMeta): ?string {
    foreach ($settingsCategoryMeta as $categoryKey => $meta) {
        if (str_starts_with($pageKey, $meta['prefix'])) {
            return $categoryKey;
        }
    }

    return null;
};

$labels = [];
$settingsPermissionMap = [];

try {
    $settingsMenuRows = (new MenuRegistryModel())->getSettingsMenus();
} catch (\Throwable $e) {
    $settingsMenuRows = [];
}

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

    $labels[$categoryKey]['subs'][$subKey] = (string) ($row['page_label'] ?? $row['menu_label'] ?? $subKey);
    $settingsPermissionMap[$categoryKey][$subKey] = trim((string) ($row['default_route_key'] ?? ''));
}

if (empty($labels)) {
    $labels = $fallbackLabels;
    $settingsPermissionMap = [
        'base-info' => [
            'work-team' => 'work_team.view',
        ],
        'organization' => [],
        'system' => [
            'codes' => 'code.view',
        ],
    ];
}

foreach ($labels as $categoryKey => &$categoryInfo) {
    foreach ($categoryInfo['subs'] as $subKey => $label) {
        $permissionKey = trim((string) ($settingsPermissionMap[$categoryKey][$subKey] ?? ''));
        if ($permissionKey !== '' && !$hasSettingsPermission($permissionKey)) {
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

$viewFile = __DIR__ . "/settings/{$cat}/{$sub}.php";
if (!file_exists($viewFile)) {
    $viewFile = __DIR__ . '/settings/base-info/company.php';
}

$pageStyles = $pageStyles ?? '';
$pageScripts = $pageScripts ?? '';

$pageStyles .=
    AssetHelper::css('/assets/css/pages/dashboard/settings.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/company.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/brand.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/cover.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/system/code.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/client.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/project.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/bank-account.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/card.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/work-team.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/databasebackup.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/employee.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/department.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/position.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/role.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/permission-assignment.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/approval-template.css');

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
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/permission-assignment.js');
}

if ($cat === 'organization' && $sub === 'approval-template') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/approval-template.js');
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

if ($cat === 'system' && $sub === 'codes') {
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
