<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$types = ['BUSINESS_INCOME_WITHHOLDING', 'LOCAL_INCOME_TAX_WITHHOLDING'];
$marks = implode(',', array_fill(0, count($types), '?'));

$query = static function (PDO $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$standards = $query($db,
    "SELECT id,sort_no,standard_type_code,policy_component_code,employment_type_code,work_scope_code,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by FROM system_statutory_standards WHERE standard_type_code IN ({$marks}) ORDER BY standard_type_code,effective_from,id",
    $types
);
$sources = $query($db,
    "SELECT source_row.* FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code IN ({$marks}) ORDER BY standard_row.standard_type_code,standard_row.effective_from,source_row.sort_no,source_row.id",
    $types
);
$codes = $query($db,
    "SELECT id,sort_no,code,code_name,note,extra_data,is_active,created_at,created_by,updated_at,updated_by FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN ({$marks}) ORDER BY sort_no,code",
    $types
);
$policyCodes = $query($db,
    "SELECT code_group,code,code_name,note,is_active FROM system_codes WHERE code_group IN ('STATUTORY_ROUNDING_METHOD','STATUTORY_CALCULATION_STAGE','STATUTORY_CALCULATION_BASE','STATUTORY_AGGREGATION_UNIT','STATUTORY_THRESHOLD_COMPARISON') ORDER BY code_group,sort_no,code"
);
$schema = $query($db,
    "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('system_statutory_standards','system_statutory_standard_sources') ORDER BY TABLE_NAME,ORDINAL_POSITION"
);
$references = $query($db, "SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='system_statutory_standards' ORDER BY TABLE_NAME,COLUMN_NAME");
$referenceCounts = [];
$standardIds = array_column($standards, 'id');
$referenceMarks = implode(',', array_fill(0, count($standardIds), '?'));
foreach ($references as $reference) {
    $table = (string) $reference['TABLE_NAME'];
    $column = (string) $reference['COLUMN_NAME'];
    $statement = $db->prepare("SELECT `{$column}` standard_id,COUNT(*) row_count FROM `{$table}` WHERE `{$column}` IN ({$referenceMarks}) GROUP BY `{$column}`");
    $statement->execute($standardIds);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $referenceCounts[] = ['table' => $table, 'column' => $column] + $row;
    }
}

echo json_encode([
    'success' => true,
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'version' => (string) $db->query('SELECT VERSION()')->fetchColumn(),
    'codes' => $codes,
    'policy_codes' => $policyCodes,
    'standards' => $standards,
    'sources' => $sources,
    'schema' => $schema,
    'reference_constraints' => $references,
    'reference_counts' => $referenceCounts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
