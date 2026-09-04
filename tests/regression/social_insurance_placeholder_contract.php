<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$webRoutes = file_get_contents($root . '/routes/web/institution.php');
$apiRoutes = file_get_contents($root . '/routes/api/institution.php');
$view = file_get_contents($root . '/app/views/institution/index.php');

$checks = [
    'page_route_kept' => str_contains($webRoutes, "'/institution/social-insurance'")
        && str_contains($webRoutes, "'web.institution.social_insurance'"),
    'placeholder_handler' => preg_match(
        "~'/institution/social-insurance'.*?'InstitutionController@webPlaceholder'~s",
        $webRoutes
    ) === 1,
    'official_notice' => str_contains(
        $webRoutes,
        '메뉴 구조가 연결되었습니다. 4대보험 신고·납부·정산 업무 기능은 추후 단계에서 제공됩니다.'
    ),
    'read_only_notice' => str_contains(
        $view,
        '현재 이 페이지에서는 직원 보험 적용이력이나 급여 계산자료를 등록·수정하지 않습니다.'
    ),
    'legacy_api_routes_removed' => !str_contains($apiRoutes, '/api/institution/social-insurance/'),
    'legacy_controller_removed' => !is_file($root . '/app/Controllers/Institution/SocialInsuranceController.php'),
    'legacy_view_removed' => !is_file($root . '/app/views/institution/social-insurance/index.php'),
    'legacy_script_removed' => !is_file($root . '/public/assets/js/pages/institution/social-insurance/index.js'),
    'legacy_style_removed' => !is_file($root . '/public/assets/css/pages/institution/social-insurance/index.css'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
