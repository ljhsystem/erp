<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings.php'
use Core\Helpers\AssetHelper;

// 1) cat/sub 처리
$cat = $cat ?? 'base-info';
$sub = $sub ?? 'company';

// 2) 라벨 매핑
$labels = [
    'base-info' => [
        'label' => '기초정보관리',
        'subs'  => [
            'company'     => '회사정보',
            'brand-logo'  => '브랜드',
            'cover'       => '커버이미지',
            'clients'     => '거래처',
            'projects'    => '프로젝트',
            'bank-accounts'    => '계좌',
            'cards'    => '카드'
        ]
    ],
    'organization' => [
        'label' => '조직관리',
        'subs'  => [
            'employees'        => '직원',
            'departments'      => '부서',
            'positions'        => '직책',
            'roles'            => '역할',
            'permissions'      => '권한',
            'role_permissions' => '권한부여',
            'approval'         => '결재템플릿'
        ]
    ],
    'system' => [
        'label' => '시스템설정',
        'subs'  => [
            'site'              => '사이트정보',
            'session'           => '세션관리',
            'security'          => '보안정책',
            'api'               => '외부연동(API)',
            'external_services' => '외부서비스연동',
            'storage'           => '파일저장소',
            'databasebackup'    => '데이터백업',
            'logs'              => '시스템로그'
        ]
    ]
];

// 3) 유효성 체크
if (!array_key_exists($cat, $labels)) {
    $cat = 'base-info';
}

if (!array_key_exists($sub, $labels[$cat]['subs'])) {
    $sub = array_key_first($labels[$cat]['subs']);
}

// 4) include 경로
$viewFile = __DIR__ . "/settings/{$cat}/{$sub}.php";
if (!file_exists($viewFile)) {
    $viewFile = __DIR__ . "/settings/base-info/company.php";
}

// 5) 공통 CSS
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';

$pageStyles .=
    AssetHelper::css('/assets/css/pages/dashboard/settings.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/company.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/brand-logo.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/cover.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/client.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/project.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/bank.account.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/card.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/databasebackup.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/employee.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/departments.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/positions.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/roles.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/permissions.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/role_permissions.css') .
    AssetHelper::css('/assets/css/pages/dashboard/settings/approval.css');

// 6) 공통 JS
$pageScripts .= '';

// 7) 페이지 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// 8) cat/sub 별 전용 스크립트 로드

// 8-1-1) 기초정보관리 - 회사정보
if ($cat === 'base-info' && $sub === 'company') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/company.js');
}

// 8-1-2) 기초정보관리 - 브랜드
if ($cat === 'base-info' && $sub === 'brand-logo') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/base/brand.js');
}

// 8-1-3) 기초정보관리 - 커버이미지
if ($cat === 'base-info' && $sub === 'cover') {
    $coverJsVersion = file_exists(PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/cover.js')
        ? filemtime(PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/cover.js')
        : time();

    $pageScripts .= '<script type="module" src="/public/assets/js/pages/dashboard/settings/base/cover.js?v=' . $coverJsVersion . '&ym=1"></script>';
}

// 8-1-4) 기초정보관리 - 거래처
if ($cat === 'base-info' && $sub === 'clients') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/client.js');
}

// 8-1-5) 기초정보관리 - 프로젝트
if ($cat === 'base-info' && $sub === 'projects') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/project.js');
}

// 8-1-6) 기초정보관리 - 계좌
if ($cat === 'base-info' && $sub === 'bank-accounts') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/bank.account.js');
}

// 8-1-7) 기초정보관리 - 카드
if ($cat === 'base-info' && $sub === 'cards') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/base/card.js');
}

// // 8-2-0) 조직관리 - 공통 
// if ($cat === 'organization') {
//     $pageScripts .= '
//         <script src="' . asset('/assets/js/pages/dashboard/settings/organization/common.utils.js') . '"></script>
//         <script src="' . asset('/assets/js/pages/dashboard/settings/organization/common.manager.select.js') . '"></script>
//         <script src="' . asset('/assets/js/pages/dashboard/settings/organization/common.main.js') . '"></script>
//     ';
// }


// 8-2-1) 조직관리 - 직원
if ($cat === 'organization' && $sub === 'employees') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/employees.js');
}

// 8-2-2) 조직관리 - 부서
if ($cat === 'organization' && $sub === 'departments') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/departments.js');
}

// 8-2-3) 조직관리 - 직책
if ($cat === 'organization' && $sub === 'positions') {
   $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/positions.js');
}

// 8-2-4) 조직관리 - 역할
if ($cat === 'organization' && $sub === 'roles') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/roles.js');
}

// 8-2-5) 조직관리 - 권한
if ($cat === 'organization' && $sub === 'permissions') {
    $pageScripts .= AssetHelper::module('/assets/js/pages/dashboard/settings/organization/permissions.js');
}

// 8-2-6) 조직관리 - 권한부여
if ($cat === 'organization' && $sub === 'role_permissions') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/organization/role_permissions.js');
}

// 8-2-7) 조직관리 - 결재
if ($cat === 'organization' && $sub === 'approval') {
    $pageScripts .=
        AssetHelper::js('https://code.jquery.com/ui/1.13.2/jquery-ui.min.js') .
        AssetHelper::js('/assets/js/pages/dashboard/settings/organization/approval.templates.js');
}

// 8-3-1) 시스템설정 - 사이트정보
if ($cat === 'system' && $sub === 'site') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/site.js');
}

// 8-3-2) 시스템설정 - 세션관리
if ($cat === 'system' && $sub === 'session') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/session.js');
}

// 8-3-3) 시스템설정 - 보안정책
if ($cat === 'system' && $sub === 'security') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/security.js');
}

// 8-3-4) 시스템설정 - 외부연동(API)
if ($cat === 'system' && $sub === 'api') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/api.js');
}

// 8-3-5) 시스템설정 - 외부서비스연동
if ($cat === 'system' && $sub === 'external_services') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/external_services.js');
}

// 8-3-6) 시스템설정 - 파일저장소
if ($cat === 'system' && $sub === 'storage') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/storage.js');
}

// 8-3-7) 시스템설정 - 데이터백업
if ($cat === 'system' && $sub === 'databasebackup') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/databasebackup.js');
}

// 8-3-8) 시스템설정 - 로그관리
if ($cat === 'system' && $sub === 'logs') {
    $pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/settings/system/logs.js');
}

// 9) Breadcrumb
?>
<main class="settings-main container-fluid">
    <div class="settings-page-header">
        <h5 class="settings-title">⚙️ 설정</h5>
    </div>

    <!-- 1차 탭: 카테고리 -->
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

    <!-- 2차 탭: 서브메뉴 -->
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

    <!-- 본문 -->
    <div class="settings-content-card">
        <?php include $viewFile; ?>
    </div>
</main>
