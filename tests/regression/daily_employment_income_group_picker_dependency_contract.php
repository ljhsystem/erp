<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$workerCards = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$model = file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$controller = file_get_contents($root . '/app/Controllers/Institution/DailyEmploymentIncomeController.php');

$checks = [
    '프로젝트와 팀 입력필드는 사업구분 정책 로딩 전후 항상 노출' => !str_contains($page, 'projectField.hidden') && !str_contains($page, 'teamField.hidden'),
    '사업구분 변경 시 프로젝트·팀 초기화' => str_contains($page, "group.project_id = ''; group.work_team_id = '';"),
    '프로젝트 변경 시 팀 초기화' => str_contains($page, 'group.project_id = project.value;')
        && str_contains($page, "group.work_team_id = '';"),
    '미적용 Payload는 NULL' => str_contains($page, "project_id: policy?.uses_project ?")
        && str_contains($page, "work_team_id: policy?.uses_work_team ?"),
    '서버 Pagination 20+1' => str_contains($model, 'LIMIT 21 OFFSET {$offset}')
        && str_contains($model, "'has_more'=>count(\$rows)>\$limit"),
    '프로젝트 선택목록은 원본 활성 프로젝트 마스터를 사용' => str_contains($model, 'FROM system_projects')
        && str_contains($model, 'is_active=1 AND deleted_at IS NULL')
        && str_contains($model, 'project_name AS text'),
    '팀 선택목록은 원본 활성 팀을 사업구분 범위로 제한' => str_contains($model, 'FROM system_work_teams t')
        && str_contains($model, 't.is_active=1 AND t.deleted_at IS NULL')
        && str_contains($model, 't.business_unit=:business_unit')
        && str_contains($model, "\$statement->execute([':business_unit'=>\$businessUnit,':q'=>\$like]);"),
    '작업자·공종 선택목록은 일용 화면 options API를 사용' => str_contains($workerCards, "option_type: 'worker'")
        && str_contains($workerCards, "option_type: 'work_type'")
        && !str_contains($workerCards, '/api/settings/base-info/client/search-picker')
        && !str_contains($workerCards, '/api/settings/system/code/list'),
    '작업자·공종은 활성 기준정보 SSOT와 sort_no를 사용' => str_contains($model, "elseif (\$type === 'worker')")
        && str_contains($model, 'FROM system_clients c')
        && str_contains($model, "elseif (\$type === 'work_type')")
        && str_contains($model, "code_group='WORK_TYPE'")
        && str_contains($model, 'ORDER BY c.sort_no,c.client_name,c.id'),
    '직접 ID 저장도 서버 검증' => str_contains($service, 'assertGroupReferences($companyId')
        && str_contains($model, 'a.worker_client_id=:worker_id'),
    '조회권한 Route를 거친다' => str_contains($controller, '$this->service->options($_GET)'),
    '재렌더 전 Select2 Destroy' => str_contains($page, "PickerSelect2.destroy(select)"),
    '과거 선택값 fallback 표시' => str_contains($page, "group.project_name || '과거 프로젝트'")
        && str_contains($page, "group.work_team_name || '과거 작업팀'"),
    '팀 퀵등록 결과를 원본 팀 목록에서 재검증' => str_contains($page, '등록한 팀을 현재 사업구분의 팀 목록에서 확인할 수 없어 바로 선택할 수 없습니다.'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: 일용근로소득 Group Picker 종속·보안·Lifecycle 계약\n";
