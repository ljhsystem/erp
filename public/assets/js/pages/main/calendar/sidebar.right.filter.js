import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
  'use strict';
  console.log('[sidebar.right.filter] loaded');

  const FilterState = window.__TASK_FILTER_STATE__ = {
    type: 'today',
    date: null,
    listId: 'inbox',
    listName: 'Inbox'
  };

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

  function bindUI() {

    btnDue.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const opened = !dd.classList.contains('is-hidden');
      dd.classList.toggle('is-hidden', opened);
      btnDue.setAttribute('aria-expanded', String(!opened));
    });

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

      if (filter === 'today') {
        FilterState.type = 'today';
        FilterState.date = null;
        syncFilterUI();

        notifyRefresh();
        closeDropdown();
        return;
      }

      if (filter === 'next7') {
        FilterState.type = 'next7';
        FilterState.date = null;
        syncFilterUI();

        notifyRefresh();
        closeDropdown();
        return;
      }

      if (filter === 'date') {
        openDatePicker();
        return;
      }

      if (listId) {
        FilterState.type     = 'list';
        FilterState.listId   = listId;
        FilterState.listName = listName;

        syncFilterUI();

        notifyRefresh();
        closeDropdown();
      }
    });

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

    const titles = Array.from(
      dd.querySelectorAll('.task-dd-group-title')
    );
    const listTitle = titles.find(el =>
      el.textContent.includes('작업 목록')
    );
    if (!listTitle) return null;

    wrap = document.createElement('div');
    wrap.className = 'task-dd-lists';

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

    const taskLists = calendars.filter(c => c.type === 'task');

    let inboxCalendar = null;

    taskLists.forEach(c => {
      const id = String(c.calendar_id || c.id);
      const name = c.name || c.title || id;

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

  async function notifyRefresh() {
    window.TaskPanel?.refresh?.({
      ...FilterState
    });
  }

  function formatDate(d) {
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

if (window.CalendarStore?.subscribe) {
  CalendarStore.subscribe(() => {
    updateFilterCounts();
  });
}


function parseTaskDate(iso) {
  if (!iso) return null;

  if (iso.includes('-')) {
    const d = new Date(iso);
    return isNaN(d) ? null : d;
  }

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
