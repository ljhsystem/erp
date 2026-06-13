<?php
error_reporting(E_ALL);
define('PROJECT_ROOT', getcwd());
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';

$ref = new ReflectionClass(App\Services\System\BankAccountService::class);
$service = $ref->newInstanceWithoutConstructor();

$call = function(string $name, array $args = []) use ($ref, $service) {
    $method = $ref->getMethod($name);
    $method->setAccessible(true);
    return $method->invokeArgs($service, $args);
};

$templateColumns = $call('resolveColumns', ['template', 'account_number,account_name,bank_name,memo']);
$templateHeaders = $call('buildHeaders', [$templateColumns]);
$defaultTemplateHeaders = $call('buildHeaders', [$call('resolveColumns', ['template', ''])]);

$downloadColumns = $call('resolveColumns', ['download', 'bank_file,account_name,sort_no']);
$downloadHeaders = $call('buildHeaders', [$downloadColumns]);
$downloadRow = $call('buildDownloadRow', [[
    'sort_no' => 7,
    'account_name' => '주계좌',
    'bank_file' => '/uploads/bank/sample.png',
    'is_active' => 1,
], $downloadColumns]);
$defaultDownloadHeaders = $call('buildHeaders', [$call('resolveColumns', ['download', ''])]);

$uploadColumns = $call('resolveColumns', ['template', 'bank_name,account_name,bank_file,currency']);
$headerMap = $call('buildHeaderIndexMap', [['은행명', '계좌명', '통장사본', '통화'], $uploadColumns]);
$payloadBlankCurrency = $call('buildUploadPayload', [['기업은행', '급여계좌', 'ignored.png', ''], $headerMap, $uploadColumns]);
$payloadWithCurrency = $call('buildUploadPayload', [['국민은행', '법인계좌', 'x.png', 'USD'], $headerMap, $uploadColumns]);
$missingMap = $call('buildHeaderIndexMap', [['은행명', '통화'], $uploadColumns]);
$missingRequired = $call('findMissingRequiredColumns', [$uploadColumns, $missingMap]);

$result = [
    'default_template_headers' => $defaultTemplateHeaders,
    'selected_template_headers' => $templateHeaders,
    'default_download_headers' => $defaultDownloadHeaders,
    'selected_download_headers' => $downloadHeaders,
    'selected_download_row' => $downloadRow,
    'upload_header_map' => $headerMap,
    'upload_payload_blank_currency' => $payloadBlankCurrency,
    'upload_payload_with_currency' => $payloadWithCurrency,
    'missing_required' => $missingRequired,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
