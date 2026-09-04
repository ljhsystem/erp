<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = $argv[1] ?? 'preflight';
if (!in_array($mode, ['preflight', 'test', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_permission_page_registry_normalization.php [preflight|test|up|verify]');
}

$pdo = DbPdo::conn();
$migration = PROJECT_ROOT . '/app/migrations/20260904_03_normalize_permission_page_registry.up.sql';
$targetKeys = [
    'approval.inbox','approval.personal_expense',
    'web.institution.human_resources.attendance','web.institution.human_resources.compensation_incentives',
    'web.institution.human_resources.employment_contracts','web.institution.human_resources.job_assignments',
    'web.institution.human_resources.performance_evaluations','web.institution.human_resources.personnel_actions',
    'web.institution.human_resources.qualification_education','web.institution.income_data.daily_employment',
    'web.institution.income_data.regular_employment','web.institution.national_tax','web.institution.local_tax',
    'web.institution.tax_agent','web.institution.filing_history','ledger.evidence_metadata',
];
$quotedKeys = implode(',', array_map(static fn(string $key): string => $pdo->quote($key), $targetKeys));
$execute = static function (PDO $connection, string $path): void {
    foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($path)))) as $statement) {
        $connection->exec($statement);
    }
};
$snapshot = static function () use ($pdo, $quotedKeys): array {
    return [
        'target_pages' => (int) $pdo->query("SELECT COUNT(*) FROM system_page_registry WHERE is_active=1 AND page_key IN ({$quotedKeys})")->fetchColumn(),
        'linked_target_menus' => (int) $pdo->query("SELECT COUNT(*) FROM system_menu_registry WHERE page_key IN ({$quotedKeys})")->fetchColumn(),
        'active_permissions_missing_page_key' => (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE is_active=1 AND (page_key IS NULL OR page_key='')")->fetchColumn(),
        'active_permissions_unknown_page_key' => (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions ap LEFT JOIN system_page_registry spr ON spr.page_key=ap.page_key AND spr.is_active=1 WHERE ap.is_active=1 AND ap.page_key IS NOT NULL AND ap.page_key<>'' AND spr.page_key IS NULL")->fetchColumn(),
    ];
};

if ($mode === 'preflight' || $mode === 'verify') {
    $state = $snapshot();
    $passed = $mode === 'preflight' || ($state['target_pages'] === count($targetKeys) && $state['active_permissions_missing_page_key'] === 0 && $state['active_permissions_unknown_page_key'] === 0);
    echo json_encode(['mode' => $mode, 'passed' => $passed, 'state' => $state], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($passed ? 0 : 1);
}

$pdo->beginTransaction();
try {
    $before = $snapshot();
    $execute($pdo, $migration);
    $after = $snapshot();
    if ($after['target_pages'] !== count($targetKeys)) {
        throw new RuntimeException('PageRegistry 정규화 대상이 모두 등록되지 않았습니다.');
    }
    if ($mode === 'test') {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
    echo json_encode(['mode' => $mode, 'before' => $before, 'after' => $after, 'rolled_back' => $mode === 'test'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

