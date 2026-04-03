<!-- 📄 /app/views/dashboard/calendar/_modal_task_view.php -->
<div id="modal-task-view"
     class="shint-modal is-hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="task-view-title">

  <div class="shint-modal__card shint-modal__card--view">

    <!-- Header -->
    <header class="shint-modal__head">
      <div class="shint-viewtitle" id="task-view-title"></div>
      <button type="button"
              class="shint-iconbtn"
              data-close="modal"
              aria-label="닫기">×</button>
    </header>

    <!-- Body -->
    <div class="shint-modal__body">
      <div class="shint-viewmeta">

        <!-- 리스트 -->
        <div class="shint-vrow">
          <div class="shint-ico">📥</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="task-view-list"></div>
          </div>
        </div>

        <!-- 마감일 -->
        <div class="shint-vrow">
          <div class="shint-ico">⏰</div>
          <div class="shint-vtext">
            <div class="shint-vsub" id="task-view-due"></div>
          </div>
        </div>

        <!-- 설명 -->
        <div class="shint-vrow">
          <div class="shint-ico">📝</div>
          <div class="shint-vtext">
            <div class="shint-vdesc" id="task-view-desc"></div>
          </div>
        </div>
        
        <!-- 알림 -->
        <div class="shint-vrow" id="task-view-alarm-row" style="display:none;">
          <div class="shint-ico">🔔</div>
          <div class="shint-vtext">
            <div class="shint-vdesc" id="task-view-alarms"></div>
          </div>
        </div>

      </div>
    </div>

    <!-- Footer -->
    <footer class="shint-viewactions shint-task-actions">

      <button type="button"
              class="shint-btn shint-btn--ghost"
              id="task-view-toggle">
        ✔ 완료됨으로 표시
      </button>

      <div class="shint-action-right">

        <button type="button"
                class="shint-iconbtn2"
                id="task-view-edit"
                aria-label="수정">✏️</button>

        <button type="button"
                class="shint-iconbtn2"
                id="task-view-delete"
                aria-label="삭제">🗑️</button>

        <!-- 🔥 추가 -->
        <!-- <button type="button" class="shint-btn shint-btn--danger is-hidden" id="task-view-hard-delete" > 완전 삭제</button> -->

      </div>

    </footer>

  </div>
</div>
