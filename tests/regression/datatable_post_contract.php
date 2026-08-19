<?php

require_once dirname(__DIR__, 2) . '/core/Helpers/DataTableRequestHelper.php';

use Core\Helpers\DataTableRequestHelper;

$columns = [];
for ($index = 0; $index < 48; $index++) {
    $columns[] = [
        'data' => 'column_' . $index,
        'name' => 'column_' . $index,
        'searchable' => 'true',
        'orderable' => 'true',
        'search' => ['value' => str_repeat('검색조건', 8), 'regex' => 'false'],
    ];
}

$body = [
    'draw' => '7',
    'start' => '100',
    'length' => '100',
    'columns' => $columns,
    'order' => [['column' => '3', 'dir' => 'asc']],
    'search' => ['value' => '근로계약', 'regex' => 'false'],
    'employee_id' => '11111111-1111-1111-1111-111111111111',
    'date_from' => '2026-01-01',
    'date_to' => '2026-12-31',
];

$queryStringLength = strlen(http_build_query($body));
assert($queryStringLength > 8192);

$input = DataTableRequestHelper::input(['box' => 'pending', 'draw' => '1'], $body);
assert($input['box'] === 'pending');
assert($input['draw'] === '7');
assert($input['start'] === '100');
assert($input['length'] === '100');
assert($input['order'][0]['column'] === '3');
assert(count($input['columns']) === 48);
assert($input['search']['value'] === '근로계약');
assert($input['employee_id'] === '11111111-1111-1111-1111-111111111111');

$response = [
    'draw' => (int) $input['draw'],
    'recordsTotal' => 250,
    'recordsFiltered' => 125,
    'data' => array_fill(0, 100, ['id' => 'fixture']),
];
assert($response['draw'] === 7);
assert($response['recordsTotal'] === 250);
assert($response['recordsFiltered'] === 125);
assert(count($response['data']) === 100);

echo json_encode([
    'success' => true,
    'columns' => count($columns),
    'get_query_string_bytes' => $queryStringLength,
    'post_body_parsed' => true,
    'draw' => $response['draw'],
    'recordsTotal' => $response['recordsTotal'],
    'recordsFiltered' => $response['recordsFiltered'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
