<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\EmploymentContractService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$service = new EmploymentContractService(Core\DbPdo::conn());
$method = new ReflectionMethod($service, 'validateBreakSchedules');
$invoke = static fn(array $breaks, int $minutes): array => $method->invoke(
    $service, $breaks, 'WORKDAY', '09:00', '18:00', 0, $minutes, '월요일'
);

$assert($invoke([], 60) === [], '상세구간 미지정 계약이 허용되지 않습니다.');
$assert(count($invoke([['start_time'=>'12:00','end_time'=>'13:00','end_day_offset'=>0]], 60)) === 1, '단일 휴게구간 검증 실패');
$mismatchBlocked = false;
try { $invoke([['start_time'=>'12:00','end_time'=>'12:30','end_day_offset'=>0]], 60); }
catch (InvalidArgumentException $exception) { $mismatchBlocked = str_contains($exception->getMessage(), '일치하지 않습니다'); }
$assert($mismatchBlocked, '상세구간 합계 불일치가 차단되지 않았습니다.');
$assert(count($invoke([
    ['start_time'=>'10:00','end_time'=>'10:15','end_day_offset'=>0],
    ['start_time'=>'12:00','end_time'=>'12:45','end_day_offset'=>0],
], 60)) === 2, '다중 휴게구간 검증 실패');

$db = Core\DbPdo::conn();
$actual = $db->query("SELECT c.contract_status,s.break_minutes,COUNT(b.id) break_count
    FROM institution_employment_contracts c
    JOIN institution_employment_contracts_weekly_schedules s ON s.contract_id=c.id AND s.day_type='WORKDAY'
    LEFT JOIN institution_employment_contracts_break_schedules b ON b.weekly_schedule_id=s.id
    WHERE c.deleted_at IS NULL AND c.employee_name_snapshot='이정호'
    GROUP BY c.id,s.id ORDER BY c.created_at,s.day_of_week LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$assert(($actual['contract_status'] ?? '') === 'APPROVED', '기존 대표자 계약이 승인상태가 아닙니다.');
$assert((int)($actual['break_minutes'] ?? 0) === 60, '기존 대표자 계약의 총 휴게시간이 보존되지 않았습니다.');
$assert((int)($actual['break_count'] ?? -1) === 1, '기존 대표자 계약의 12:00~13:00 상세 휴게구간이 보존되지 않았습니다.');

$js = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js') ?: '';
$assert(str_contains($js, 'openTimePickerForInput(input)'), '상세 휴게구간이 공용 Time Picker를 사용하지 않습니다.');
$assert(str_contains($js, '미지정 · 총 휴게시간'), '과거 미지정 상세 표시가 없습니다.');
$assert(str_contains($js, "button.textContent = rows.length > 0 ? '설정' : '미설정';"), '상세 휴게구간 설정 여부 버튼 문구가 적용되지 않았습니다.');
$assert(str_contains($js, "column('day_type', '근무구분', { width: 160"), '근무구분 컬럼 너비가 조정되지 않았습니다.');
$assert(str_contains($js, "column('end_day_offset', '퇴근일구분', { width: 145"), '퇴근일구분 컬럼 너비가 부족합니다.');
$assert(str_contains($js, "column('break_schedules_display', '상세 휴게구간', { width: 110"), '상세 휴게구간 컬럼 너비가 축소되지 않았습니다.');
$assert(str_contains($js, "break_schedules: [{ start_time: '12:00', end_time: '13:00', end_day_offset: 0 }]"), '일반근무 기본설정에 12:00~13:00 상세 휴게구간이 없습니다.');
$assert(str_contains($js, "(defaults.break_schedules || []).map(item => ({ ...item }))"), '요일별 기본 상세 휴게구간이 독립 복제되지 않습니다.');
$assert(str_contains($js, 'if (activeRows.length === 0) componentRow();'), '지급조건 재추가 회귀가 발생했습니다.');
$assert(str_contains($js, "createRevisionDraft('CHANGE', reason, contractDate)"), '조건변경 개정 경로가 없습니다.');
$assert(str_contains($js, "createRevisionDraft('CORRECTION', reason, contractDate)"), '입력누락 정정 경로가 없습니다.');
$serviceSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/EmploymentContractService.php') ?: '';
$assert(str_contains($serviceSource, "'CREATE_CORRECTION'"), '입력정정 감사 Snapshot 경로가 없습니다.');

echo "employment contract break schedule contract: OK\n";
