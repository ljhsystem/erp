<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = Core\DbPdo::conn();
$requestedType = trim((string) ($argv[2] ?? ''));
if (($argv[1] ?? '') === 'sources') {
    $sources = $db->query(
        "SELECT s.standard_type_code,src.id,src.source_name,src.organization_name,src.law_name,src.notice_no,"
        . "src.published_at,src.source_url,src.note,src.file_path"
        . " FROM system_statutory_standard_sources src"
        . " JOIN system_statutory_standards s ON s.id=src.standard_id"
        . " ORDER BY s.standard_type_code,s.effective_from,src.sort_no"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($requestedType !== '') {
        $sources = array_values(array_filter($sources, static fn(array $row): bool => $row['standard_type_code'] === $requestedType));
    }
    echo json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'source-review') {
    $sources = $db->query(
        "SELECT s.standard_type_code,s.effective_from,src.id,src.source_name,src.organization_name,src.law_name,"
        . "src.notice_no,src.published_at,src.source_url,src.note"
        . " FROM system_statutory_standard_sources src"
        . " JOIN system_statutory_standards s ON s.id=src.standard_id"
        . " WHERE COALESCE(src.notice_no,'')='' OR src.published_at IS NULL"
        . " ORDER BY s.standard_type_code,s.effective_from,src.sort_no"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($sources as &$source) {
        $url = (string) ($source['source_url'] ?? '');
        $isPromulgatedLawSource = str_contains($url, 'law.go.kr')
            && (str_contains($url, 'lsInfoP.do') || str_contains($url, 'lsRvsDoc'));
        $source['review_classification'] = $isPromulgatedLawSource
            ? 'LEGAL_METADATA_REVIEW_REQUIRED'
            : 'OFFICIAL_GUIDANCE_PAGE_NO_PROMULGATION_METADATA';
    }
    unset($source);
    echo json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
$rows = $db->query(
    "SELECT s.id,s.standard_type_code,s.effective_from,s.effective_to,s.value_data,s.note,COUNT(src.id) source_count"
    . " FROM system_statutory_standards s"
    . " LEFT JOIN system_statutory_standard_sources src ON src.standard_id=s.id"
    . " GROUP BY s.id ORDER BY s.standard_type_code,s.effective_from"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result = [];
foreach ($rows as $row) {
    if ($requestedType !== '' && $row['standard_type_code'] !== $requestedType) {
        continue;
    }
    $value = json_decode((string) $row['value_data'], true);
    $result[(string) $row['standard_type_code']][] = [
        'id' => $row['id'],
        'from' => $row['effective_from'],
        'to' => $row['effective_to'],
        'value' => $value,
        'note' => $row['note'],
        'sources' => (int) $row['source_count'],
    ];
}
if (($argv[1] ?? '') === 'summary') {
    $summary = [];
    foreach ($result as $type => $items) {
        $summary[$type] = [
            'count' => count($items),
            'first' => $items[0]['from'],
            'last_from' => $items[array_key_last($items)]['from'],
            'last_to' => $items[array_key_last($items)]['to'],
            'sources' => array_sum(array_column($items, 'sources')),
            'periods' => array_map(static fn(array $item): string => $item['from'] . '~' . ($item['to'] ?? 'NULL'), $items),
        ];
    }
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'quality') {
    $quality = [];
    foreach ($result as $type => $items) {
        $gaps = [];
        $overlaps = [];
        for ($index = 1; $index < count($items); $index++) {
            $previousTo = $items[$index - 1]['to'];
            if ($previousTo === null || $items[$index]['from'] <= $previousTo) {
                $overlaps[] = $items[$index - 1]['id'] . ' / ' . $items[$index]['id'];
                continue;
            }
            $expected = (new DateTimeImmutable($previousTo))->modify('+1 day')->format('Y-m-d');
            if ($items[$index]['from'] !== $expected) {
                $gaps[] = $expected . '~' . (new DateTimeImmutable($items[$index]['from']))->modify('-1 day')->format('Y-m-d');
            }
        }
        $lastTo = $items[array_key_last($items)]['to'];
        $currentCoverageGap = $lastTo !== null && $lastTo < date('Y-m-d')
            ? (new DateTimeImmutable($lastTo))->modify('+1 day')->format('Y-m-d') . '~현재'
            : null;
        $quality[$type] = ['gaps' => $gaps, 'overlaps' => $overlaps, 'current_coverage_gap' => $currentCoverageGap];
    }
    $sourceRows = $db->query(
        "SELECT src.* FROM system_statutory_standard_sources src"
        . " JOIN system_statutory_standards s ON s.id=src.standard_id ORDER BY s.standard_type_code,s.effective_from"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $metadataFields = ['source_name', 'organization_name', 'law_name', 'notice_no', 'published_at', 'source_url', 'note'];
    $missing = array_fill_keys($metadataFields, 0);
    foreach ($sourceRows as $source) {
        foreach ($metadataFields as $field) {
            if (trim((string) ($source[$field] ?? '')) === '') {
                $missing[$field]++;
            }
        }
    }
    echo json_encode(['types' => $quality, 'source_count' => count($sourceRows), 'source_metadata_missing' => $missing],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === 'values') {
    foreach ($result as &$items) {
        foreach ($items as &$item) {
            unset($item['value']['_schema']);
        }
        unset($item);
    }
    unset($items);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
