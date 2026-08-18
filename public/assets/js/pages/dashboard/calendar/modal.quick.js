import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { getTimeListPicker, closeTimeListPicker } from '/public/assets/js/common/picker/timeListSingleton.js';
(() => {
  'use strict';

  console.log('[QuickModal] loaded');

  if (window.__CAL_QUICK_MODAL_LOADED__) return;
  window.__CAL_QUICK_MODAL_LOADED__ = true;

  // Namespace
  window.AppCore = window.AppCore || {};
  const AppCore = window.AppCore;
  AppCore.util = AppCore.util || {};

  //탭 객체 상태 선언
  window.QuickState = {
    type: 'event',
    baseDate: null
  };

  function formatDate(d) {
    if (!(d instanceof Date)) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  const modal = document.getElementById('modal-quick');
  if (!modal) return;

  const tabs      = modal.querySelectorAll('.shint-tab.qt');
  const title     = modal.querySelector('#quick-title');
  const eventRows = modal.querySelectorAll('.shint-only-event');
  const taskRows  = modal.querySelectorAll('.shint-only-task');

  function showEventTab() {
    tabs.forEach(t => {
      const on = t.dataset.qtype === 'event';
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on);
    });
    eventRows.forEach(el => el.style.display = '');
    taskRows.forEach(el  => el.style.display = 'none');
    title && (title.textContent = '이벤트 생성');
  }

  function showTaskTab() {
    tabs.forEach(t => {
      const on = t.dataset.qtype === 'task';
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on);
    });
    taskRows.forEach(el  => el.style.display = '');
    eventRows.forEach(el => el.style.display = 'none');
    title && (title.textContent = '작업 생성');
  }











  function openQuickModal({ mode = 'event', date = null } = {}) {

  const locInput = modal.querySelector('#quick-input-location');

  if (locInput && window.LocationAutocomplete) {
    window.LocationAutocomplete.bind('#quick-input-location');
  }

    modal.querySelector('#quick-input-title').value = '';
    modal.querySelector('#quick-input-desc').value  = '';
    modal.querySelector('#quick-input-allday').checked = (mode === 'event');
    modal.querySelector('#quick-input-start-date').value = '';
    modal.querySelector('#quick-input-end-date').value = '';
    modal.querySelector('#quick-input-start-time').value = '09:00';
    modal.querySelector('#quick-input-end-time').value = '10:00';

    const taskDueInput = modal.querySelector('#quick-input-task-due');
    if (taskDueInput) {
      taskDueInput.value = '';
      taskDueInput.dataset.iso = '';
    }

    showEventTab();
    QuickState.type = 'event';

    if (date instanceof Date) {
      QuickState.baseDate = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate()
      );
    }
    else if (date) {
      const d = new Date(date);
      QuickState.baseDate = new Date(
        d.getFullYear(),
        d.getMonth(),
        d.getDate()
      );
    }
    else {
      QuickState.baseDate = null;
    }

    if (QuickState.baseDate && isNaN(QuickState.baseDate.getTime())) {
    QuickState.baseDate = null;
    }

    const tl = document.getElementById('tasklist-dropdown-quick');
    if (tl) tl.hidden = true;

    const cl = document.getElementById('quick-calendar-list');
    if (cl) cl.hidden = true;

    modal.classList.remove('is-hidden');
    document.body.classList.add('is-modal-open');

    bindAllDayToggleOnce(modal);
    syncAllDayClass(modal);

    if (mode === 'task') {
      showTaskTab();
      QuickState.type = 'task';

      bindQuickTaskListSelectorOnce();
      applyDefaultQuickTaskList();
      requestAnimationFrame(() => initTaskDefaults(modal, QuickState.baseDate));
    }

    if (mode === 'event') {
      bindQuickCalendarSelectorOnce();
      applyDefaultQuickCalendar();
    }
  }







  AppCore.openQuickModal  = openQuickModal;
  AppCore.closeQuickModal = closeQuickModal;

  function bindAllDayToggleOnce(modal) {
    if (!modal || modal.__allDayBound) return;
    modal.__allDayBound = true;

    const chk = modal.querySelector('#quick-input-allday');
    if (!chk) return;

    modal.classList.toggle('is-allday', chk.checked);

    chk.addEventListener('change', () => {
      modal.classList.toggle('is-allday', chk.checked);
    });
  }

  function syncAllDayClass(modal) {
    const chk = modal?.querySelector('#quick-input-allday');
    if (!chk) return;
    modal.classList.toggle('is-allday', chk.checked);
  }


  function closeQuickModal(reason = 'cancel') {
    closeTimeListPicker();
    datetimePicker?.close?.();

    if (reason === 'cancel') {
      removeQuickTempEvent();
    }

    const calList = document.getElementById('quick-calendar-list');
    if (calList) calList.hidden = true;

    const taskList = document.getElementById('tasklist-dropdown-quick');
    if (taskList) taskList.hidden = true;

    QuickState.type = 'event';

    modal.classList.add('is-hidden');
    document.body.classList.remove('is-modal-open');
  }

  const pad = n => String(n).padStart(2, '0');

  function toDateOnly(d) {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function toDatetimeLocal(d) {
    return `${toDateOnly(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function parseDate(v) {
    if (!v) return null;

    const parts = v.trim().split(/[ T]/);
    const datePart = parts[0];
    const timePart = parts[1] || '00:00';

    const [y, m, d] = datePart.split('-').map(Number);
    const [hh, mm] = timePart.split(':').map(Number);

    if (!y || !m || !d) return null;

    return new Date(y, m - 1, d, hh || 0, mm || 0, 0, 0);
  }

  let todayPicker = null;
  let datetimePicker = null;

  function ensureTodayPicker() {
    if (todayPicker) return todayPicker;

    const container = document.getElementById('today-picker');
    if (!container) return null;

    todayPicker = AdminPicker.create({ type: 'today', container });

    todayPicker.subscribe((_, date) => {
      const input = todayPicker.__target;
      if (!input || !date) return;

      input.value = formatDate(date);

      requestAnimationFrame(() => {
        const isStart = input.id.includes('start');
        normalizeQuickStartEnd(isStart ? 'start' : 'end');
      });

      todayPicker.close();
    });


    return todayPicker;
  }

  function ensureDateTimePicker() {
    if (datetimePicker) return datetimePicker;

    const container = document.getElementById('datetime-picker');
    if (!container) return null;

    datetimePicker = AdminPicker.create({ type: 'datetime', container });

    datetimePicker.subscribe((state, date) => {
      const input = datetimePicker.__target;
      if (!input || !date) return;

      if (!state.timeEnabled) {
        const d = toDateOnly(date);

        input.value = d;
        input.dataset.iso = d;
        return;
      }

      const iso = toDatetimeLocal(date);
      const display = formatDisplayDatetime(iso);

      input.value = display;
      input.dataset.iso = iso;
    });


    return datetimePicker;
  }


  function parseGuests() {
    return [];
  }

  function getCalendarId() {
    return document.querySelector('#quick-input-calendar')?.value || null;
  }

  function collectQuickFormData(modal) {
    const type = QuickState.type;

    const allDay = modal.querySelector('#quick-input-allday')?.checked === true;

    const startDate = modal.querySelector('#quick-input-start-date')?.value;
    const startTime = modal.querySelector('#quick-input-start-time')?.value || '09:00';

    const endDate = modal.querySelector('#quick-input-end-date')?.value;
    const endTime = modal.querySelector('#quick-input-end-time')?.value || '09:00';

    const start = buildISO(startDate, startTime, allDay);
    const end = buildISO(endDate, endTime, allDay);

    if (type === 'event') {
      return {
        type: 'event',
        title: modal.querySelector('#quick-input-title').value,
        start,
        end,
        allDay,
        guests: parseGuests(),
        location: modal.querySelector('#quick-input-location').value,
        desc: modal.querySelector('#quick-input-desc').value,
        calendarId: getCalendarId()
      };
    }

    if (type === 'task') {
      return {
        type: 'task',
        title: modal.querySelector('#quick-input-title').value,
        due: formatISOFromDisplay(modal.querySelector('#quick-input-task-due').value),
        listId: modal.querySelector('#quick-input-tasklist').value,
        desc: modal.querySelector('#quick-input-desc').value
      };
    }
  }

function initTaskDefaults(modal, baseDate = null) {
  const taskDue = modal.querySelector('#quick-input-task-due');
  if (!taskDue) return;

  if ((taskDue.value && taskDue.value.trim()) || taskDue.dataset.iso) {
    return;
  }

  let d;

  if (baseDate instanceof Date && !isNaN(baseDate.getTime())) {
    d = new Date(
      baseDate.getFullYear(),
      baseDate.getMonth(),
      baseDate.getDate()
    );
  } else {
    const now = new Date();
    d = new Date(
      now.getFullYear(),
      now.getMonth(),
      now.getDate()
    );
  }

  const ymd = toDateOnly(d);

  taskDue.value = ymd;
  taskDue.dataset.iso = ymd;

  const picker = ensureDateTimePicker();
  if (picker) {
    picker.__target = taskDue;

    picker.setDate(d);

    picker.toggleTime(false);
  }
}

  function todayAt0900() {
    const d = new Date();
    d.setHours(9, 0, 0, 0);
    return d;
  }

  function formatDisplayDatetime(iso) {
    if (!iso) return '';
    return iso.replace('T', ' ');
  }

  function formatISOFromDisplay(display) {
    if (!display) return '';
    return display.replace(' ', 'T');
  }

    function renderQuickCalendarList(calendars, currentId) {
      const listEl = document.getElementById('quick-calendar-list');
      if (!listEl) return;

      listEl.innerHTML = '';

      calendars
        .filter(cal => {
          if (!cal.href) return false;
          if (cal.supports_task === true) return false;
          if (cal.type === 'task') return false;
          return true;
        })
        .forEach(cal => {
          const id    = String(cal.calendar_id || cal.id);
          const name  =
            cal.name ||
            cal.calendar_name ||
            cal.displayname ||
            '캘린더';

            const color =
            cal.admin_calendar_color ||
            CalendarStore.getCalendarColor?.(id) ||
            cal.color ||
            cal.calendar_color ||
            '#9CA3AF';

          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'evt-cal-item';

          if (String(currentId) === id) {
            btn.classList.add('is-active');
          }

          btn.innerHTML = `
            <span class="evt-cal-color" style="background:${color}"></span>
            <span class="evt-cal-name">${name}</span>
          `;

          btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            document.getElementById('quick-calendar-color').style.backgroundColor = color;
            document.getElementById('quick-calendar-name').textContent = name;

            document.getElementById('quick-input-calendar').value = id;
            document.getElementById('quick-input-calendar-href').value = cal.href;

            listEl.hidden = true;
          });

          listEl.appendChild(btn);
        });
    }
    function bindQuickCalendarSelectorOnce() {
      const btn  = document.getElementById('quick-calendar-btn');
      const list = document.getElementById('quick-calendar-list');
      if (!btn || !list) return;

      if (btn.__calendarBound) return;
      btn.__calendarBound = true;

      btn.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (!list.hidden) {
          list.hidden = true;
          return;
        }

        const calendars = window.CalendarContext?.calendars || [];
        const currentId =
          document.getElementById('quick-input-calendar')?.value || null;

        renderQuickCalendarList(calendars, currentId);

        if (list.parentNode !== document.body) {
          document.body.appendChild(list);
        }

        list.hidden = false;

        requestAnimationFrame(() => {

          const w = btn.offsetWidth;
          list.style.width =
          list.style.minWidth =
          list.style.maxWidth = `${w}px`;

          positionCalendarListToViewport(list, btn, 6);
        });
      });


    }



  function applyDefaultQuickCalendar() {
    const calendars = window.CalendarContext?.calendars || [];
    if (!calendars.length) return;

    const hidden = document.getElementById('quick-input-calendar');
    if (hidden && hidden.value) return;

    const personal =
    calendars.find(c =>
      c.type === 'calendar' &&
      (c.name || c.calendar_name || c.displayname) === '개인'
    ) || calendars.find(c => c.type === 'calendar');


    if (!personal) return;

    const id    = personal.calendar_id || personal.id;
    const name  =
      personal.name ||
      personal.calendar_name ||
      personal.displayname ||
      '개인';

      const color =
      personal.admin_calendar_color ||
      CalendarStore.getCalendarColor?.(id) ||
      personal.color ||
      personal.calendar_color ||
      '#9CA3AF';

    document.getElementById('quick-calendar-color').style.backgroundColor = color;
    document.getElementById('quick-calendar-name').textContent = name;
    hidden.value = id;

    const hrefInput = document.getElementById('quick-input-calendar-href');
    if (hrefInput) {
      if (!personal.href) {
        console.error('[NO CALENDAR COLLECTION]', personal);
        return;
      }

      hrefInput.value = personal.href;

    }

  }

function openPickerSafely(picker, pickerEl, input) {
  if (!picker || !pickerEl || !input) return;

  pickerEl.classList.add('is-hidden');

  picker.__target = input;
  picker.open();

  requestAnimationFrame(() => {

    if (pickerEl.parentNode !== document.body) {
      document.body.appendChild(pickerEl);
    }

    const r = input.getBoundingClientRect();
    pickerEl.style.position = 'fixed';
    pickerEl.style.left = `${Math.round(r.left)}px`;
    pickerEl.style.top  = `${Math.round(r.bottom + 8)}px`;
    pickerEl.style.zIndex = '999999';

    const h = pickerEl.offsetHeight || 300;
    if (r.bottom + 8 + h > window.innerHeight - 8) {
      pickerEl.style.top = `${Math.round(r.top - h - 8)}px`;
    }

    pickerEl.classList.remove('is-hidden');
  });
}

  function bindQuickModalPickers(modal) {

    modal.querySelectorAll('[data-picker="date"]').forEach(input => {
      input.addEventListener('mousedown', e => {
        e.preventDefault();
        e.stopPropagation();

        const picker = ensureTodayPicker();
        if (!picker) return;

        picker.__target = input;

        const d = parseDate(input.value);
        if (d) picker.setDate(d);

        picker.open({ anchor: input });
      });
    });

    modal.querySelectorAll('[data-picker="time-list"]').forEach(input => {
      input.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();

        const picker = getTimeListPicker();
        const pickerEl = document.getElementById('time-list-picker');
        if (!picker || !pickerEl) return;

        closeTimeListPicker();

        picker.__target = input;

        const [h, m] = (input.value || '09:00').split(':').map(Number);
        picker.setTime({ hour: h, minute: m });

        picker.onSelect = ({ hour, minute }) => {
          const hh = String(hour).padStart(2, '0');
          const mm = String(minute).padStart(2, '0');

          input.value = `${hh}:${mm}`;

          requestAnimationFrame(() => {
            const isStart = input.id.includes('start');
            normalizeQuickStartEnd(isStart ? 'start' : 'end');
          });

          closeTimeListPicker();
        };

        pickerEl.classList.remove('is-hidden');

        const r = input.getBoundingClientRect();
        pickerEl.style.position = 'fixed';
        pickerEl.style.left = `${r.left}px`;
        pickerEl.style.top  = `${r.bottom + 6}px`;
        pickerEl.style.zIndex = 999999;

        const w = Math.round(r.width);
        pickerEl.style.width = `${w}px`;
        // pickerEl.style.minWidth = `${w}px`;
        // pickerEl.style.maxWidth = `${w}px`;
        pickerEl.style.width = `${Math.round(r.width)}px`;

        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            picker.scrollToValue?.(input.value);
          });
        });


      });
    });

    modal.querySelectorAll('[data-picker="datetime"]').forEach(input => {
      input.onclick = e => {
        e.preventDefault();

        const picker = ensureDateTimePicker();
        const pickerEl = document.getElementById('datetime-picker');
        if (!picker || !pickerEl) return;

        picker.__target = input;
        openPickerSafely(picker, pickerEl, input);

        const d = parseDate(input.value);
        if (d) picker.setDate(d);
      };
    });



  }

  function positionCalendarListToViewport(list, btn, offset = 6) {
    if (!list || !btn) return;

    const r = btn.getBoundingClientRect();

    list.style.position = 'fixed';
    list.style.left = `${Math.round(r.left)}px`;
    list.style.top  = `${Math.round(r.bottom + offset)}px`;
    list.style.zIndex = '100000';
  }

function getQuickTaskLists() {
  const lists = window.CalendarContext?.calendars || [];
  return lists.filter(c => c.type === 'task' || c.supports_task === true);
}

function renderQuickTaskListDropdown(currentId) {
  const dropdown = document.getElementById('tasklist-dropdown-quick');
  if (!dropdown) return;

  dropdown.innerHTML = '';

  const taskLists = getQuickTaskLists();
  if (!taskLists.length) {
    dropdown.innerHTML =
      `<div style="padding:10px 12px;color:#64748b;">작업 목록 없음</div>`;
    return;
  }

  taskLists.forEach(tl => {
    const id = String(tl.id || tl.calendar_id);
    const name =
      tl.name || tl.calendar_name || tl.displayname || '작업목록';

      const color =
      tl.admin_calendar_color ||
      CalendarStore.getCalendarColor?.(id) ||
      tl.color ||
      tl.calendar_color ||
      '#94a3b8';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'evt-cal-item';
    if (String(currentId) === id) btn.classList.add('is-active');

    btn.innerHTML = `
      <span class="evt-cal-color" style="background:${color}"></span>
      <span class="evt-cal-name">${name}</span>
    `;

    btn.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();

      document.querySelector(
        '#tasklist-btn-quick .evt-cal-color'
      ).style.backgroundColor = color;

      document.querySelector(
        '#tasklist-btn-quick .evt-cal-name'
      ).textContent = name;

      document.getElementById('quick-input-tasklist').value = id;

      dropdown.hidden = true;
    });

    dropdown.appendChild(btn);
  });
}


function bindQuickTaskListSelectorOnce() {
  const btn = document.getElementById('tasklist-btn-quick');
  const dropdown = document.getElementById('tasklist-dropdown-quick');
  if (!btn || !dropdown) return;

  if (btn.__tasklistBound) return;
  btn.__tasklistBound = true;

  btn.addEventListener('mousedown', e => {
    e.preventDefault();
    e.stopPropagation();

    if (!dropdown.hidden) {
      dropdown.hidden = true;
      return;
    }

    const currentId =
      document.getElementById('quick-input-tasklist')?.value || '';

    renderQuickTaskListDropdown(currentId);

    if (dropdown.parentNode !== document.body) {
      document.body.appendChild(dropdown);
    }

    dropdown.hidden = false;

    requestAnimationFrame(() => {
      const w = btn.offsetWidth;
      dropdown.style.width =
      dropdown.style.minWidth =
      dropdown.style.maxWidth = `${w}px`;

      positionCalendarListToViewport(dropdown, btn, 6);
    });
  });

}

function applyDefaultQuickTaskList() {
  const hidden = document.getElementById('quick-input-tasklist');
  const nameEl = document.querySelector('#tasklist-btn-quick .evt-cal-name');
  const colorEl = document.querySelector('#tasklist-btn-quick .evt-cal-color');

  if (!hidden || hidden.value) return;

  const lists = getQuickTaskLists();
  const inbox =
    lists.find(l =>
      (l.name || l.calendar_name || '').toLowerCase().includes('inbox')
    ) || lists[0];

  if (!inbox) return;

  hidden.value = inbox.id || inbox.calendar_id;
  nameEl.textContent =
    inbox.name || inbox.calendar_name || inbox.displayname || 'Inbox';

    const id = inbox.id || inbox.calendar_id;

    colorEl.style.backgroundColor =
      inbox.admin_calendar_color ||
      CalendarStore.getCalendarColor?.(id) ||
      inbox.color ||
      inbox.calendar_color ||
      '#94a3b8';
}


function removeQuickTempEvent() {
  if (window.__quickTempEvent) {
    window.__quickTempEvent.remove();
    window.__quickTempEvent = null;
  }
}

function buildISO(date, time, allDay) {
  if (!date) return null;

  if (allDay) {
    return date;
  }

  return `${date}T${time || '09:00'}`;
}

function toICSDatetime(iso) {
  if (!iso) return null;

  if (!iso.includes('T')) {
    return iso.replace(/-/g, '');
  }

  const [date, time] = iso.split('T');
  return date.replace(/-/g, '') + 'T' + time.replace(':', '') + '00';
}


function createLocalDateFromYMD(ymd) {
  if (!ymd) return null;
  const [y, m, d] = String(ymd).split('-').map(Number);
  return new Date(y, m - 1, d, 0, 0, 0, 0);
}

function normalizeQuickStartEnd(changedField = null) {
  const allDay = document.getElementById('quick-input-allday')?.checked === true;

  const startDateEl = document.getElementById('quick-input-start-date');
  const endDateEl   = document.getElementById('quick-input-end-date');
  const startTimeEl = document.getElementById('quick-input-start-time');
  const endTimeEl   = document.getElementById('quick-input-end-time');

  if (!startDateEl?.value || !endDateEl?.value) return;

  const sDate = createLocalDateFromYMD(startDateEl.value);
  const eDate = createLocalDateFromYMD(endDateEl.value);
  if (!sDate || !eDate) return;

  if (!allDay) {
    const [sh, sm] = (startTimeEl?.value || '09:00').split(':').map(Number);
    const [eh, em] = (endTimeEl?.value   || '10:00').split(':').map(Number);
    sDate.setHours(sh, sm, 0, 0);
    eDate.setHours(eh, em, 0, 0);
  }

  if (sDate.getTime() <= eDate.getTime()) return;

  if (changedField === 'end') {
    startDateEl.value = endDateEl.value;
    if (!allDay && startTimeEl && endTimeEl) {
      startTimeEl.value = endTimeEl.value || startTimeEl.value;
    }
    return;
  }

  endDateEl.value = startDateEl.value;
  if (!allDay && startTimeEl && endTimeEl) {
    endTimeEl.value = startTimeEl.value || endTimeEl.value;
  }
}

    const quickSaveBtn = document.getElementById('btn-quick-save');

    if (quickSaveBtn) {
      quickSaveBtn.addEventListener('click', async () => {
        try {
          const activeTab =
            document.querySelector('.shint-tab.qt.is-active');
          const type = activeTab?.dataset.qtype || 'event';

          if (type === 'event') {

            const calendarId =
              document.getElementById('quick-input-calendar')?.value || null;

            if (!calendarId) {
              alert('캘린더를 선택하세요.');
              return;
            }

            const calendars = window.CalendarContext?.calendars || [];

            const selectedCal = calendars.find(c =>
              String(c.calendar_id || c.id) === String(calendarId)
            );

            if (!selectedCal) {
              console.error('[CALENDAR NOT FOUND]', calendarId, calendars);
              alert('캘린더 정보를 찾을 수 없습니다.');
              return;
            }

            if (!selectedCal.href) {
              alert('캘린더 경로가 올바르지 않습니다.');
              return;
            }

            let collectionHref = selectedCal.href;

            if (
              selectedCal.alias_target_href &&
              /\/home\/?$/.test(collectionHref)
            ) {
              collectionHref = selectedCal.alias_target_href;
            }

            const saveCalendarId = selectedCal.calendar_id || selectedCal.id;


            parseGuests();

            const allDay =
              document.getElementById('quick-input-allday')?.checked === true;

            const title =
              document.getElementById('quick-input-title')?.value?.trim() || '';

            if (!title) {
              alert('제목을 입력하세요.');
              return;
            }

            const startDate =
            document.getElementById('quick-input-start-date')?.value;

            const startTime =
              document.getElementById('quick-input-start-time')?.value;

            const endDate =
              document.getElementById('quick-input-end-date')?.value;

            const endTime =
              document.getElementById('quick-input-end-time')?.value;

            const start = buildISO(startDate, startTime, allDay);
            const end   = buildISO(endDate,   endTime,   allDay, true);



            const startDateTime = parseDate(start.replace('T', ' '));
            const endDateTime   = parseDate(end.replace('T', ' '));

            if (!startDateTime || !endDateTime) {
              alert('날짜가 올바르지 않습니다.');
              return;
            }

            if (!allDay && endDateTime.getTime() <= startDateTime.getTime()) {
              alert('종료 시간은 시작 시간 이후여야 합니다.');
              return;
            }

            if (!allDay && endDateTime.getTime() <= startDateTime.getTime()) {
              alert('종료 시간은 시작 시간 이후여야 합니다.');
              return;
            }

            const descInput = document.getElementById('quick-input-desc');
            const description = descInput ? descInput.value.trim() : '';

            const location =
              document.getElementById('quick-input-location')?.value?.trim() || '';

            const payload = {
              type: 'event',
              title,

              calendar_id: saveCalendarId,
              collection_href: collectionHref,

              logical_calendar_id: saveCalendarId,

              description,
              location,
              allDay,
              start,
              end
            };




            console.log('[QUICK EVENT CREATE]', payload);

            const res = await CalendarAPI.createEvent(payload);

            removeQuickTempEvent();

            AppCore.closeQuickModal?.();
            return;



          }

        if (type === 'task') {
          const taskListId =
            document.getElementById('quick-input-tasklist')?.value || null;

          if (!taskListId) {
            alert('작업 리스트를 선택하세요.');
            return;
          }

          const collectionHref =
            CalendarStore.getCollectionHref?.(taskListId) || null;

          if (!collectionHref) {
            console.error('[NO TASK COLLECTION HREF]', taskListId);
            alert('작업 리스트 경로(collection)를 찾을 수 없습니다.');
            return;
          }

          const title =
            document.getElementById('quick-input-title')?.value?.trim() || '';

          if (!title) {
            alert('작업 제목을 입력하세요.');
            return;
          }

          const descInput = document.getElementById('quick-input-desc');
          const description = descInput ? descInput.value.trim() : '';

          const taskDueInput = document.getElementById('quick-input-task-due');

          let due = null;

          if (taskDueInput) {
            let iso = taskDueInput.dataset.iso;

            if (!iso) {
              iso = taskDueInput.value.replace(' ', 'T');
            }

            if (!iso) {
              alert('마감일을 선택하세요.');
              return;
            }

            if (!iso.includes('T')) {
              due = iso.replace(/-/g, '');
            } else {
              due = toICSDatetime(iso);
            }
          }

          if (!due) {
            alert('마감일을 선택하세요.');
            return;
          }

          const payload = {
            type: 'task',
            title,
            calendar_id: taskListId,
            collection_href: collectionHref,
            due,
            tzid: 'Asia/Seoul',
            description
          };

          console.log('[QUICK TASK CREATE]', payload);

          await CalendarAPI.createTask(payload);

          AppCore.closeQuickModal?.();
          return;
        }

        } catch (err) {
          console.error('[QUICK SAVE ERROR]', err);
          alert('저장 실패');
        }
      });
    }

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-quick');
    if (!modal) return;

    (function bindQuickTitleOnce() {
      const modal = document.getElementById('modal-quick');
      if (!modal || modal.__titleBound) return;
      modal.__titleBound = true;

      const input = modal.querySelector('#quick-input-title');
      if (!input) return;

      input.addEventListener('input', () => {
        const ev = window.__quickTempEvent;
        if (!ev) return;

        const title = input.value?.trim() || '(제목 없음)';
        ev.setProp('title', title);
      });
    })();

    if (!modal.__pickersBound) {
      bindQuickModalPickers(modal);
      modal.__pickersBound = true;
    }

      bindQuickCalendarSelectorOnce();

  });

  modal.addEventListener('click', e => {
    const wrap = e.target.closest('.qt-time-wrap');
    if (!wrap) return;

    const input = wrap.querySelector('[data-picker="time-list"]');
    if (input) input.click();
  });

  modal.addEventListener('click', (e) => {
    const tab = e.target.closest('.shint-tab.qt');
    if (!tab) return;

    e.stopPropagation();

    const type = tab.dataset.qtype;
    QuickState.type = type;

    if (type === 'task') {
      showTaskTab();

      bindQuickTaskListSelectorOnce();
      applyDefaultQuickTaskList();

      requestAnimationFrame(() => initTaskDefaults(modal, QuickState.baseDate));
    } else {
      showEventTab();
    }

    syncAllDayClass(modal);


  });


  document.getElementById('btn-quick-detail')
  ?.addEventListener('click', () => {

    const modal = document.getElementById('modal-quick');
    if (!modal) return;

    const payload = collectQuickFormData(modal);

    closeQuickModal('transfer');

    if (payload.type === 'event') {
      if (!window.AppCore?.openEventEditModal) {
        console.error('[QuickDetail] openEventEditModal not found');
        return;
      }

      window.AppCore.openEventEditModal({
        __mode: 'create',

        title: payload.title,
        start: payload.start,
        end: payload.end,
        allDay: payload.allDay,

        extendedProps: {
          calendar_id: payload.calendarId,
          _real_calendar_id: payload.calendarId,
          location: payload.location,
          description: payload.desc,

          raw: {
            DTSTART: { value: toICSDatetime(payload.start) },
            DTEND:   { value: toICSDatetime(payload.end) },

            LOCATION: {
              value: payload.location || ''
            },
            DESCRIPTION: {
              value: payload.desc || ''
            }
          }
        }
      });


    }

    if (payload.type === 'task') {
      window.AppCore.openTaskEditModal({
        __mode: 'create',
        ...payload
      });
    }
  });


  document.addEventListener('calendar:quick:create', (e) => {
    const detail = e.detail || {};
    const modal = document.getElementById('modal-quick');
    if (!modal) return;

    const isTask = detail.type === 'task';

    window.AppCore?.openQuickModal({
      mode: isTask ? 'task' : 'event',
      date: detail.date || detail.event?.start || null
    });

    if (detail.date instanceof Date) {
      const d = detail.date;

      const sd = modal.querySelector('#quick-input-start-date');
      const ed = modal.querySelector('#quick-input-end-date');

      if (sd) sd.value = toDateOnly(d);
      if (ed) ed.value = toDateOnly(d);
    }

    const ev = detail.event;

    if (ev) {
      const start = ev.start;
      let end     = ev.end || new Date(start.getTime() + 60 * 60 * 1000);

      if (ev.allDay && ev.end) {
        end = new Date(ev.end);
        end.setDate(end.getDate() - 1);
      }

      const sd = modal.querySelector('#quick-input-start-date');
      const ed = modal.querySelector('#quick-input-end-date');

      if (sd) sd.value = toDateOnly(start);
      if (ed) ed.value = toDateOnly(end);

      const allDayChk = modal.querySelector('#quick-input-allday');
      if (allDayChk) {
        allDayChk.checked = ev.allDay === true;
        syncAllDayClass(modal);
      }

      if (!ev.allDay) {
        modal.querySelector('#quick-input-start-time').value =
          `${pad(start.getHours())}:${pad(start.getMinutes())}`;
        modal.querySelector('#quick-input-end-time').value =
          `${pad(end.getHours())}:${pad(end.getMinutes())}`;
      }

      window.__quickTempEvent = ev;
    }


    const start = ev.start;
    let end     = ev.end || new Date(start.getTime() + 60 * 60 * 1000);

    if (ev.allDay && ev.end) {
      end = new Date(ev.end);
      end.setDate(end.getDate() - 1);
    }

    const sd = modal.querySelector('#quick-input-start-date');
    const ed = modal.querySelector('#quick-input-end-date');

    if (sd) sd.value = toDateOnly(start);
    if (ed) ed.value = toDateOnly(end);


    const allDayChk = modal.querySelector('#quick-input-allday');
    if (allDayChk) {
      allDayChk.checked = ev.allDay === true;
      syncAllDayClass(modal);
    }

    if (!ev.allDay) {
      modal.querySelector('#quick-input-start-time').value =
        `${pad(start.getHours())}:${pad(start.getMinutes())}`;
      modal.querySelector('#quick-input-end-time').value =
        `${pad(end.getHours())}:${pad(end.getMinutes())}`;
    }

    window.__quickTempEvent = ev;
  });


  document.getElementById('btn-quick-close')
  ?.addEventListener('click', () => {
    closeQuickModal('cancel');
  });

  document.getElementById('modal-quick')
  ?.addEventListener('mousedown', (e) => {
    if (e.target.closest('.shint-modal__card')) return;
    closeQuickModal('cancel');
  });

  document.addEventListener('mousedown', (e) => {
    const picker = document.getElementById('time-list-picker');
    if (!picker || picker.classList.contains('is-hidden')) return;

    if (picker.contains(e.target)) return;

    if (e.target.closest('[data-picker="time-list"]')) return;

    closeTimeListPicker();
  }, true);

  document.addEventListener('mousedown', (e) => {
    const dt = document.getElementById('datetime-picker');
    if (!dt || dt.classList.contains('is-hidden')) return;

    if (dt.contains(e.target)) return;

    if (e.target.closest('[data-picker="datetime"]')) return;

    dt.classList.add('is-hidden');
    datetimePicker?.close?.();
  }, true);

  document.addEventListener('mousedown', (e) => {
    const list = document.getElementById('quick-calendar-list');
    const btn  = document.getElementById('quick-calendar-btn');

    if (!list || list.hidden) return;
    if (btn && btn.contains(e.target)) return;
    if (list.contains(e.target)) return;

    list.hidden = true;
  }, true);

  document.addEventListener('mousedown', (e) => {
    const list = document.getElementById('tasklist-dropdown-quick');
    const btn  = document.getElementById('tasklist-btn-quick');

    if (!list || list.hidden) return;
    if (btn && btn.contains(e.target)) return;
    if (list.contains(e.target)) return;

    list.hidden = true;
  }, true);

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const modal = document.getElementById('modal-quick');
    if (!modal || modal.classList.contains('is-hidden')) return;

    e.preventDefault();
    e.stopPropagation();

    let closed = false;

    const dt = document.getElementById('datetime-picker');
    if (dt && !dt.classList.contains('is-hidden')) {
      dt.classList.add('is-hidden');
      datetimePicker?.close?.();
      closed = true;
    }

    const tl = document.getElementById('time-list-picker');
    if (tl && !tl.classList.contains('is-hidden')) {
      closeTimeListPicker();
      closed = true;
    }

    if (closed) return;
  }, true);

modal.addEventListener('focusin', (e) => {
  const input = e.target;

  if (input.id !== 'quick-input-guests') return;

  input.blur();

  AppCore.notify?.(
    'warn',
    '게스트 기능은 Synology에서 관리하세요.'
  );
});

modal.addEventListener('input', (e) => {
  const input = e.target;

  if (input.id !== 'quick-input-guests') return;

  input.value = '';
});




})();
