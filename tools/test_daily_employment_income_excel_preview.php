<?php

declare(strict_types=1);

use App\Services\Institution\DailyEmploymentIncomeExcelService;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$service = new DailyEmploymentIncomeExcelService($db);
$before = [
    'headers' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),
    'groups' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_groups')->fetchColumn(),
    'items' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),
];
$spreadsheet = $service->createTemplate();
$sheet = $spreadsheet->getActiveSheet();
$headers = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, false)[0];
$workerIdColumn = array_search('작업자ID', $headers, true);
if ($workerIdColumn === false) throw new RuntimeException('작업자ID 컬럼을 찾을 수 없습니다.');
$businessUnitColumn = array_search('사업구분코드', $headers, true);
$workTypeColumn = array_search('공종코드', $headers, true);
$dayRateColumn = array_search('1일 단가', $headers, true);
$dayMinutesColumn = array_search('1일 실제근로시간(휴게시간 제외)', $headers, true);
if (in_array(false, [$businessUnitColumn, $workTypeColumn, $dayRateColumn, $dayMinutesColumn], true)) {
    throw new RuntimeException('실제근로시간 Excel 컬럼을 찾을 수 없습니다.');
}
$workerId = $db->query("SELECT id FROM system_clients WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,client_name,id LIMIT 1")->fetchColumn();
$workType = $db->query("SELECT code FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1 ORDER BY sort_no,code LIMIT 1")->fetchColumn();
if (!$workerId || !$workType) throw new RuntimeException('Excel Fixture 기준정보를 찾을 수 없습니다.');
$sheet->setCellValue([$workerIdColumn + 1, 2], (string) $workerId);
$sheet->setCellValue([$businessUnitColumn + 1, 2], 'HQ');
$sheet->setCellValue([$workTypeColumn + 1, 2], (string) $workType);
$sheet->setCellValue([$dayRateColumn + 1, 2], 90000);
$sheet->setCellValue([$dayMinutesColumn + 1, 2], 480);
$path = tempnam(sys_get_temp_dir(), 'daily-income-excel-');
if ($path === false) throw new RuntimeException('임시 파일을 생성할 수 없습니다.');
(new Xlsx($spreadsheet))->save($path);
try {
    $preview = $service->preview($path, '2026-08');
} finally {
    @unlink($path);
}
$after = [
    'headers' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),
    'groups' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_groups')->fetchColumn(),
    'items' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),
];
if (($preview['data']['valid'] ?? false) !== true) {
    throw new RuntimeException('실제근로시간 480분 Excel Preview가 실패했습니다: ' . json_encode($preview['data']['errors'] ?? [], JSON_UNESCAPED_UNICODE));
}
$previewMinutes = (int) ($preview['data']['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes'] ?? 0);
if ($previewMinutes !== 480) throw new RuntimeException('Excel Preview에서 실제근로시간 480분이 유지되지 않았습니다.');
$download = $service->createDownload($preview['data']['groups'], ['income_year_month' => '2026-08', 'payment_date' => '2026-09-10']);
$downloadSheet = $download->getActiveSheet();
$downloadHeaders = $downloadSheet->rangeToArray('A1:' . $downloadSheet->getHighestColumn() . '1', null, true, true, false)[0];
$downloadMinutesColumn = array_search('1일 실제근로시간(휴게시간 제외)', $downloadHeaders, true);
if ($downloadMinutesColumn === false || (int) $downloadSheet->getCell([$downloadMinutesColumn + 1, 2])->getValue() !== 480) {
    throw new RuntimeException('Excel 다운로드에서 실제근로시간 480분이 유지되지 않았습니다.');
}
if ($before !== $after) throw new RuntimeException('Preview 과정에서 DB 건수가 변경되었습니다.');
echo json_encode(['preview' => $preview['data']['summary'], 'actual_work_minutes' => $previewMinutes, 'download_actual_work_minutes' => 480, 'before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
