<!-- 📄 _sidebar_right_edit.php -->
<!-- 수정 -->
<aside id="task-panel" class="calendar-right-panel right-edit-panel">

  <div class="task-topbar">
    <button id="btn-task-edit-back" class="task-close">
      <i class="bi bi-arrow-left"></i>
    </button>
    <div style="font-weight:900">작업 편집</div>
    <span style="width:34px"></span>
  </div>

  <div class="task-edit-form">

    <label class="task-form-label">작업 이름</label>
    <input id="task-edit-title" type="text" class="task-input">

    <label class="task-form-label">마감 시간</label>
    <input id="task-edit-due" type="text" class="task-input" placeholder="기한 없음" readonly>


    <label class="task-form-label">작업 목록</label>
    <div class="tasklist-selector">
      <button type="button"
              id="tasklist-btn-edit"
              class="task-input tasklist-btn">
        <span class="tasklist-color"></span>
        <span class="tasklist-name">선택</span>
      </button>

      <input type="hidden" id="task-edit-list">

      <!-- 🔥 여기로 이동 -->
      <div id="tasklist-dropdown-edit"
          class="tasklist-dropdown is-hidden"></div>
    </div>


    <label class="task-form-label">설명</label>
    <textarea id="task-edit-desc" class="task-textarea"></textarea>

    <div class="task-edit-actions">
      <button id="task-edit-save" class="btn-primary">저장</button>
    </div>

  </div>

</aside>
