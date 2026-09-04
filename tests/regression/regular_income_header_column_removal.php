<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$db = DbPdo::conn();
$columns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$constraints = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$indexes = $db->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$removedColumns = ['correction_of_id', 'revision_no', 'request_key', 'snapshot_at'];
$checks = [
    'unused_columns_removed' => array_intersect($removedColumns, $columns) === [],
    'correction_constraints_removed' => !in_array('fk_regular_income_correction', $constraints, true)
        && !in_array('chk_regular_income_revision', $constraints, true),
    'unused_indexes_removed' => !in_array('idx_regular_income_correction', $indexes, true)
        && !in_array('uk_regular_income_request', $indexes, true),
    'required_runtime_columns_kept' => array_diff([
        'document_status',
        'current_approval_request_id',
        'calculation_version',
        'calculation_source_code',
        'approved_at',
    ], $columns) === [],
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($failed === [] ? 0 : 1);
