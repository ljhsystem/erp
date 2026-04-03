/**
 * sidebar.left.js
 * ----------------------------------------
 * 역할:
 *  - 캘린더(VEVENT) 목록 렌더
 *  - 작업(Task, VTODO 컬렉션) 목록 렌더 (왼쪽)
 *  - 체크 상태 관리
 *  - 색상 변경 / 단일 표시
 *  - 미니 달력
 */

(() => {
  'use strict';

  const BUILD = '2026-01-SIDEBAR-LEFT-CAL+TASK-FINAL';
  if (window.__CAL_SIDEBAR_LEFT_BUILD__ === BUILD) return;
  window.__CAL_SIDEBAR_LEFT_BUILD__ = BUILD;

  // =====================================================
  // State
  // =====================================================
  const State = {
    calendars: [],
    tasks: [],
    activeCalendars: new Set(), // calendar id들
    activeTasks: new Set(),     // task href들
  };

// =====================================================
// Prefs
// - 체크 상태 저장
// - Task 색상은 "override"만 저장 (기본은 Synology)
// =====================================================
const Prefs = {
  keyEnabled: 'calendar_sidebar_left_enabled_v2',
  data: { enabled: {} },

  load() {
    try {
      const raw = localStorage.getItem(this.keyEnabled);
      if (raw) this.data = JSON.parse(raw);
    } catch {
      this.data = { enabled: {} };
    }
  },

  saveEnabled() {
    try {
      localStorage.setItem(this.keyEnabled, JSON.stringify(this.data));
    } catch {}
  },

  isEnabled(id, def = true) {
    const k = String(id);
    return (k in this.data.enabled) ? !!this.data.enabled[k] : def;
  },

  setEnabled(id, v) {
    const k = String(id);
    this.data.enabled[k] = !!v;
    this.saveEnabled();
  }
};



  // =====================================================
  // Utils
  // =====================================================
  const esc = s =>
    String(s ?? '').replace(/[&<>"']/g, m =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])
    );

  function getName(obj) {
    // ✅ list 응답에서 필드명이 왔다갔다 해서 최대한 흡수
    return obj?.name ?? obj?.calendar_name ?? obj?.displayname ?? obj?.title ?? '(이름 없음)';
  }

  function getColor(obj) {
    return obj?.admin_color || obj?.calendar_color || obj?.color || '#666F80';
  }

  // =====================================================
  // Boot
  // =====================================================
  document.addEventListener('DOMContentLoaded', async () => {
    await loadCalendarList();
    renderCalendars();
    renderTasks();
    bindEvents();             // ✅ 한 번에 위임 처리
    bindLeftSectionToggle();  // ✅ 접기/펼치기
    initMiniCalendar();
    syncActiveAllFromUI();
  });

  // =====================================================
  // Load
  // =====================================================
  async function loadCalendarList() {
    const res = await fetch('/api/dashboard/calendar/list', { credentials: 'same-origin' });
    const json = await res.json();
    if (!json?.success) return;

    const list = json.data || [];

    // ✅ 여기서 캘린더/태스크 둘 다 채움 (핵심!)
    State.calendars = list.filter(x => x.type === 'calendar');
    State.tasks     = list.filter(x => x.type === 'task');

    // 외부에서도 사용 가능하도록 원본을 저장
    window.__CALENDAR_LIST_DATA__ = list;

    // 다른 모듈이 기다릴 수 있으니 이벤트도 쏴줌
    document.dispatchEvent(new CustomEvent('calendar:list:loaded'));
  }

  // =====================================================
  // Render
  // =====================================================
  function renderCalendars() {
    const ul = document.getElementById('calendar-list');
    if (!ul) return;

    ul.innerHTML = State.calendars.map(c => {
      const rawId = String(c.id ?? c.calendar_id ?? '');
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

    syncActiveCalendarsFromUI();
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
  
      // ✅ Synology에서 내려온 색상만 사용
      const color = getColor(t);
  
      const key = `task:${calId}`;
      const on  = Prefs.isEnabled(key, true);
  
      return buildItemHTML({
        kind: 'task',
        rawId: calId,
        key,
        name,
        color,
        checked: on,
      });
    }).join('');
  
    syncActiveTasksFromUI();
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
          <div class="cal-menu-row has-sub">
            <button class="cal-menu-item" type="button">색상 변경</button>
            <i class="bi bi-caret-right-fill"></i>
            <div class="cal-submenu is-hidden">
              ${buildColorPalette(color)}
            </div>
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
  // Events (캘린더/태스크 공통 위임)
  // =====================================================
  function bindEvents() {
    const root = document.getElementById('calendar-left-root') || document.querySelector('.calendar-left');
    if (!root || root.__bound) return;
    root.__bound = true;

    // 체크박스 (캘린더/태스크 모두)
    root.addEventListener('change', (e) => {
      const cb = e.target.closest('.cal-checkbox');
      if (!cb) return;
    
      const li = cb.closest('.cal-item');
      if (!li) return;
    
      const kind = li.dataset.kind;
      const key  = li.dataset.key;    
 
      li.classList.toggle('is-checked', cb.checked);
    
      if (kind === 'cal') {
        syncActiveCalendarsFromUI();
      } else {
        syncActiveTasksFromUI();
      }
    });
    

    // ⋮ 메뉴 열기
    root.addEventListener('click', (e) => {
      const btn = e.target.closest('.cal-more');
      if (!btn) return;

      e.stopPropagation();

      const li = btn.closest('.cal-item');
      const menu = li?.querySelector('.cal-menu');
      if (!menu) return;

      document.querySelectorAll('.cal-menu').forEach(m => {
        if (m !== menu) m.classList.add('is-hidden');
      });

      menu.classList.toggle('is-hidden');
    });

    // 색상 서브메뉴 hover
    root.addEventListener('mouseover', (e) => {
      const row = e.target.closest('.cal-menu-row.has-sub');
      if (!row) return;

      const menu = row.closest('.cal-menu');
      if (!menu || menu.classList.contains('is-hidden')) return;

      menu.querySelectorAll('.cal-submenu').forEach(s => s.classList.add('is-hidden'));
      row.querySelector('.cal-submenu')?.classList.remove('is-hidden');
    });

    // 색상 클릭 (🔥 refetch 절대 금지)
    root.addEventListener('click', (e) => {
      const colorBtn = e.target.closest('.cal-color');
      if (!colorBtn) return;

      e.preventDefault();
      e.stopPropagation();

      const li = colorBtn.closest('.cal-item');
      if (!li) return;

      const kind      = li.dataset.kind;       // cal | task
      const rawId     = li.dataset.rawId;      // calendar_id
      const baseColor = li.dataset.baseColor;  // 🔥 Synology color
      const color     = colorBtn.dataset.color;

      // 1️⃣ 사이드바 UI
      li.style.setProperty('--cal', color);

      // 2️⃣ Store / Prefs
      if (kind === 'cal') {
        CalendarStore.setCalendarColor(rawId, color);
      } else {
        if (color === baseColor) {
          Prefs.removeTaskColor(rawId);        // 🔥 원래 색 → override 제거
          CalendarStore.setTaskColor?.(rawId, null);
        } else {
          Prefs.setTaskColor(rawId, color);
          CalendarStore.setTaskColor?.(rawId, color);
        }
      }

      // 3️⃣ 달력 즉시 반영
      const cal = window.__calendar;
      if (cal) {
        cal.getEvents().forEach(ev => {
          const evCalId = String(ev.extendedProps?.calendar_id || '');
          if (evCalId === String(rawId)) {
            ev.setProp('backgroundColor', color);
            ev.setProp('borderColor', color);
          }
        });
      }

      // 4️⃣ 메뉴 닫기
      closeAllColorMenus();
    });



    // 이 목록만 표시 (캘린더는 캘린더끼리, 태스크는 태스크끼리)
    root.addEventListener('click', (e) => {
      const colorBtn = e.target.closest('.cal-color');
      if (!colorBtn) return;
    
      e.preventDefault();
      e.stopPropagation();
    
      const li = colorBtn.closest('.cal-item');
      if (!li) return;
    
      const rawId = li.dataset.rawId;   // calendar_id
      const color = colorBtn.dataset.color;
    
      // 1️⃣ 사이드바 UI 색상
      li.style.setProperty('--cal', color);
    
      // 2️⃣ Store에 반영 (캘린더/태스크 동일)
      CalendarStore.setCalendarColor(rawId, color);
    
      // 3️⃣ 달력 즉시 반영 (refetch ❌)
      const cal = window.__calendar;
      if (cal) {
        cal.getEvents().forEach(ev => {
          if (String(ev.extendedProps?.calendar_id) === rawId) {
            ev.setProp('backgroundColor', color);
            ev.setProp('borderColor', color);
          }
        });
      }
    
      closeAllColorMenus();
    });
    
    
    

    // 바깥 클릭 닫기
    document.addEventListener('click', () => {
      closeAllColorMenus();
    });    
  }

  // =====================================================
  // Active Sync
  // =====================================================
  function syncActiveCalendarsFromUI() {
    const next = new Set();
  
    document.querySelectorAll('#calendar-list .cal-item.is-checked')
      .forEach(li => next.add(String(li.dataset.rawId || '')));
  
    // 🔥 Store에 “단일 진실”로 반영
    CalendarStore.setActiveCalendars(next);
  
    // (디버그용 유지해도 무방)
    State.activeCalendars = new Set(next);
    window.__CAL_ACTIVE_CALS__ = new Set(next);
  }
  

  function syncActiveTasksFromUI() {
    const next = new Set();
  
    document.querySelectorAll('#task-list .cal-item.is-checked')
      .forEach(li => {
        const id = String(li.dataset.rawId || '').trim();
        if (id) next.add(id);
      });
  
    // ✅ Store에 반영 (메서드 없으면 조용히 무시)
    CalendarStore?.setActiveTasks?.(next);
  
    // (디버그/보조용)
    State.activeTasks = new Set(next);
    window.__CAL_ACTIVE_TASKS__ = new Set(next);
  }
  
  

  function syncActiveAllFromUI() {
    syncActiveCalendarsFromUI();
    syncActiveTasksFromUI();
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

  // =====================================================
  // Mini Calendar
  // =====================================================
  function initMiniCalendar() {
    const input = document.getElementById('mini-calendar-input');
    const wrap  = document.querySelector('.mini-calendar-wrap');
    if (!input || !wrap || !window.flatpickr) return;

    flatpickr(input, {
      inline: true,
      locale: 'ko',
      static: true,
      appendTo: wrap,
      onChange(d) {
        d[0] && window.__calendar?.gotoDate?.(d[0]);
      }
    });
  }

  // =====================================================
  // Collapse (캘린더 목록 / 작업 목록 각각)
  // =====================================================
  function bindLeftSectionToggle() {
    document.querySelectorAll('.cal-collapse-btn').forEach(btn => {
      if (btn.__bound) return;
      btn.__bound = true;

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const section = btn.closest('.cal-section');
        if (!section) return;

        const head = section.querySelector('.cal-collapsible');
        const body = section.querySelector('.cal-collapsible-body');
        if (!head || !body) return;

        const collapsed = head.classList.toggle('is-collapsed');
        body.classList.toggle('is-hidden', collapsed);
      });
    });
  }
    // =====================================================
  // 내부유틸
  // =====================================================
  function closeAllColorMenus() {
    document.querySelectorAll('.cal-submenu').forEach(m => {
      m.classList.add('is-hidden');
    });
    document.querySelectorAll('.cal-menu').forEach(m => {
      m.classList.add('is-hidden');
    });
  }
  
})();
