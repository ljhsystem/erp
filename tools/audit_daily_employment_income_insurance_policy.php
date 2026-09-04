<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$types = [
    'NATIONAL_PENSION',
    'HEALTH_INSURANCE',
    'LONG_TERM_CARE_INSURANCE',
    'EMPLOYMENT_INSURANCE',
    'INDUSTRIAL_ACCIDENT_INSURANCE',
    'DAILY_WORKER_INCOME_TAX',
    'LOCAL_INCOME_TAX_WITHHOLDING',
];
$quotedTypes = implode(',', array_map([$db, 'quote'], $types));

$result = [
    'code_templates' => $db->query(
        "SELECT code, code_name, extra_data FROM system_codes"
        . " WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN ({$quotedTypes}) ORDER BY sort_no, code"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'standards' => $db->query(
        "SELECT *"
        . " FROM system_statutory_standards WHERE standard_type_code IN ({$quotedTypes})"
        . " ORDER BY standard_type_code, effective_from"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'workplaces' => (int) $db->query('SELECT COUNT(*) FROM institution_social_insurance_workplaces')->fetchColumn(),
    'coverages' => $db->query(
        'SELECT insurance_type_code, application_status_code, COUNT(*) AS row_count'
        . ' FROM institution_daily_worker_social_insurance_coverages'
        . ' GROUP BY insurance_type_code, application_status_code ORDER BY insurance_type_code, application_status_code'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
