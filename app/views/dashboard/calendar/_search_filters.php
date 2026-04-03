<!-- 📄 /app/views/dashboard/calendar/_search_filters.php -->



<div id="calendar-search-filters" class="search-filters is-hidden">

  <!-- =========================
       캘린더 필터
  ========================== -->
  <div class="filter-block">

  <div class="filter-guide">
    조건을 선택하여 원하는 일정만 빠르게 검색하세요.
  </div>

  <div class="filter-title">캘린더</div>

  <div class="multi-select" id="calendar-multi">
    <div 
      class="multi-selected"
      id="calendar-selected"
      data-placeholder="여러 개 선택 가능합니다."
    ></div>
    <div class="dropdown" id="filter-calendar-list"></div>
  </div>

</div>

  <!-- =========================
       이벤트 색상 필터
  ========================== -->
  <div class="filter-block">
  <div class="filter-title">이벤트 색상</div>


  <div class="multi-select" id="color-multi">
    <div 
      class="multi-selected"
      id="color-selected"
      data-placeholder="여러 개 선택 가능합니다."
    ></div>
    <div class="dropdown" id="filter-color-list"></div>
  </div>

</div>

  <!-- =========================
       키워드
  ========================== -->
  <div class="filter-block">
    <div class="filter-title">키워드</div>
    <input id="filter-keyword" type="text" placeholder="제목, 설명, 장소 등 키워드를 입력하세요 (예: 회의, 본사)">
  </div>

  <!-- =========================
       기간 (TodayPicker 사용)
  ========================== -->
  <div class="filter-block">
    <div class="filter-title">기간</div>

    <div class="date-range">
      <div class="date-input-wrap">
        <input 
          id="filter-from"
          type="text"
          readonly
          placeholder="시작일 선택"
          data-picker="date"
        >
        <i class="bi bi-calendar3"></i>
      </div>

      <span>~</span>

      <div class="date-input-wrap">
        <input 
          id="filter-to"
          type="text"
          readonly
          placeholder="종료일 선택"
          data-picker="date"
        >
        <i class="bi bi-calendar3"></i>
      </div>
    </div>
    
  </div>

  <div class="filter-actions">
    <button id="btn-filter-reset">재설정</button>
    <button id="btn-filter-apply">검색</button>
  </div>

</div>