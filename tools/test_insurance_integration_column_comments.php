<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$sourceSchema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'tmp_insurance_comments_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
$tables = ['system_statutory_standards','institution_daily_employment_income_calculation_results'];
$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter=$match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed=rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement=trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer='';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$schemaFingerprint = static function (PDO $connection, string $table): string {
    $statement=$connection->prepare("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA,ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION");
    $statement->execute(['table'=>$table]);
    return hash('sha256', json_encode($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
$rowHash = static function (PDO $connection, string $table): string {
    return hash('sha256', json_encode($connection->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$created=false;
try {
    $db->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); $created=true;
    $db->exec("CREATE TABLE `{$schema}`.`_codex_execution_marker`(id TINYINT PRIMARY KEY,owner_code VARCHAR(100) NOT NULL)");
    $db->exec("INSERT INTO `{$schema}`.`_codex_execution_marker` VALUES(1,'INSURANCE_COLUMN_COMMENTS_20260831')");
    foreach ($tables as $table) {
        $db->exec("CREATE TABLE `{$schema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $db->exec("INSERT INTO `{$schema}`.`{$table}` SELECT * FROM `{$sourceSchema}`.`{$table}`");
    }
    $db->exec("USE `{$schema}`");
    $before=[];
    foreach ($tables as $table) $before[$table]=['schema'=>$schemaFingerprint($db,$table),'rows'=>$rowHash($db,$table)];
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_05_add_insurance_integration_column_comments.up.sql');
    $after=[];
    foreach ($tables as $table) $after[$table]=['schema'=>$schemaFingerprint($db,$table),'rows'=>$rowHash($db,$table)];
    if ($before !== $after) throw new RuntimeException('COMMENT 외 Schema 또는 업무행이 변경됐습니다.');
    $comments=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='system_statutory_standards' AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')) OR (TABLE_NAME='institution_daily_employment_income_calculation_results' AND COLUMN_NAME IN('daily_employment_income_item_id','eligibility_status_code','eligibility_reason_code','missing_inputs','snapshot_schema_version'))) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($comments)!==10 || count(array_filter($comments, static fn(array $row): bool => trim((string)$row['COLUMN_COMMENT'])===''))>0) throw new RuntimeException('COMMENT 적용 결과가 다릅니다.');
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_05_add_insurance_integration_column_comments.down.sql');
    $down=[];
    foreach ($tables as $table) $down[$table]=['schema'=>$schemaFingerprint($db,$table),'rows'=>$rowHash($db,$table)];
    if ($before !== $down) throw new RuntimeException('Down 후 COMMENT 외 기준선이 복원되지 않았습니다.');
    echo json_encode(['success'=>true,'schema'=>$schema,'before'=>$before,'after_up'=>$after,'comments'=>$comments,'down_restored'=>true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $db->exec("USE `{$sourceSchema}`");
    if ($created) {
        $marker=(int)$db->query("SELECT COUNT(*) FROM `{$schema}`.`_codex_execution_marker` WHERE owner_code='INSURANCE_COLUMN_COMMENTS_20260831'")->fetchColumn();
        if ($marker!==1) throw new RuntimeException('격리 Schema Marker가 없습니다.');
        $db->exec("DROP DATABASE `{$schema}`");
    }
}
