<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/main/settings/base-info/partials/work-team_modal.php');
$pageView = file_get_contents($root . '/app/views/main/settings/base-info/work-team.php');
$quick = file_get_contents($root . '/public/assets/js/pages/main/settings/base/work-team/quick-modal.js');
$index = file_get_contents($root . '/public/assets/js/pages/main/settings/base/work-team/index.js');
$controller = file_get_contents($root . '/app/Controllers/Main/Settings/WorkTeamController.php');
$model = file_get_contents($root . '/app/Models/System/WorkTeamModel.php');
$clientModal = file_get_contents($root . '/public/assets/js/pages/main/settings/base/client/modal.js');

$assertions = [
    '팀 빠른 등록 모달이 존재한다' => str_contains($view, 'id="workTeamQuickModal"'),
    '빠른 등록에는 사업구분과 팀명이 있다' => str_contains($view, 'name="business_unit"') && str_contains($view, 'id="workTeamQuickForm"'),
    '상세입력 버튼이 존재한다' => str_contains($view, 'data-role="quick-detail"'),
    '신규등록은 빠른 등록을 연다' => str_contains($index, 'quickModalModule.open()'),
    '상세입력은 기존 상세모달로 전환한다' => str_contains($index, 'modalModule.openCreateModal({ initialValues })'),
    '빠른 저장은 기존 팀 저장 API를 재사용한다' => str_contains($quick, 'api.SAVE'),
    '사업구분이 서버 저장계약에 포함된다' => str_contains($controller, "'business_unit'") && str_contains($model, ':business_unit'),
    '팀 페이지는 코드관리 원본 퀵모달 DOM을 재사용한다' => str_contains($pageView, "system/partials/code_modal.php"),
    '팀장 추가는 거래처 원본 퀵모달 함수를 직접 사용한다' => str_contains($index, "import { openClientQuickCreate } from '/public/assets/js/pages/main/settings/base/client.js'")
        && str_contains($index, 'openClientQuickCreate({'),
    '팀 페이지는 거래처 원본 퀵모달 DOM을 재사용한다' => str_contains($pageView, "partials/client_modal.php"),
    '팀 도메인에는 별도 거래처 퀵모달을 만들지 않는다' => !str_contains($quick, 'clientQuickModal')
        && !str_contains($index, '/api/settings/base-info/client/save'),
    '팀 퀵모달은 원본 partial만 사용하고 동적 복제하지 않는다' => !str_contains($quick, 'wrapper.innerHTML')
        && str_contains($quick, "document.getElementById('workTeamQuickModal')"),
    '외부 화면에서도 상세입력 버튼과 팀 원본 상세모달 전환을 유지한다' => str_contains($quick, 'openOriginalWorkTeamDetail(values)')
        && str_contains($quick, 'createWorkTeamModalModule({')
        && str_contains($quick, 'detailButton.hidden = false'),
    '거래처 원본 퀵모달은 메타를 준비한 뒤 한글 라벨을 사용한다' => str_contains($clientModal, 'await fetchDataTableMetaColumns({ metaDomain: CLIENT_META_DOMAIN })')
        && str_contains($clientModal, "clientFieldLabel('client_name', '거래처명')")
        && !str_contains($clientModal, "clientFieldLabel('client_name', 'Client Name')"),
    '거래처 상세모달은 중첩 모달 최상단에 배치되고 부모 팀 모달을 복원한다' => str_contains($clientModal, 'bringClientDetailModalToFront(modalEl)')
        && str_contains($clientModal, 'captureClientDetailModalContext(modalEl)')
        && str_contains($clientModal, 'restoreClientDetailModalContext(modalEl)')
        && str_contains($clientModal, 'Math.max(20020'),
    '거래처 퀵 상세전환은 원본 상세 초기화와 열기 함수를 사용한다' => str_contains($clientModal, 'initModal();')
        && str_contains($clientModal, "openClientCreateDetailModal({ ...values, is_active: values.is_active || '1' })")
        && str_contains($clientModal, 'bindModalCardCollapses(modalEl, { resetOnShow: true })'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "팀 신규등록은 퀵모달을 열고 상세입력으로 기존 상세모달에 전환됩니다.\n";
