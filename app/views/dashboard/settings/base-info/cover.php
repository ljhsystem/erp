<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/cover.php'
?>

<div class="cover-page" id="cover-main">

    <!-- =========================
         HEADER
    ========================== -->
    <div class="page-header">
        <h5 class="mb-1 fw-bold">🖼️ 커버이미지관리</h5>
        <span id="coverCount" class="text-primary cover-count page-count"></span>
    </div>

    <div class="content-area">

        <?php
        /* =========================================================
           🔥 공통 검색폼 (년도형 커스터마이징)
        ========================================================= */

        $searchId = 'cover';

        $dateOptions = '
            <option value="year">년도</option>
        ';

        $periodButtons = [
            ['key' => 'thisYear', 'label' => '올해'],
            ['key' => 'lastYear', 'label' => '작년'],
            ['key' => '3years', 'label' => '3년'],
            ['key' => '5years', 'label' => '5년'],
            ['key' => '10years', 'label' => '10년'],
        ];

        $dateInputClass = 'form-control form-control-sm year-input';
        $dateInputAttrs = 'autocomplete="off" inputmode="none" readonly';
        $dateStartPlaceholder = '시작년월';
        $dateEndPlaceholder   = '종료년월';

        $searchFieldOptions = '
            <option value="year">년도</option>
            <option value="title">제목</option>
            <option value="alt">ALT</option>
            <option value="description">설명</option>
        ';

        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        ?>

        <?php
        /* =========================================================
           🔥 공통 테이블
        ========================================================= */

        $tableId       = 'cover-table';
        $ajaxUrl       = '/api/settings/base-info/cover/list';
        $columnsType   = 'cover'; // JS에서 매핑
        $enableButtons = true;
        $enableSearch  = false; // 검색폼 따로 있음
        $enablePaging  = true;
        $enableReorder = true;

        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>


        <?php
        /* =========================================================
        🔥 커버이미지 휴지통 모달
        ========================================================= */
        /* 🔥 모달 설정 */
        $modalId      = 'coverTrashModal';
        $type         = 'cover';
        $modalTitle   = '커버이미지 휴지통';

        /* 🔥 테이블 설정 */
        $tableId      = 'cover-trash-table';
        $checkAllId   = 'coverTrashCheckAll';

        /* 🔥 버튼 */
        $btnRestoreId = 'btnRestoreSelectedCover';
        $btnDeleteId  = 'btnDeleteSelectedCover';
        $btnDeleteAll = 'btnDeleteAllCover';

        /* 🔥 컬럼 */
        $tableHead = '
        <th>코드</th>
        <th>이미지</th>
        <th>년도</th>
        <th>제목</th>
        <th>삭제일</th>
        <th>삭제자</th>
        <th>관리</th>
        ';

        /* 🔥 상세 영역 메시지 */
        $emptyMessage = '삭제된 커버이미지를 선택하세요';

        include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
        ?>



    </div>
</div>


<!-- =========================
     모달
========================= -->
<?php include __DIR__ . '/partials/cover_modal.php'; ?>


<!-- =========================
     피커 (공통 유지)
========================= -->
<div class="picker-root">
  <div id="mini-picker" class="picker is-hidden"></div>
  <div id="base-picker" class="picker is-hidden"></div>
  <div id="datetime-picker" class="picker is-hidden"></div>
  <div id="today-picker" class="picker is-hidden"></div>
  <div id="year-month-picker" class="picker is-hidden"></div>
  <div id="time-list-picker" class="picker is-hidden"></div>
</div>
