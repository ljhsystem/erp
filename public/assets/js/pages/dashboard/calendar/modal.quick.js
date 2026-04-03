// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/modal.quick.js'
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
    type: 'event',      // 'event' | 'task'
    baseDate: null      // ✅ FullCalendar에서 선택된 날짜 기억
  };

  function formatDate(d) {
    if (!(d instanceof Date)) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  
  //모달진입상태-----------------------------------



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

  // 🔥 위치 자동검색 1회 바인딩
  const locInput = modal.querySelector('#quick-input-location');

  if (locInput && window.LocationAutocomplete) {
    window.LocationAutocomplete.bind('#quick-input-location');
  }

    // 🔥 이전 값 완전 초기화
    modal.querySelector('#quick-input-title').value = '';
    modal.querySelector('#quick-input-desc').value  = '';
    modal.querySelector('#quick-input-allday').checked = (mode === 'event');
    modal.querySelector('#quick-input-start-date').value = '';
    modal.querySelector('#quick-input-end-date').value = '';
    modal.querySelector('#quick-input-start-time').value = '09:00';
    modal.querySelector('#quick-input-end-time').value = '10:00';

    // 🔥 추가
    const taskDueInput = modal.querySelector('#quick-input-task-due');
    if (taskDueInput) {
      taskDueInput.value = '';
      taskDueInput.dataset.iso = '';
    }

    // ✅🔥 1. 무조건 초기 상태 리셋
    showEventTab();
    QuickState.type = 'event';
  
    // 🔥 선택 날짜 기억
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

    // 🔒 task dropdown 강제 닫기
    const tl = document.getElementById('tasklist-dropdown-quick');
    if (tl) tl.hidden = true;
  
    // 🔒 calendar dropdown 강제 닫기
    const cl = document.getElementById('quick-calendar-list');
    if (cl) cl.hidden = true;
  
    // ===============================
    // 기본 모달 오픈
    // ===============================
    modal.classList.remove('is-hidden');
    document.body.classList.add('is-modal-open');
  
    // ✅🔥 🔑 누락된 핵심 (이 두 줄만 추가)
    bindAllDayToggleOnce(modal);
    syncAllDayClass(modal);
  
    // ===============================
    // 모드별 전환
    // ===============================
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
    
  
  // ✅ all-day 토글: "딱 1번만" 바인딩
  function bindAllDayToggleOnce(modal) {
    if (!modal || modal.__allDayBound) return;
    modal.__allDayBound = true;

    const chk = modal.querySelector('#quick-input-allday');
    if (!chk) return;

    // 최초 상태 반영
    modal.classList.toggle('is-allday', chk.checked);

    chk.addEventListener('change', () => {
      modal.classList.toggle('is-allday', chk.checked);
    });
  }

  // ✅ 상태 동기화 전용 (이벤트 X)
  function syncAllDayClass(modal) {
    const chk = modal?.querySelector('#quick-input-allday');
    if (!chk) return;
    modal.classList.toggle('is-allday', chk.checked);
  }



  //모달진입상태----------------------------------- 
  


  //closeQuickModal() 정의(QuickModal만 닫음, picker 찌꺼기 제거, QuickState 초기화)
  function closeQuickModal(reason = 'cancel') {
    closeTimeListPicker();
    datetimePicker?.close?.();
  
    // 🔥 cancel일 때만 임시 이벤트 제거
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
  
  


  
  // Utils
  const pad = n => String(n).padStart(2, '0');

  function toDateOnly(d) {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function toDatetimeLocal(d) {
    return `${toDateOnly(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function parseDate(v) {
    if (!v) return null;
  
    // YYYY-MM-DD 또는 YYYY-MM-DD HH:mm 수동 파싱
    const parts = v.trim().split(/[ T]/);
    const datePart = parts[0];
    const timePart = parts[1] || '00:00';
  
    const [y, m, d] = datePart.split('-').map(Number);
    const [hh, mm] = timePart.split(':').map(Number);
  
    if (!y || !m || !d) return null;
  
    return new Date(y, m - 1, d, hh || 0, mm || 0, 0, 0);
  }
 


  // Picker Instances (singleton)
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
    
      todayPicker.close(); // 🔥 핵심
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
    
      // 📌 시간 OFF → 날짜만
      if (!state.timeEnabled) {
        const d = toDateOnly(date);
    
        input.value = d;
        input.dataset.iso = d; // ⛔ T, 00:00 없음
        return;
      }
    
      // 📌 시간 ON → 날짜 + 시간
      const iso = toDatetimeLocal(date);          // 2026-01-27T09:00
      const display = formatDisplayDatetime(iso); // 2026-01-27 09:00
    
      input.value = display;
      input.dataset.iso = iso;
    });
    
  
    return datetimePicker;
  }
  

  
  





  //참석자(비워두는게 정답)
  function parseGuests() {
    return [];
  }

  //캘린더 아이디
  function getCalendarId() {
    return document.querySelector('#quick-input-calendar')?.value || null;
  }
  

  //Quick Modal 상태 관리
  function collectQuickFormData(modal) {
    const type = QuickState.type;
  
    const allDay = modal.querySelector('#quick-input-allday')?.checked === true;
  
    const startDate = modal.querySelector('#quick-input-start-date')?.value;
    const startTime = modal.querySelector('#quick-input-start-time')?.value || '09:00';  // 기본값 설정
  
    const endDate = modal.querySelector('#quick-input-end-date')?.value;
    const endTime = modal.querySelector('#quick-input-end-time')?.value || '09:00';  // 기본값 설정
  
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
  

//탭 날짜피커
function initTaskDefaults(modal, baseDate = null) {
  const taskDue = modal.querySelector('#quick-input-task-due');
  if (!taskDue) return;

  // ✅ 이미 값 있으면 덮어쓰지 않음 (사용자 입력 보호)
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

  // 🔥 날짜만
  const ymd = toDateOnly(d);  // YYYY-MM-DD

  taskDue.value = ymd;        // 화면 표시: 2026-02-24
  taskDue.dataset.iso = ymd;  // 시간 없이 저장

  const picker = ensureDateTimePicker();
  if (picker) {
    picker.__target = taskDue;

    picker.setDate(d);

    // 🔥 시간 OFF (핵심)
    picker.toggleTime(false);
  }
}
  
  //탭 날짜 변환
  function todayAt0900() {
    const d = new Date();
    d.setHours(9, 0, 0, 0);
    return d;
  }







  //탭 날짜 변환 1
  function formatDisplayDatetime(iso) {
    if (!iso) return '';
    return iso.replace('T', ' ');
  }
  //탭 날짜 변환 2
  function formatISOFromDisplay(display) {
    if (!display) return '';
    return display.replace(' ', 'T');
  }
  










  // ===============================
  // Quick Calendar Selector (전용)
  // ===============================
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
    
            // 버튼 반영
            document.getElementById('quick-calendar-color').style.backgroundColor = color;
            document.getElementById('quick-calendar-name').textContent = name;
    
            // hidden 값
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
      
        // 🔁 이미 열려 있으면 닫기
        if (!list.hidden) {
          list.hidden = true;
          return;
        }
      
        const calendars = window.CalendarContext?.calendars || [];
        const currentId =
          document.getElementById('quick-input-calendar')?.value || null;
      
        renderQuickCalendarList(calendars, currentId);
      
        // body portal 보장
        if (list.parentNode !== document.body) {
          document.body.appendChild(list);
        }
      
        list.hidden = false;
      
        requestAnimationFrame(() => {
          // width = 버튼 width
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
  
    // 이미 값 있으면 유지 (Edit → Quick 대응)
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




// ✅ Picker를 body로 포탈 + 위치 고정 오픈
function openPickerSafely(picker, pickerEl, input) {
  if (!picker || !pickerEl || !input) return;

  pickerEl.classList.add('is-hidden');

  picker.__target = input;
  picker.open();

  requestAnimationFrame(() => {
    // portal to body
    if (pickerEl.parentNode !== document.body) {
      document.body.appendChild(pickerEl);
    }

    // position below input
    const r = input.getBoundingClientRect();
    pickerEl.style.position = 'fixed';
    pickerEl.style.left = `${Math.round(r.left)}px`;
    pickerEl.style.top  = `${Math.round(r.bottom + 8)}px`;
    pickerEl.style.zIndex = '999999';

    // 화면 아래 넘치면 위로
    const h = pickerEl.offsetHeight || 300;
    if (r.bottom + 8 + h > window.innerHeight - 8) {
      pickerEl.style.top = `${Math.round(r.top - h - 8)}px`;
    }

    pickerEl.classList.remove('is-hidden');
  });
}



  // ===============================
  // Picker 전용 바인딩
  // ===============================
  function bindQuickModalPickers(modal) {

    // today picker
    modal.querySelectorAll('[data-picker="date"]').forEach(input => {
      input.addEventListener('mousedown', e => {
        e.preventDefault();
        e.stopPropagation();
    
        const picker = ensureTodayPicker();
        if (!picker) return;
    
        picker.__target = input;
    
        // input 값 → picker 동기화
        const d = parseDate(input.value);
        if (d) picker.setDate(d);
    
        // ✅ 이것만 호출 (anchor 필수)
        picker.open({ anchor: input });
      });
    });
    

    // timelist picker
    modal.querySelectorAll('[data-picker="time-list"]').forEach(input => {
      input.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
    
        const picker = getTimeListPicker();
        const pickerEl = document.getElementById('time-list-picker');
        if (!picker || !pickerEl) return;
    
        closeTimeListPicker();
    
        picker.__target = input;
    
        // 현재 값 → picker 반영
        const [h, m] = (input.value || '09:00').split(':').map(Number);
        picker.setTime({ hour: h, minute: m });
    
        // 🔥 핵심: 선택 콜백 (이게 없어서 아무 반응 없던 것)
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
    
        // 표시
        pickerEl.classList.remove('is-hidden');
    
        // 위치
        const r = input.getBoundingClientRect();
        pickerEl.style.position = 'fixed';
        pickerEl.style.left = `${r.left}px`;
        pickerEl.style.top  = `${r.bottom + 6}px`;
        pickerEl.style.zIndex = 999999;

        // 🔥 드롭 넓이 = input 넓이
        const w = Math.round(r.width);
        pickerEl.style.width = `${w}px`;
        // pickerEl.style.minWidth = `${w}px`;
        // pickerEl.style.maxWidth = `${w}px`;
        pickerEl.style.width = `${Math.round(r.width)}px`;

        // ✅ 열릴 때마다 현재 값 위치로 스크롤 (start/end 둘 다 동일)
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            picker.scrollToValue?.(input.value);
          });
        });
        
        
      });
    });
    


    // datetime picker
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




  // ===============================
  // 📍 Calendar List Position (GLOBAL)
  // ===============================
  function positionCalendarListToViewport(list, btn, offset = 6) {
    if (!list || !btn) return;

    const r = btn.getBoundingClientRect();

    list.style.position = 'fixed';
    list.style.left = `${Math.round(r.left)}px`;
    list.style.top  = `${Math.round(r.bottom + offset)}px`;
    list.style.zIndex = '100000';
  }

      


// ===============================
// Quick TaskList Selector (전용)
// ===============================
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

      // 버튼 반영
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
  
    // 🔁 재클릭 → 닫기
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


// ✅ 온종일 이벤트는 날짜 그대로만 전달
function buildISO(date, time, allDay) {
  if (!date) return null;

  if (allDay) {
    return date;   // 🔥 더 이상 +1 안함
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

  // ✅ 정상
  if (sDate.getTime() <= eDate.getTime()) return;

  // =======================================================
  // 🔥 양방향 보정
  // changedField 기준으로 "반대편"을 끌어다 맞춘다
  // =======================================================

  // 1) end를 바꿔서 역전됨 → start를 end로 끌어올림
  if (changedField === 'end') {
    startDateEl.value = endDateEl.value;
    if (!allDay && startTimeEl && endTimeEl) {
      startTimeEl.value = endTimeEl.value || startTimeEl.value;
    }
    return;
  }

  // 2) start를 바꿔서 역전됨 → end를 start로 끌어내림 (기존 동작)
  endDateEl.value = startDateEl.value;
  if (!allDay && startTimeEl && endTimeEl) {
    endTimeEl.value = startTimeEl.value || endTimeEl.value;
  }
}
 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // =====================================================
    // 💾 Quick Modal SAVE (Event / Task)
    // =====================================================
    const quickSaveBtn = document.getElementById('btn-quick-save');

    if (quickSaveBtn) {
      quickSaveBtn.addEventListener('click', async () => {
        try {
          const activeTab =
            document.querySelector('.shint-tab.qt.is-active');
          const type = activeTab?.dataset.qtype || 'event';

          /* ==================================================
          * 📅 EVENT CREATE
          * ================================================== */
          if (type === 'event') {

            // -----------------------------------------
            // 1️⃣ UI에서 선택된 캘린더 ID
            // -----------------------------------------
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

            // -----------------------------------------
            // 2️⃣ 실제 저장 캘린더 검증 (🔥 정답)
            // -----------------------------------------
            if (!selectedCal.href) {
              alert('캘린더 경로가 올바르지 않습니다.');
              return;
            }
            
            // 🔥 개인 캘린더 alias 처리
            let collectionHref = selectedCal.href;

            // 개인 캘린더 + alias target 있으면 실제 collection 사용
            if (
              selectedCal.alias_target_href &&
              /\/home\/?$/.test(collectionHref)
            ) {
              collectionHref = selectedCal.alias_target_href;
            }

            const saveCalendarId = selectedCal.calendar_id || selectedCal.id;


            // -----------------------------------------
            // 3️⃣ 입력값 수집
            // -----------------------------------------
            //게스트호출
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
            



            // -----------------------------------------
            // 4️⃣ Payload (🔥 기준 통일)
            // -----------------------------------------
            const payload = {
              type: 'event',
              title,
            
              calendar_id: saveCalendarId,
              collection_href: collectionHref, // ✅ 실제 collection
            
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

            
            // ✅ QuickModal 닫기
            AppCore.closeQuickModal?.();
            return;
            

            
          }

          /* ==================================================
          * 📝 TASK CREATE
          * ================================================== */
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

          // ✅ 설명 추가
          const descInput = document.getElementById('quick-input-desc');
          const description = descInput ? descInput.value.trim() : '';

          // ✅ due: dataset.iso(YYYY-MM-DDTHH:mm) → ICS(YYYYMMDDTHHMMSS)
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
          
            // 🔥 날짜만 있는 경우 (YYYY-MM-DD)
            if (!iso.includes('T')) {
              due = iso.replace(/-/g, ''); // 20260225
            } else {
              // 🔥 시간 포함된 경우만 DATE-TIME으로 변환
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
            due,                       // ✅ 20260225T090000 형태
            tzid: 'Asia/Seoul',        // (옵션이지만 넣어두면 좋음)
            description                // ✅ 핵심
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



  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-quick');
    if (!modal) return;

    // ===============================
    // 🔥 Quick Title → Temp Event Sync
    // ===============================
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

    // modal이 열릴 때 1회만 바인딩
    if (!modal.__pickersBound) {
      bindQuickModalPickers(modal);
      modal.__pickersBound = true;
    }
      // 🔥 여기서 강제 바인딩 (1회)
      bindQuickCalendarSelectorOnce();




  });

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  modal.addEventListener('click', e => {
    const wrap = e.target.closest('.qt-time-wrap');
    if (!wrap) return;

    const input = wrap.querySelector('[data-picker="time-list"]');
    if (input) input.click();
  });


  // ✅ 탭 클릭은 이것 하나로 통일
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
    

    // ✅ 클래스만 반영 (바인딩은 openQuickModal에서 1회만)
    syncAllDayClass(modal);


  });





  //세부정보
  document.getElementById('btn-quick-detail')
  ?.addEventListener('click', () => {
  
    const modal = document.getElementById('modal-quick');
    if (!modal) return;
  
    const payload = collectQuickFormData(modal);
  
    closeQuickModal('transfer');
  
    // 🔥 이벤트 / 작업 분기 확실하게
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
      
          // ✅ VEVENT 흉내 (최소 계약)
          raw: {
            DTSTART: { value: toICSDatetime(payload.start) },
            DTEND:   { value: toICSDatetime(payload.end) },            
      
            // 🔥 핵심 추가
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
  

  // 생성할 때 (빈 영역 클릭 → Quick Event)
  document.addEventListener('calendar:quick:create', (e) => {
    const detail = e.detail || {};
    const modal = document.getElementById('modal-quick');
    if (!modal) return;

    const isTask = detail.type === 'task';

    window.AppCore?.openQuickModal({
      mode: isTask ? 'task' : 'event',
      date: detail.date || detail.event?.start || null
    });

    /* =========================================
    * 1️⃣ Sidebar Create (event / task)
    * ========================================= */
    if (detail.date instanceof Date) {
      const d = detail.date;
    
      const sd = modal.querySelector('#quick-input-start-date');
      const ed = modal.querySelector('#quick-input-end-date');
    
      if (sd) sd.value = toDateOnly(d);
      if (ed) ed.value = toDateOnly(d);
    }
    

    /* =========================================
    * 2️⃣ FullCalendar 빈 영역 클릭
    * ========================================= */
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



  //1️⃣ QuickModal 닫기 (X 버튼 + 바깥 클릭)
  // ❌ X 버튼 닫기 (Quick 전용)
  document.getElementById('btn-quick-close')
  ?.addEventListener('click', () => {
    closeQuickModal('cancel');
  });

  // 🔥 QuickModal 바깥 클릭 → Quick 닫기
  document.getElementById('modal-quick')
  ?.addEventListener('mousedown', (e) => {
    if (e.target.closest('.shint-modal__card')) return;
    closeQuickModal('cancel');
  });

  //2️⃣ ⏰ TimeListPicker 외부 클릭 → 닫기 (Quick 전용, 단 하나)
  document.addEventListener('mousedown', (e) => {
    const picker = document.getElementById('time-list-picker');
    if (!picker || picker.classList.contains('is-hidden')) return;

    // picker 내부 클릭 보호
    if (picker.contains(e.target)) return;

    // 트리거(input) 클릭 보호
    if (e.target.closest('[data-picker="time-list"]')) return;

    closeTimeListPicker();
  }, true);

  //3️⃣ 📅 DateTimePicker 외부 클릭 → 닫기 (Quick 전용)
  document.addEventListener('mousedown', (e) => {
    const dt = document.getElementById('datetime-picker');
    if (!dt || dt.classList.contains('is-hidden')) return;

    // picker 내부 클릭 보호
    if (dt.contains(e.target)) return;

    // 트리거(input) 클릭 보호
    if (e.target.closest('[data-picker="datetime"]')) return;

    dt.classList.add('is-hidden');
    datetimePicker?.close?.();
  }, true);

  //4️⃣ 📅 Quick Calendar Dropdown 외부 클릭
  document.addEventListener('mousedown', (e) => {
    const list = document.getElementById('quick-calendar-list');
    const btn  = document.getElementById('quick-calendar-btn');

    if (!list || list.hidden) return;
    if (btn && btn.contains(e.target)) return;
    if (list.contains(e.target)) return;

    list.hidden = true;
  }, true);

  //5️⃣ 📋 Quick TaskList Dropdown 외부 클릭
  document.addEventListener('mousedown', (e) => {
    const list = document.getElementById('tasklist-dropdown-quick');
    const btn  = document.getElementById('tasklist-btn-quick');

    if (!list || list.hidden) return;
    if (btn && btn.contains(e.target)) return;
    if (list.contains(e.target)) return;

    list.hidden = true;
  }, true);

  //6️⃣ ⎋ ESC 키 → Picker만 닫기 (QuickModal 유지)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const modal = document.getElementById('modal-quick');
    if (!modal || modal.classList.contains('is-hidden')) return;

    e.preventDefault();
    e.stopPropagation();

    let closed = false;

    // DateTimePicker
    const dt = document.getElementById('datetime-picker');
    if (dt && !dt.classList.contains('is-hidden')) {
      dt.classList.add('is-hidden');
      datetimePicker?.close?.();
      closed = true;
    }

    // TimeListPicker
    const tl = document.getElementById('time-list-picker');
    if (tl && !tl.classList.contains('is-hidden')) {
      closeTimeListPicker();
      closed = true;
    }

    if (closed) return;
  }, true);


// =====================================================
// 👥 Guest Field Block (Quick 전용 - 안정형)
// =====================================================
modal.addEventListener('focusin', (e) => {
  const input = e.target;

  if (input.id !== 'quick-input-guests') return;

  input.blur(); // 포커스 제거

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