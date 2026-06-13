<?php
error_reporting(E_ALL);
define('PROJECT_ROOT', getcwd());
require PROJECT_ROOT . '/vendor/autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE system_clients (id TEXT PRIMARY KEY, client_name TEXT, deleted_at TEXT NULL, sort_no INTEGER DEFAULT 0)');
$pdo->exec("INSERT INTO system_clients (id, client_name, deleted_at, sort_no) VALUES ('client-1', '홍길동 거래처', NULL, 1)");
$pdo->exec("INSERT INTO system_clients (id, client_name, deleted_at, sort_no) VALUES ('client-2', '김팀장 거래처', NULL, 2)");

$service = new App\Services\System\WorkTeamService($pdo);
$ref = new ReflectionClass($service);
$call = function(string $name, array $args = []) use ($ref, $service) {
    $method = $ref->getMethod($name);
    $method->setAccessible(true);
    return $method->invokeArgs($service, $args);
};

$templateDefault = $call('buildHeaders', [$call('resolveColumns', ['template', ''])]);
$templateSelectedColumns = $call('resolveColumns', ['template', 'team_leader_client_id,team_name,memo']);
$templateSelected = $call('buildHeaders', [$templateSelectedColumns]);

$downloadDefault = $call('buildHeaders', [$call('resolveColumns', ['download', ''])]);
$downloadSelectedColumns = $call('resolveColumns', ['download', 'team_leader_client_id,team_leader_client_name,team_name']);
$downloadSelected = $call('buildHeaders', [$downloadSelectedColumns]);
$downloadRow = $call('buildDownloadRow', [[
    'team_name' => '시공팀',
    'team_leader_client_name' => '홍길동 거래처',
    'team_leader_client_id' => 'client-1',
    'is_active' => 1,
], $downloadSelectedColumns]);

$uploadColumns = $call('resolveColumns', ['template', 'team_name,team_leader_client_name,team_leader_client_id,memo']);
$headerMap = $call('buildHeaderIndexMap', [['팀명', '팀장', '팀장 거래처 ID', '메모'], $uploadColumns]);
$payloadNameOnly = $call('buildUploadPayload', [['시공팀', '홍길동 거래처', '', '메모1'], $headerMap, $uploadColumns]);
$payloadIdOnly = $call('buildUploadPayload', [['시공팀', '', 'client-2', '메모2'], $headerMap, $uploadColumns]);
$payloadBoth = $call('buildUploadPayload', [['시공팀', '홍길동 거래처', 'client-2', '메모3'], $headerMap, $uploadColumns]);
$missingMap = $call('buildHeaderIndexMap', [['팀장', '메모'], $uploadColumns]);
$missingRequired = $call('findMissingRequiredColumns', [$uploadColumns, $missingMap]);

$result = [
    'template_default' => $templateDefault,
    'template_selected' => $templateSelected,
    'download_default' => $downloadDefault,
    'download_selected' => $downloadSelected,
    'download_row' => $downloadRow,
    'upload_header_map' => $headerMap,
    'payload_name_only' => $payloadNameOnly,
    'payload_id_only' => $payloadIdOnly,
    'payload_both' => $payloadBoth,
    'missing_required' => $missingRequired,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
