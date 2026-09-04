<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/institution/daily-employment-income/index.php');
$daily = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$regular = file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$common = file_get_contents($root . '/public/assets/js/common/insurance-eligibility-badge.js');
$cards = file_get_contents($root . '/public/assets/js/common/income-calculation-cards.js');
$css = file_get_contents($root . '/public/assets/css/components/income-calculation-cards.css');
$webRoutes = file_get_contents($root . '/routes/web/institution.php');

$checks = [
    'snapshot_dom_removed' => !str_contains($view, 'dailyIncomeCalculationSnapshot') && !str_contains($view, '계산 Snapshot'),
    'snapshot_renderer_removed' => !str_contains($daily, 'renderCalculationSnapshot'),
    'snapshot_data_preserved' => str_contains($daily, 'calculation_revision?.results') && str_contains($daily, 'eligibility_snapshot'),
    'shared_card_renderer' => str_contains($cards, 'insurance-eligibility-badge.js?v=') && str_contains($cards, 'bindInsuranceEligibilityBadge'),
    'actual_route_view_asset_chain' => str_contains($webRoutes, "'/institution/income-data/daily-employment'")
        && str_contains($view, "AssetHelper::module('/assets/js/pages/institution/daily-employment-income/index.js')")
        && str_contains($view, "AssetHelper::css('/assets/css/components/income-calculation-cards.css')")
        && str_contains($view, 'id="dailyIncomeModal"')
        && str_contains($view, 'id="dailyIncomeWorkerResult"'),
    'korean_statuses' => count(array_filter(['적용', '일부 적용', '적용 제외', '확인 필요', '계산 오류'], static fn(string $label): bool => str_contains($common, $label))) === 5,
    'delegated_interactions' => count(array_filter(['pointerover', 'pointerout', 'focusin', 'focusout', "'click'", "'keydown'", "'Escape'", "'Enter'"], static fn(string $token): bool => str_contains($common, $token))) === 8,
    'dynamic_badge_contract' => str_contains($common, 'initializeInsuranceEligibilityBadges(host)') && str_contains($common, "closest?.(BADGE_SELECTOR)") && !str_contains($common, "element.addEventListener"),
    'bootstrap_timing_independent' => !str_contains($common, 'window.bootstrap') && !str_contains($common, 'new window.bootstrap.Popover'),
    'modal_disposes' => str_contains($common, "'hidden.bs.modal'") && str_contains($common, 'disposeInsuranceEligibilityPopovers'),
    'safe_text_projection' => str_contains($common, 'textContent = value') && !str_contains($common, 'innerHTML'),
    'fallback_reason' => str_contains($common, '판정 사유 확인 필요') && !str_contains($common, '보험사업장 미지정'),
    'native_disclosure_fallback' => str_contains($cards, "createElement('details')")
        && str_contains($cards, "createElement('summary')")
        && str_contains($common, 'insurance-eligibility-fallback')
        && str_contains($css, '.insurance-eligibility-disclosure.is-popover-visible .insurance-eligibility-fallback')
        && str_contains($common, 'verifyPopoverVisibility')
        && str_contains($common, 'elementFromPoint'),
    'click_is_not_closed_by_document_bubble' => str_contains($common, 'event.target.closest?.(BADGE_SELECTOR) || event.target.closest?.(`.${POPOVER_CLASS}`)')
        && !str_contains($common, "event.stopPropagation();"),
    'host_initializes_after_dom_render' => strpos($cards, 'host.append(grid);') < strpos($cards, 'initializeInsuranceEligibilityBadges(host);'),
    'compact_non_button_badge' => str_contains($cards, "createElement('summary')") && str_contains($css, 'padding: 2px 7px') && str_contains($css, 'flex: 0 0 auto'),
    'asset_cache_versioned' => str_contains($daily, 'income-calculation-cards.js?v=20260831-income-cards-14') && str_contains($regular, 'income-calculation-cards.js?v=20260831-income-cards-14')
        && str_contains($cards, 'insurance-eligibility-badge.js?v=20260831-income-status-13'),
    'manual_group_setting_projection' => str_contains($common, "decisionSourceCode === 'GROUP_MANUAL_SETTING'")
        && str_contains($common, '설정방식') && str_contains($common, '설정사유'),
    'company_burden_projection' => str_contains($common, "'DAILY_GROUP_MANUAL_SETTING', 'BUSINESS_DIVISION_POLICY'")
        && str_contains($common, '회사부담') && str_contains($common, '부담설정 출처')
        && !str_contains($common, "appendRow(list, '보험사업장'"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) throw new RuntimeException('보험 가입자격 Badge 계약 실패: ' . $name);
}

echo json_encode(['success' => true, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
