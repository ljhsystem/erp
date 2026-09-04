<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

const MIGRATION_ID = '20260828_07_add_income_insurance_applicability_policy';
const SOURCE_CODE_ID = '20260828-0701-4000-8000-000000000001';

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = static fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$scalar = static fn(string $sql): int|float|string => $pdo->query($sql)->fetchColumn();
$mode = $argv[1] ?? 'preflight';
if (!in_array($mode, ['preflight', 'apply', 'resume', 'repair-checks'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_income_insurance_applicability_policy.php [preflight|apply|resume|repair-checks]');
}

$snapshot = static function () use ($rows): array {
    return [
        'counts' => $rows(
            "SELECT 'groups' name,COUNT(*) row_count FROM institution_daily_employment_income_groups UNION ALL "
            . "SELECT 'items',COUNT(*) FROM institution_daily_employment_income_items UNION ALL "
            . "SELECT 'workdays',COUNT(*) FROM institution_daily_employment_income_workdays UNION ALL "
            . "SELECT 'lines',COUNT(*) FROM institution_daily_employment_income_lines"
        ),
        'line_amounts' => $rows(
            'SELECT COALESCE(SUM(final_amount),0) final_amount_sum,'
            . 'COALESCE(SUM(calculated_amount),0) calculated_amount_sum '
            . 'FROM institution_daily_employment_income_lines'
        ),
        'header_amounts' => $rows(
            'SELECT id,status_code,total_gross_amount,total_deduction_amount,total_net_payment_amount,'
            . 'total_employer_burden_amount FROM institution_daily_employment_incomes ORDER BY id'
        ),
    ];
};

$historyTableExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES "
    . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_migration_history'"
)->fetchColumn() === 1;

$report = [
    'migration_id' => MIGRATION_ID,
    'migration_history_table_exists' => $historyTableExists,
    'migration_history' => $historyTableExists
        ? $rows(
            "SELECT migration_id,plan_id,status_code,started_at,completed_at"
            . " FROM system_migration_history WHERE migration_id LIKE '20260828_07%' ORDER BY started_at"
        )
        : [],
    'contracts' => $rows(
        'SELECT id,employee_id,previous_contract_id,revision_no,contract_start_date,contract_end_date,'
        . 'contract_status,current_approval_request_id,approved_at,created_at,created_by,updated_at,updated_by '
        . 'FROM institution_employment_contracts ORDER BY id'
    ),
    'groups' => $rows(
        "SELECT g.id,g.business_unit,g.project_id,g.work_team_id,g.updated_at,g.updated_by,"
        . "h.status_code AS document_status,c.code_name,c.extra_data "
        . "FROM institution_daily_employment_income_groups g "
        . "JOIN institution_daily_employment_incomes h ON h.id=g.daily_employment_income_id "
        . "LEFT JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=g.business_unit "
        . 'ORDER BY g.id'
    ),
    'actual_source_codes' => $rows(
        "SELECT id,sort_no,code,code_name,is_active FROM system_codes "
        . "WHERE code_group='INCOME_ACTUAL_APPLICATION_SOURCE' "
        . "OR id='20260828-0701-4000-8000-000000000001' ORDER BY sort_no,id"
    ),
    'target_columns' => $rows(
        "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
        . "AND TABLE_NAME IN ('institution_employment_contracts','institution_daily_employment_income_groups') "
        . "AND COLUMN_NAME IN ('employment_insurance_application_status_code','employment_insurance_exclusion_reason',"
        . "'industrial_accident_application_status_code','industrial_accident_exclusion_reason',"
        . "'employment_insurance_decision_reason','employment_insurance_decision_source_code_id',"
        . "'industrial_accident_decision_reason','industrial_accident_decision_source_code_id') "
        . 'ORDER BY TABLE_NAME,COLUMN_NAME'
    ),
    'target_constraints' => $rows(
        "SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS "
        . "WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ("
        . "'ck_employment_contract_employment_insurance','ck_employment_contract_industrial_accident',"
        . "'ck_daily_group_employment_insurance_decision','ck_daily_group_industrial_accident_decision',"
        . "'fk_daily_group_employment_insurance_source','fk_daily_group_industrial_accident_source')"
    ),
    'check_clauses' => $rows(
        "SELECT tc.TABLE_NAME,tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc "
        . "JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA "
        . "AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() "
        . "AND tc.CONSTRAINT_NAME IN ('ck_employment_contract_employment_insurance',"
        . "'ck_employment_contract_industrial_accident','ck_daily_group_employment_insurance_decision',"
        . "'ck_daily_group_industrial_accident_decision') ORDER BY tc.TABLE_NAME,tc.CONSTRAINT_NAME"
    ),
    'foreign_key_rules' => $rows(
        "SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN "
        . "('fk_daily_group_employment_insurance_source','fk_daily_group_industrial_accident_source') "
        . 'ORDER BY CONSTRAINT_NAME'
    ),
    'target_indexes' => $rows(
        "SELECT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() "
        . "AND INDEX_NAME IN ('idx_daily_group_employment_insurance_source','idx_daily_group_industrial_accident_source')"
    ),
    'before' => $snapshot(),
];

$expectedBackfill = array_values(array_filter(
    $report['groups'],
    static function (array $group): bool {
        $policy = json_decode((string) ($group['extra_data'] ?? ''), true);
        return !empty($group['project_id']) && !empty($policy['daily_employment_income']['uses_project']);
    }
));
$report['expected_backfill_ids'] = array_column($expectedBackfill, 'id');

if ($mode === 'apply') {
    if ($report['migration_history'] !== [] || $report['target_columns'] !== []
        || $report['target_constraints'] !== [] || $report['target_indexes'] !== []) {
        throw new RuntimeException('동일 Migration 이력 또는 대상 스키마가 이미 존재합니다.');
    }
    if (count($report['contracts']) !== 2 || count($report['groups']) !== 1) {
        throw new RuntimeException('기존 근로계약 또는 Group 건수가 승인된 예상과 다릅니다.');
    }
    foreach ($report['actual_source_codes'] as $code) {
        if ($code['id'] === SOURCE_CODE_ID || $code['code'] === 'MANUAL_INTERIM_GROUP') {
            throw new RuntimeException('공용코드 ID 또는 코드가 이미 존재합니다.');
        }
    }
    $sql = (string) file_get_contents(
        PROJECT_ROOT . '/app/migrations/' . MIGRATION_ID . '.up.sql'
    );
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
        $pdo->exec($statement);
    }
    $report['after'] = $snapshot();
    if ($report['before'] !== $report['after']) {
        throw new RuntimeException('Migration 전후 기존 건수 또는 금액이 달라졌습니다.');
    }
    $report['applied_columns'] = $rows(
        "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN "
        . "('institution_employment_contracts','institution_daily_employment_income_groups') "
        . "AND (COLUMN_NAME LIKE '%insurance_application_status_code' "
        . "OR COLUMN_NAME LIKE '%insurance_%reason' OR COLUMN_NAME LIKE '%insurance_%source_code_id') "
        . 'ORDER BY TABLE_NAME,ORDINAL_POSITION'
    );
    $report['backfilled_groups'] = $rows(
        "SELECT id,employment_insurance_application_status_code,employment_insurance_decision_reason,"
        . "employment_insurance_decision_source_code_id,industrial_accident_application_status_code,"
        . "industrial_accident_decision_reason,industrial_accident_decision_source_code_id,updated_at,updated_by "
        . 'FROM institution_daily_employment_income_groups ORDER BY id'
    );
}

if ($mode === 'resume') {
    $contractColumns = array_values(array_filter(
        $report['target_columns'],
        static fn(array $row): bool => $row['TABLE_NAME'] === 'institution_employment_contracts'
    ));
    $groupColumns = array_values(array_filter(
        $report['target_columns'],
        static fn(array $row): bool => $row['TABLE_NAME'] === 'institution_daily_employment_income_groups'
    ));
    $contractChecks = array_values(array_filter(
        $report['target_constraints'],
        static fn(array $row): bool => $row['TABLE_NAME'] === 'institution_employment_contracts'
    ));
    $manualCodes = array_values(array_filter(
        $report['actual_source_codes'],
        static fn(array $row): bool => $row['id'] === SOURCE_CODE_ID && $row['code'] === 'MANUAL_INTERIM_GROUP'
    ));
    $contractValues = $rows(
        'SELECT id FROM institution_employment_contracts WHERE '
        . 'employment_insurance_application_status_code IS NOT NULL '
        . 'OR employment_insurance_exclusion_reason IS NOT NULL '
        . 'OR industrial_accident_application_status_code IS NOT NULL '
        . 'OR industrial_accident_exclusion_reason IS NOT NULL'
    );
    if (count($manualCodes) !== 1 || count($contractColumns) !== 4 || count($contractChecks) !== 2
        || $groupColumns !== [] || $report['target_indexes'] !== [] || $contractValues !== []) {
        throw new RuntimeException('운영 DB의 부분 적용 상태가 승인된 예상과 다릅니다.');
    }
    if (count($report['contracts']) !== 2 || count($report['groups']) !== 1
        || $report['expected_backfill_ids'] !== []) {
        throw new RuntimeException('기존 계약·Group 또는 Backfill Scope가 승인된 예상과 다릅니다.');
    }
    $sql = (string) file_get_contents(
        PROJECT_ROOT . '/app/migrations/' . MIGRATION_ID . '.up.sql'
    );
    $statements = array_values(array_filter(array_map(
        'trim',
        preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []
    )));
    if (count($statements) !== 4) {
        throw new RuntimeException('승인 Migration SQL 문장 수가 예상과 다릅니다.');
    }
    $pdo->exec($statements[2]);
    $pdo->exec($statements[3]);
    $report['after'] = $snapshot();
    if ($report['before'] !== $report['after']) {
        throw new RuntimeException('재개 적용 전후 기존 건수 또는 금액이 달라졌습니다.');
    }
    $report['resume_applied'] = true;
}

if ($mode === 'repair-checks') {
    if (count($report['check_clauses']) !== 4 || count($report['foreign_key_rules']) !== 2
        || count($report['target_columns']) !== 10 || count($report['contracts']) !== 2
        || count($report['groups']) !== 1) {
        throw new RuntimeException('CHECK 교체 전 스키마 또는 기존 데이터 건수가 승인된 예상과 다릅니다.');
    }
    foreach ($report['foreign_key_rules'] as $rule) {
        if ($rule['UPDATE_RULE'] !== 'RESTRICT' || $rule['DELETE_RULE'] !== 'RESTRICT') {
            throw new RuntimeException('보험 판정원천 FK 규칙이 RESTRICT가 아닙니다.');
        }
    }
    $nonNullContracts = $rows(
        'SELECT id FROM institution_employment_contracts WHERE '
        . 'employment_insurance_application_status_code IS NOT NULL OR employment_insurance_exclusion_reason IS NOT NULL '
        . 'OR industrial_accident_application_status_code IS NOT NULL OR industrial_accident_exclusion_reason IS NOT NULL'
    );
    $nonNullGroups = $rows(
        'SELECT id FROM institution_daily_employment_income_groups WHERE '
        . 'employment_insurance_application_status_code IS NOT NULL OR employment_insurance_decision_reason IS NOT NULL '
        . 'OR employment_insurance_decision_source_code_id IS NOT NULL OR industrial_accident_application_status_code IS NOT NULL '
        . 'OR industrial_accident_decision_reason IS NOT NULL OR industrial_accident_decision_source_code_id IS NOT NULL'
    );
    if ($nonNullContracts !== [] || $nonNullGroups !== []) {
        throw new RuntimeException('기존 계약 또는 본사 Group의 신규 보험 값이 NULL이 아닙니다.');
    }
    $pdo->exec('ALTER TABLE institution_employment_contracts '
        . 'DROP CONSTRAINT ck_employment_contract_employment_insurance,'
        . 'DROP CONSTRAINT ck_employment_contract_industrial_accident');
    $pdo->exec("ALTER TABLE institution_employment_contracts "
        . "ADD CONSTRAINT ck_employment_contract_employment_insurance CHECK (COALESCE("
        . "(employment_insurance_application_status_code IS NULL AND employment_insurance_exclusion_reason IS NULL) OR "
        . "(employment_insurance_application_status_code='APPLICABLE' AND employment_insurance_exclusion_reason IS NULL) OR "
        . "(employment_insurance_application_status_code='EXCLUDED' AND employment_insurance_exclusion_reason IS NOT NULL "
        . "AND CHAR_LENGTH(employment_insurance_exclusion_reason) BETWEEN 1 AND 500 "
        . "AND BINARY employment_insurance_exclusion_reason=BINARY TRIM(employment_insurance_exclusion_reason)),FALSE)),"
        . "ADD CONSTRAINT ck_employment_contract_industrial_accident CHECK (COALESCE("
        . "(industrial_accident_application_status_code IS NULL AND industrial_accident_exclusion_reason IS NULL) OR "
        . "(industrial_accident_application_status_code='APPLICABLE' AND industrial_accident_exclusion_reason IS NULL) OR "
        . "(industrial_accident_application_status_code='EXCLUDED' AND industrial_accident_exclusion_reason IS NOT NULL "
        . "AND CHAR_LENGTH(industrial_accident_exclusion_reason) BETWEEN 1 AND 500 "
        . "AND BINARY industrial_accident_exclusion_reason=BINARY TRIM(industrial_accident_exclusion_reason)),FALSE))");
    $pdo->exec('ALTER TABLE institution_daily_employment_income_groups '
        . 'DROP CONSTRAINT ck_daily_group_employment_insurance_decision,'
        . 'DROP CONSTRAINT ck_daily_group_industrial_accident_decision');
    $pdo->exec("ALTER TABLE institution_daily_employment_income_groups "
        . "ADD CONSTRAINT ck_daily_group_employment_insurance_decision CHECK (COALESCE("
        . "(employment_insurance_application_status_code IS NULL AND employment_insurance_decision_reason IS NULL "
        . "AND employment_insurance_decision_source_code_id IS NULL) OR "
        . "(employment_insurance_application_status_code='CONFIRMATION_REQUIRED' "
        . "AND employment_insurance_decision_source_code_id IS NULL AND employment_insurance_decision_reason IS NOT NULL "
        . "AND CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason)) OR "
        . "(employment_insurance_application_status_code='APPLICABLE' "
        . "AND employment_insurance_decision_source_code_id IS NOT NULL "
        . "AND (employment_insurance_decision_reason IS NULL OR (CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason)))) OR "
        . "(employment_insurance_application_status_code IN ('EXCLUDED','CONFIRMATION_REQUIRED') "
        . "AND employment_insurance_decision_source_code_id IS NOT NULL AND employment_insurance_decision_reason IS NOT NULL "
        . "AND CHAR_LENGTH(employment_insurance_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY employment_insurance_decision_reason=BINARY TRIM(employment_insurance_decision_reason)),FALSE)),"
        . "ADD CONSTRAINT ck_daily_group_industrial_accident_decision CHECK (COALESCE("
        . "(industrial_accident_application_status_code IS NULL AND industrial_accident_decision_reason IS NULL "
        . "AND industrial_accident_decision_source_code_id IS NULL) OR "
        . "(industrial_accident_application_status_code='CONFIRMATION_REQUIRED' "
        . "AND industrial_accident_decision_source_code_id IS NULL AND industrial_accident_decision_reason IS NOT NULL "
        . "AND CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason)) OR "
        . "(industrial_accident_application_status_code='APPLICABLE' "
        . "AND industrial_accident_decision_source_code_id IS NOT NULL "
        . "AND (industrial_accident_decision_reason IS NULL OR (CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason)))) OR "
        . "(industrial_accident_application_status_code IN ('EXCLUDED','CONFIRMATION_REQUIRED') "
        . "AND industrial_accident_decision_source_code_id IS NOT NULL AND industrial_accident_decision_reason IS NOT NULL "
        . "AND CHAR_LENGTH(industrial_accident_decision_reason) BETWEEN 1 AND 500 "
        . "AND BINARY industrial_accident_decision_reason=BINARY TRIM(industrial_accident_decision_reason)),FALSE))");
    $report['after'] = $snapshot();
    if ($report['before'] !== $report['after']) {
        throw new RuntimeException('CHECK 교체 전후 기존 건수 또는 금액이 달라졌습니다.');
    }
    $report['checks_repaired'] = true;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
