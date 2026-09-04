<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$column = static function (string $name) use ($db): ?array {
    $statement = $db->prepare(
        'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_COMMENT '
        . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() '
        . "AND TABLE_NAME='institution_daily_employment_incomes' AND COLUMN_NAME=:name"
    );
    $statement->execute(['name' => $name]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};
$beforeCount = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn();
$before = ['description' => $column('description'), 'memo' => $column('memo')];

if (($before['description'] === null) !== ($before['memo'] === null)) {
    throw new RuntimeException('비고·메모 컬럼이 부분 적용된 상태입니다. 자동 적용을 중단합니다.');
}

$status = 'already_applied';
if ($before['description'] === null) {
    $db->exec(
        "ALTER TABLE institution_daily_employment_incomes "
        . "ADD COLUMN description VARCHAR(500) NULL COMMENT '비고' AFTER document_title, "
        . "ADD COLUMN memo TEXT NULL COMMENT '메모' AFTER description"
    );
    $status = 'applied';
}

$afterCount = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn();
$after = ['description' => $column('description'), 'memo' => $column('memo')];
if ($beforeCount !== $afterCount || $after['description'] === null || $after['memo'] === null) {
    throw new RuntimeException('Migration 적용 후 컬럼 또는 기존 문서 건수 검증에 실패했습니다.');
}

echo json_encode([
    'migration' => '20260827_22_add_daily_income_header_description_memo',
    'status' => $status,
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'header_count_before' => $beforeCount,
    'header_count_after' => $afterCount,
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
