// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/topbar.js'

(() => {
  'use strict';
  console.log('[topbar] loaded');

  window.__CAL_FILTER_STATE__ = window.__CAL_FILTER_STATE__ || {
    keyword: '',
    calendars: [],
    colors: [],
    from: '',
    to: ''
  };

  window.__CAL_LIVE_KEYWORD__ = '';


  const ADMIN_EVENT_COLORS = [
    { value:'#CC4E4E', name:'장미' },
    { value:'#D67240', name:'파파야' },
    { value:'#DB8F2C', name:'탠저린' },
    { value:'#E0A416', name:'망고' },
    { value:'#E0BB00', name:'레몬' },
    { value:'#ADB204', name:'올리브' },
    { value:'#92A822', name:'라임' },
    { value:'#75A828', name:'잔디' },
    { value:'#49A33B', name:'숲' },
    { value:'#1C9963', name:'비취' },
  
    { value:'#008E9E', name:'아쿠아' },
    { value:'#0877CC', name:'하늘색' },
    { value:'#3267D1', name:'데님' },
    { value:'#5555E0', name:'인디고' },
    { value:'#8153DB', name:'라벤더' },
    { value:'#9E4CC7', name:'라일락' },
    { value:'#B240AF', name:'말로우' },
    { value:'#C24279', name:'동백' },
  
    { value:'#6E6E6E', name:'연기' },
    { value:'#666F80', name:'철' },
  
    { value:null, name:'투명' }   // 🔥 중요
  ];







  document.addEventListener('DOMContentLoaded', () => {
    bindSearchInput();
    bindFilterButtons();
    bindFilterToggle();
    bindSidebarToggles();

    bindTopbarDatePickers();

    bindMultiSelect();
    function bindMultiSelect() {

      const multiSelects = document.querySelectorAll('.multi-select');

      multiSelects.forEach(el => {

        el.addEventListener('click', e => {
          e.stopPropagation();

          const isOpen = el.classList.contains('open');

          // 🔥 전부 닫기
          multiSelects.forEach(ms => ms.classList.remove('open'));

          // 🔥 현재만 열기
          if (!isOpen) {
            el.classList.add('open');
          }
        });

      });

      // 🔥 바깥 클릭 시 전부 닫기
      document.addEventListener('click', () => {
        multiSelects.forEach(ms => ms.classList.remove('open'));
      });
    }

    bindEnterSearch();
    bindUserCard();

  });

  document.addEventListener('calendar:ready', () => {
    buildCalendarFilterList();
    buildColorFilterList();
  });

  // =========================================================
// 📅 Topbar TodayPicker (EventEditModal 패턴 그대로)
// =========================================================

let topbarTodayPicker = null;

function ensureTopbarTodayPicker() {
  if (topbarTodayPicker) return topbarTodayPicker;

  const container = document.getElementById('today-picker');
  if (!container) return null;

  if (!window.AdminPicker) {
    console.error('AdminPicker not loaded');
    return null;
  }

  topbarTodayPicker = window.AdminPicker.create({
    type: 'today',
    container
  });

  topbarTodayPicker.subscribe((_, date) => {

    const input = topbarTodayPicker.__target;
    if (!input || !date) return;
  
    const pad = n => String(n).padStart(2, '0');
    const formatted =
      `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`;
  
    input.value = formatted;
  
    if (input.id === 'filter-from') {
      window.__CAL_FILTER_STATE__.from = formatted;
    }
  
    if (input.id === 'filter-to') {
      window.__CAL_FILTER_STATE__.to = formatted;
    }
  
    // 🔥 여기 핵심
    enforceDateRule(input.id);
  
    topbarTodayPicker.close?.();
  });

  return topbarTodayPicker;
}

function enforceDateRule(changedField) {

  const fromInput = document.getElementById('filter-from');
  const toInput   = document.getElementById('filter-to');

  if (!fromInput || !toInput) return;

  const fromVal = fromInput.value;
  const toVal   = toInput.value;

  if (!fromVal || !toVal) return;

  const fromDate = new Date(fromVal);
  const toDate   = new Date(toVal);

  if (fromDate > toDate) {

    if (changedField === 'filter-from') {

      // 🔥 시작일을 앞으로 밀었으면 → 종료일 맞춤
      toInput.value = fromVal;
      window.__CAL_FILTER_STATE__.to = fromVal;

    } else if (changedField === 'filter-to') {

      // 🔥 종료일을 뒤로 밀었으면 → 시작일 맞춤
      fromInput.value = toVal;
      window.__CAL_FILTER_STATE__.from = toVal;
    }
  }
}

function bindTopbarDatePickers() {
  const inputs = [
    document.getElementById('filter-from'),
    document.getElementById('filter-to')
  ];

  inputs.forEach(input => {
    if (!input) return;

    input.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
    
      const picker = ensureTopbarTodayPicker();
      if (!picker) return;
    
      picker.__target = input;
    
      // 🔥 핵심 추가
      picker.setDate(null);   // 내부 선택 초기화
    
      // 기존 값 있으면 동기화
      if (input.value) {
        const d = new Date(input.value);
        if (!isNaN(d)) picker.setDate(d);
      }
    
      picker.open({ anchor: input });
    });
  });
}

  function debounce(fn, delay) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), delay);
    };
  }

  // =========================================================
  // 🔍 Search / Filter
  // =========================================================
  function bindSearchInput() {

    const input = document.getElementById('calendar-search');
    if (!input) return;
  
    input.addEventListener('input', debounce((e) => {
  
      // 🔥 실시간 전용 키워드
      const value = e.target.value.trim();

      window.__CAL_FILTER_STATE__.keyword = value;
      window.__CAL_LIVE_KEYWORD__ = value.toLowerCase();
      
      // 🔥 입력이 완전히 비워지면 캐시 제거
      if (!value) {
        window.__CAL_LAST_FETCH__ = null;
      }
      
      triggerCalendarRefetch();
  
    }, 250));
  }

  function collectFilterValues() {

    const calendars = Array.from(
      document.querySelectorAll('#calendar-selected .multi-tag')
    ).map(t => t.dataset.value);
  
    const colors = Array.from(
      document.querySelectorAll('#color-selected .multi-tag')
    ).map(t => t.dataset.value);
  
    const keyword = document.getElementById('filter-keyword')?.value.trim() || '';
    const from = document.getElementById('filter-from')?.value || '';
    const to = document.getElementById('filter-to')?.value || '';
  
    window.__CAL_FILTER_STATE__ = {
      calendars,
      colors,
      keyword,
      from,
      to
    };
  }


  function bindEnterSearch() {

    document.addEventListener('keydown', function (e) {
  
      if (e.key !== 'Enter') return;
  
      const active = document.activeElement;
      if (!active) return;
  
      const id = active.id;
  
      if (
        id === 'calendar-search' ||
        id === 'filter-keyword' ||
        id === 'filter-from' ||
        id === 'filter-to'
      ) {
        e.preventDefault();
      
        // 🔥 calendar-search → filter-keyword 동기화
        if (id === 'calendar-search') {
          const mainInput = document.getElementById('calendar-search');
          const filterInput = document.getElementById('filter-keyword');
      
          if (mainInput && filterInput) {
            filterInput.value = mainInput.value;
          }
        }
      
        executeSearch();
      }
  
    });
  
  }


  function bindFilterButtons() {

    const applyBtn = document.getElementById('btn-filter-apply');
    if (applyBtn) {
      applyBtn.addEventListener('click', executeSearch);
    }
    
    const resetBtn = document.getElementById('btn-filter-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        resetFiltersUI();
        window.__CAL_FILTER_STATE__ = {
          keyword: '',
          calendars: [],
          colors: [],
          from: '',
          to: ''
        };
        triggerCalendarRefetch();
      });
    }
  }
  function triggerCalendarRefetch() {

    const cal = window.__calendar;
    if (!cal) return;
  
    cal.refetchEvents();
  }

  function bindFilterToggle() {
    const toggleBtn = document.getElementById('btn-search-filter');
    const panel = document.getElementById('calendar-search-filters');
    if (!toggleBtn || !panel) return;

    toggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
    
      const isHidden = panel.classList.contains('is-hidden');
    
      panel.classList.toggle('is-hidden');
    
    });
    document.addEventListener('click', (e) => {
      if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
        panel.classList.add('is-hidden');
      }
    });
  }
  
// =========================================================
// 📐 Sidebar Toggle (Topbar 책임) — FINAL
// =========================================================
function bindSidebarToggles() {
  const shell = document.querySelector('.calendar-shell');
  if (!shell) return;

  /* ==========================
     좌측 사이드바 토글
  ========================== */
  const btnLeft = document.getElementById('btn-global-sidebar');

  btnLeft?.addEventListener('click', () => {
    shell.classList.toggle('left-collapsed');

    requestAnimationFrame(() => {
      window.__calendar?.updateSize();
    });
  });

  /* ==========================
     우측 사이드바 토글
  ========================== */
  const btnRight  = document.getElementById('btn-task-panel');
  const listPanel = document.getElementById('right-list-panel');
  const editPanel = document.getElementById('task-panel');

  btnRight?.addEventListener('click', () => {
    const isClosed = shell.classList.contains('right-collapsed');

    if (isClosed) {
      // 🔓 우측 사이드바 열기 → 리스트 패널
      shell.classList.remove('right-collapsed');

      listPanel?.classList.add('is-open');
      editPanel?.classList.remove('is-open');
    } else {
      // 🔒 우측 사이드바 닫기 → 전부 숨김
      shell.classList.add('right-collapsed');

      listPanel?.classList.remove('is-open');
      editPanel?.classList.remove('is-open');
    }

    requestAnimationFrame(() => {
      window.__calendar?.updateSize();
    });
  });
}


function buildCalendarFilterList() {

  const container   = document.getElementById('filter-calendar-list');
  const selectedBox = document.getElementById('calendar-selected');
  if (!container) return;

  container.innerHTML = '';
  selectedBox.innerHTML = '';

  const calendars = window.CalendarContext?.calendars || [];

  calendars.forEach(cal => {

    const calendarId = String(cal.calendar_id || cal.id);

    const calendarColor =
      cal.admin_calendar_color ||
      CalendarStore.getCalendarColor?.(calendarId) ||
      cal.color ||
      cal.calendar_color ||
      '#9CA3AF';

    const calendarName =
      cal.name ||
      cal.calendar_name ||
      cal.displayname ||
      '캘린더';

    const row = document.createElement('label');
    row.className = 'filter-item';

    row.innerHTML = `
      <input type="checkbox" value="${calendarId}">
      <span class="cal-color-box" style="background:${calendarColor}"></span>
      <span>${calendarName}</span>
    `;

    const checkbox = row.querySelector('input');

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        addTag(
          selectedBox,
          calendarName,
          calendarId,
          calendarColor,
          'calendar'
        );
      } else {
        removeTag(selectedBox, calendarId);
      }
    });

    container.appendChild(row);
  });
}

function buildColorFilterList() {

  const container   = document.getElementById('filter-color-list');
  const selectedBox = document.getElementById('color-selected');
  if (!container) return;

  container.innerHTML = '';
  selectedBox.innerHTML = '';

  ADMIN_EVENT_COLORS.forEach(c => {

    const row = document.createElement('label');
    row.className = 'filter-item';

    const colorDot = c.value
      ? `<span class="event-color-dot" style="background:${c.value}"></span>`
      : `<span class="event-color-dot" style="background:transparent;border:1px solid #ccc;"></span>`;

    row.innerHTML = `
      <input type="checkbox" value="${c.value === null ? 'null' : c.value}">
      ${colorDot}
      <span>${c.name}</span>
    `;

    const checkbox = row.querySelector('input');

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        addTag(
          selectedBox,
          c.name,
          c.value ?? 'null',
          c.value ?? 'transparent',
          'color'
        );
      } else {
        removeTag(selectedBox, c.value ?? 'null');
      }
    });

    container.appendChild(row);
  });
}

function addTag(container, label, value, color, type) {

  if (container.querySelector(`[data-value="${value}"]`)) return;

  const tag = document.createElement('div');
  tag.className = 'multi-tag';
  tag.dataset.value = value;

  tag.innerHTML = `
    <span style="background:${color};width:8px;height:8px;border-radius:50%;display:inline-block;"></span>
    ${label}
    <span class="remove">×</span>
  `;

  tag.querySelector('.remove').addEventListener('click', e => {
    e.stopPropagation();
    removeTag(container, value);

    // 체크박스 해제 (안전 방식)
    const input = document.querySelector(`input[value="${value}"]`);
    if (input) {
      input.checked = false;
    }
  });

  container.appendChild(tag);

  updateFilterState();
}

function removeTag(container, value) {
  const tag = container.querySelector(`[data-value="${value}"]`);
  if (tag) tag.remove();
  updateFilterState();
}



function executeSearch() {

// 🔥 keyword 강제 동기화
const mainInput = document.getElementById('calendar-search');
const filterInput = document.getElementById('filter-keyword');

if (mainInput && mainInput.value.trim()) {
  window.__CAL_FILTER_STATE__.keyword = mainInput.value.trim();
}

  collectFilterValues();

  // 🔥 검색 키워드 양방향 동기화
if (mainInput && filterInput) {
  mainInput.value = filterInput.value;
}

  // 🔥 실시간 검색 끄고 검색모드 진입
  window.__CAL_LIVE_KEYWORD__ = '';
  window.__CAL_SEARCH_MODE__ = true;

  const cal = window.__calendar;

  const backBtn = document.getElementById('btn-search-back');
  if (backBtn) {
    backBtn.classList.remove('is-hidden');
  }

  if (cal) {

    // 🔥 현재 뷰 저장 (뒤로가기용)
    if (!window.__CAL_PREV_VIEW__) {
      window.__CAL_PREV_VIEW__ = cal.view.type;
    }

    // 🔥 검색결과는 listMonth로 전환
    if (cal.view.type !== 'listMonth') {
      cal.changeView('listMonth');
    }

    // 🔥 검색조건 반영하여 다시 로딩
    cal.refetchEvents();
  }

  renderSearchHeader();

  // 🔥 검색 패널 닫기
  const panel = document.getElementById('calendar-search-filters');
  if (panel) {
    panel.classList.add('is-hidden');
  }

  // 🔥 열려있는 드롭 닫기
  document
    .querySelectorAll('.multi-select.open')
    .forEach(el => el.classList.remove('open'));

  window.updateSearchButtonState?.();
}



function updateFilterState() {
  collectFilterValues();
}

function resetFiltersUI() {

  // 1️⃣ 키워드 초기화
  const keywordInput = document.getElementById('filter-keyword');
  if (keywordInput) keywordInput.value = '';

  // 2️⃣ 날짜 초기화
  const fromInput = document.getElementById('filter-from');
  const toInput   = document.getElementById('filter-to');

  if (fromInput) fromInput.value = '';
  if (toInput)   toInput.value   = '';

  // 3️⃣ 태그 제거
  const calendarSelected = document.getElementById('calendar-selected');
  const colorSelected    = document.getElementById('color-selected');

  if (calendarSelected) calendarSelected.innerHTML = '';
  if (colorSelected)    colorSelected.innerHTML    = '';

  // 4️⃣ 체크박스 전부 해제
  document
    .querySelectorAll('#filter-calendar-list input[type="checkbox"], #filter-color-list input[type="checkbox"]')
    .forEach(cb => cb.checked = false);

  // 5️⃣ 상태값 초기화
  window.__CAL_FILTER_STATE__ = {
    keyword: '',
    calendars: [],
    colors: [],
    from: '',
    to: ''
  };

  // 6️⃣ 실시간 키워드도 초기화
  window.__CAL_LIVE_KEYWORD__ = '';

// 🔥 검색 입력창도 초기화
const mainSearchInput = document.getElementById('calendar-search');
if (mainSearchInput) mainSearchInput.value = '';
}


function exitSearchMode() {

  const cal = window.__calendar;
  if (!cal) return;

  window.__CAL_SEARCH_MODE__ = false;

  resetFiltersUI();

  // 🔥 검색 캐시 제거 (핵심)
  window.__CAL_LAST_FETCH__ = null;

  // 오버레이 제거
  const empty = document.getElementById('calendar-empty-state');
  if (empty) empty.remove();

  window.updateSearchButtonState?.();

  // 🔥 여기서 refetch 반드시 필요
  cal.refetchEvents();

  renderSearchHeader();

// 🔥 검색 입력창 초기화
const mainSearchInput = document.getElementById('calendar-search');
if (mainSearchInput) mainSearchInput.value = '';

}

function renderSearchHeader() {

  const header = document.getElementById('calendar-view-header');
  if (!header) return;

  if (!window.__CAL_SEARCH_MODE__) {
    header.innerHTML = '';
    header.classList.remove('is-search-mode');
    return;
  }

  const state = window.__CAL_FILTER_STATE__ || {};
  const count = window.__CAL_LAST_RESULT_COUNT__ ?? 0;

  const parts = [];

  if (state.keyword) {
    parts.push(`"${state.keyword}"`);
  }

  if (state.colors?.length) {
    parts.push(`색상 ${state.colors.length}개`);
  }

  if (state.from || state.to) {
    parts.push(`${state.from || '전체'} ~ ${state.to || '전체'}`);
  }

  const conditionText = parts.join(' · ') || '전체 조건';

  header.classList.add('is-search-mode');

  header.innerHTML = `
  <div class="search-header-inner">

    <button id="btn-search-back-inline" class="search-back-btn">
      ← 검색결과 닫기
    </button>

    <div class="search-info">
        <div class="search-title">
          ${count === 0 ? '검색 결과 없음' : `검색 결과 ${count}건`}

          <div class="search-info-wrapper">
            <button 
              id="search-info-toggle" 
              class="search-info-btn"
              type="button"
            >
              i
            </button>

            <div class="search-info-box" id="search-info-box">
              일치하는 항목이 너무 많으면 일부 결과가 누락될 수 있습니다.<br>
              날짜 범위를 줄이거나 구체적인 키워드를 사용하세요.
            </div>
          </div>
        </div>

      <div class="search-meta">
        ${conditionText}
      </div>

    </div>
  </div>
`;

  document
    .getElementById('btn-search-back-inline')
    ?.addEventListener('click', exitSearchMode);

  //검색결과닫기옆에 정보아이콘추가
  const btn = document.getElementById('search-info-toggle');
  const box = document.getElementById('search-info-box');
  
  if (btn && box) {
  
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
  
      const isOpen = box.style.display === 'block';
  
      box.style.display = isOpen ? 'none' : 'block';
      btn.setAttribute('aria-expanded', !isOpen);
    });
  
    // 외부 클릭 시 닫기
    document.addEventListener('click', () => {
      box.style.display = 'none';
      btn.setAttribute('aria-expanded', false);
    });
  }
}


function bindUserCard() {

  const btn  = document.getElementById('btn-user');
  const card = document.getElementById('topbar-user-card');

  if (!btn || !card) return;

  btn.addEventListener('click', async (e) => {
    e.stopPropagation();

    const isHidden = card.classList.contains('is-hidden');

    if (!isHidden) {
      card.classList.add('is-hidden');
      return;
    }

    await loadUserCard();

    card.classList.remove('is-hidden');
  });

  // 외부 클릭 시 닫기
  document.addEventListener('click', (e) => {
    if (!card.contains(e.target) && !btn.contains(e.target)) {
      card.classList.add('is-hidden');
    }
  });
}

async function loadUserCard() {

  const photo = document.getElementById('user-card-photo');
  const name  = document.getElementById('user-card-name');
  const email = document.getElementById('user-card-email');
  const syno  = document.getElementById('user-syno-content');

  try {

    const res = await fetch('/api/dashboard/profile-summary', {
      credentials: 'same-origin'
    });

    const json = await res.json();
    if (!json.success) return;

    const data = json.data;

    name.textContent  = data.user.name || '';
    email.textContent = data.user.email || '';

    const imgUrl =
    data.user.profile_image_url
    || (data.user.profile_image
          ? `/api/file/preview?path=${encodeURIComponent(data.user.profile_image)}`
          : null);
  
    photo.src = imgUrl
      ? `${imgUrl}&v=${Date.now()}`
      : '/public/assets/img/default-profile.png';

    // 🔥 Synology 렌더
    if (!data.synology.connected) {

      syno.innerHTML = `
        <div class="syno-row" style="color:#9ca3af;">
          외부 서비스 연결 없음
        </div>
      `;
    
    } else {
    
      syno.innerHTML = `
        <div class="syno-row"><b>계정:</b> ${data.synology.login_id}</div>
        <div class="syno-row"><b>서버:</b> ${data.synology.host}</div>
        <div class="syno-row"><b>CalDAV:</b> ${data.synology.base_url}</div>
        <button class="copy-btn" id="btn-copy-caldav">
          경로 복사
        </button>
      `;
    
      document
        .getElementById('btn-copy-caldav')
        ?.addEventListener('click', () => {
    
          navigator.clipboard.writeText(
            data.synology.base_url
          );
    
          alert('CalDAV 주소가 복사되었습니다.');
        });
    }

  } catch (e) {
    console.error('profile load error', e);
  }
}







document.getElementById('btn-trash')?.addEventListener('click', () => {
  document.getElementById('modal-trash')?.classList.remove('is-hidden');
  document.body.classList.add('is-modal-open');
});

document.getElementById('btn-trash-close')?.addEventListener('click', () => {
  document.getElementById('modal-trash')?.classList.add('is-hidden');
  document.body.classList.remove('is-modal-open');
});

document.getElementById('btn-trash')
  ?.addEventListener('click', () => {
    window.dispatchEvent(new CustomEvent('trash:open'));
  });

  document.addEventListener('keydown', (e) => {

    if (e.key !== 'Escape') return;
  
    const openMulti = document.querySelector('.multi-select.open');
    const panel = document.getElementById('calendar-search-filters');
  
    // 1️⃣ 드롭이 열려있으면 → 드롭만 닫기
    if (openMulti) {
      openMulti.classList.remove('open');
      return; // 🔥 여기서 종료
    }
  
    // 2️⃣ 드롭이 없고 검색패널 열려있으면 → 패널 닫기
    if (panel && !panel.classList.contains('is-hidden')) {
      panel.classList.add('is-hidden');
    }
  
  });

  document
  .getElementById('btn-search-back')
  ?.addEventListener('click', exitSearchMode);


  document.addEventListener('mousedown', (e) => {
    const picker = document.getElementById('today-picker');
    if (!picker || picker.classList.contains('is-hidden')) return;
  
    if (picker.contains(e.target)) return;
    if (e.target.closest('[data-picker="date"]')) return;
  
    picker.classList.add('is-hidden');
  }, true);


  document.addEventListener('calendar:search:updated', () => {
    if (window.__CAL_SEARCH_MODE__) {
      renderSearchHeader();
    }
  });



})();
