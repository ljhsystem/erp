<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$resolver = (string) file_get_contents($root . '/core/PageKeyResolver.php');
$institutionRoutes = (string) file_get_contents($root . '/routes/web/institution.php');
$approvalRoutes = (string) file_get_contents($root . '/routes/web/approval.php');
$ledgerRoutes = (string) file_get_contents($root . '/routes/web/ledger.php');
$migration = (string) file_get_contents($root . '/app/migrations/20260904_03_normalize_permission_page_registry.up.sql');

$failures = [];
foreach ([
    "'api.approval.leave-request.' => 'approval.leave_request'",
    "'api.institution.human_resources.attendance.' => 'web.institution.human_resources.attendance'",
    "'api.institution.human_resources.employment_contract.' => 'web.institution.human_resources.employment_contracts'",
    "'api.institution.income_data.regular_employment.' => 'web.institution.income_data.regular_employment'",
    "'api.ledger.evidence_metadata.' => 'ledger.evidence_metadata'",
    "'api.user.profile.detail' => 'profile.view'",
] as $mapping) {
    if (!str_contains($resolver, $mapping)) $failures[] = 'PageKeyResolver 소유 화면 매핑 누락: ' . $mapping;
}
if (!str_contains($institutionRoutes, "'page_key' => \$key === 'web.institution.dashboard' ? 'institution.dashboard' : \$key")) {
    $failures[] = '대외기관 WEB Route의 명시적 Page Key 계약이 누락됐습니다.';
}
foreach (['approval.inbox', 'approval.personal_expense', 'approval.leave_request'] as $pageKey) {
    if (!str_contains($approvalRoutes, "'page_key' => '{$pageKey}'")) $failures[] = '결재 WEB Route Page Key 누락: ' . $pageKey;
}
if (!str_contains($ledgerRoutes, "'page_key' => 'ledger.evidence_metadata'")) $failures[] = '증빙정책 WEB Route Page Key가 누락됐습니다.';
foreach (['approval.inbox', 'approval.personal_expense', 'web.institution.income_data.daily_employment', 'ledger.evidence_metadata'] as $pageKey) {
    if (!str_contains($migration, "'{$pageKey}'")) $failures[] = 'PageRegistry Migration 누락: ' . $pageKey;
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Permission PageRegistry normalization contract PASS\n";
