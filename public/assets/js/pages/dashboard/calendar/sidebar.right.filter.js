// 📄 /public/assets/js/pages/dashboard/calendar/sidebar.right.filter.js
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
  'use strict';
  console.log('[sidebar.right.filter] loaded');
  /* =====================================================
   * GLOBAL FILTER STATE
   * ===================================================== */
  const FilterState = window.__TASK_FILTER_STATE__ = {
    type: 'today',        // today | next7 | date | list
    date: null,           // YYYY-MM-DD
    listId: 'inbox',
    listName: 'Inbox'
  };

  // DOM
  let btnDue, dd, ddDateItem;
  let btnClose;
  let filterDatePicker = null;
  let unbindOutside = null;

  document.addEventListener('DOMContentLoaded', () => {
    btnDue     = document.getElementById('right-list-btn-due');
    dd         = document.getElementById('right-list-dd');
    ddDateItem = dd?.querySelector('.right-list-dd-item[data-filter="date"]');
    btnClose   = document.getElementById('right-list-btn-close');
      

    if (!btnDue || !dd) return;

    initCreateDefaultList(); 
    bindUI();
    bindClose();   
    initDatePicker();
    syncFilterUI();

    renderTaskLists();
    updateFilterCounts();

  });

  /* =====================================================
   * UI BIND
   * ===================================================== */
  function bindUI() {

    // =========================
    // 필터 드롭다운 열기
    // =========================
    btnDue.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
  
      const opened = !dd.classList.contains('is-hidden');
      dd.classList.toggle('is-hidden', opened);
      btnDue.setAttribute('aria-expanded', String(!opened));
    });
  
    // =========================
    // 필터 항목 클릭
    // =========================
    dd.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
  
      const item =
      e.target.closest('[data-filter]') ||
      e.target.closest('.right-list-dd-item');    
      if (!item) return;    
  
      const filter   = item.dataset.filter;
      const listId   = item.dataset.list || item.dataset.listId || null;
      const listName = item.dataset.listName || item.textContent?.trim();

  
      // 🔹 오늘
      if (filter === 'today') {
        FilterState.type = 'today';
        FilterState.date = null;
        syncFilterUI();

        notifyRefresh();
        closeDropdown();
        return;
      }
  
      // 🔹 다음 7일
      if (filter === 'next7') {
        FilterState.type = 'next7';
        FilterState.date = null;
        syncFilterUI();

        notifyRefresh();
        closeDropdown();
        return;
      }
  
      // 🔹 특정 날짜
      if (filter === 'date') {
        openDatePicker();
        return;
      }
  
      // 🔹 작업 목록
      if (listId) {
        FilterState.type     = 'list';
        FilterState.listId   = listId;
        FilterState.listName = listName;
  
        syncFilterUI();

        notifyRefresh();
        closeDropdown();
      }
    });
  
    // =========================
    // 바깥 클릭 → 닫기
    // =========================
    document.addEventListener('click', (e) => {
      
      if (dd.classList.contains('is-hidden')) return;
      if (e.target.closest('#right-list-dd')) return;
      if (e.target.closest('#right-list-btn-due')) return;
      closeDropdown();
    });
  }
  

  function bindClose() {
    if (!btnClose) return;
  
    btnClose.addEventListener('click', e => {
      e.stopPropagation();
  
      // ✅ 반드시 전역 토글 함수만 호출
      window.toggleRightPanel(false);
  
      closeDropdown?.();
    });
  }
  
  

  
  /* =====================================================
   * DATE PICKER (FILTER)
   * ===================================================== */
  function initDatePicker() {
    const el = document.getElementById('mini-picker');
    if (!el) return;

    filterDatePicker = AdminPicker.create({
      type: 'mini',
      container: el
    });

    filterDatePicker.subscribe((_, date) => {
      if (!date) return;

      FilterState.type = 'date';
      FilterState.date = formatDate(date);

      syncFilterUI();

      notifyRefresh();

      closeDatePicker();
      closeDropdown();
    });
  }

  function openDatePicker() {
    if (!filterDatePicker) return;

    const anchor = ddDateItem;
    if (!anchor) return;

    filterDatePicker.open({
      anchor,
      placement: 'left',
      offset: 12
    });

    unbindOutside?.();
    unbindOutside = AdminPicker.bindOutsideClick(
      document.getElementById('mini-picker'),
      closeDatePicker
    );
  }

  function closeDatePicker() {
    filterDatePicker?.close?.();
    unbindOutside?.();
    unbindOutside = null;
  }

  function closeDropdown() {
    dd.classList.add('is-hidden');
    btnDue.setAttribute('aria-expanded', 'false');
    closeDatePicker();
  }

  /* =====================================================
   * FILTER UI SYNC
   * ===================================================== */
  function syncFilterUI() {
    dd.querySelectorAll('.right-list-dd-item[data-filter]')
      .forEach(item => {
        item.classList.toggle(
          'is-active',
          item.dataset.filter === FilterState.type
        );
      });
  
    let label = '오늘';
  
    if (FilterState.type === 'today') label = '오늘';
    else if (FilterState.type === 'next7') label = '다음 7일';
    else if (FilterState.type === 'date') label = FilterState.date || '특정 날짜';
    else if (FilterState.type === 'list') label = FilterState.listName || 'Inbox';
  
    btnDue.querySelector('.right-list-dd-label').textContent = label;
  }
  
  


 
  
  function setCount(selector, n) {
    const el = dd.querySelector(selector);
    if (!el) return;
  
    let em = el.querySelector('em');
    if (!em) {
      em = document.createElement('em');
      el.querySelector('span')?.appendChild(em);
    }
    em.textContent = `(${n})`;
  }  



  function updateFilterCounts() {
    if (!dd || !window.CalendarStore) return;
  
    const tasks = CalendarStore.getTasks
      ? CalendarStore.getTasks()
      : [];
  
    const todayStr = formatDate(new Date());
  
    const start = new Date();
    start.setHours(0, 0, 0, 0);
  
    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    end.setHours(23, 59, 59, 999);
  
    // 오늘
    const cToday = tasks.filter(t => {
      const dStr = getTaskDateLocal(t);
      return dStr === todayStr;
    }).length;
  
    // 다음 7일
    const cNext7 = tasks.filter(t => {
      const dStr = getTaskDateLocal(t);
      if (!dStr) return false;
      const d = new Date(dStr);
      return d >= start && d <= end;
    }).length;
  
    setCount('[data-filter="today"]', cToday);
    setCount('[data-filter="next7"]', cNext7);
  
    // 기존 기능 유지
    renderTaskLists();
  }
  
  
  function getTaskDateLocal(t) {
    const d = parseTaskDate(t?.due_iso);
    if (!d) return null;
  
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }
  
  
  
  function ensureListContainer() {
    if (!dd) return null;
  
    let wrap = dd.querySelector('.task-dd-lists');
    if (wrap) return wrap;
  
    // 🔥 "작업 목록" 타이틀 찾기
    const titles = Array.from(
      dd.querySelectorAll('.task-dd-group-title')
    );
    const listTitle = titles.find(el =>
      el.textContent.includes('작업 목록')
    );
    if (!listTitle) return null;
  
    // 🔥 컨테이너 생성
    wrap = document.createElement('div');
    wrap.className = 'task-dd-lists';
  
    // 타이틀 바로 뒤에 삽입
    listTitle.insertAdjacentElement('afterend', wrap);
  
    return wrap;
  }
  
  function renderTaskLists() {
    if (!window.CalendarStore) return;
  
    const wrap = ensureListContainer();
    if (!wrap) return;
  
    const calendars = CalendarStore.getCalendars();
    const tasks = CalendarStore.getTasks
      ? CalendarStore.getTasks()
      : [];
  
    // 🔥 task별 카운트
    const countMap = {};
    tasks.forEach(t => {
      const id =
        t.calendar_id ??
        t.extendedProps?.calendar_id ??
        null;
      if (!id) return;
      countMap[id] = (countMap[id] || 0) + 1;
    });
  
    wrap.innerHTML = '';
  
    // ============================
    // 🔥 task calendars만 사용
    // ============================
    const taskLists = calendars.filter(c => c.type === 'task');
  
    let inboxCalendar = null;
  
    taskLists.forEach(c => {
      const id = String(c.calendar_id || c.id);
      const name = c.name || c.title || id;
  
      // 🔥 Inbox 판별 (이름 기준)
      if (name.toLowerCase() === 'inbox') {
        inboxCalendar = c;
        return;
      }
  
      wrap.appendChild(
        makeListButton(
          id,
          name,
          countMap[id] || 0,
          false
        )
      );
    });
  
    // ============================
    // 🔥 Inbox는 “있을 때만”, 맨 위
    // ============================
    if (inboxCalendar) {
      const id = String(inboxCalendar.calendar_id || inboxCalendar.id);
      wrap.prepend(
        makeListButton(
          id,
          'Inbox',
          countMap[id] || 0,
          true
        )
      );
    }          
  }
  
  function initCreateDefaultList() {
    const chip  = document.getElementById('task-meta-list-chip');
    const label = document.getElementById('task-meta-list');
    const icon  = chip?.querySelector('i.bi');
  
    if (!chip || !label || !icon) return;
  
    // 🔥 기본값: Inbox
    window.CreateState = window.CreateState || {};
    window.CreateState.listId   = 'inbox';
    window.CreateState.listName = 'Inbox';
  
    icon.className = 'bi bi-inbox';
    label.textContent = 'Inbox';
  }
  
  
  function makeListButton(id, name, count, isInbox = false) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'right-list-dd-item';
    btn.dataset.list = id;
    btn.dataset.listName = name; // 🔥 핵심
  
    const icon = isInbox
      ? '<i class="bi bi-inbox"></i>'
      : '<i class="bi bi-folder"></i>';
  
    btn.innerHTML = `
      ${icon}
      <span>${name} <em>(${count})</em></span>
    `;
  
    return btn;
  }
  
  
  
  




  /* =====================================================
   * NOTIFY
   * ===================================================== */
  async function notifyRefresh() {

    // 🔥 절대 fetchAll 호출 금지
    // Store에 이미 전체 tasks 들어있음
  
    window.TaskPanel?.refresh?.({
      ...FilterState
    });
  }
  

  /* =====================================================
   * UTILS
   * ===================================================== */
  function formatDate(d) {
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }


  // 🔥 Store 변경 시 카운트 자동 갱신
if (window.CalendarStore?.subscribe) {
  CalendarStore.subscribe(() => {
    updateFilterCounts();
  });
}


function parseTaskDate(iso) {
  if (!iso) return null;

  // 이미 JS가 읽을 수 있는 경우
  if (iso.includes('-')) {
    const d = new Date(iso);
    return isNaN(d) ? null : d;
  }

  // YYYYMMDDTHHmmss
  if (/^\d{8}T\d{6}$/.test(iso)) {
    const y = iso.slice(0, 4);
    const m = iso.slice(4, 6);
    const d = iso.slice(6, 8);
    const hh = iso.slice(9, 11);
    const mm = iso.slice(11, 13);
    const ss = iso.slice(13, 15);

    return new Date(`${y}-${m}-${d}T${hh}:${mm}:${ss}`);
  }

  return null;
}



const btnListCreate = document.getElementById('right-list-btn-list-create');

btnListCreate?.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();

  if (window.AppCore?.notify) {
    AppCore.notify(
      'info',
      '작업 목록은 Synology에서 생성 및 공유 관리됩니다.'
    );
  } else {
    alert('작업 목록은 Synology에서 생성 및 공유 관리됩니다.');
  }
});










})();
