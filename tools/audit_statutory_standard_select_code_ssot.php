<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$groups = [
    'STATUTORY_POLICY_COMPONENT',
    'STATUTORY_EMPLOYMENT_TYPE',
    'STATUTORY_WORK_SCOPE',
    'STATUTORY_CONDITION_COMBINATION',
    'INSURANCE_ELIGIBILITY_DECISION',
    'INSURANCE_ELIGIBILITY_RESULT',
    'INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE',
    'INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY',
    'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT',
    'INSURANCE_ELIGIBILITY_INCOME_BASIS',
    'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE',
    'INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD',
    'INSURANCE_ELIGIBILITY_TRANSITION_POLICY',
    'INSURANCE_ELIGIBILITY_TRANSITION_STATUS',
    'STATUTORY_STANDARD_PERIOD_STATUS',
];
$marks = implode(',', array_fill(0, count($groups), '?'));
$statement = $db->prepare("SELECT code_group,code,code_name,is_active,id FROM system_codes WHERE code_group IN ({$marks}) ORDER BY code_group,sort_no,id");
$statement->execute($groups);
$codes = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

$templateStatement = $db->query("SELECT code,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') ORDER BY code");
$templates = $templateStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$migrationTemplates = $db->query("SELECT id,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$selectFields = 0;
$systemCodeSelectFields = 0;
$embeddedOptionFields = 0;
$missingReferences = [];
foreach ($templates as $template) {
    $extraData = json_decode((string)$template['extra_data'], true, 512, JSON_THROW_ON_ERROR);
    foreach (($extraData['field_sets']['eligibility'] ?? []) as $field) {
        if (($field['type'] ?? null) !== 'select') {
            continue;
        }
        $selectFields++;
        if (($field['option_source'] ?? null) === 'SYSTEM_CODES') {
            $systemCodeSelectFields++;
            $group = (string)($field['option_code_group'] ?? '');
            if ($group === '') {
                $missingReferences[] = [$template['code'], $field['code'] ?? null, 'EMPTY_GROUP'];
                continue;
            }
            $reference = $db->prepare('SELECT COUNT(*) FROM system_codes WHERE code_group=:code_group');
            $reference->execute(['code_group' => $group]);
            if ((int)$reference->fetchColumn() === 0) {
                $missingReferences[] = [$template['code'], $field['code'] ?? null, $group];
            }
        }
        if (array_key_exists('options', $field)) {
            $embeddedOptionFields++;
        }
    }
}

echo json_encode([
    'read_only' => true,
    'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
    'version' => $db->query('SELECT VERSION()')->fetchColumn(),
    'group_count' => count(array_unique(array_column($codes, 'code_group'))),
    'code_count' => count($codes),
    'duplicate_count' => (int)$db->query("SELECT COUNT(*) FROM (SELECT code_group,code FROM system_codes GROUP BY code_group,code HAVING COUNT(*)>1) duplicated")->fetchColumn(),
    'select_field_count' => $selectFields,
    'system_code_select_field_count' => $systemCodeSelectFields,
    'embedded_option_field_count' => $embeddedOptionFields,
    'missing_reference_count' => count($missingReferences),
    'missing_references' => $missingReferences,
    'codes_hash' => hash('sha256', json_encode($codes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'templates_hash' => hash('sha256', json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'migration_templates_hash' => hash('sha256', json_encode($migrationTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
