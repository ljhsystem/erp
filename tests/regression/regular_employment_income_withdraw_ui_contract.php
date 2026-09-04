<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$js = (string) file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$service = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$route = (string) file_get_contents($root . '/routes/api/institution.php');

$contracts = [
    'workflow action sync' => 'function syncWorkflowActions()',
    'pending status policy' => "documentStatus==='PENDING'&&requestId!==''",
    'withdraw enabled' => 'withdraw.disabled=!withdrawable',
    'withdraw visible' => "withdraw.classList.toggle('d-none',!withdrawable)",
    'readonly resync' => 'syncWorkflowActions();',
    'request id payload' => 'JSON.stringify({request_id:requestId})',
];
foreach ($contracts as $name => $needle) {
    if (!str_contains($js, $needle)) throw new RuntimeException("상용근로소득 회수 UI 계약 누락: {$name}");
}
if (!str_contains($service, 'ApprovalWorkflowService($this->db))->withdraw(')
    || !str_contains($service, "'WITHDRAWN'")) {
    throw new RuntimeException('상용근로소득 회수 Service 계약이 없습니다.');
}
if (!str_contains($route, '/api/institution/income-data/regular-employment/withdraw')) {
    throw new RuntimeException('상용근로소득 회수 Route가 없습니다.');
}

echo "regular employment income withdraw UI contract: OK\n";
