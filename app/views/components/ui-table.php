<?php
// 경로: PROJECT_ROOT . '/app/views/components/ui-table.php'
// 공통 테이블 컴포넌트

$tableId        = $tableId        ?? 'main-table';
$tableClass     = $tableClass     ?? 'table table-bordered align-middle table-cross-highlight';
$ajaxUrl        = $ajaxUrl        ?? '';
$columnsType    = $columnsType    ?? '';
$enableButtons  = $enableButtons  ?? true;
$enableSearch   = $enableSearch   ?? true;
$enablePaging   = $enablePaging   ?? true;
$enableReorder  = $enableReorder  ?? false;
?>

<div class="table-box"
     data-table-id="<?= $tableId ?>"
     data-ajax="<?= $ajaxUrl ?>"
     data-columns="<?= $columnsType ?>"
     data-buttons="<?= $enableButtons ? '1' : '0' ?>"
     data-search="<?= $enableSearch ? '1' : '0' ?>"
     data-paging="<?= $enablePaging ? '1' : '0' ?>"
     data-reorder="<?= $enableReorder ? '1' : '0' ?>">

    <!-- 상단 toolbar 영역 -->
    <div class="table-toolbar d-flex justify-content-between align-items-center">

        <div class="left-actions">
            <!-- 필요시 버튼 삽입 -->
        </div>

        <div class="right-actions">
            <!-- DataTables가 채움 -->
        </div>

    </div>

    <!-- 테이블: DataTables scrollX가 스크롤 래퍼를 직접 관리한다. -->
    <table id="<?= $tableId ?>"
           class="<?= $tableClass ?>"
           style="width:100%;">

        <thead>
            <tr>
                <!-- JS에서 자동 생성 -->
            </tr>
        </thead>

        <tbody></tbody>

    </table>

    <!-- 하단 영역 -->
    <div class="table-footer d-flex justify-content-between align-items-center">

        <div class="table-info"></div>
        <div class="table-pagination"></div>

    </div>

</div>
