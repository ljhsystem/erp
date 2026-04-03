<header class="calendar-topbar">

  <!-- 좌측 -->
  <div class="topbar-left">
    <button id="btn-global-sidebar" type="button">☰</button>

    <!-- 타이틀 -->
    <div>
      <h5 class="page-title" translate="no">
        <?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?>
      </h5>
      <p translate="no">일정/캘린더.</p>
    </div>  
  </div>

  <!-- 중앙 -->
<div class="topbar-center topbar-search-wrap">
  <div class="calendar-searchbox">
    <input
      id="calendar-search"
      type="search"
      placeholder="검색 (제목, 설명, 장소)"
      autocomplete="off"
    >
    <button id="btn-search-filter" type="button" class="search-icon-btn" aria-label="검색 필터">
      <i class="bi bi-sliders"></i>
    </button>
  </div>

  <?php include __DIR__ . '/_search_filters.php'; ?>
</div>

  <!-- 우측 -->
<div class="topbar-right">
  <button id="btn-task-panel" type="button">
    <i class="bi bi-check-circle"></i>
  </button>

  <button id="btn-trash">
  <i class="bi bi-trash"></i>
</button>

  <button id="btn-user" type="button">
    <i class="bi bi-person-circle"></i>
  </button>
</div>


</header>




<div id="topbar-user-card" class="topbar-user-card is-hidden">
  <div class="user-card-inner">

    <div class="user-card-header">
      <img id="user-card-photo" class="user-photo" src="" alt="">
      <div class="user-meta">
        <div id="user-card-name" class="user-name"></div>
        <div id="user-card-email" class="user-email"></div>
      </div>
    </div>

    <div class="user-card-divider"></div>

    <div class="user-card-section">
      <div class="section-title">Synology Calendar</div>

      <div id="user-syno-content"></div>
    </div>

  </div>
</div>