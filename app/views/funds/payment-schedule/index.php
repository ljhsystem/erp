<?php

use Core\Helpers\AssetHelper;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$layoutOptions = ['header' => true, 'navbar' => true, 'sidebar' => true, 'footer' => false, 'wrapper' => 'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/funds/payment-schedule/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/funds/payment-schedule/index.js');
$options = $filterOptions ?? [];
?>
<main class="payment-schedule-page" id="paymentSchedulePage">
    <div class="container-fluid py-3">
        <header class="payment-schedule-heading">
            <div>
                <h5><i class="bi bi-calendar-range me-2"></i>지급예정현황 <span class="text-primary" id="paymentScheduleCount"></span></h5>
                <p>지급의무와 실제 은행 출금의 연결 상태, 잔여 지급액과 연체 현황을 관리합니다.</p>
            </div>
            <div class="payment-schedule-actions">
                <button class="btn btn-outline-secondary btn-sm" id="paymentScheduleRefresh"><i class="bi bi-arrow-clockwise me-1"></i>새로고침</button>
                <button class="btn btn-outline-success btn-sm" id="paymentScheduleExcel"><i class="bi bi-file-earmark-excel me-1"></i>엑셀</button>
                <button class="btn btn-outline-secondary btn-sm" id="paymentSchedulePrint"><i class="bi bi-printer me-1"></i>인쇄</button>
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#paymentScheduleTrashModal"><i class="bi bi-trash3 me-1"></i>휴지통</button>
            </div>
        </header>

        <section class="payment-schedule-summary" aria-label="지급예정 요약">
            <?php foreach ([
                'scheduled_amount' => '지급예정액',
                'paid_amount' => '기지급액',
                'remaining_amount' => '잔여액',
                'overdue_remaining_amount' => '연체 잔여액',
                'hold_remaining_amount' => '보류 잔여액',
            ] as $key => $label): ?>
                <article><span><?= $label ?></span><strong data-summary="<?= $key ?>">0</strong><small>원</small></article>
            <?php endforeach; ?>
        </section>

        <form class="payment-schedule-filter" id="paymentScheduleFilter">
            <label><span>지급예정일 시작</span><input class="form-control form-control-sm" type="date" name="date_from" value="<?= $escape($_GET['date_from'] ?? '') ?>"></label>
            <label><span>지급예정일 종료</span><input class="form-control form-control-sm" type="date" name="date_to" value="<?= $escape($_GET['date_to'] ?? '') ?>"></label>
            <label><span>상태</span><select class="form-select form-select-sm" name="payment_status"><option value="">전체</option><option value="WAITING">지급대기</option><option value="PARTIAL">일부지급</option><option value="COMPLETED">지급완료</option><option value="OVERDUE">연체</option><option value="ON_HOLD">지급보류</option><option value="REVIEW_REQUIRED">검토필요</option><option value="CANCELLED">취소</option></select></label>
            <?php foreach (['clients' => '거래처', 'projects' => '프로젝트', 'assignees' => '담당자', 'bank_accounts' => '지급계좌', 'source_types' => '원천유형'] as $key => $label): ?>
                <?php $field = ['clients'=>'client_id','projects'=>'project_id','assignees'=>'assignee_id','bank_accounts'=>'payment_bank_account_id','source_types'=>'source_type'][$key]; ?>
                <label><span><?= $label ?></span><select class="form-select form-select-sm" name="<?= $field ?>"><option value="">전체</option><?php foreach (($options[$key] ?? []) as $item): ?><option value="<?= $escape($item['id'] ?? '') ?>"><?= $escape($item['name'] ?? '') ?></option><?php endforeach; ?></select></label>
            <?php endforeach; ?>
            <label class="payment-schedule-keyword"><span>검색어</span><input class="form-control form-control-sm" type="search" name="q" placeholder="원천 ID, 메모, 거래처, 프로젝트, 계좌명"></label>
            <div class="payment-schedule-filter-buttons"><button class="btn btn-outline-secondary btn-sm" type="reset">초기화</button><button class="btn btn-primary btn-sm" type="submit">조회</button></div>
        </form>

        <?php
        $tableId = 'paymentScheduleTable';
        $ajaxUrl = '/api/funds/payment-schedule/list';
        $columnsType = 'paymentSchedule';
        $enableButtons = false;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = false;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</main>

<?php include __DIR__ . '/partials/form-modal.php'; ?>
<?php include __DIR__ . '/partials/detail-modal.php'; ?>

<?php
$modalId = 'paymentScheduleTrashModal';
$type = 'paymentSchedule';
$modalTitle = '지급예정 휴지통';
$tableId = 'payment-schedule-trash-table';
$checkAllId = 'paymentScheduleTrashCheckAll';
$tableHead = '<th>지급예정일</th><th>원천</th><th>거래처</th><th class="text-end">지급예정액</th><th>삭제일시</th><th width="90">관리</th>';
$emptyMessage = '휴지통에 삭제된 지급예정이 없습니다.';
$listUrl = '/api/funds/payment-schedule/trash';
$restoreUrl = '/api/funds/payment-schedule/restore';
$deleteUrl = '';
$deleteAllUrl = '';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>
