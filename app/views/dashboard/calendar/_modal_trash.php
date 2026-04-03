<!-- 📄 /app/views/dashboard/calendar/_modal_trash.php -->

<div id="modal-trash" class="shint-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="trash-title">
  <div class="shint-modal__card shint-modal__card--large">

    <!-- 헤더 -->
    <header class="shint-modal__head">
      <h3 id="trash-title">휴지통🗑️</h3>
      <button type="button" class="shint-modal__close" id="btn-trash-close">
        <i class="bi bi-x-lg"></i>
      </button>
    </header>

    <!-- 탭 -->
    <div class="trash-tabs-wrap">
      <div class="trash-tabs">
        <button type="button" class="trash-tab is-active" data-type="event">
          <i class="bi bi-calendar-event"></i> 이벤트
        </button>
        <button type="button" class="trash-tab" data-type="task">
          <i class="bi bi-check2-square"></i> 태스크
        </button>
      </div>
    </div>

    <!-- 본문 -->
    <div class="trash-body trash-body-split">

      <!-- 좌측: 목록 -->
      <div class="trash-list-area">

        <!-- 툴바 -->
        <div class="trash-toolbar">
          <div class="trash-toolbar-left">

            <!-- 🔹 선택 복원 -->
            <button id="btn-trash-restore-selected" class="btn btn-outline-success">
              선택 복원
            </button>

            <!-- 🔹 선택 영구삭제 -->
            <button id="btn-trash-delete-selected" class="btn btn-outline-danger">
              선택 영구삭제
            </button>

          </div>

          <div class="trash-toolbar-right">

            <!-- 🔥 전체 영구삭제 -->
            <button id="btn-trash-delete-all" class="btn btn-danger">
              전체 영구삭제
            </button>

          </div>
        </div>

        <!-- 테이블 -->
        <div class="trash-table-wrap">
          <table class="trash-table">
            <thead>
              <tr>
                <th width="40">
                  <input type="checkbox" id="trash-check-all">
                </th>
                <th>제목</th>
                <th width="160">삭제일</th>
                <th width="140">삭제자</th>
                <th width="160">관리</th>
              </tr>
            </thead>
            <tbody id="trash-table-body">
              <tr class="trash-empty-row">
                <td colspan="5">삭제된 항목이 없습니다.</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- 우측: 상세 -->
      <div class="trash-detail-area">

        <div class="trash-detail-empty">
          상세정보
        </div>

        <div class="trash-detail-content is-hidden">

          <h4 class="trash-detail-title"></h4>

          <div class="trash-detail-meta"></div>

          <div class="trash-detail-desc"></div>

          <!-- 🔹 상세 복원/삭제 버튼 -->
          <div class="trash-detail-actions">

            <!-- <button id="btn-trash-restore-single" class="btn btn-success btn-sm">
              복원
            </button>

            <button id="btn-trash-delete-single" class="btn btn-danger btn-sm">
              영구삭제
            </button> -->

          </div>

        </div>

      </div>

    </div>

  </div>
</div>