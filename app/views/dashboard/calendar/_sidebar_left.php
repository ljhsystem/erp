<!-- 📄 /app/views/dashboard/calendar/_sidebar_left.php -->
<aside class="calendar-left" id="calendar-left-root">

  <!-- =========================
       🔒 고정 영역
  ========================== -->
  <div class="cal-fixed">

    <!-- 이벤트 / 작업 생성 -->
    <div class="cal-section">
      <div class="split-action" id="create-split">
        <button id="btn-create-event" type="button" class="split-main">이벤트 생성</button>
        <button id="btn-create-menu" type="button" class="split-toggle" aria-label="생성 메뉴">
          <i class="bi bi-caret-down-fill"></i>
        </button>
      </div>

      <div id="create-menu" class="event-create-menu is-hidden" role="menu">
        <button type="button" class="menu-item" data-action="event">+ 이벤트</button>
        <button type="button" class="menu-item" data-action="task">+ 작업</button>
      </div>
    </div>

    <!-- 미니 달력 -->
    <div class="cal-section mini-calendar-section">
      <div class="mini-calendar-scale">
        <div class="mini-calendar-wrap">
          <input type="text" id="mini-calendar-input" readonly>
        </div>
      </div>
    </div>

  </div>

  <!-- =========================
       🔥 스크롤 영역
  ========================== -->
  <div class="cal-scroll">

    <!-- 캘린더 목록 -->
    <div class="cal-section" id="calendar-section">
      <div class="cal-section-title cal-title-row cal-collapsible">
        <button id="btn-calendar-toggle"
                type="button"
                class="icon-btn cal-collapse-btn"
                aria-label="캘린더 접기/펼치기">
          <i class="bi bi-chevron-down"></i>
        </button>
        <span class="cal-title-text">캘린더 목록</span>
      </div>

      <div id="calendar-body" class="cal-collapsible-body">
        <ul id="calendar-list" class="cal-list">
          <!-- JS 렌더링 -->
        </ul>
      </div>
    </div>

    <!-- 작업 목록 -->
    <div class="cal-section" id="task-section">
      <div class="cal-section-title cal-title-row cal-collapsible">
        <button id="btn-task-toggle"
                type="button"
                class="icon-btn cal-collapse-btn"
                aria-label="작업 접기/펼치기">
          <i class="bi bi-chevron-down"></i>
        </button>
        <span class="cal-title-text">작업 목록</span>
      </div>

      <div id="task-body" class="cal-collapsible-body">
        <ul id="task-list" class="cal-list">
          <!-- JS 렌더링 -->
        </ul>
      </div>
    </div>

  </div>
</aside>
