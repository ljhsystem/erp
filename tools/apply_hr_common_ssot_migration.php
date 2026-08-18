<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$mode = $argv[1] ?? 'verify';
if (!in_array($mode, ['preflight', 'test', 'up', 'followup-test', 'followup-up', 'baseline-test', 'baseline-up', 'verify', 'down', 'parse'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_hr_common_ssot_migration.php [preflight|test|up|followup-test|followup-up|baseline-test|baseline-up|verify|down|parse]\n");
    exit(1);
}

$migrationBase = PROJECT_ROOT . '/app/migrations/20260804_01_create_hr_common_ssot';
$followupBase = PROJECT_ROOT . '/app/migrations/20260804_02_allow_scheduled_hr_assignment_end_dates';
$baselineBase = PROJECT_ROOT . '/app/migrations/20260804_03_finalize_hr_common_ssot_baseline';

/** @return list<string> */
function migrationStatements(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Migration SQL을 읽을 수 없습니다: ' . $path);
    }

    $delimiter = ';';
    $buffer = '';
    $statements = [];
    foreach ($lines as $line) {
        if (trim($buffer) === '' && (trim($line) === '' || str_starts_with(ltrim($line), '--'))) {
            continue;
        }
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches) === 1) {
            if (trim($buffer) !== '') {
                throw new RuntimeException('DELIMITER 변경 전에 종료되지 않은 SQL이 있습니다.');
            }
            $delimiter = $matches[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $statements[] = $statement;
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('파일 끝에 종료되지 않은 SQL이 있습니다.');
    }
    return $statements;
}

$upStatements = migrationStatements($migrationBase . '.up.sql');
$downStatements = migrationStatements($migrationBase . '.down.sql');
$followupUpStatements = migrationStatements($followupBase . '.up.sql');
$followupDownStatements = migrationStatements($followupBase . '.down.sql');
$baselineUpStatements = migrationStatements($baselineBase . '.up.sql');
$baselineDownStatements = migrationStatements($baselineBase . '.down.sql');
if ($mode === 'parse') {
    echo json_encode(
        [
            'up_statements' => count($upStatements),
            'down_statements' => count($downStatements),
            'followup_up_statements' => count($followupUpStatements),
            'followup_down_statements' => count($followupDownStatements),
            'baseline_up_statements' => count($baselineUpStatements),
            'baseline_down_statements' => count($baselineDownStatements),
        ],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    ), PHP_EOL;
    exit;
}

$pdo = DbPdo::conn();

if ($mode === 'baseline-test') {
    $sourceSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $testSchema = 'codex_hr_baseline_test_' . date('YmdHis');
    if (preg_match('/^codex_hr_baseline_test_[0-9]{14}$/', $testSchema) !== 1) {
        throw new RuntimeException('Baseline 테스트 DB 이름 검증에 실패했습니다.');
    }
    $pdo->exec("CREATE DATABASE `{$testSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    try {
        foreach (['system_codes', 'institution_job_assignments_workplace_histories'] as $table) {
            $pdo->exec("CREATE TABLE `{$testSchema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        }
        $pdo->exec("INSERT INTO `{$testSchema}`.`system_codes` SELECT * FROM `{$sourceSchema}`.`system_codes`");
        $pdo->exec("USE `{$testSchema}`");
        foreach ($baselineUpStatements as $statement) {
            $pdo->exec($statement);
        }
        foreach ($baselineDownStatements as $statement) {
            $pdo->exec($statement);
        }
        echo "HR common SSOT Baseline up/down test: OK\n";
    } finally {
        $pdo->exec("USE `{$sourceSchema}`");
        $pdo->exec("DROP DATABASE `{$testSchema}`");
    }
    exit;
}

if ($mode === 'baseline-up') {
    foreach ($baselineUpStatements as $statement) {
        $pdo->exec($statement);
    }
    echo "HR common SSOT Baseline migration: OK\n";
    exit;
}

if ($mode === 'followup-test') {
    $sourceSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $testSchema = 'codex_hr_followup_test_' . date('YmdHis');
    if (preg_match('/^codex_hr_followup_test_[0-9]{14}$/', $testSchema) !== 1) {
        throw new RuntimeException('후속 테스트 DB 이름 검증에 실패했습니다.');
    }
    $tables = [
        'institution_job_assignments_job_histories',
        'institution_job_assignments_project_histories',
        'institution_job_assignments_workplace_histories',
    ];
    $pdo->exec("CREATE DATABASE `{$testSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    try {
        foreach ($tables as $table) {
            $pdo->exec("CREATE TABLE `{$testSchema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        }
        $pdo->exec("USE `{$testSchema}`");
        foreach ($followupUpStatements as $statement) {
            $pdo->exec($statement);
        }
        foreach ($followupDownStatements as $statement) {
            $pdo->exec($statement);
        }
        echo "HR scheduled assignment follow-up up/down test: OK\n";
    } finally {
        $pdo->exec("USE `{$sourceSchema}`");
        $pdo->exec("DROP DATABASE `{$testSchema}`");
    }
    exit;
}

if ($mode === 'followup-up') {
    foreach ($followupUpStatements as $statement) {
        $pdo->exec($statement);
    }
    echo "HR scheduled assignment follow-up migration: OK\n";
    exit;
}

if ($mode === 'test') {
    $sourceSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $testSchema = 'codex_hr_ssot_test_' . date('YmdHis');
    if (preg_match('/^codex_hr_ssot_test_[0-9]{14}$/', $testSchema) !== 1) {
        throw new RuntimeException('테스트 DB 이름 검증에 실패했습니다.');
    }
    $dependencies = [
        'auth_users', 'user_employees', 'user_departments', 'user_positions',
        'system_projects', 'system_codes', 'user_approval_requests',
    ];
    $pdo->exec("CREATE DATABASE `{$testSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    try {
        foreach ($dependencies as $table) {
            $pdo->exec("CREATE TABLE `{$testSchema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        }
        $pdo->exec("INSERT INTO `{$testSchema}`.`auth_users` SELECT * FROM `{$sourceSchema}`.`auth_users`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`user_employees` SELECT * FROM `{$sourceSchema}`.`user_employees`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`user_departments` SELECT * FROM `{$sourceSchema}`.`user_departments`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`user_positions` SELECT * FROM `{$sourceSchema}`.`user_positions`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`system_projects` SELECT * FROM `{$sourceSchema}`.`system_projects`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`system_codes` SELECT * FROM `{$sourceSchema}`.`system_codes`");
        $pdo->exec("INSERT INTO `{$testSchema}`.`user_approval_requests` SELECT * FROM `{$sourceSchema}`.`user_approval_requests`");
        $pdo->exec("USE `{$testSchema}`");
        foreach ($upStatements as $statement) {
            $pdo->exec($statement);
        }
        echo json_encode(verification($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
        foreach ($downStatements as $statement) {
            $pdo->exec($statement);
        }
        $rollbackObjects = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME IN (
                   'institution_personnel_actions','institution_personnel_actions_targets',
                   'institution_personnel_actions_changes','institution_job_assignments_jobs',
                   'institution_job_assignments_employment_status_histories','institution_job_assignments_leave_periods',
                   'institution_job_assignments_job_histories','institution_job_assignments_project_histories',
                   'institution_job_assignments_workplace_histories'
               )"
        )->fetchColumn();
        $rollbackColumns = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_employees'
               AND COLUMN_NAME IN ('employment_status','job_id')"
        )->fetchColumn();
        echo json_encode(
            ['rollback_objects' => $rollbackObjects, 'rollback_columns' => $rollbackColumns],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ), PHP_EOL;
    } finally {
        $pdo->exec("USE `{$sourceSchema}`");
        $pdo->exec("DROP DATABASE `{$testSchema}`");
    }
    exit;
}

function preflight(PDO $pdo): array
{
    $ambiguous = $pdo->query(
        "SELECT e.id, e.employee_name, e.doc_hire_date, e.real_hire_date,
                e.doc_retire_date, e.real_retire_date,
                u.approved, u.is_active
         FROM user_employees e
         LEFT JOIN auth_users u ON u.id = e.user_id
         WHERE u.id IS NULL
            OR (e.real_hire_date IS NULL AND e.doc_hire_date IS NULL)
            OR (e.real_retire_date IS NOT NULL
                AND e.real_retire_date < COALESCE(e.real_hire_date, e.doc_hire_date))
            OR (e.doc_retire_date IS NOT NULL
                AND e.doc_retire_date < COALESCE(e.doc_hire_date, e.real_hire_date))
         ORDER BY e.sort_no, e.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $existing = $pdo->query(
        "SELECT TABLE_NAME object_name, 'TABLE' object_type
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN (
               'institution_personnel_actions', 'institution_personnel_actions_targets',
               'institution_personnel_actions_changes', 'institution_job_assignments_jobs',
               'institution_job_assignments_employment_status_histories', 'institution_job_assignments_leave_periods',
               'institution_job_assignments_job_histories', 'institution_job_assignments_project_histories',
               'institution_job_assignments_workplace_histories'
           )
         UNION ALL
         SELECT COLUMN_NAME, 'COLUMN'
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_employees'
           AND COLUMN_NAME IN ('employment_status', 'job_id')"
    )->fetchAll(PDO::FETCH_ASSOC);
    return ['ambiguous_employees' => $ambiguous, 'existing_objects' => $existing];
}

function verification(PDO $pdo): array
{
    $tables = [
        'institution_personnel_actions', 'institution_personnel_actions_targets',
        'institution_personnel_actions_changes', 'institution_job_assignments_jobs',
        'institution_job_assignments_employment_status_histories', 'institution_job_assignments_leave_periods',
        'institution_job_assignments_job_histories', 'institution_job_assignments_project_histories',
        'institution_job_assignments_workplace_histories',
    ];
    $quoted = implode(',', array_map(static fn(string $table): string => $pdo->quote($table), $tables));
    $schema = $pdo->query(
        "SELECT t.TABLE_NAME, t.TABLE_COMMENT,
                COUNT(DISTINCT c.COLUMN_NAME) column_count,
                COUNT(DISTINCT CASE WHEN tc.CONSTRAINT_TYPE = 'FOREIGN KEY' THEN tc.CONSTRAINT_NAME END) fk_count,
                COUNT(DISTINCT CASE WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN tc.CONSTRAINT_NAME END) uk_count,
                COUNT(DISTINCT CASE WHEN tc.CONSTRAINT_TYPE = 'CHECK' THEN tc.CONSTRAINT_NAME END) check_count,
                COUNT(DISTINCT s.INDEX_NAME) index_count
         FROM information_schema.TABLES t
         LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME
         LEFT JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.TABLE_SCHEMA=t.TABLE_SCHEMA AND tc.TABLE_NAME=t.TABLE_NAME
         LEFT JOIN information_schema.STATISTICS s ON s.TABLE_SCHEMA=t.TABLE_SCHEMA AND s.TABLE_NAME=t.TABLE_NAME
         WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME IN ({$quoted})
         GROUP BY t.TABLE_NAME, t.TABLE_COMMENT ORDER BY t.TABLE_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);
    $statusCounts = $pdo->query(
        'SELECT employment_status, COUNT(*) employee_count FROM user_employees GROUP BY employment_status ORDER BY employment_status'
    )->fetchAll(PDO::FETCH_ASSOC);
    $integrity = [
        'employees' => (int) $pdo->query('SELECT COUNT(*) FROM user_employees')->fetchColumn(),
        'status_histories' => (int) $pdo->query('SELECT COUNT(*) FROM institution_job_assignments_employment_status_histories')->fetchColumn(),
        'status_mismatch' => (int) $pdo->query(
            "SELECT COUNT(*) FROM user_employees e
             WHERE e.employment_status <> CASE
                 WHEN COALESCE(e.real_hire_date,e.doc_hire_date) > CURRENT_DATE THEN 'PENDING_HIRE'
                 WHEN COALESCE(e.real_retire_date,e.doc_retire_date) IS NOT NULL
                      AND COALESCE(e.real_retire_date,e.doc_retire_date) <= CURRENT_DATE THEN 'RETIRED'
                 ELSE 'ACTIVE' END"
        )->fetchColumn(),
        'status_history_orphans' => (int) $pdo->query(
            'SELECT COUNT(*) FROM institution_job_assignments_employment_status_histories h LEFT JOIN user_employees e ON e.id=h.employee_id WHERE e.id IS NULL'
        )->fetchColumn(),
        'missing_column_comments' => (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$quoted}) AND TRIM(COLUMN_COMMENT)=''"
        )->fetchColumn(),
        'code_count' => (int) $pdo->query(
            "SELECT COUNT(*) FROM system_codes WHERE code_group IN
             ('PERSONNEL_ACTION_TYPE','PERSONNEL_ACTION_STATUS','EMPLOYMENT_STATUS',
              'EMPLOYEE_LEAVE_TYPE','EMPLOYEE_WORKPLACE_TYPE','EMPLOYEE_ASSIGNMENT_STATUS')"
        )->fetchColumn(),
    ];
    $assignmentPolicy = $pdo->query(
        "SELECT cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
         FROM information_schema.CHECK_CONSTRAINTS cc
         WHERE cc.CONSTRAINT_SCHEMA=DATABASE()
           AND cc.CONSTRAINT_NAME IN (
               'chk_employee_job_assignment_result',
               'chk_employee_project_assignment_result',
               'chk_employee_workplace_result'
           )
         ORDER BY cc.CONSTRAINT_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);
    $generatedColumns = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME, GENERATION_EXPRESSION
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND (TABLE_NAME, COLUMN_NAME) IN (
               ('institution_job_assignments_project_histories','active_primary_employee_id'),
               ('institution_job_assignments_workplace_histories','active_employee_id')
           )
         ORDER BY TABLE_NAME, COLUMN_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);
    return [
        'schema' => $schema,
        'status_counts' => $statusCounts,
        'integrity' => $integrity,
        'assignment_policy' => $assignmentPolicy,
        'generated_columns' => $generatedColumns,
    ];
}

if ($mode === 'preflight') {
    echo json_encode(preflight($pdo), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if ($mode === 'up') {
    $result = preflight($pdo);
    if ($result['ambiguous_employees'] !== [] || $result['existing_objects'] !== []) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        throw new RuntimeException('Migration preflight를 통과하지 못했습니다.');
    }
    foreach ($upStatements as $statement) {
        $pdo->exec($statement);
    }
}

if ($mode === 'down') {
    foreach ($downStatements as $statement) {
        $pdo->exec($statement);
    }
    echo "HR common SSOT down migration: OK\n";
    exit;
}

echo json_encode(verification($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
