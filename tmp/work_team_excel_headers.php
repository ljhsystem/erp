<?php
error_reporting(E_ALL);
require getcwd() . '/vendor/autoload.php';
$ref = new ReflectionClass(App\Services\System\WorkTeamService::class);
$service = $ref->newInstanceWithoutConstructor();
$call = function(string $name, array $args = []) use ($ref, $service) {
    $method = $ref->getMethod($name);
    $method->setAccessible(true);
    return $method->invokeArgs($service, $args);
};
$result = [
    'template_default' => $call('buildHeaders', [$call('resolveColumns', ['template', ''])]),
    'template_selected' => $call('buildHeaders', [$call('resolveColumns', ['template', 'team_leader_client_id,team_name,memo'])]),
    'download_default' => $call('buildHeaders', [$call('resolveColumns', ['download', ''])]),
    'download_selected' => $call('buildHeaders', [$call('resolveColumns', ['download', 'team_leader_client_id,team_leader_client_name,team_name'])]),
];
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
