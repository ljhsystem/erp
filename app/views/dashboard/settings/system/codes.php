<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/system/codes.php'
?>

<div class="code-page" id="code-main">
    <div class="page-header">
        <h4 class="mb-1 fw-bold">
<i class="bi bi-collection me-2"></i>코드관리
        </h4>
        <span id="codeCount" class="text-primary code-count page-count"></span>
    </div>

    <div class="content-area">
        <?php
        $searchId = 'code';
        $dateOptions = '
            <option value="created_at">등록일시</option>
            <option value="updated_at">수정일시</option>
        ';
        $searchFieldOptions = '<option value="">선택</option>';
        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        ?>

        <?php
        $tableId = 'code-table';
        $ajaxUrl = '/api/settings/system/code/list';
        $columnsType = 'code';
        $enableButtons = true;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = true;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</div>

<?php include __DIR__ . '/partials/code_modal.php'; ?>

<div class="picker-root">
    <div id="today-picker" class="picker is-hidden"></div>
</div>
