<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

$pdo = Core\DbPdo::conn();
$rows = $pdo->query("SELECT id,effective_from,effective_to,value_data FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE' ORDER BY effective_from,id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result = [];
foreach ($rows as $row) {
    $value = json_decode((string) $row['value_data'], true) ?: [];
    $matrix = (array) ($value['additional_employer_rates'] ?? []);
    $result[] = [
        'id'=>$row['id'],'effective_from'=>$row['effective_from'],'effective_to'=>$row['effective_to'],
        'row_count'=>count($matrix),'rows'=>array_map(static fn(array $item): array => [
            'business_size_name'=>$item['business_size_name']??null,
            'business_size_code'=>$item['business_size_code']??null,
            'employer_rate'=>$item['employer_rate']??null,
        ], $matrix),
    ];
}
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
