<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$db = DbPdo::conn();

$normalize = static function (mixed $value) use (&$normalize): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map($normalize, $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = $normalize($item);
    }
    return $value;
};

$rows = $db->query(
    "SELECT id,standard_type_code,policy_component_code,employment_type_code,work_scope_code,"
    . "additional_dimension_data,additional_dimension_key,effective_from,effective_to,value_data,note "
    . "FROM system_statutory_standards "
    . "WHERE policy_component_code='ELIGIBILITY' "
    . "ORDER BY standard_type_code,employment_type_code,work_scope_code,additional_dimension_key,effective_from,id"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$canonicalRows = [];
$timelineRows = [];
$decodeFailures = [];
foreach ($rows as $row) {
    $value = json_decode((string) $row['value_data'], true);
    $dimensions = json_decode((string) ($row['additional_dimension_data'] ?? '{}'), true);
    if (!is_array($value) || !is_array($dimensions)) {
        $decodeFailures[] = (string) $row['id'];
        continue;
    }
    $canonicalRows[] = [
        'id' => (string) $row['id'],
        'standard_type_code' => (string) $row['standard_type_code'],
        'policy_component_code' => (string) $row['policy_component_code'],
        'employment_type_code' => (string) $row['employment_type_code'],
        'work_scope_code' => (string) $row['work_scope_code'],
        'additional_dimension_data' => $normalize($dimensions),
        'additional_dimension_key' => (string) $row['additional_dimension_key'],
        'effective_from' => (string) $row['effective_from'],
        'effective_to' => $row['effective_to'],
        'value_data' => $normalize($value),
        'note' => $row['note'],
    ];
    $grain = implode('|', [
        $row['standard_type_code'],
        $row['policy_component_code'],
        $row['employment_type_code'],
        $row['work_scope_code'],
        $row['additional_dimension_key'],
    ]);
    $timelineRows[$grain][] = [
        'id' => (string) $row['id'],
        'from' => (string) $row['effective_from'],
        'to' => $row['effective_to'] === null ? null : (string) $row['effective_to'],
    ];
}

$timelineIssues = [];
foreach ($timelineRows as $grain => $timeline) {
    usort($timeline, static fn(array $left, array $right): int => [$left['from'], $left['id']] <=> [$right['from'], $right['id']]);
    for ($index = 1; $index < count($timeline); $index++) {
        $previous = $timeline[$index - 1];
        $current = $timeline[$index];
        if ($previous['to'] === null || $current['from'] <= $previous['to']) {
            $timelineIssues[] = ['grain' => $grain, 'type' => 'OVERLAP', 'left' => $previous, 'right' => $current];
            continue;
        }
        $expected = (new DateTimeImmutable($previous['to']))->modify('+1 day')->format('Y-m-d');
        if ($current['from'] !== $expected) {
            $timelineIssues[] = ['grain' => $grain, 'type' => 'GAP', 'expected_from' => $expected, 'right' => $current];
        }
    }
}

$sourceRows = $db->query(
    "SELECT source_row.id,source_row.standard_id,source_row.organization_name,source_row.law_name,"
    . "source_row.notice_no,source_row.source_name,source_row.source_url,source_row.published_at,source_row.note "
    . "FROM system_statutory_standard_sources source_row "
    . "JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id "
    . "WHERE standard_row.policy_component_code='ELIGIBILITY' "
    . "ORDER BY source_row.standard_id,source_row.sort_no,source_row.id"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$legacyRows = array_values(array_filter(
    $canonicalRows,
    static fn(array $row): bool => in_array($row['standard_type_code'], ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'], true)
));
$legacySourceRows = array_values(array_filter(
    $sourceRows,
    static fn(array $row): bool => str_starts_with((string) $row['standard_id'], '20260831-10')
));

$encode = static fn(array $value): string => json_encode(
    $value,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
);

echo json_encode([
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'version' => (string) $db->query('SELECT VERSION()')->fetchColumn(),
    'eligibility_revision_count' => count($canonicalRows),
    'eligibility_source_count' => count($sourceRows),
    'legacy_revision_count' => count($legacyRows),
    'legacy_source_count' => count($legacySourceRows),
    'legacy_revision_hash' => hash('sha256', $encode($legacyRows)),
    'legacy_source_hash' => hash('sha256', $encode($legacySourceRows)),
    'employment_eligibility_count' => count(array_filter($canonicalRows, static fn(array $row): bool => $row['standard_type_code'] === 'EMPLOYMENT_INSURANCE')),
    'industrial_eligibility_count' => count(array_filter($canonicalRows, static fn(array $row): bool => $row['standard_type_code'] === 'INDUSTRIAL_ACCIDENT')),
    'timeline_issues' => $timelineIssues,
    'decode_failures' => $decodeFailures,
    'workplace_count' => (int) $db->query('SELECT COUNT(*) FROM institution_social_insurance_workplaces')->fetchColumn(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
