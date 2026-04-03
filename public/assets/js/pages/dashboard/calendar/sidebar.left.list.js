// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/sidebar.left.list.js'

(() => {
  'use strict';
  console.log('[sidebar.left.list] loaded');
  const BUILD = '2026-01-SIDEBAR-LEFT-LIST';
  if (window.__CAL_SIDEBAR_LEFT_LIST__ === BUILD) return;
  window.__CAL_SIDEBAR_LEFT_LIST__ = BUILD;

  const State = {
    calendars: [],
    tasks: [],
    activeCalendars: new Set(),
    activeTasks: new Set()
  };

  let currentOpenMenu = null;
  let isPaletteOpen = false;
  let __leftDocBound = false;
  let paletteAnchorBtn = null;   // 어떤 버튼 기준으로 열렸는지


  /* ================= Utils ================= */
  const esc = s =>
    String(s ?? '').replace(/[&<>"']/g, m =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])
    );

  const getName = o =>
    o?.name ?? o?.calendar_name ?? o?.displayname ?? o?.title ?? '(이름 없음)';

  const getColor = o =>
    o?.admin_calendar_color || '#666F80';


/* =====================================================
 * Init (🔥 calendar:ready 놓치는 문제 해결)
 * ===================================================== */
function initFromContext() {
  const list = window.CalendarContext?.calendars || [];

  if (!Array.isArray(list) || !list.length) {
    return false;
  }

  State.calendars = list.filter(x => x.type === 'calendar');
  State.tasks = list.filter(x =>
    x.type === 'task' ||
    x.type === 'todo' ||
    x.type === 'VTODO'
  );

  renderCalendars();
  renderTasks();
  bindEvents();
  bindCollapse();
  syncAll();

  return true;
}

// 1️⃣ 즉시 시도 (이미 bootstrap 완료된 경우)
if (!initFromContext()) {
  // 2️⃣ 아직이면 calendar:ready 대기
  document.addEventListener(
    'calendar:ready',
    () => initFromContext(),
    { once: true }
  );
}



  // =====================================================
  // Render
  // =====================================================
  function renderCalendars() {
    const ul = document.getElementById('calendar-list');
    if (!ul) return;

    ul.innerHTML = State.calendars.map(c => {
      const rawId = String(c.calendar_id);
      const name  = getName(c);
      const color = getColor(c);
      CalendarStore.setCalendarColor(rawId, color);
      const key   = `cal:${rawId}`;
      const on = true;;
      

      return buildItemHTML({
        kind: 'cal',
        rawId,
        key,
        name,
        color,
        checked: on,
      });
    }).join('');

    syncCalendars();
  }

  function renderTasks() {
    const ul = document.getElementById('task-list');
    if (!ul) return;
  
    if (!State.tasks.length) {
      ul.innerHTML = `<li class="cal-item is-empty">작업이 없습니다</li>`;
      return;
    }
  
    ul.innerHTML = State.tasks.map(t => {
      const calId = String(t.calendar_id || '');
      const name  = getName(t);

      // 🔥 핵심 수정 부분
      let color = t?.admin_calendar_color;
  
      if (!color) {
        color = CalendarStore.getCalendarColor?.(calId) || '#666F80';
      }

      // 🔥 task도 CalendarStore에 컬러 등록 (핵심)
      CalendarStore.setCalendarColor(calId, color);
  
      const key = `task:${calId}`;
      const on  = true;
  
      return buildItemHTML({
        kind: 'task',
        rawId: calId,
        key,
        name,
        color,
        checked: on,
      });
    }).join('');
  
    syncTasks();
  }






  /* ================= Events ================= */
  function bindEvents() {
    const root = document.getElementById('calendar-left-root');
    if (!root) return;
  
    /* =========================
     * 체크박스
     * ========================= */
    root.addEventListener('change', e => {
      const cb = e.target.closest('.cal-checkbox');
      if (!cb) return;
  
      const li = cb.closest('.cal-item');
      li.classList.toggle('is-checked', cb.checked);
  
      li.dataset.kind === 'cal'
        ? syncCalendars()
        : syncTasks();
    });
  
    /* =========================
     * ⋯ 메뉴 열기/닫기
     * ========================= */
    root.addEventListener('click', e => {
      const btn = e.target.closest('.cal-more');
      if (!btn) return;
        
      const li = btn.closest('.cal-item');
      const menu = li?.querySelector('.cal-menu');
      if (!menu) return;
    
      // 같은 메뉴 재클릭
      if (currentOpenMenu === menu) {
        closeAll();
        return;
      }
    
      closeAll();
    
      menu.classList.remove('is-hidden');
      currentOpenMenu = menu;
    });
  
    root.addEventListener('mouseleave', e => {

      const menu = e.target.closest('.cal-menu');
      if (!menu) return;
    
      setTimeout(() => {
        if (!isPaletteOpen) return;
        hideGlobalPalette();
      }, 150);
    
    });
    
    /* =========================
    * 색상 변경 (HOVER 방식)
    * ========================= */

    root.addEventListener('mouseover', e => {

      const btn = e.target.closest('.cal-menu-item[data-action="color"]');
      if (!btn) return;

      const li = btn.closest('.cal-item');
      if (!li) return;

      const rawId = String(li.dataset.rawId || '');
      const kind  = String(li.dataset.kind || '');

      const currentColor =
        getComputedStyle(li).getPropertyValue('--cal').trim() ||
        (CalendarStore.getCalendarColor?.(rawId) || '#666F80');

      paletteAnchorBtn = btn;

      showGlobalPalette(btn, rawId, kind, currentColor);
    });


    /* =========================
    * 이것만 보기
    * ========================= */
    root.addEventListener('click', e => {
      const btn = e.target.closest('.cal-menu-item[data-action="only"]');
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const li = btn.closest('.cal-item');
      if (!li) return;

      const kind  = li.dataset.kind;
      const rawId = li.dataset.rawId;

      /* 1️⃣ 같은 kind 전부 OFF */
      root.querySelectorAll(`.cal-item[data-kind="${kind}"]`)
        .forEach(item => {
          const cb = item.querySelector('.cal-checkbox');
          if (cb) cb.checked = false;
          item.classList.remove('is-checked');
        });

      /* 2️⃣ 선택한 것만 ON */
      const cb = li.querySelector('.cal-checkbox');
      if (cb) cb.checked = true;
      li.classList.add('is-checked');

      /* 🔥 3️⃣ FullCalendar 동기화 추가 */
      if (kind === 'cal') {
        syncCalendars();
      } else {
        syncTasks();
      }

      /* 4️⃣ 메뉴 닫기 */
      const menu = li.querySelector('.cal-menu');
      if (menu) menu.classList.add('is-hidden');
    });


  
    if (!__leftDocBound) {
      __leftDocBound = true;
    
      document.addEventListener('mousedown', e => {

        if (e.button !== 0) return;
      
        const paletteEl = globalPalette;
        const menuEl = currentOpenMenu;
      
        const clickedInsidePalette =
          paletteEl && paletteEl.contains(e.target);
      
        const clickedInsideMenu =
          menuEl && menuEl.contains(e.target);
      
        const clickedColorButton =
          paletteAnchorBtn && paletteAnchorBtn.contains(e.target);
      
        // 🔥 메뉴/팔레트/색상버튼 외부 클릭이면 닫기
        if (!clickedInsidePalette && !clickedInsideMenu && !clickedColorButton) {
          closeAll();
        }
      
      });
    
      document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
    
        if (isPaletteOpen) {
          hideGlobalPalette();
          return;
        }
    
        if (currentOpenMenu) {
          currentOpenMenu.classList.add('is-hidden');
          currentOpenMenu = null;
        }
      });
    }




  }
  

  /* ================= Sync ================= */
  function syncCalendars() {
    const set = new Set();
    document.querySelectorAll('#calendar-list .cal-item.is-checked')
      .forEach(li => set.add(li.dataset.rawId));
    CalendarStore.setActiveCalendars(set);
  }

  function syncTasks() {
    const set = new Set();
    document.querySelectorAll('#task-list .cal-item.is-checked')
      .forEach(li => set.add(li.dataset.rawId));
    CalendarStore.setActiveTasks?.(set);
  }

  function syncAll() {
    syncCalendars();
    syncTasks();
  }

  function bindCollapse() {
    document.querySelectorAll('.cal-collapse-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const section = btn.closest('.cal-section');
        if (!section) return;
  
        const header = section.querySelector('.cal-collapsible');
        const body   = section.querySelector('.cal-collapsible-body');
  
        if (!header || !body) return;
  
        const isHidden = body.classList.toggle('is-hidden');
  
        // 🔥 핵심: 헤더 상태 동기화
        header.classList.toggle('is-collapsed', isHidden);
      });
    });
  }

  function buildItemHTML({ kind, rawId, key, name, color, checked }) {
    // kind: "cal" | "task"
    return `
      <li class="cal-item ${checked ? 'is-checked' : ''} ${kind === 'task' ? 'is-task' : ''}"
          data-kind="${esc(kind)}"
          data-raw-id="${esc(rawId)}"
          data-key="${esc(key)}"
          style="--cal:${esc(color)}">

        <label class="cal-label">
          <input type="checkbox" class="cal-checkbox" ${checked ? 'checked' : ''}>
          <span class="cal-checkbox-ui"></span>
          <span class="cal-name">${esc(name)}</span>
        </label>

        <button class="cal-more icon-btn" type="button" aria-label="메뉴">
          <i class="bi bi-three-dots-vertical"></i>
        </button>

        <div class="cal-menu is-hidden">
          <div class="cal-menu-row">
            <button class="cal-menu-item"
                    type="button"
                    data-action="color">
              색상 변경
            </button>
          </div>

          <div class="cal-menu-divider"></div>

          <button class="cal-menu-item" type="button" data-action="only">
            이 목록만 표시
          </button>
        </div>
      </li>
    `;
  }
    // =====================================================
  // Palette
  // =====================================================
  function buildColorPalette(current) {
    const COLORS = [
      '#CC4E4E','#D67240','#DB8F2C','#E0A416','#E0BB00',
      '#ADB204','#92A822','#75A828','#49A33B','#1C9963',
      '#008E9E','#0877CC','#3267D1','#5555E0','#8153DB',
      '#9E4CC7','#B240AF','#C24279',
      '#6E6E6E','#666F80'
    ];

    const cur = String(current || '').toUpperCase();

    return `
      <div class="cal-color-grid">
        ${COLORS.map(c => {
          const up = c.toUpperCase();
          return `<button type="button"
                          class="cal-color ${up === cur ? 'is-selected' : ''}"
                          data-color="${c}" style="--c:${c}"></button>`;
        }).join('')}
      </div>
    `;
  }
  

/* =====================================================
 * GLOBAL COLOR PALETTE
 * ===================================================== */
let globalPalette = null;

function createGlobalPalette() {

  if (globalPalette) return globalPalette;

  globalPalette = document.createElement('div');
  globalPalette.id = 'global-color-palette';
  globalPalette.className = 'global-color-palette is-hidden';
  globalPalette.setAttribute('data-role', 'color-palette');

  document.body.appendChild(globalPalette);  // 🔥 body에 붙임

  return globalPalette;
}

function closeAll() {

  if (currentOpenMenu) {
    currentOpenMenu.classList.add('is-hidden');
    currentOpenMenu = null;
  }

  hideGlobalPalette();
}

function showGlobalPalette(triggerBtn, rawId, kind, currentColor) {

  const palette = createGlobalPalette();

  palette.innerHTML = buildColorPalette(currentColor);

  const rect = triggerBtn.getBoundingClientRect();

  palette.style.position = 'fixed';
  palette.style.top  = rect.top + 'px';
  palette.style.left = (rect.right + 8) + 'px';
  palette.style.zIndex = '200000';

  palette.classList.remove('is-hidden');
  isPaletteOpen = true;

  palette.querySelectorAll('.cal-color').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const color = btn.dataset.color;
      applyColorChange(rawId, kind, color);
      closeAll();
    });
  });

  palette.addEventListener('mouseleave', () => {
    hideGlobalPalette();
  });
}

function hideGlobalPalette() {
  if (!globalPalette) return;

  globalPalette.classList.add('is-hidden');
  isPaletteOpen = false;
  paletteAnchorBtn = null;
}

function applyColorChange(rawId, kind, color) {

  // 1) 리스트 UI 반영
  const li = document.querySelector(`.cal-item[data-raw-id="${rawId}"]`);
  if (li) li.style.setProperty('--cal', color);

  // 2) Store 반영 (calendar/task 공통)
  CalendarStore.setCalendarColor(rawId, color);

  // 3) DB 저장 (calendar/task 동일 endpoint 사용)
  fetch('/api/dashboard/calendar/update-admin-color', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({
      calendar_id: rawId,
      admin_calendar_color: color
    })
  }).catch(err => console.error('[ADMIN COLOR SAVE ERROR]', err));

  // 4) FullCalendar 재렌더
  if (window.__calendar) {
    window.__calendar.refetchEvents();
  }
}

window.__CAL_LEFT_DEBUG__ = {
  closeAll,
  hideGlobalPalette,
  showGlobalPalette,
  getPalette: () => globalPalette,
};



})();