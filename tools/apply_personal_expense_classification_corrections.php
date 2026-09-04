<?php

declare(strict_types=1);

$baseUrl = rtrim(trim((string) getenv('ERP_BASE_URL')), '/');
$sessionCookie = trim((string) getenv('ERP_SESSION_COOKIE'));
$mode = strtolower(trim((string) ($argv[1] ?? '')));
if ($mode !== 'apply' || $baseUrl === '' || $sessionCookie === '') {
    fwrite(STDERR, "사용법: ERP_BASE_URL과 ERP_SESSION_COOKIE를 설정한 뒤 php tools/apply_personal_expense_classification_corrections.php apply\n");
    exit(1);
}

$batchKey = 'PERSONAL_EXPENSE-2013-07-CLASSIFICATION-CORRECTION-01';
$payload = [
    'personal_expense_id' => 'a31253c0-e0b4-45bf-bebc-6a4f5c7c53fa',
    'approval_request_id' => '084a2417-6d8c-47e1-a80d-6b8739b52195',
    'correction_batch_key' => $batchKey,
    'correction_reason' => '개인경비 승인자료의 공식 회계분류 정정',
    'items' => [
        ['personal_expense_item_id' => 'b2bdf16e-570d-4d8c-858b-7da7253a6c9f', 'evidence_id' => '669f2e36-5e6f-4e34-a70e-96ad7a2f1536', 'corrected_category' => 'FEES_AND_COMMISSIONS', 'request_key' => $batchKey . '-01'],
        ['personal_expense_item_id' => 'd69ce562-0e90-492e-a999-969576f29b31', 'evidence_id' => 'ed3aa4b9-3226-467b-951b-f764c1f5ce32', 'corrected_category' => 'FEES_AND_COMMISSIONS', 'request_key' => $batchKey . '-02'],
        ['personal_expense_item_id' => 'c1c42f36-676b-4590-8b5b-0b0ffa70befe', 'evidence_id' => '3a2d09b9-2320-4f5f-be2b-c725c0ff4003', 'corrected_category' => 'SUPPLIES', 'request_key' => $batchKey . '-03'],
        ['personal_expense_item_id' => '9d8f8195-ecf1-4133-86a0-1d26284ae44c', 'evidence_id' => '281608ac-7ec7-47fd-9cb4-6855270deadc', 'corrected_category' => 'MEAL', 'request_key' => $batchKey . '-04'],
    ],
];

$curl = curl_init($baseUrl . '/api/approval/personal-expense/correct-classification');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cookie: ' . $sessionCookie],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);
if (!is_string($body) || $body === '') {
    fwrite(STDERR, '정정 API 호출에 실패했습니다: ' . $error . PHP_EOL);
    exit(2);
}
echo $body . PHP_EOL;
exit($status >= 200 && $status < 300 ? 0 : 3);
