<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Integration\ExternalIntegrationService;
use Core\Security\SecretResolutionException;
use Core\Security\SecretResolver;

$resolver = new SecretResolver();
$contracts = [
    'SECURITY_RRN_KEY' => 'secret',
    'GOOGLE_SMTP_MAIN' => 'password',
    'BUSINESS_STATUS_API' => 'service_key',
    'INTERNAL_API_MAIN' => 'secret',
];

foreach ($contracts as $code => $field) {
    try {
        $resolver->resolve($code, $field);
        echo $code . "_CONFIGURED=true\n";
    } catch (SecretResolutionException $e) {
        if ($e->errorCode() === 'SECRET_NOT_CONFIGURED') {
            echo $code . "_CONFIGURED=false\n";
            continue;
        }
        echo $code . "_CONFIG_ERROR=" . $e->errorCode() . "\n";
    }
}

$service = new ExternalIntegrationService();
$method = new ReflectionMethod($service, 'buildRequestUrl');
$url = $method->invoke($service);
if (!is_string($url) || !str_contains($url, 'serviceKey=') || str_contains($url, ' ')) {
    throw new RuntimeException('Business API URL 조립 실패');
}
unset($url, $service, $resolver);

echo "appsetting_secret_runtime_contract: PASS\n";
