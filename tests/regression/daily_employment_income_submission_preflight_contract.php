<?php

$root = dirname(__DIR__, 2);
$files = [
    'route' => file_get_contents($root . '/routes/api/daily-employment-income.php'),
    'controller' => file_get_contents($root . '/app/Controllers/Institution/DailyEmploymentIncomeController.php'),
    'service' => file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php'),
    'model' => file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php'),
    'view' => file_get_contents($root . '/app/views/institution/daily-employment-income/index.php'),
    'js' => file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js'),
];

$expectations = [
    'route' => ["['GET', 'preflight', 'apiPreflight'"],
    'controller' => ['function apiPreflight', 'submissionPreflight'],
    'service' => [
        "['DRAFT', 'REJECTED', 'WITHDRAWN']",
        "'can_submit'", "'blocking_errors'", "'warnings'", "'calculation_status'",
        "'insurance_status'", "'non_tax_status'", "'source_hash'", "'checked_at'",
        'duplicateWorkdayDocuments', 'compareStoredCalculation', 'STATUTORY_REVISION_CHANGED',
        'NON_TAX_REASON_REQUIRED', 'REGULAR_DAILY_IDENTITY_UNVERIFIED',
    ],
    'model' => [
        "h.status_code IN ('DRAFT','PENDING','APPROVED','REJECTED','WITHDRAWN')",
        "status_code IN ('DRAFT','REJECTED','WITHDRAWN')",
    ],
    'view' => [
        'id="dailyIncomeWithdraw" disabled',
        'id="dailyIncomeSubmit" disabled',
    ],
    'js' => ["WITHDRAWN: ['회수'", "['DRAFT', 'REJECTED', 'WITHDRAWN']", 'API.PREFLIGHT', 'preflight.blocking_errors'],
];

foreach ($expectations as $name => $needles) {
    foreach ($needles as $needle) {
        if (!str_contains($files[$name], $needle)) {
            fwrite(STDERR, "FAIL {$name}: {$needle}\n");
            exit(1);
        }
    }
}

if (str_contains($files['service'], '[\'reason\' => $exception->getMessage()]')) {
    fwrite(STDERR, "FAIL: Exception 원문을 API 응답에 노출합니다.\n");
    exit(1);
}
if (!str_contains($files['js'], '결재 Lifecycle은 아직 연결되지 않았습니다.')) {
    fwrite(STDERR, "FAIL: Preflight 통과 후 미연결 Lifecycle 상태를 안내하지 않습니다.\n");
    exit(1);
}

echo "PASS: 일용근로소득 상신 Preflight 계약\n";
