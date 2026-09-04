<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$excel = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeExcelService.php');
$source = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationSourceService.php');
$ui = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$migration = (string) file_get_contents($root . '/app/migrations/20260828_01_add_daily_income_calculation_note.up.sql');

$checks = [
    'Migration은 NULL 허용 VARCHAR(500)만 추가한다' => str_contains($migration, 'calculation_note VARCHAR(500) NULL'),
    'Migration은 공백 산정내역을 차단한다' => str_contains($migration, 'OCTET_LENGTH(TRIM(calculation_note))'),
    'Service는 산정내역을 Trim하고 500자를 검증한다' => str_contains($service, "'calculation_note' => \$calculationNote")
        && str_contains($service, '산정내역은 500자 이하로 입력해 주세요.'),
    'Model은 산정내역을 Workday에 저장한다' => str_contains($model, "'calculation_note' => \$day['calculation_note'] ?? null"),
    'UI는 Workday 산정내역 열을 제공한다' => str_contains($ui, "key: 'calculation_note', label: '산정내역'"),
    'UI는 공백과 길이를 검증한다' => str_contains($ui, "String(row.values[columnKey] ?? '').trim()")
        && str_contains($ui, 'note.length > 500'),
    'Excel은 날짜별 산정내역을 왕복한다' => str_contains($excel, "'_calculation_note'")
        && str_contains($excel, '일 산정내역'),
    '계산 source hash는 산정내역을 제외한다' => !str_contains($source, 'calculation_note'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ', $label, PHP_EOL;
}

require $root . '/vendor/autoload.php';
$sourceService = new App\Services\Institution\DailyEmploymentIncomeCalculationSourceService();
$baseSource = [
    'daily_employment_income_id' => 'document-1',
    'income_year_month' => '2013-08',
    'payment_date' => '2013-09-11',
    'groups' => [[
        'id' => 'group-1', 'sort_no' => 1, 'business_unit' => 'HEAD_OFFICE',
        'project_id' => null, 'work_team_id' => null,
        'items' => [[
            'id' => 'item-1', 'sort_no' => 1, 'worker_client_id' => 'worker-1',
            'work_type_code' => 'STONE', 'work_description' => '석재 작업',
            'workdays' => [[
                'id' => 'workday-1', 'work_date' => '2013-08-06',
                'actual_work_minutes' => 480, 'daily_rate_amount' => 90000,
                'calculation_note' => '변경 전 산정내역', 'lines' => [],
            ]],
        ]],
    ]],
];
$changedNoteSource = $baseSource;
$changedNoteSource['groups'][0]['items'][0]['workdays'][0]['calculation_note'] = '변경 후 산정내역';
if ($sourceService->hash($baseSource) !== $sourceService->hash($changedNoteSource)) {
    throw new RuntimeException('FAIL: 산정내역 변경 전후 계산 source hash가 다릅니다.');
}
echo 'PASS: 산정내역 변경 전후 계산 source hash가 같습니다.', PHP_EOL;
