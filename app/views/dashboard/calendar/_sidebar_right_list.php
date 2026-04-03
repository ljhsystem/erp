<!-- 📄 /app/views/dashboard/calendar/_sidebar_right_list.php -->
<!-- 리스트 -->
<aside id="right-list-panel"
       class="calendar-right-panel right-list-panel">

  <!-- =========================
       Topbar
  ========================== -->
  <div class="right-list-topbar">

    <!-- 완료일 필터 트리거 -->
    <button
      id="right-list-btn-due"
      type="button"
      class="right-list-dd-trigger"
      aria-haspopup="true"
      aria-expanded="false"
    >
      <span class="right-list-dd-label">오늘</span>
      <i class="bi bi-caret-down-fill"></i>
    </button>

    <!-- 닫기 -->
    <button
      id="right-list-btn-close"
      type="button"
      class="right-list-close"
      aria-label="닫기"
    >
      <i class="bi bi-x-lg"></i>
    </button>

    <!-- =========================
         Dropdown
    ========================== -->
    <div id="right-list-dd" class="right-list-dd is-hidden">

      <!-- 완료일 -->
      <div class="right-list-dd-group-title">작업 만료일</div>

      <button
        type="button"
        class="right-list-dd-item is-active"
        data-filter="today"
      >
        <i class="bi bi-check2"></i>
        <span>오늘 <em>(0)</em></span>
      </button>

      <button
        type="button"
        class="right-list-dd-item"
        data-filter="next7"
      >
        <i class="bi bi-check2"></i>
        <span>다음 7일 <em>(0)</em></span>
      </button>

      <!-- 특정 날짜 -->
      <div
        class="right-list-dd-item has-submenu"
        data-filter="date"
      >
        <div class="right-list-dd-item-row">
          <span>특정 날짜</span>
          <i class="bi bi-chevron-right"></i>
        </div>

        <div class="right-list-dd-submenu is-hidden">
          <button
            type="button"
            id="right-list-btn-date-open"
            class="right-list-date-open"
          >
            <i class="bi bi-calendar3"></i>
            <span>날짜 선택</span>
          </button>
        </div>
      </div>

      <hr class="right-list-dd-sep">

      <!-- 작업 목록 -->
      <div class="right-list-dd-group-title">작업 목록</div>
      <!-- 🔥 이 줄 추가 -->
      <div class="task-dd-lists"></div>
      <!-- JS 렌더링 -->
      <hr class="right-list-dd-sep">

      <button
        type="button"
        class="right-list-dd-item right-list-dd-cta"
        id="right-list-btn-list-create"
      >
        작업 목록 생성
      </button>
    </div>
  </div>














  <!-- =========================
       Add Task
  ========================== -->
  <div class="right-list-add">
  <div class="right-list-add-wrap">
    <input
      id="right-list-add-input"
      type="text"
      placeholder="작업 추가"
      autocomplete="off"
    >

    <!-- 🔥 추가 버튼 -->
    <button
      id="right-list-add-btn"
      type="button"
      class="right-list-add-btn is-hidden"
      aria-label="작업 추가"
    >
      <i class="bi bi-plus-lg"></i>
    </button>
  </div>
</div>

  <!-- =========================
       Meta chips
  ========================== -->
  <div class="right-list-meta">
    <span class="right-list-chip" id="right-list-meta-date-chip">
      <i class="bi bi-calendar3"></i>
      <span id="filter-meta-date"></span>
    </span>

    <!-- 목록 선택 -->
    <span class="right-list-chip" id="right-list-meta-list-chip">
      <i class="bi bi-inbox"></i>
      <span id="right-list-meta-list"></span>
      <i
        class="bi bi-caret-down-fill"
        style="font-size:10px;opacity:.6"
      ></i>
    </span>
  </div>

  <!-- 작업목록 선택 드롭다운 -->
  <div id="right-list-list-dd" class="right-list-dd is-hidden">
    <div class="right-list-dd-group-title">작업 목록</div>
          <!-- 🔥 이 줄 추가 -->
          <div class="task-dd-lists"></div>
    <!-- JS에서 동적 생성 -->
  </div>















  <!-- =========================
       Task list
  ========================== -->
  <div class="right-list-task-list">
    <div class="right-list-empty">
      <div class="right-list-empty-icon">
        <i class="bi bi-file-earmark-text"></i>
      </div>
      <div class="right-list-empty-text">작업 없음</div>
    </div>
  </div>







  

</aside>