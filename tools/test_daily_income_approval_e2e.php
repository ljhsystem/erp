<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

const TEST_SCHEMA = 'tmp_erp_daily_evidence_p1_test';
const MANIFEST_TABLE = 'tmp_test_fixture_manifest';
const MIGRATION_SUITE = 'DAILY_EVIDENCE_MIGRATION_P1';
const E2E_SUITE = 'DAILY_APPROVAL_E2E_P1';
const TOOL_VERSION = '20260901.1';

$options = getopt('', [
    'schema:',
    'no-create-schema',
    'no-drop-schema',
    'bootstrap-fixtures',
    'reset-fixtures',
    'cleanup-fixtures',
    'cleanup-dry-run',
    'run-failure-injection',
]);

$schema = trim((string) ($options['schema'] ?? ''));
$bootstrap = array_key_exists('bootstrap-fixtures', $options);
$reset = array_key_exists('reset-fixtures', $options);
$cleanup = array_key_exists('cleanup-fixtures', $options);
$cleanupDryRun = array_key_exists('cleanup-dry-run', $options);
$runFailureInjection = array_key_exists('run-failure-injection', $options);

$fail = static function (string $errorCode, string $message, array $context = []): never {
    echo json_encode([
        'success' => false,
        'error_code' => $errorCode,
        'message' => $message,
        'context' => $context,
        'operating_ddl' => 0,
        'operating_dml' => 0,
        'operating_rows_copied' => 0,
        'schema_created' => 0,
        'schema_dropped' => 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
};

if ($schema !== TEST_SCHEMA || !str_starts_with($schema, 'tmp_')) {
    $fail('TEST_SCHEMA_NOT_ALLOWED', '승인된 고정 테스트 Schema만 사용할 수 있습니다.');
}
if (!array_key_exists('no-create-schema', $options) || !array_key_exists('no-drop-schema', $options)) {
    $fail('TEST_SCHEMA_SAFETY_OPTIONS_REQUIRED', 'Schema 생성·삭제 금지 옵션이 모두 필요합니다.');
}
if (!$cleanup && !$bootstrap) {
    $fail('BOOTSTRAP_OPTION_REQUIRED', '승인 E2E 실행에는 --bootstrap-fixtures가 필요합니다.');
}
if ($reset && !$bootstrap) {
    $fail('RESET_REQUIRES_BOOTSTRAP', '--reset-fixtures는 --bootstrap-fixtures와 함께 사용해야 합니다.');
}
if ($cleanupDryRun && !$cleanup) {
    $fail('CLEANUP_DRY_RUN_REQUIRES_CLEANUP', '--cleanup-dry-run은 --cleanup-fixtures와 함께 사용해야 합니다.');
}

try {
    $pdo = DbPdo::conn();
    $connectedSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (strcasecmp($connectedSchema, $schema) === 0) {
        $fail('OPERATING_SCHEMA_COLLISION', '현재 기본 연결 Schema와 테스트 Schema가 동일합니다.');
    }

    $schemaStatement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema');
    $schemaStatement->execute([':schema' => $schema]);
    if ((int) $schemaStatement->fetchColumn() !== 1) {
        $fail('TEST_SCHEMA_NOT_FOUND', '사용자가 준비한 테스트 Schema를 찾을 수 없습니다.');
    }

    $pdo->exec('USE `' . $schema . '`');
    $assertTarget = static function () use ($pdo, $schema, $fail): void {
        if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
            $fail('TEST_SCHEMA_SESSION_CHANGED', 'DB 세션의 현재 Schema가 변경됐습니다.');
        }
    };
    $assertTarget();

    $manifestExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table'
    );
    $manifestExists->execute([':schema' => $schema, ':table' => MANIFEST_TABLE]);
    if ((int) $manifestExists->fetchColumn() !== 1) {
        $fail('TEST_SCHEMA_STRUCTURE_MISMATCH', '공용 Fixture Manifest가 없습니다.');
    }

    $columnStatement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION'
    );
    $columnStatement->execute([':schema' => $schema, ':table' => MANIFEST_TABLE]);
    $actualManifestColumns = $columnStatement->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $requiredManifestColumns = [
        'suite_code',
        'fixture_key',
        'object_type',
        'object_name',
        'row_id',
        'created_at',
        'tool_version',
    ];
    $missingManifestColumns = array_values(array_diff($requiredManifestColumns, $actualManifestColumns));
    if ($missingManifestColumns !== []) {
        $fail(
            'TEST_SCHEMA_STRUCTURE_MISMATCH',
            '공용 Fixture Manifest가 승인된 객체별 소유권 계약과 일치하지 않습니다.',
            [
                'table' => MANIFEST_TABLE,
                'missing_columns' => $missingManifestColumns,
                'automatic_alter_performed' => false,
            ]
        );
    }

    if ($cleanup) {
        $ownedStatement = $pdo->prepare(
            'SELECT object_type,object_name,row_id,COUNT(*) AS owned_count'
            . ' FROM tmp_test_fixture_manifest WHERE suite_code=:suite_code'
            . ' GROUP BY object_type,object_name,row_id ORDER BY object_type,object_name,row_id'
        );
        $ownedStatement->execute([':suite_code' => E2E_SUITE]);
        $owned = $ownedStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$cleanupDryRun) {
            $fail('CLEANUP_REQUIRES_SEPARATE_APPROVAL', '이번 실행에서는 실제 Fixture 정리를 허용하지 않습니다.');
        }
        echo json_encode([
            'success' => true,
            'mode' => 'cleanup_dry_run',
            'schema' => $schema,
            'suite_code' => E2E_SUITE,
            'owned_objects' => $owned,
            'owned_object_count' => count($owned),
            'excluded_suites' => [MIGRATION_SUITE],
            'schema_drop_count' => 0,
            'rows_deleted' => 0,
            'tables_dropped' => 0,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $requiredE2eTables = [
        'institution_daily_employment_incomes',
        'institution_daily_employment_income_items',
        'institution_daily_employment_income_workdays',
        'institution_daily_employment_income_calculation_results',
        'user_approval_requests',
        'user_approval_request_steps',
        'user_approval_templates',
        'user_approval_template_steps',
        'auth_users',
        'user_employees',
        'system_clients',
        'system_projects',
        'system_work_teams',
        'ledger_transactions',
        'ledger_transaction_items',
        'ledger_transaction_settlements',
        'ledger_transaction_files',
        'ledger_evidence_links',
        'institution_daily_employment_income_accounting_links',
        'institution_daily_employment_income_closures',
    ];
    $tableStatement = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES'
        . ' WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND TABLE_TYPE=\'BASE TABLE\''
    );
    $missingE2eTables = [];
    foreach ($requiredE2eTables as $table) {
        $tableStatement->execute([':schema' => $schema, ':table' => $table]);
        if ($tableStatement->fetchColumn() === false) {
            $missingE2eTables[] = $table;
        }
    }
    if ($missingE2eTables !== []) {
        $fail(
            'TEST_SCHEMA_STRUCTURE_MISMATCH',
            '실제 승인 Service E2E에 필요한 기반 테이블이 없습니다.',
            [
                'suite_code' => E2E_SUITE,
                'missing_tables' => $missingE2eTables,
                'unapproved_additional_dependencies' => array_values(array_intersect(
                    $missingE2eTables,
                    ['system_projects', 'system_work_teams']
                )),
                'additional_dependency_reason' => 'DailyEmploymentIncomeModel::groups()가 두 테이블을 항상 LEFT JOIN합니다.',
                'additional_fixture_rows_required' => false,
                'automatic_ddl_performed' => false,
            ]
        );
    }

    $fail('E2E_FIXTURE_BOOTSTRAP_NOT_READY', '합성 승인 Fixture Bootstrap 구현이 준비되지 않았습니다.', [
        'suite_code' => E2E_SUITE,
        'tool_version' => TOOL_VERSION,
        'reset_requested' => $reset,
        'failure_injection_requested' => $runFailureInjection,
    ]);
} catch (Throwable $exception) {
    $fail('TEST_ENVIRONMENT_ERROR', '격리 승인 E2E 사전검증 중 오류가 발생했습니다.', [
        'exception_type' => $exception::class,
    ]);
}
