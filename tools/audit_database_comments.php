<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$protectedTables = ['main_calendar_events', 'main_calendar_list', 'main_calendar_tasks'];
$tables = $db->query(
    "SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION,TABLE_ROWS,CREATE_TIME,UPDATE_TIME,TABLE_COMMENT"
    . " FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_TYPE,TABLE_NAME"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$columns = $db->query(
    "SELECT c.TABLE_NAME,c.ORDINAL_POSITION,c.COLUMN_NAME,c.COLUMN_TYPE,c.DATA_TYPE,c.CHARACTER_SET_NAME,c.COLLATION_NAME,"
    . "c.IS_NULLABLE,c.COLUMN_DEFAULT,c.EXTRA,c.GENERATION_EXPRESSION,c.COLUMN_KEY,c.COLUMN_COMMENT,"
    . "k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME"
    . " FROM information_schema.COLUMNS c LEFT JOIN information_schema.KEY_COLUMN_USAGE k"
    . " ON k.CONSTRAINT_SCHEMA=c.TABLE_SCHEMA AND k.TABLE_NAME=c.TABLE_NAME AND k.COLUMN_NAME=c.COLUMN_NAME"
    . " AND k.REFERENCED_TABLE_NAME IS NOT NULL WHERE c.TABLE_SCHEMA=DATABASE()"
    . " ORDER BY c.TABLE_NAME,c.ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$brokenPattern = '/(?:\?뚯|\?뱀|\?붿|\?꾩|\?대|\?놁|硫|媛|湲|�)/u';
$koreanPattern = '/[가-힣]/u';
$classify = static function (string $comment, string $name) use ($brokenPattern, $koreanPattern): string {
    $comment = trim($comment);
    if ($comment === '') return 'MISSING';
    if (preg_match($brokenPattern, $comment)) return 'BROKEN';
    if (!preg_match($koreanPattern, $comment)) return 'NON_KOREAN';
    if (mb_strtolower(preg_replace('/\s+/u', '', $comment)) === mb_strtolower(str_replace('_', '', $name))) return 'NAME_REPEAT';
    return 'NORMAL';
};
$tableIssues = [];
$protectedTableRows = [];
$baseTableCount = 0;
$viewCount = 0;
foreach ($tables as $table) {
    if ($table['TABLE_TYPE'] === 'BASE TABLE') $baseTableCount++; else $viewCount++;
    $status = $classify((string)$table['TABLE_COMMENT'], (string)$table['TABLE_NAME']);
    if (in_array($table['TABLE_NAME'], $protectedTables, true)) {
        $protectedTableRows[] = $table + ['STATUS'=>'PROTECTED_EXTERNAL_INTEGRATION'];
    } elseif ($table['TABLE_TYPE'] === 'BASE TABLE' && $status !== 'NORMAL') {
        $tableIssues[] = $table + ['STATUS'=>$status];
    }
}
$baseNames = array_flip(array_column(array_filter($tables, static fn(array $row): bool => $row['TABLE_TYPE'] === 'BASE TABLE'), 'TABLE_NAME'));
$columnIssues = [];
$counts = ['NORMAL'=>0,'MISSING'=>0,'NON_KOREAN'=>0,'BROKEN'=>0,'NAME_REPEAT'=>0];
$protectedCounts = ['column_count'=>0,'MISSING'=>0,'NON_KOREAN'=>0,'BROKEN'=>0,'NAME_REPEAT'=>0,'NORMAL'=>0];
foreach ($columns as $column) {
    if (!isset($baseNames[$column['TABLE_NAME']])) continue;
    $status = $classify((string)$column['COLUMN_COMMENT'], (string)$column['COLUMN_NAME']);
    if (in_array($column['TABLE_NAME'], $protectedTables, true)) {
        $protectedCounts['column_count']++;
        $protectedCounts[$status]++;
        continue;
    }
    $counts[$status]++;
    if ($status !== 'NORMAL') $columnIssues[] = $column + ['STATUS'=>$status];
}
$triggers = (int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
$businessIncomeEvidenceAmounts = $db->query('SELECT COUNT(*) evidence_rows,COALESCE(SUM(total_amount),0) total_amount,COALESCE(SUM(raw_gross_payment_amount),0) raw_gross_payment_amount,COALESCE(SUM(raw_total_deduction_amount),0) raw_total_deduction_amount,COALESCE(SUM(raw_net_payment_amount),0) raw_net_payment_amount FROM ledger_evidence_business_income')->fetch(PDO::FETCH_ASSOC) ?: [];
$artifactRoles = $db->query("SELECT CASE WHEN artifact_role IS NULL THEN '<NULL>' WHEN artifact_role='' THEN '<EMPTY>' ELSE artifact_role END artifact_role,COUNT(*) row_count FROM institution_daily_employment_income_accounting_links GROUP BY artifact_role ORDER BY artifact_role")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$artifactConstraints = $db->query("SELECT tc.CONSTRAINT_NAME,tc.CONSTRAINT_TYPE,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc LEFT JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='institution_daily_employment_income_accounting_links' ORDER BY tc.CONSTRAINT_TYPE,tc.CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$issueCountsByTable = [];
foreach ($columnIssues as $row) $issueCountsByTable[$row['TABLE_NAME']] = ($issueCountsByTable[$row['TABLE_NAME']] ?? 0) + 1;
arsort($issueCountsByTable);
$rowEstimateByTable = [];
foreach ($tables as $table) $rowEstimateByTable[$table['TABLE_NAME']] = (int)($table['TABLE_ROWS'] ?? 0);
$issueTableSummary = [];
foreach ($issueCountsByTable as $tableName => $issueCount) {
    $issueTableSummary[] = ['table'=>$tableName,'issue_count'=>$issueCount,'estimated_rows'=>$rowEstimateByTable[$tableName] ?? 0];
}
$issueColumnNames = [];
foreach ($columnIssues as $row) $issueColumnNames[$row['COLUMN_NAME']] = ($issueColumnNames[$row['COLUMN_NAME']] ?? 0) + 1;
arsort($issueColumnNames);
$success = count($tableIssues) === 0 && count($columnIssues) === 0 && $triggers === 0;
echo json_encode([
    'success'=>$success,'schema'=>$schema,'base_table_count'=>$baseTableCount,'view_count'=>$viewCount,
    'physical_column_count'=>array_sum($counts)+$protectedCounts['column_count'],'column_status_counts'=>$counts,
    'table_issue_count'=>count($tableIssues),'column_issue_count'=>count($columnIssues),
    'maintenance_table_count'=>$baseTableCount-count($protectedTables),
    'maintenance_column_count'=>array_sum($counts),
    'protected'=>['status'=>'PROTECTED_EXTERNAL_INTEGRATION','reason'=>'Synology Calendar·CalDAV 외부연동 보호','tables'=>$protectedTableRows,'counts'=>$protectedCounts],
    'trigger_count'=>$triggers,'business_income_evidence_amounts'=>$businessIncomeEvidenceAmounts,
    'artifact_roles'=>$artifactRoles,'artifact_constraints'=>$artifactConstraints,
    'issue_counts_by_table'=>$issueCountsByTable,'issue_table_summary'=>$issueTableSummary,
    'issue_column_names'=>$issueColumnNames,
    'table_issues'=>$tableIssues,'column_issues'=>in_array('--summary', $argv, true) ? [] : $columnIssues,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($success ? 0 : 1);
