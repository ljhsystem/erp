<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$grid = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$view = file_get_contents($root . '/app/views/institution/daily-employment-income/index.php');
$teamQuick = file_get_contents($root . '/public/assets/js/pages/main/settings/base/work-team/quick-modal.js');
$projectPartial = file_get_contents($root . '/app/views/main/settings/base-info/partials/project_modal.php');
$codeSelect = file_get_contents($root . '/public/assets/js/pages/main/settings/system/code-select.js');
$codeRuntime = file_get_contents($root . '/public/assets/js/pages/main/settings/system/code-modal-runtime.js');
$layout = file_get_contents($root . '/public/assets/css/pages/layout/layout.css');
$picker = file_get_contents($root . '/public/assets/js/common/picker/picker.select2.js');

$assertions = [
    'Group 선택필드는 공용 AJAX PickerSelect2를 사용한다' => str_contains($page, "import { PickerSelect2 }") && str_contains($page, 'PickerSelect2.createAjax(select'),
    '공용 Select2는 긴 선택값과 검색결과를 고정 폭 안에서 줄임표시한다' => str_contains($layout, '.select2-container--default .select2-selection--single .select2-selection__rendered {')
        && str_contains($layout, 'text-overflow: ellipsis;')
        && str_contains($layout, '.select2-results__option {')
        && str_contains($layout, 'white-space: nowrap;')
        && str_contains($picker, "maxWidth: `\${width}px`")
        && str_contains($picker, "getBoundingClientRect?.().width"),
    '빈 값 문구는 공용 선택 없음 계약이다' => str_contains($page, "group.business_unit, '선택(없음)'")
        && str_contains($grid, "new Option('선택(없음)'") && !str_contains($page, '사업구분 선택'),
    'Group 목록은 서버 20건 Pagination을 사용한다' => str_contains($page, "pagination: { more: data.data?.has_more === true }")
        && str_contains($page, "page: params.page || 1"),
    '추가 항목은 공용 PickerSelect2가 마지막에 배치한다' => str_contains($page, 'includeCommonAdd: showCommonAdd')
        && str_contains($page, 'quickAddEnabled'),
    '사업구분 추가는 코드 퀵모달을 연다' => str_contains($page, "openCodeQuickModal({ codeGroup: 'BUSINESS_UNIT'"),
    '프로젝트는 원본 퀵모달이 없어 추가항목만 비활성 표시한다' => str_contains($page, "groupPicker(project, 'project', group, false, true)")
        && !str_contains($page, 'openProjectQuickCreate')
        && !str_contains($projectPartial, 'projectQuickModal'),
    '팀 추가는 팀 퀵모달을 연다' => str_contains($page, 'openWorkTeamQuickCreate({'),
    '작업자는 작업자카드의 공용 PickerSelect2를 사용한다' => str_contains($grid, 'PickerSelect2.create')
        && str_contains($grid, "'작업자 *'"),
    '거래처 원본 퀵모달 DOM을 재사용한다' => str_contains($view, "partials/client_modal.php"),
    '팀 원본 퀵모달 DOM을 재사용한다' => str_contains($view, "partials/work-team_modal.php"),
    '코드관리 원본 퀵모달 DOM을 재사용한다' => str_contains($view, "system/partials/code_modal.php"),
    '중첩 원본모달 입력은 부모 FocusTrap에 막히지 않는다' => str_contains($page, 'bootstrap.Modal.getOrCreateInstance(modalElement, { focus: false })'),
    '팀 퀵모달은 개별 DOM을 동적 생성하지 않는다' => str_contains($teamQuick, "document.getElementById('workTeamQuickModal')")
        && !str_contains($teamQuick, 'wrapper.innerHTML'),
    '팀 원본 퀵모달의 상세입력은 원본 상세모달로 이어진다' => str_contains($teamQuick, 'openOriginalWorkTeamDetail(values)')
        && str_contains($teamQuick, 'createWorkTeamModalModule({')
        && str_contains($teamQuick, 'detailButton.hidden = false'),
    '코드관리 외부 호출도 원본 시스템정보와 collapse 런타임을 사용한다' => str_contains($codeSelect, 'initCodeModalRuntime(modalEl)')
        && str_contains($codeSelect, 'renderCodeSystemInfo(data)')
        && str_contains($codeRuntime, 'bindModalCardCollapses(modalElement, { resetOnShow: true })'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "사업구분·소속팀·작업자는 선택(없음)→목록→+ 추가 공용 Select2 계약을 사용합니다.\n";
