<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeAccountingLinkPolicyService;
use App\Services\Institution\DailyEmploymentIncomeScopeKeyService;

$migration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260827_23_create_daily_income_mariadb_compatible_baseline.up.sql');
$manifest = json_decode((string) file_get_contents(
    PROJECT_ROOT . '/config/migration-plans/daily_income_mariadb_baseline_v2.json'
), true, 512, JSON_THROW_ON_ERROR);
$scope = new DailyEmploymentIncomeScopeKeyService();
$keys = $scope->lineKeys('item-1', 'workday-1', 'revision-1', null, null);
$periodKeys = $scope->lineKeys('item-1', null, null, '2026-08-01', '2026-08-31');
$policy = new DailyEmploymentIncomeAccountingLinkPolicyService();
$validLink = $policy->validate([
    'artifact_role' => 'WORKER_PAYMENT',
    'closure_id' => 'closure-1',
    'daily_employment_income_id' => 'document-1',
    'daily_employment_income_group_id' => 'group-1',
    'daily_employment_income_item_id' => 'item-1',
    'business_key_hash' => str_repeat('a', 64),
    'payload_hash' => str_repeat('b', 64),
    'evidence_id' => 'evidence-1',
    'worker_client_id' => 'worker-1',
    'transaction_id' => 'transaction-1',
]);

$checks = [
    '기존 _07·_19는 수정하지 않음' => !str_contains($migration, 'migrate_20260827_07') && !str_contains($migration, 'migrate_20260827_19'),
    'GENERATED Scope Key 없음' => !str_contains(strtoupper($migration), 'GENERATED ALWAYS'),
    'FK 컬럼 역할 교차 CHECK 없음' => !str_contains($migration, "generation_role='DAILY_INCOME_EVIDENCE' AND transaction_id"),
    '물리 Scope Key 3종' => str_contains($migration, 'workday_scope_key VARCHAR(36) NOT NULL')
        && str_contains($migration, 'revision_scope_key VARCHAR(36) NOT NULL')
        && str_contains($migration, 'period_scope_key VARCHAR(32) NOT NULL'),
    'Manifest supersedes 명시' => $manifest['supersedes']['20260827_23_create_daily_income_mariadb_compatible_baseline'] === [
        '20260827_07_create_daily_employment_income_closure_registry',
        '20260827_19_create_daily_income_non_taxable_revisions',
    ],
    'Workday Scope 서버 생성' => $keys['workday_scope_key'] === 'workday-1' && $keys['revision_scope_key'] === 'revision-1',
    '기간 Scope 서버 생성' => $periodKeys['workday_scope_key'] === 'ITEM'
        && $periodKeys['period_scope_key'] === '2026-08-01:2026-08-31',
    '지급 역할 FK 조합 검증' => $validLink['artifact_role'] === 'WORKER_PAYMENT',
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: MariaDB 호환 Baseline·Plan·Scope Key·역할 검증 계약\n";
