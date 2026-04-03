// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/modal.event.edit.js'
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { getTimeListPicker, closeTimeListPicker } from '/public/assets/js/common/picker/timeListSingleton.js';
(() => {
  'use strict';

    // ✅ 중복 로드 방지 (SPA/partial reload 대응)
    if (window.__CAL_EVENT_EDIT_MODAL_LOADED__) return;
    window.__CAL_EVENT_EDIT_MODAL_LOADED__ = true;

  console.log('[EventEditModal] loaded');

  

  const modal = document.getElementById('modal-event-edit');
  if (!modal) return;

  function closeEventEditModal(reason = 'cancel') {
    if (reason === 'cancel' && window.__quickTempEvent) {
      removeQuickTempEvent();
    }
  
    const calList = document.getElementById('event-calendar-list');
    if (calList) calList.hidden = true;
  
    modal.classList.add('is-hidden');
    document.body.classList.remove('is-modal-open');
  }
  

  function removeQuickTempEvent() {
    if (window.__quickTempEvent) {
      window.__quickTempEvent.remove();
      window.__quickTempEvent = null;
    }
  }
  

  window.AppCore = window.AppCore || {};
  window.AppCore.closeEventEditModal = closeEventEditModal; 

  const $ = id => document.getElementById(id);

  let todayPicker = null;

// =====================================================
// 👥 Guest Field Block (DOM Delegation 방식 - 안전)
// =====================================================
modal.addEventListener('click', (e) => {
  if (e.target.matches('input[placeholder="게스트 추가"]')) {
    e.preventDefault();
    e.stopPropagation();

    AppCore.notify(
      'warn',
      '게스트 기능은 Synology에서 관리하세요.'
    );
  }
});



  const MAX_REMINDERS = 5;

  const REMINDER_OPTIONS = [
    { value: 'at',  label: '이벤트 시' },
    { value: '5m',  label: '시작 5분 전' },
    { value: '10m', label: '시작 10분 전' },
    { value: '30m', label: '시작 30분 전' },
    { value: '1h',  label: '시작 1시간 전' }
  ];
  const REMINDER_OPTIONS_CREATE = [
    { value:'at', label:'이벤트 시' },
    { value:'5m', label:'시작 5분 전' },
    { value:'10m', label:'시작 10분 전' },
    { value:'30m', label:'시작 30분 전' },
    { value:'1h', label:'시작 1시간 전' },
    { value:'2h', label:'시작 2시간 전' },
    { value:'6h', label:'시작 6시간 전' },
    { value:'12h',label:'시작 12시간 전' },
    { value:'1d', label:'시작 1일 전' },
    { value:'2d', label:'시작 2일 전' },
    { value:'3d', label:'시작 3일 전' },
    { value:'5d', label:'시작 5일 전' },
    { value:'7d', label:'시작 7일 전' },
    { value:'14d',label:'시작 14일 전' }
  ];
  
  const REMINDER_OPTIONS_EDIT = [
    // 당일 절대
    { value:'ABS:0@06:00', label:'6:00 당일' },
    { value:'ABS:0@08:00', label:'8:00 당일' },
    { value:'ABS:0@09:00', label:'9:00 당일' },  
    // 1일 전
    { value:'REL:-1@09:00', label:'시작 1일 전 9:00' },
    { value:'REL:-1@21:00', label:'시작 1일 전 21:00' },  
    // 2일 전
    { value:'REL:-2@09:00', label:'시작 2일 전 9:00' },
    { value:'REL:-2@21:00', label:'시작 2일 전 21:00' },  
    // 1주 전
    { value:'REL:-7@09:00', label:'시작 1주 전 9:00' },
    { value:'REL:-7@21:00', label:'시작 1주 전 21:00' },  
    // 2주 전
    { value:'REL:-14@09:00', label:'시작 2주 전 9:00' },
    { value:'REL:-14@21:00', label:'시작 2주 전 21:00' }
  ];

  const EDIT_REMINDER_OPTIONS = [
    { value: '0d-6h',  label: '6:00 당일' },
    { value: '0d-8h',  label: '8:00 당일' },
    { value: '0d-9h',  label: '9:00 당일' },
  
    { value: '1d-9h',  label: '시작 1일 전 9:00' },
    { value: '1d-21h', label: '시작 1일 전 21:00' },
  
    { value: '2d-9h',  label: '시작 2일 전 9:00' },
    { value: '2d-21h', label: '시작 2일 전 21:00' },
  
    { value: '1w-9h',  label: '시작 1주 전 9:00' },
    { value: '1w-21h', label: '시작 1주 전 21:00' },
  
    { value: '2w-9h',  label: '시작 2주 전 9:00' },
    { value: '2w-21h', label: '시작 2주 전 21:00' }
  ];
  
  
  let remindersState = [];   // ⭐ 유일한 진실


  
  // 🔁 반복 상태 (단일 진실)
  let repeatState = {
    rrule: null,        // 실제 RRULE 문자열
    source: 'none',     // none | preset | custom
  };

  let repeatBound = false;

  // 🎨 이벤트 컬러 상태 (단일 진실)
  let eventColorState = {
    color: null,            // 팔레트 선택값 (null 가능)
    effectiveColor: '#9CA3AF',
    calendarColor: '#9CA3AF', // 🔥 추가
    source: 'calendar'
  }; 

  let eventColorBound = false;

  eventColorState.source = 'calendar';
  eventColorState.color  = null; 

  let calendarBtnBound = false;

  /* =====================================================
   * Utils (null-safe)
   * ===================================================== */
  function setVal(id, value = '') {
    const el = $(id);
    if (el) el.value = value;
  }

  function setChecked(id, checked = false) {
    const el = $(id);
    if (el) el.checked = !!checked;
  }

  function ensureTodayPicker() {
    if (todayPicker) return todayPicker;
  
    const container = document.getElementById('today-picker');
    if (!container) return null;
  
    todayPicker = AdminPicker.create({ type: 'today', container });
  
    todayPicker.subscribe((_, date) => {
      const input = todayPicker.__target;
      if (!input || !date) return;
    
      input.value = formatDate(date);
      normalizeStartEnd(input.id.includes('start') ? 'start' : 'end');
      // 🔥 안전하게 닫기
      if (typeof todayPicker.close === 'function') {
        todayPicker.close();
      } else {
        document.getElementById('today-picker')
          ?.classList.add('is-hidden');
      }
    });
    
    
  
    return todayPicker;
  }
  
  const pad = n => String(n).padStart(2, '0');

  function formatDate(d) {
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  }  
  
  function initEventEditPickers(modal) {  
    // 온종일
    const chk = modal.querySelector('#event-allday');
    if (chk) {
      modal.classList.toggle('is-allday', chk.checked);
      chk.onchange = () => {
        modal.classList.toggle('is-allday', chk.checked);
      };
    }
  }
  
  // ===============================
  // 📍 Picker Position Helper (Edit Modal 전용)
  // ===============================
  function positionPickerBelowInput(pickerEl, anchorEl, offset = 6) {
    if (!pickerEl || !anchorEl) return;

    const r = anchorEl.getBoundingClientRect();

    pickerEl.style.position = 'fixed';
    pickerEl.style.left = `${Math.round(r.left)}px`;
    pickerEl.style.top  = `${Math.round(r.bottom + offset)}px`;
    pickerEl.style.zIndex = '999999';

    // 🔥 화면 아래 넘치면 위로 뒤집기
    const h = pickerEl.offsetHeight || 280;
    if (r.bottom + offset + h > window.innerHeight - 8) {
      pickerEl.style.top = `${Math.round(r.top - h - offset)}px`;
    }
  }
  
  /* =====================================================
   * Synology / FullCalendar Date Parser
   * ===================================================== */
  function parseDate(v) {
    if (!v) return '';

    // Date object
    if (v instanceof Date) {
      const pad = n => String(n).padStart(2, '0');
      return `${v.getFullYear()}-${pad(v.getMonth() + 1)}-${pad(v.getDate())}T${pad(v.getHours())}:${pad(v.getMinutes())}`;
    }

    // YYYYMMDD
    if (/^\d{8}$/.test(v)) {
      return `${v.slice(0,4)}-${v.slice(4,6)}-${v.slice(6,8)}`;
    }

    // YYYYMMDDTHHMM
    if (/^\d{8}T\d{4}/.test(v)) {
      return `${v.slice(0,4)}-${v.slice(4,6)}-${v.slice(6,8)}T${v.slice(9,11)}:${v.slice(11,13)}`;
    }

    return v;
  }


  function formatUntil(until) {
    if (!until) return null;
  
    const s = String(until).trim();
  
    // 1) DATE only: 20260207  → 그대로
    if (/^\d{8}$/.test(s)) {
      const y = s.slice(0,4), m = s.slice(4,6), d = s.slice(6,8);
      return `${y}-${m}-${d}`;
    }
  
    // 2) DATE-TIME: 20260206T150000Z / 20260206T150000
    const m = s.match(/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?$/);
    if (m) {
      const y = +m[1], mo = +m[2]-1, d = +m[3];
      const hh = +m[4], mm = +m[5], ss = +m[6];
      const isZ = !!m[7];
  
      // Z면 UTC로 만들고, 아니면 로컬로 만든다
      const dt = isZ
        ? new Date(Date.UTC(y, mo, d, hh, mm, ss))
        : new Date(y, mo, d, hh, mm, ss);
  
      const pad = n => String(n).padStart(2,'0');
      return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}`;
    }
  
    // fallback: 앞 8자리라도 시도
    const m2 = s.match(/^(\d{4})(\d{2})(\d{2})/);
    if (m2) return `${m2[1]}-${m2[2]}-${m2[3]}`;
  
    return null;
  }

  
  // 🔥 Synology 종일 이벤트 DTEND 보정 (exclusive → UI용 inclusive)
  function normalizeAllDayEnd(startDateStr, endRaw, isAllDay) {
    if (!isAllDay || !startDateStr) return endRaw;
  
    // 🔥 raw DTEND가 YYYYMMDD 형식이면
    if (/^\d{8}$/.test(endRaw)) {
      const y = parseInt(endRaw.slice(0,4), 10);
      const m = parseInt(endRaw.slice(4,6), 10);
      const d = parseInt(endRaw.slice(6,8), 10);
  
      const dt = new Date(y, m-1, d);
      dt.setDate(dt.getDate() - 1);
  
      return formatDate(dt);
    }
  
    // 이미 YYYY-MM-DD면 그대로
    if (/^\d{4}-\d{2}-\d{2}$/.test(endRaw)) {
      return endRaw;
    }
  
    return startDateStr;
  }

  function formatRRuleSafe(rrule) {
    if (!rrule) return '반복되지 않음';
  
    const map = {};
    rrule.split(';').forEach(part => {
      const [k, v] = part.split('=');
      if (k && v) map[k] = v;
    });

    const until = map.UNTIL || null;
    const count = map.COUNT || null;

    let endText = '';

    if (until) {
      const d = formatUntil(until);
      if (d) endText = `, ${d}까지`;
    } else if (count) {
      endText = `, ${count}회까지`;
    }


    const freq = map.FREQ;
    let byday = map.BYDAY || null;
    let bysetpos = map.BYSETPOS || null;
  
    const DAY_MAP = {
      MO: '월', TU: '화', WE: '수', TH: '목',
      FR: '금', SA: '토', SU: '일'
    };
  
    const POS_MAP = {
      '1': '첫번째',
      '2': '두번째',
      '3': '세번째',
      '4': '네번째',
      '-1': '마지막'
    };
  
    // =========================
    // 🔥 MONTHLY: BYDAY=1TH 처리
    // =========================
    if (freq === 'MONTHLY' && byday && !bysetpos) {
      const m = byday.match(/^(-?\d)([A-Z]{2})$/);
      if (m) {
        bysetpos = m[1];   // 1, -1
        byday = m[2];      // TH
      }
    }
  
    // DAILY
    if (freq === 'DAILY') return '날마다';
  
    // WEEKLY
    if (freq === 'WEEKLY') {
      if (byday) {
        const days = byday.split(',').map(d => DAY_MAP[d] || d);
        return `매주 (${days.join(', ')}요일에)`;
      }
      return '매주';
    }
  
    // MONTHLY - nth weekday ⭐⭐⭐
    if (freq === 'MONTHLY' && bysetpos && byday) {
      return `매월, ${POS_MAP[bysetpos]} ${DAY_MAP[byday]}요일에${endText}`;
    }
    
  
    // MONTHLY - 날짜 기준
    if (freq === 'MONTHLY') {
      return `매월${endText}`;
    }
    
  
    // YEARLY
    if (freq === 'YEARLY') return '매년';
  
    return '반복 일정';
  }
  
  function normalizeRRuleString(s) {
    if (!s) return null;
    let r = String(s).trim();
  
    // "RRULE:FREQ=..." 형태면 prefix 제거
    if (r.toUpperCase().startsWith('RRULE:')) {
      r = r.slice(6);
    }
  
    // 혹시 "DTSTART:... RRULE:..." 같이 섞여오면 RRULE 뒤만 취함
    const idx = r.toUpperCase().indexOf('FREQ=');
    if (idx > 0) r = r.slice(idx);
  
    return r || null;
  }
  
  // FullCalendar rrule 객체(options.freq 숫자) → "FREQ=MONTHLY;..." 문자열
  function buildRRuleFromRRuleOptions(opts) {
    if (!opts || typeof opts !== 'object') return null;
  
    // rrule lib freq enum: 0 YEARLY, 1 MONTHLY, 2 WEEKLY, 3 DAILY, ...
    const FREQ_MAP = {
      0: 'YEARLY',
      1: 'MONTHLY',
      2: 'WEEKLY',
      3: 'DAILY',
      4: 'HOURLY',
      5: 'MINUTELY',
      6: 'SECONDLY'
    };
  
    const parts = [];
    const freq = (typeof opts.freq === 'number') ? FREQ_MAP[opts.freq] : opts.freq;
  
    if (freq) parts.push(`FREQ=${freq}`);
    if (opts.interval) parts.push(`INTERVAL=${opts.interval}`);
    if (opts.count) parts.push(`COUNT=${opts.count}`);
  
    // until: Date면 YYYYMMDDTHHMMSSZ로(대충) 만들거나 날짜만 쓰고 싶으면 YYYYMMDD로
    if (opts.until) {
      const u = opts.until instanceof Date ? opts.until : new Date(opts.until);
      if (!isNaN(u)) {
        const pad = n => String(n).padStart(2, '0');
        // Synology 호환: UTC Z
        const until =
          u.getUTCFullYear() +
          pad(u.getUTCMonth() + 1) +
          pad(u.getUTCDate()) + 'T' +
          pad(u.getUTCHours()) +
          pad(u.getUTCMinutes()) +
          pad(u.getUTCSeconds()) + 'Z';
        parts.push(`UNTIL=${until}`);
      }
    }
  
    // BYDAY
    if (Array.isArray(opts.byweekday) && opts.byweekday.length) {
      const days = opts.byweekday
        .map(d => String(d).toUpperCase())
        .join(',');
      parts.push(`BYDAY=${days}`);
    }
  
    // BYMONTH, BYMONTHDAY
    if (Array.isArray(opts.bymonth) && opts.bymonth.length) {
      parts.push(`BYMONTH=${opts.bymonth.join(',')}`);
    }
    if (Array.isArray(opts.bymonthday) && opts.bymonthday.length) {
      parts.push(`BYMONTHDAY=${opts.bymonthday.join(',')}`);
    }
  
    // BYSETPOS (nth weekday)
    if (opts.bysetpos) parts.push(`BYSETPOS=${opts.bysetpos}`);
  
    return parts.length ? parts.join(';') : null;
  }
  
  /* =====================================================
  * 📅 Calendar List Render (Edit Modal)
  * - 한 줄 = [캘린더색 + 캘린더이름] + [이벤트색]
  * ===================================================== */
  function renderEventCalendarList(calendars, currentId) {
    const listEl = document.getElementById('event-calendar-list');
    if (!listEl) return;

    listEl.innerHTML = '';

    calendars
      // 🔥 1️⃣ Task / VTODO 캘린더 제거
      .filter(cal => {
        // Synology / ERP 공통 안전 필터
        if (cal.supports_task === true) return false;
        if (cal.type === 'task') return false;
        if (cal.is_task === true) return false;
        return true;
      })

      // 🔥 2️⃣ 이벤트 캘린더만 렌더
      .forEach(cal => {
        const calendarId    = String(cal.calendar_id || cal.id);
        const calendarName  =
          cal.name ||
          cal.calendar_name ||
          cal.displayname ||
          '캘린더';

          const calendarColor =
            cal.admin_calendar_color ||
            CalendarStore.getCalendarColor?.(calendarId) ||
            cal.color ||
            cal.calendar_color ||
            '#9CA3AF';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'evt-cal-item';

        if (calendarId === String(currentId)) {
          btn.classList.add('is-active');
        }

        btn.innerHTML = `
          <span class="evt-cal-color" style="background:${calendarColor}"></span>
          <span class="evt-cal-name">${calendarName}</span>
        `;

        btn.onclick = e => {
          e.preventDefault();
          e.stopPropagation();
        
          const form = document.getElementById('event-form');
          const mode = form?.dataset.mode;
        
          const currentId =
            document.getElementById('event-calendar-id')?.value;
        
          // 🔒 수정 모드 + 다른 캘린더 선택 시 차단
          if (mode === 'edit' && calendarId !== currentId) {
            AppCore.notify(
              'warn',
              '기존 이벤트는 캘린더를 변경할 수 없습니다.'
            );
          
            listEl.hidden = true;   // 🔥 추가
            return;
          }
        
          // ✅ 같은 캘린더면 그냥 닫기
          if (calendarId === currentId) {
            listEl.hidden = true;
            return;
          }
        
          // ✅ 생성 모드에서는 정상 변경
          const colorEl = document.getElementById('event-calendar-color');
          const nameEl  = document.getElementById('event-calendar-name');
          const idInput = document.getElementById('event-calendar-id');
        
          if (colorEl) colorEl.style.backgroundColor = calendarColor;
          if (nameEl)  nameEl.textContent = calendarName;
          if (idInput) idInput.value = calendarId;
        
          listEl.hidden = true;
        };

        listEl.appendChild(btn);
      });
  }


  function normalizeColor(v) {
    if (!v) return null;

    const s = String(v).trim();

    // HEX
    if (s[0] === '#') return s.toUpperCase();

    // rgb(a) -> HEX
    const m = s.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (m) {
      const r = Math.max(0, Math.min(255, parseInt(m[1], 10)));
      const g = Math.max(0, Math.min(255, parseInt(m[2], 10)));
      const b = Math.max(0, Math.min(255, parseInt(m[3], 10)));
      const hex = `#${[r,g,b].map(n => n.toString(16).padStart(2,'0')).join('')}`;
      return hex.toUpperCase();
    }

    // 기타(이상값)
    return null;
  }

  function buildEventColorPalette(currentColor) {
    const COLORS = [
      '#CC4E4E','#D67240','#DB8F2C','#E0A416','#E0BB00',
      '#ADB204','#92A822','#75A828','#49A33B','#1C9963',
      '#008E9E','#0877CC','#3267D1','#5555E0','#8153DB',
      '#9E4CC7','#B240AF','#C24279',
      '#6E6E6E','#666F80'
    ];
  
    const cur = normalizeColor(currentColor);
  
    // ✅ 핵심: 무색(체커보드)이 "선택"으로 보이는 경우는
    // 사용자가 'event' 소스로 무색(null)을 명시적으로 선택했을 때만
    const noneSelected = (eventColorState?.source === 'event' && cur === null);
  
    return `
      <div class="evt-color-none">
        <button type="button"
          class="evt-color-option evt-color-option--none ${noneSelected ? 'is-selected' : ''}"
          data-color="">
        </button>
      </div>
  
      <div class="evt-color-divider"></div>
  
      <div class="evt-color-grid">
        ${COLORS.map(c => {
          const hex = normalizeColor(c);
          const selected = (hex === cur) ? 'is-selected' : '';
          return `
            <button type="button"
              class="evt-color-option ${selected}"
              data-color="${hex}"
              style="--c:${hex}">
            </button>
          `;
        }).join('')}
      </div>
    `;
  }

  function bindEventColorOnce() {
    const colorBtn = document.getElementById('event-color-btn');
    const palette  = document.getElementById('evt-color-palette');
    const colorDot = document.getElementById('event-color-dot');
    const colorInp = document.getElementById('event-color');

    if (!colorBtn || !palette || eventColorBound) return;
    eventColorBound = true;

    colorBtn.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
    
      // 🔁 이미 열려 있으면 닫기
      if (!palette.hidden) {
        palette.hidden = true;
        return;
      }
      
      if (palette.parentNode !== document.body) {
        document.body.appendChild(palette);
      }
    
      // 🔥 최신 상태로 DOM 자체를 다시 생성
      palette.innerHTML = buildEventColorPalette(
        eventColorState.color
      );
           
      palette.hidden = false;
    
      requestAnimationFrame(() => {
        positionPickerBelowInput(palette, colorBtn, 6);
      });
    });


    // 색상 선택
    palette.addEventListener('click', e => {
      const btn = e.target.closest('.evt-color-option');
      if (!btn) return;
    
      const pickedRaw = btn.dataset.color;
      const picked = pickedRaw === '' ? null : normalizeColor(pickedRaw);
    
      // ✅ 무조건 사용자 선택이면 event
      eventColorState.color  = picked;       // null 가능
      eventColorState.source = 'event';
    
      // ✅ DOT 반영
      if (picked) {
        colorDot.style.backgroundColor = picked;
        colorDot.classList.remove('is-none');
      } else {
        colorDot.style.backgroundColor = '';
        colorDot.classList.add('is-none');
      }
    
      // hidden input 반영
      colorInp.value = picked || '';
    
      // ✅ 다시 렌더 (이때 buildEventColorPalette에서 noneSelected가 정확히 걸림)
      palette.innerHTML = buildEventColorPalette(
        eventColorState.color
      );
      
    
      setTimeout(() => palette.hidden = true, 80);
    });
  }


  bindEventColorOnce(); // ✅ 여기


// ✅ YYYY-MM-DD 를 "로컬 00:00" Date로 만든다 (UTC 파싱 금지)
function createLocalDateFromYMD(ymd) {
  if (!ymd) return null;

  // ymd가 "YYYYMMDD"로 들어오는 경우도 방어
  if (/^\d{8}$/.test(ymd)) {
    const y = parseInt(ymd.slice(0, 4), 10);
    const m = parseInt(ymd.slice(4, 6), 10);
    const d = parseInt(ymd.slice(6, 8), 10);
    return new Date(y, m - 1, d, 0, 0, 0, 0);
  }

  // "YYYY-MM-DD"
  const m = String(ymd).match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return null;

  const y = parseInt(m[1], 10);
  const mo = parseInt(m[2], 10);
  const d = parseInt(m[3], 10);
  return new Date(y, mo - 1, d, 0, 0, 0, 0);
}

function parseSynologyAlarm(trigger, startDateStr, isAllDay) {
  if (!trigger) return null;

  const t = String(trigger).trim().toUpperCase();

  // 당일 양수 PT6H 처리
  const positive = t.match(/^PT(\d+)H$/);
  if (positive && isAllDay) {
    const hour = parseInt(positive[1], 10);
    return `0d-${hour}h`;
  }

  // 음수 총시간 -PT15H 처리
  const total = t.match(/^-PT(\d+)H$/);
  if (total && isAllDay) {
    const totalHours = parseInt(total[1], 10);

    const dayDiff = Math.floor(totalHours / 24);
    const remain  = totalHours % 24;

    const alarmHour = 24 - remain;

    if (alarmHour === 24) {
      return `${dayDiff}d-0h`;
    }

    return `${dayDiff + 1}d-${alarmHour}h`;
  }

  // 기존 -P1DT9H 형식 처리
  const legacy = t.match(/^-P(?:(\d+)D)?T(\d+)H$/);
  if (legacy) {
    const days  = parseInt(legacy[1] || 0, 10);
    const hours = parseInt(legacy[2] || 0, 10);
    return `${days}d-${hours}h`;
  }

  return null;
}


  


  /* =====================================================
  * 📎 File Icon Resolver (Edit Modal)
  ===================================================== */
  function getFileIconByName(file) {
    const name = String(file || '');
    const ext = name.split('.').pop().toLowerCase();

    switch (ext) {
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
      case 'webp':
        return '🖼️';

      case 'pdf':
        return '📄';

      case 'xls':
      case 'xlsx':
      case 'csv':
        return '📊';

      case 'doc':
      case 'docx':
        return '📝';

      case 'ppt':
      case 'pptx':
        return '📽️';

      case 'zip':
      case 'rar':
      case '7z':
        return '🗜️';

      default:
        return '📎';
    }
  }





  /* =====================================================
  * 🔔 Event Reminders (STATE ONLY)
  ===================================================== */
  const REMINDER_STATE = {
    bound: false,
  };

  function ensureReminderUI() {
    const wrap = document.getElementById('event-reminders');
    if (!wrap) return null;

    const addBtn = wrap.querySelector('.shint-btn');
    if (!addBtn) return null;

    // ✅ 템플릿 row(HTML에 있는 1개)는 "템플릿"로만 쓰고 렌더링 때 제거
    const tpl = wrap.querySelector('.evt-reminder-row');

    if (!REMINDER_STATE.bound) {
      REMINDER_STATE.bound = true;

      addBtn.addEventListener('click', () => {
        if (remindersState.length >= MAX_REMINDERS) return;
      
        // 🔥 at 제외한 첫 사용 가능 옵션 찾기
        const used = new Set(remindersState);
        const next = EDIT_REMINDER_OPTIONS.find(
          o => !used.has(o.value)
        );
        
        if (!next) return;
      
        remindersState.push(next.value);
        normalizeReminders();
        renderReminders();
      });
      
    }

    return { wrap, addBtn, tpl };
  }

  function normalizeReminders() {

    remindersState = (remindersState || []).filter(v => {
  
      if (EDIT_REMINDER_OPTIONS.some(o => o.value === v)) {
        return true;
      }
  
      // 🔥 여기 추가
      ensureOptionExists(v);
      return true;
    });
  
    const seen = new Set();
    remindersState = remindersState.filter(v => {
      if (seen.has(v)) return false;
      seen.add(v);
      return true;
    });
  
    if (remindersState.length > MAX_REMINDERS) {
      remindersState = remindersState.slice(0, MAX_REMINDERS);
    }
  }
  

  function renderReminders() {
    const ui = ensureReminderUI();
    if (!ui) return;

    const { wrap, addBtn, tpl } = ui;

    // ✅ 템플릿 포함 모든 row 제거 후 재생성
    wrap.querySelectorAll('.evt-reminder-row').forEach(r => r.remove());

    remindersState.forEach((val, idx) => {
      const row = document.createElement('div');
      row.className = 'evt-reminder-row shint-inline is-open';

      const select = document.createElement('select');
      select.className = 'shint-select';

      const options = buildReminderOptions(val);

      options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt.value;
        o.textContent = opt.label;
        if (opt.value === val) o.selected = true;
        select.appendChild(o);
      });
      

      select.addEventListener('change', () => {
        remindersState[idx] = select.value;
      
        normalizeReminders();   // 중복 제거
        renderReminders();      // 🔥 모든 select 다시 재계산
      });
      

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'shint-iconbtn';
      remove.textContent = '−';
      remove.addEventListener('click', () => {
        remindersState.splice(idx, 1);
        normalizeReminders();
        renderReminders();
      });

      row.appendChild(select);
      row.appendChild(remove);
      wrap.insertBefore(row, addBtn);
    });

    // ✅ 템플릿 row는 남겨두지 않는다(혼선 방지)
    tpl?.remove();

    // + 버튼 제한
    addBtn.disabled = remindersState.length >= MAX_REMINDERS;

    // 상태 클래스 동기화
    const has = remindersState.length > 0;
    wrap.classList.toggle('is-open', has);
    modal.classList.toggle('has-reminder', has);
  }

  /* =====================================================
  * ✅ 모달 열릴 때 reminders 초기화 + 렌더
  ===================================================== */
  function initRemindersFromData() {
    // ❌ remindersState를 여기서 건드리지 않는다
    // 이미 openEventEditModal에서 세팅됨
  
    normalizeReminders();
    renderReminders();
  
    // 편집 모드 활성화
    document
      .querySelectorAll('#event-reminders select, #event-reminders button')
      .forEach(el => el.disabled = false);
  }
  

  function buildReminderOptions(currentValue) {
    const used = new Set(remindersState);
  
    return EDIT_REMINDER_OPTIONS.filter(opt => {
      // 현재 row 값은 항상 유지
      if (opt.value === currentValue) return true;
  
      // 다른 row에서 쓰는 값은 제거
      return !used.has(opt.value);
    });
  }

  function buildAlarms(remindersState, isAllDay) {
    return (remindersState || []).map(v => {
      if (v === 'at') return { trigger: 'PT0S' };
  
      const m = v.match(/^(\d+)([dw])-(\d+)h$/);
      if (!m) return null;
  
      let [, dRaw, unit, hRaw] = m;
      let days  = parseInt(dRaw, 10);
      let hour  = parseInt(hRaw, 10);
  
      if (unit === 'w') days *= 7;
  
      // ✅ 종일 이벤트: "달력 기준 시각" 규칙
      // - 1d-9h  => 전날 09:00 => -PT(24-9)= -PT15H
      // - 2d-9h  => 이틀전 09:00 => -PT(48-9)= -PT39H
      // - 1d-21h => 전날 21:00 => -PT(24-21)= -PT3H
      // - 0d-9h  => 당일 09:00 =>  PT9H (양수)
      if (isAllDay) {
        if (days === 0) {
          return { trigger: `PT${hour}H` };   // 당일 HH:00
        }
        const totalHours = (days * 24) - hour; // 🔥 핵심
        return { trigger: `-PT${totalHours}H` };
      }
  
      // ✅ 일반 이벤트(시간 이벤트): 기존 방식 유지(상대시간)
      return { trigger: `-P${days}DT${hour}H` };
    }).filter(Boolean);
  }
  
  
  
  
  


  function normalizeAllDayEndForSave(start, end, isAllDay) {
    if (!isAllDay) return end;
  
    if (!start) return null;
  
    // 🔥 종료일이 없으면 하루짜리
    if (!end) return start;
  
    // 🔥 UI는 inclusive
    // 🔥 서버는 DATE exclusive로 +1 day 처리함
    // 👉 JS에서는 그대로 end 보내면 됨
  
    return end;
  }
  
  


// =====================================================
// 🔁 반복 이벤트 scope 선택
// =====================================================

function askRepeatScopeAdvanced() {
  return new Promise(resolve => {

    const html = `
      <div class="repeat-scope-overlay">
        <div class="repeat-scope-box">
          <h3>반복 이벤트 편집</h3>

          <label>
            <input type="radio" name="repeatScope" value="single" checked>
            이 이벤트만
          </label>

          <label>
            <input type="radio" name="repeatScope" value="future">
            이 이벤트 및 다음 이벤트 모두
          </label>

          <label>
            <input type="radio" name="repeatScope" value="all">
            모든 이벤트
          </label>

          <div class="actions">
            <button id="repeatCancel">취소</button>
            <button id="repeatApply">적용</button>
          </div>
        </div>
      </div>
    `;

    const wrap = document.createElement('div');


    wrap.style.position = 'fixed';
    wrap.style.inset = '0';
    wrap.style.zIndex = '99999999';
    wrap.style.display = 'block';
    const box = wrap.querySelector('.repeat-scope-box');
    if (box) {
      box.style.position = 'absolute';
      box.style.left = '50%';
      box.style.top = '50%';
      box.style.transform = 'translate(-50%, -50%)';
      box.style.zIndex = '100000000';
    }



    wrap.innerHTML = html;
    document.body.appendChild(wrap);

    wrap.querySelector('#repeatCancel').onclick = () => {
      wrap.remove();
      resolve(null);
    };

    wrap.querySelector('#repeatApply').onclick = () => {
      const val = wrap.querySelector('input[name="repeatScope"]:checked').value;
      wrap.remove();
      resolve(val);
    };

  });
}

function ensureOptionExists(value) {

  if (!value || value === 'at') return;

  const m = value.match(/^(\d+)([dw])-(\d+)h$/);
  if (!m) return;

  let [, dRaw, unit, hRaw] = m;

  let days  = parseInt(dRaw, 10);
  let hours = parseInt(hRaw, 10);

  // 🔥 시간 정규화 (24 이상 방지)
  if (hours >= 24) {
    days += Math.floor(hours / 24);
    hours = hours % 24;
  }

  const normalized = `${days}${unit}-${hours}h`;

  if (EDIT_REMINDER_OPTIONS.some(o => o.value === normalized)) return;

  let label = '';

  if (unit === 'w') {
    label = `시작 ${days}주 전 ${hours}:00`;
  } else {
    if (days === 0) {
      label = `${hours}:00 당일`;
    } else {
      label = `시작 ${days}일 전 ${hours}:00`;
    }
  }

  EDIT_REMINDER_OPTIONS.push({
    value: normalized,
    label
  });
}

function normalizeStartEnd(changedField = null) {

  const allDay = document.getElementById('event-allday')?.checked === true;

  const startDateEl = document.getElementById('event-start-date');
  const endDateEl   = document.getElementById('event-end-date');
  const startTimeEl = document.getElementById('event-start-time');
  const endTimeEl   = document.getElementById('event-end-time');

  if (!startDateEl?.value || !endDateEl?.value) return;

  const sDate = createLocalDateFromYMD(startDateEl.value);
  const eDate = createLocalDateFromYMD(endDateEl.value);
  if (!sDate || !eDate) return;

  if (!allDay) {
    const [sh, sm] = (startTimeEl?.value || '00:00').split(':').map(Number);
    const [eh, em] = (endTimeEl?.value   || '00:00').split(':').map(Number);

    sDate.setHours(sh, sm, 0, 0);
    eDate.setHours(eh, em, 0, 0);
  }

  if (sDate.getTime() <= eDate.getTime()) {
    return; // 정상
  }

  // 🔥 역전 발생

  if (changedField === 'start') {
    // start를 건드렸으면 → end를 맞춘다
    endDateEl.value = startDateEl.value;

    if (!allDay && startTimeEl && endTimeEl) {
      endTimeEl.value = startTimeEl.value;
    }

  } else if (changedField === 'end') {
    // end를 건드렸으면 → start를 맞춘다
    startDateEl.value = endDateEl.value;

    if (!allDay && startTimeEl && endTimeEl) {
      startTimeEl.value = endTimeEl.value;
    }

  } else {
    // fallback (안전)
    endDateEl.value = startDateEl.value;
    if (!allDay && startTimeEl && endTimeEl) {
      endTimeEl.value = startTimeEl.value;
    }
  }
}





function extractRRuleFromIcs(ics) {
  if (!ics) return null;
  const s = String(ics);

  // RRULE은 줄바꿈 folding 될 수 있음(다음 줄이 space로 시작)
  // 일단 RRULE 시작 라인부터 다음 속성 라인 전까지 잡아 붙인다.
  const m = s.match(/(?:^|\r?\n)RRULE:(.+)(?:\r?\n(?![ \t].*)(?![A-Z-]+:)|$)/i);
  if (!m) return null;

  // folding(줄 시작이 공백/탭) 제거
  let r = m[1].replace(/\r?\n[ \t]/g, '');
  return normalizeRRuleString(r);
}



function getRRuleOptionsFromFcWrapper(rruleWrap) {
  if (!rruleWrap || typeof rruleWrap !== 'object') return null;

  // 1) 혹시 직접 options가 있으면 그게 최우선
  if (rruleWrap.options && typeof rruleWrap.options === 'object') return rruleWrap.options;

  // 2) rruleSet이 있으면 내부 rule options를 꺼낸다 (rrule.js 구조 대응)
  const rs = rruleWrap.rruleSet || rruleWrap._rruleSet || null;
  if (!rs) return null;

  // rrules() 제공하는 경우
  if (typeof rs.rrules === 'function') {
    const rules = rs.rrules();
    if (rules && rules[0] && rules[0].options) return rules[0].options;
  }

  // 내부 _rrule 배열을 직접 쓰는 경우
  if (Array.isArray(rs._rrule) && rs._rrule[0]?.options) {
    return rs._rrule[0].options;
  }

  // 최후: rs.options (dtstart 포함한 공통 옵션일 수 있음)
  if (rs.options && typeof rs.options === 'object') return rs.options;

  return null;
}






 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


  // 🔥 캘린더 목록 즉시 렌더 (Quick → Edit 대응)
  document.addEventListener('calendar:ready', () => {
    renderEventCalendarList(
      window.CalendarContext?.calendars || [],
      document.getElementById('event-calendar-id')?.value || null
    );
  });

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////




  /* =====================================================
   * Open Event Create / Edit Modal
   * (called from Quick Modal or Calendar)
   * ===================================================== */
  AppCore.openEventEditModal = function (data = {}) {


    if (!modal) {
      console.error('[EventModal] modal-event-edit not found');
      return;
    }
  
    // 🔥 여기 추가
    const locInput = modal.querySelector('#event-location');
    if (locInput && window.LocationAutocomplete) {
      window.LocationAutocomplete.bind('#event-location');
    }

    console.group('[EDIT MODAL OPEN]');
    console.log('data:', data);
    console.log('data.alarms:', data.alarms);
    console.log('extendedProps.alarms:', data.extendedProps?.alarms);
    console.log('raw.VALARM:', data.extendedProps?.raw?.raw?.VALARM);

    console.log('remindersState:', remindersState);
    console.log('EDIT_REMINDER_OPTIONS:', EDIT_REMINDER_OPTIONS);
    console.log('🔥 BEFORE initRemindersFromData:', remindersState);
    console.log('🔥 initRemindersFromData START:', remindersState);

    const stamp = Date.now() + ':' + Math.random().toString(16).slice(2);
    console.groupCollapsed('🧨 openEventEditModal CALL', stamp);
    console.log('data.__mode:', data?.__mode);
    console.log('data.id:', data?.id);
    console.log('data.href:', data?.href);
    console.trace('CALL STACK'); // ✅ 누가 호출했는지 바로 잡힘
    console.groupEnd();




    console.groupEnd();








    


  






      // =====================================================
      // 🔑 UPDATE 필수: caldav object href 주입 + 단일 mode 결정(안전)
      // =====================================================
      const form = modal.querySelector('#event-form');

      // ✅ 여기서는 ep/raw를 "중복 선언"하지 말고 안전하게 읽기만
      const _ep  = data.extendedProps || {};
      const _raw =
        _ep.raw?.raw ||
        _ep.raw ||
        data.raw ||
        {};

      // ✅ UID: Synology UID 우선, 마지막에 data.id fallback
      const resolvedUid =
        _ep.uid ||
        _raw?.UID?.value ||
        _raw?.uid ||
        data.uid ||
        data.id ||
        null;

      // ✅ href: update에 필수
      const href =
        _ep.href ||
        _ep.caldav_href ||
        _ep.object_href ||
        _raw?.href ||
        _raw?.['X-SYNO-HREF']?.value ||
        data.href ||
        '';

        const requestedMode =
        (data?.__mode === 'edit') ? 'edit'
        : (data?.__mode === 'create') ? 'create'
        : (data?.id ? 'edit' : 'create');   // fallback
      
      if (form) {
        form.dataset.eventId = resolvedUid || '';
        form.dataset.href    = href || '';
        form.dataset.mode    = requestedMode;  // ✅ href와 무관하게 강제
      }
      
      console.log('[MODE RESOLVE]', {
        resolvedUid,
        href,
        mode: form?.dataset.mode,
        requestedMode,
        __mode: data?.__mode
      });

      console.log('[MODE RESOLVE]', {
        resolvedUid,
        href,
        mode: form?.dataset.mode
      });
      
    

    if (!modal) {
      console.error('[EventModal] modal-event-edit not found');
      return;
    }

    const titleEl = modal.querySelector('.shint-modal__title');

    const mode = (form?.dataset.mode === 'edit') ? 'edit' : 'create';


      //캘린더 이름
      const calBtn = document.getElementById('event-calendar-btn');

      if (calBtn) {
        if (mode === 'edit') {
          calBtn.dataset.readonly = 'true';
        } else {
          delete calBtn.dataset.readonly;
        }
      }
      



    /* ===============================
     * Modal Title
     * =============================== */
    if (titleEl) {
      titleEl.textContent =
        mode === 'edit'
          ? '이벤트 편집'
          : '이벤트 생성';
    }

    const ep  = data.extendedProps || {};
    const raw = ep.raw?.raw || ep.raw || {};

              
    // 🔥 RRULE 최종 추출 (딱 1번만)
    let rruleRaw = null;

    // 0) raw에서 RRULE 직접
    rruleRaw = normalizeRRuleString(normalizeRRule(raw.RRULE));

    // 1) raw.ics에서 RRULE 파싱
    if (!rruleRaw) {
      const ics =
        raw.ics ||
        raw.ICS ||
        ep.raw?.ics ||
        ep.raw?.ICS ||
        null;

      rruleRaw = extractRRuleFromIcs(ics);
    }

    // 2) FullCalendar rrule string
    if (!rruleRaw && typeof data.rrule === 'string') {
      rruleRaw = normalizeRRuleString(data.rrule);
    }

    // 3) FullCalendar rrule wrapper
    if (!rruleRaw && data.rrule && typeof data.rrule === 'object') {
      const opts = getRRuleOptionsFromFcWrapper(data.rrule);
      rruleRaw = buildRRuleFromRRuleOptions(opts);
    }

    // repeatState 동기화 (단일 진실)
    repeatState.rrule  = rruleRaw;
    repeatState.source = rruleRaw ? 'custom' : 'none';

    // dataset 저장(저장은 여기서 1번만)
    const eventFormEl = document.getElementById('event-form');
    if (eventFormEl) {
      eventFormEl.dataset.isRecurring = rruleRaw ? '1' : '0';
      eventFormEl.dataset.rruleRaw    = rruleRaw || '';
    }

    console.log('✅ rruleRaw(final):', rruleRaw);




    //제목
    setVal(
      'event-title',
      data.title ||
      raw.SUMMARY?.value ||
      ''
    );

    //날짜
    const start =
      raw.DTSTART?.value ||
      data.start ||
      null;
    
    const end =
      raw.DTEND?.value ||
      data.end ||
      null;

      // 온종일이벤트 체크박스
      const isAllDay =
        data.allDay === true ||
        raw.DTSTART?.value?.length === 8;

      let startDate;
      let endDate;

      const isRecurring = !!rruleRaw;

      if (isAllDay) {

        const formatYMD = (v) => {
          if (!v) return null;
          if (/^\d{8}$/.test(v)) return `${v.slice(0,4)}-${v.slice(4,6)}-${v.slice(6,8)}`;
          if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
          return null;
        };

        const rawStart = raw.DTSTART?.value || null;
        const rawEnd   = raw.DTEND?.value   || null;

        startDate = formatYMD(rawStart);

        // ✅ 반복이든 아니든, DTEND가 있으면 기간을 유지한다
        endDate = formatYMD(rawEnd) || startDate;

        // ✅ Synology DTEND(exclusive) → UI(inclusive) 보정
        // (DTEND가 start보다 큰 경우에만 -1)
        if (startDate && endDate) {
          const s = createLocalDateFromYMD(startDate);
          const e = createLocalDateFromYMD(endDate);

          if (s && e && e.getTime() > s.getTime()) {
            e.setDate(e.getDate() - 1);
            endDate = formatDate(e);
          }
        }

        if (!endDate) endDate = startDate;

      } else {

        const parsedStart = parseDate(start);
        const parsedEnd   = parseDate(end);

        startDate = parsedStart?.slice(0,10);
        endDate   = parsedEnd?.slice(0,10);
      }
      
      setVal('event-start-date', startDate);
      setVal('event-end-date',   endDate);
  
    // ✅ alarms 파싱용 startDateStr (UI에 세팅된 값을 기준으로)
    // - raw DTSTART가 YYYYMMDD(종일)든 DATE-TIME이든 상관없이
    // - 최종 UI start-date 값을 사용
    const startDateStr = document.getElementById('event-start-date')?.value || startDate || null;

    // 시간
    if (parseDate(start)?.includes('T')) {
      setVal('event-start-time', parseDate(start).slice(11,16));
    }
    if (parseDate(end)?.includes('T')) {
      setVal('event-end-time', parseDate(end).slice(11,16));
    }


    setChecked('event-allday', isAllDay);


    // 사용자(시놀로지 계정정보)
    const syno =
      window.CalendarContext?.externalAccounts?.synology;

    const ownerEl = document.getElementById('event-owner');
    if (ownerEl) {
      ownerEl.textContent =
        syno?.external_login_id
          ? `Synology · ${syno.external_login_id}`
          : '담당자';
    } 

    // 반복 버튼
    const repeatBtn  = document.getElementById('event-repeat-btn');
    const repeatMenu = document.getElementById('event-repeat-menu');

    function normalizeRRule(raw) {
      if (!raw) return null;
    
      // 배열
      if (Array.isArray(raw)) {
        return raw[0]?.value || null;
      }
    
      // 객체
      if (typeof raw === 'object') {
        return raw.value || null;
      }
    
      // 문자열
      if (typeof raw === 'string') {
        return raw;
      }
    
      return null;
    }
    

console.log('✅ rruleRaw(final):', rruleRaw);
console.log('✅ raw.RRULE:', raw.RRULE);
console.log('✅ raw.ics has:', !!(raw.ics || raw.ICS || ep.raw?.ics || ep.raw?.ICS));






    if (repeatBtn) {
      const labelEl = repeatBtn.querySelector('.evt-repeat-label');
      if (labelEl) {
        labelEl.textContent = formatRRuleSafe(rruleRaw);
      }
    }

    if (repeatBtn && repeatMenu && !repeatBound) {
      repeatBound = true;

      // 버튼 → 메뉴 열기
      repeatBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
      
        const willOpen = repeatMenu.hidden;
        repeatMenu.hidden = !repeatMenu.hidden;
      
        // 🔥 열릴 때만 스크롤을 맨 아래로
        if (willOpen) {
          requestAnimationFrame(() => {
            repeatMenu.scrollTop = repeatMenu.scrollHeight;
          });
        }
      });
      

      // 메뉴 클릭 (이벤트 위임)
      repeatMenu.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-preset]');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const preset  = btn.dataset.preset;
        const labelEl = repeatBtn.querySelector('.evt-repeat-label');

        // ======================
        // 사용자 지정
        // ======================
        if (preset === 'custom') {
          repeatMenu.hidden = true;

          AppCore.openEventEditRepeatModal?.({
            rrule: repeatState.rrule,          // ⭐ 기존 RRULE 그대로
            startDate: raw.DTSTART?.value || null
          });
          return;
        }

        // ======================
        // 반복 없음
        // ======================
        if (preset === 'none') {
          repeatState.rrule  = null;
          repeatState.source = 'none';

          if (labelEl) labelEl.textContent = '반복되지 않음';
          repeatMenu.hidden = true;
          return;
        }

        // ======================
        // 프리셋 RRULE 생성
        // ======================
        const PRESET_RRULE = {
          daily:   'FREQ=DAILY',
          weekly:  'FREQ=WEEKLY',
          monthly: 'FREQ=MONTHLY',
          yearly:  'FREQ=YEARLY'
        };

        repeatState.rrule  = PRESET_RRULE[preset];
        repeatState.source = 'preset';

        if (labelEl) {
          labelEl.textContent = formatRRuleSafe(repeatState.rrule);
        }

        repeatMenu.hidden = true;
      });
    }

    
    
    
    


    //위치
    setVal('event-location', raw.LOCATION?.value || ep.location || '');




    // 🔹 현재 이벤트의 캘린더 정보
    const calendarId =
      data.extendedProps?.calendar_id ||
      data.extendedProps?.calendarId ||
      raw['X-SYNO-CAL-ID']?.value ||
      null;

    const calendars = window.CalendarContext?.calendars || [];

    const currentCal = calendars.find(c =>
      String(c.calendar_id || c.id) === String(calendarId)
    );

    const calendarColorEl = document.getElementById('event-calendar-color');
    const calendarNameEl  = document.getElementById('event-calendar-name');
    const calendarIdInput = document.getElementById('event-calendar-id');

    if (currentCal) {
      if (calendarColorEl) {
        const currentId = String(currentCal.calendar_id || currentCal.id);

        calendarColorEl.style.backgroundColor =
          currentCal.admin_calendar_color ||
          CalendarStore.getCalendarColor?.(currentId) ||
          currentCal.color ||
          currentCal.calendar_color ||
          '#9CA3AF';
      }

      if (calendarNameEl) {
        calendarNameEl.textContent =
          currentCal.name ||
          currentCal.calendar_name ||
          currentCal.displayname ||
          '캘린더';
      }

      if (calendarIdInput) {
        calendarIdInput.value = currentCal.calendar_id || currentCal.id;
      }
    } else {
      if (calendarColorEl) calendarColorEl.style.backgroundColor = '#9CA3AF';
      if (calendarNameEl)  calendarNameEl.textContent = '캘린더';
    }







    if (calBtn && !calendarBtnBound) {
      calendarBtnBound = true;
    
      calBtn.addEventListener('mousedown', (e) => {
    
        e.preventDefault();
        e.stopPropagation();
    
        const calList = document.getElementById('event-calendar-list');
        if (!calList) return;
    
        if (!calList.hidden) {
          calList.hidden = true;
          return;
        }
    
        const calendars = window.CalendarContext?.calendars || [];
        const currentId =
          document.getElementById('event-calendar-id')?.value || null;
    
        renderEventCalendarList(calendars, currentId);
    
        if (calList.parentNode !== document.body) {
          document.body.appendChild(calList);
        }
    
        calList.hidden = false;
    
        requestAnimationFrame(() => {
          positionPickerBelowInput(calList, calBtn, 6);
    
          const w = calBtn.offsetWidth;
          calList.style.width =
          calList.style.minWidth =
          calList.style.maxWidth = `${w}px`;
        });
      });
    }
    
     



      /* ===============================
      * 🎨 Event Color BUTTON SYNC (ADMIN 기준 최종본)
      * =============================== */
      const colorDot = document.getElementById('event-color-dot');
      const colorInp = document.getElementById('event-color');

      /**
       * 🔑 색상 원본 우선순위
       * 1) admin_event_color (ERP)
       * 2) event_color (Synology 최초)
       * 3) calendar_color
       */
      const rawAdminColor =
        ep.admin_event_color ?? null;

      const rawEventColor =
        ep.event_color ??
        ep.color ??
        null;

      // 정규화
      const adminColor = normalizeColor(rawAdminColor);
      const eventColor = normalizeColor(rawEventColor);

      // 캘린더 기본색
      const currentId = String(currentCal?.calendar_id || currentCal?.id || '');

      const calendarBaseColor =
        normalizeColor(
          currentCal?.admin_calendar_color ||
          CalendarStore.getCalendarColor?.(currentId) ||
          currentCal?.color ||
          currentCal?.calendar_color
        ) || '#9CA3AF';

      // ===============================
      // 🎯 단일 진실 상태 갱신
      // ===============================
      eventColorState.calendarColor = calendarBaseColor;

      // ERP 컬러가 있으면 무조건 우선
      if (adminColor) {
        eventColorState.color = adminColor;
        eventColorState.effectiveColor = adminColor;
        eventColorState.source = 'admin';
      }
      // ERP 컬러 없고, Synology 이벤트 컬러만 있는 경우
      else if (eventColor) {
        eventColorState.color = null;              // ❗ 직접 선택 아님
        eventColorState.effectiveColor = eventColor;
        eventColorState.source = 'calendar-event';
      }
      // 둘 다 없으면 캘린더 기본색
      else {
        eventColorState.color = null;
        eventColorState.effectiveColor = calendarBaseColor;
        eventColorState.source = 'calendar';
      }

      // ===============================
      // 🎨 버튼 DOT 반영
      // ===============================
      if (colorDot) {
        if (adminColor) {
          // ✅ ERP 컬러가 있을 때만 "선택됨"
          colorDot.style.backgroundColor = adminColor;
          colorDot.classList.remove('is-none');
        } else {
          // ❌ Synology / calendar 색은 점 표시 안 함
          colorDot.style.backgroundColor = '';
          colorDot.classList.add('is-none');
        }
      }

      // ===============================
      // 📦 hidden input (ERP 저장용)
      // ===============================
      if (colorInp) {
        // 🔥 오직 admin_event_color만 담는다
        colorInp.value = adminColor || '';
      }


    // 🔔 이벤트 알람
    remindersState = [];
    


    function getExValue(ex, key) {
      return (
        ex?.[key] ??
        ex?.raw?.[key] ??
        ex?.raw?.raw?.[key] ??
        null
      );
    }
    
    const alarmsRaw =
    data.alarms ??
    data.extendedProps?.alarms ??   // 🔥 이 줄 추가
    getExValue(ep, 'alarms') ??
    getExValue(ep, 'VALARM') ??
    [];
  
    

    const alarms = Array.isArray(alarmsRaw)
      ? alarmsRaw
      : [alarmsRaw];

      alarms.forEach(a => {

        // 🔥 모든 케이스 안전 처리
        let rawTrigger = null;
      
        if (!a) return;
      
        // 1️⃣ 가장 일반적인 형태
        if (typeof a.trigger === 'string') {
          rawTrigger = a.trigger;
        }
      
        // 2️⃣ Synology RAW 구조
        else if (a.TRIGGER?.value) {
          rawTrigger = a.TRIGGER.value;
        }
      
        // 3️⃣ nested raw
        else if (a.raw?.TRIGGER?.value) {
          rawTrigger = a.raw.TRIGGER.value;
        }
      
        if (!rawTrigger) return;
      
        const parsed = parseSynologyAlarm(
          rawTrigger,
          startDateStr,
          isAllDay
        );
        



      
        console.log('🔥 rawTrigger:', rawTrigger);
        console.log('🔥 parsed:', parsed);
      
        if (parsed) {
          ensureOptionExists(parsed);
          remindersState.push(parsed);
        }
      });

      
      normalizeReminders();
renderReminders();



    //설명
    setVal(
      'event-desc',
      raw.DESCRIPTION?.value ||
      ep.description ||
      ''
    );

    //첨부파일(버튼)
    const uploadBtn = modal.querySelector('.shint-btn--light');
    if (uploadBtn) {
      uploadBtn.onclick = () => {
        AppCore.notify('warn',
          'ERP에서는 파일 업로드가 불가능합니다.\nSynology에서 관리해주세요.'
        );
      };
    }   

    //첨부파일(링크)
    const attachWrap = uploadBtn?.parentNode;

    attachWrap
      ?.querySelectorAll('.shint-file-row')
      .forEach(el => el.remove());

    const att = raw.ATTACH;
    if (att && attachWrap) {
      const list = Array.isArray(att) ? att : [att];

      list.forEach(a => {
        const row = document.createElement('div');
        row.className = 'shint-file-row';

        const ico = document.createElement('span');
        ico.className = 'shint-file-ico';
        ico.textContent = getFileIconByName(
          a.params?.['X-SYNO-REL-URI'] || a.value
        );

        const link = document.createElement('a');
        link.href = a.value;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = decodeURIComponent(
          a.params?.['X-SYNO-REL-URI']?.split('/').pop()
          || a.value.split('/').pop()
        );

        row.append(ico, link);
        attachWrap.appendChild(row);
      });
    }

    /* ===============================
     * Show Modal
     * =============================== */
    modal.classList.remove('is-hidden');
    document.body.classList.add('is-modal-open');


    initEventEditPickers(modal);


    
  };



  // =====================================================
  // 🔁 반복설정 모달 → 수정모달 값 전달 브리지
  // =====================================================
  AppCore.applyRepeatFromCustom = function (rrule) {
    console.log('[EventEditModal] applyRepeatFromCustom:', rrule);

    // 1️⃣ 상태 갱신 (단일 진실)
    repeatState.rrule  = rrule;
    repeatState.source = 'custom';

    // 2️⃣ 반복 버튼 라벨 갱신
    const repeatBtn = document.getElementById('event-repeat-btn');
    if (repeatBtn) {
      const labelEl = repeatBtn.querySelector('.evt-repeat-label');
      if (labelEl) {
        labelEl.textContent = formatRRuleSafe(rrule);
      }
    }

    // 3️⃣ 반복 메뉴 닫기 (열려 있었다면)
    const repeatMenu = document.getElementById('event-repeat-menu');
    if (repeatMenu) repeatMenu.hidden = true;
  };


 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


  // =====================================================
  // 💾 Event Edit Modal SAVE (Create / Update)
  // =====================================================
  const eventForm = document.getElementById('event-form');

  if (eventForm) {

      // ✅ submit 중복 바인딩 방지
      if (eventForm.dataset.submitBound === '1') return;
      eventForm.dataset.submitBound = '1';

        eventForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          try {
            /* ===============================
            * 1️⃣ mode / eventId 먼저
            * =============================== */
            const safe = v => {
              if (v === undefined || v === null) return null;
              if (v === 'null' || v === 'undefined') return null;
              return v;
            };   

            const mode = eventForm.dataset.mode || 'create';

            const eventId = safe(eventForm.dataset.eventId);
            const href    = safe(eventForm.dataset.href);



            console.log('[EVENT SAVE MODE]', mode, eventId);

            /* ===============================
            * 2️⃣ 날짜 / 시간 계산
            * =============================== */
            const allDay = document.getElementById('event-allday')?.checked === true;

            const buildISO = (date, time, allDay) => {
              if (!date) return null;
            
              if (allDay) {
                return date;   // 🔥 여기서 끝
              }
            
              return `${date}T${time || '00:00'}`;
            };
            

            let start = buildISO(
              document.getElementById('event-start-date')?.value,
              document.getElementById('event-start-time')?.value,
              allDay
            );
            
            let end = buildISO(
              document.getElementById('event-end-date')?.value,
              document.getElementById('event-end-time')?.value,
              allDay
            );
            
            // 🔥 종일 이벤트 보정 (여기서!)
            if (allDay) {
              end = normalizeAllDayEndForSave(start, end, true);
            }
            
            


            //calendar_id 기준으로 실제 캘린더 찾기
            const calendarId =
            document.getElementById('event-calendar-id')?.value || null;
          
          const calendars = window.CalendarContext?.calendars || [];
          
          const selectedCal = calendars.find(c =>
            String(c.calendar_id || c.id) === String(calendarId)
          );
          
          if (!selectedCal) {
            AppCore.notify('error',
              '캘린더 정보를 찾을 수 없습니다.'
            );
            return;
          }
          
          //create 모드일 때만 collection_href 계산
          let collectionHref = null;

          if (mode === 'create') {
            if (!selectedCal.href) {
              AppCore.notify('error',
                '캘린더 경로(collection)가 없습니다.'
              );
              return;
            }
          
            collectionHref = selectedCal.href;
          
            // 🔥 개인 캘린더 alias 보정 (퀵모달과 동일)
            if (
              selectedCal.alias_target_href &&
              /\/home\/?$/.test(collectionHref)
            ) {
              collectionHref = selectedCal.alias_target_href;
            }
          }
          

            /* ===============================
            * 3️⃣ payload 생성 (🔥 여기서 처음!)
            * =============================== */
            const payload = {
              title: document.getElementById('event-title')?.value?.trim(),
              calendar_id: document.getElementById('event-calendar-id')?.value,
              description: document.getElementById('event-desc')?.value || '',
              location: document.getElementById('event-location')?.value || null, // ✅ 추가
              allDay,
              start,
              end,
              admin_event_color: document.getElementById('event-color')?.value || null,
              alarms: buildAlarms(remindersState, allDay),
              rrule: repeatState.rrule || null,
              __source: 'ui'
            };
            



            // 🔥 edit 전용 필드
            if (mode === 'edit') {
              payload.uid  = eventId;
              payload.href = href;
            }

            // 🔥 create 전용 필드
            if (mode === 'create') {
              payload.collection_href = collectionHref;
            }        

            console.log('[EVENT SAVE PAYLOAD]', payload);

            /* ===============================
            * 4️⃣ 검증 (여기서 모두 끝냄)
            * =============================== */
            if (!payload.title || !payload.calendar_id) {
              AppCore.notify('warn',
                '제목 또는 캘린더가 없습니다.'
              );
              return;
            }

            if (mode === 'edit' && !payload.href) {
              alert('이 이벤트는 href가 없어 수정할 수 없습니다.\n(동기화 데이터 누락)');
              console.error('[UPDATE BLOCKED - NO HREF]', payload);
              return;
            }





                        
            /* ===============================
            * 5️⃣ 반복 scope 먼저 처리 (정상 로직)
            * =============================== */

            if (mode === 'edit') {

              const wasRecurring  = eventForm.dataset.isRecurring === '1';
              const willRecurring = !!payload.rrule;

              console.log('[RECUR CHECK]', {
                wasRecurring,
                willRecurring,
                datasetIsRecurring: eventForm.dataset.isRecurring,
                payloadRrule: payload.rrule
              });

              // 🔥 기존에 반복이 있었던 경우에만 scope 질문
              if (wasRecurring) {

                console.log('[SCOPE ASK] open');

                const scope = await askRepeatScopeAdvanced();

                console.log('[SCOPE ASK] result:', scope);

                if (!scope) return;

                payload.scope = scope;

                // recurrence_id는 “현재 인스턴스 기준”
                payload.recurrence_id =
                  (document.getElementById('event-start-date')?.value || '')
                    .replaceAll('-', '');
              }

              console.log('[UPDATE] call updateEvent');
              await CalendarAPI.updateEvent(payload);
              console.log('[UPDATE] done');

            } else {

              await CalendarAPI.createEvent(payload);

            }



            /* ===============================
            * 6️⃣ UI 반영 (🔥 정답)
            * =============================== */

            const cal = window.__calendar;
            if (cal && mode === 'edit') {
              const ev = cal.getEventById(eventId);
              if (ev) {
                ev.setProp('title', payload.title);
            
                ev.setStart(payload.start);
                ev.setEnd(payload.end);

                ev.setAllDay(payload.allDay);
            
                ev.setExtendedProp('description', payload.description);
                ev.setExtendedProp('location', payload.location);                
                ev.setExtendedProp('admin_event_color', payload.admin_event_color);
                
                const hadRrule = !!ev.extendedProps?.rrule;
                const willHaveRrule = !!payload.rrule;

                ev.setExtendedProp('rrule', payload.rrule);
                
                if (hadRrule || willHaveRrule) {

                  // 🔥 로컬 조작 절대 금지
                  CalendarAPI.clearCache?.();
                
                  // 🔥 서버 기준으로 완전 재조회
                  cal.refetchEvents();
                
                  AppCore.closeEventEditModal('saved');
                  return;
                }
                
                
                

                if (payload.admin_event_color) {
                  ev.setProp('backgroundColor', payload.admin_event_color);
                  ev.setProp('borderColor', payload.admin_event_color);
                } else {
                  // 색상 제거 시 (캘린더 기본색으로 돌아가게)
                  ev.setProp('backgroundColor', null);
                  ev.setProp('borderColor', null);
                }

              }
            }
            

            // ✅ 캐시는 비워도 됨 (선택)
            CalendarAPI.clearCache?.();

            // ✅ 모달만 닫는다
            window.AppCore.closeEventEditModal('saved');

          } catch (err) {
            console.error('[EVENT SAVE ERROR]', err);
            AppCore.notify('error',
              '이벤트 저장 실패'
            );
          }
        });




  }


  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  document.addEventListener('mousedown', (e) => {
    const datePicker = document.getElementById('today-picker');
    const timePicker = document.getElementById('time-list-picker');
  
    // =========================
    // 📅 Today Picker
    // =========================
    if (
      datePicker &&
      !datePicker.classList.contains('is-hidden')
    ) {
      // 🔥 picker 내부 클릭 보호
      if (datePicker.contains(e.target)) return;
  
      // 🔥 트리거(input) 클릭 보호
      if (e.target.closest('[data-picker="date"]')) return;
  
      datePicker.classList.add('is-hidden');
    }
  
    // =========================
    // ⏰ Time List Picker
    // =========================
    if (
      timePicker &&
      !timePicker.classList.contains('is-hidden')
    ) {
      // 🔥 picker 내부 클릭 보호
      if (timePicker.contains(e.target)) return;
  
      // 🔥 트리거(input) 클릭 보호
      if (e.target.closest('[data-picker="time-list"]')) return;
  
      timePicker.classList.add('is-hidden');
    }
  }, true);
  
  

  document.addEventListener('mousedown', (e) => {
    const palette  = document.getElementById('evt-color-palette');
    const colorBtn = document.getElementById('event-color-btn');
  
    if (!palette || palette.hidden) return;
  
    // ✅ 팔레트 내부 클릭이면 유지
    if (palette.contains(e.target)) return;
  
    // ✅ 색상 버튼 클릭이면(토글 로직이 처리) 유지
    if (colorBtn && colorBtn.contains(e.target)) return;
  
    // ✅ 그 외는 닫기
    palette.hidden = true;
  }, true);



  
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
  
    const today = document.getElementById('today-picker');
    const time = document.getElementById('time-list-picker');
  
    let closed = false;
  
    if (today && !today.classList.contains('is-hidden')) {
      today.classList.add('is-hidden');
      closed = true;
    }
  
    if (time && !time.classList.contains('is-hidden')) {
      time.classList.add('is-hidden');
      closed = true;
    }
  
    // picker 하나라도 닫혔으면 모달은 유지
    if (closed) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);


 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


  // ❌ X 버튼 / 취소 버튼
  modal.addEventListener('click', (e) => {
    if (e.target.closest('[data-close="modal"]')) {
      closeEventEditModal();
    }
  });




  //투데이피커
  modal.querySelectorAll('[data-picker="date"]').forEach(input => {
    input.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
  
      const picker = ensureTodayPicker();
      if (!picker) return;
  
      // 타깃 지정
      picker.__target = input;
  
      // input 값 → picker 날짜 동기화
      const v = input.value;
      if (v) {
        const d = new Date(v);
        if (!isNaN(d)) picker.setDate(d);
      }
  
      // 🔥 이것만 호출
      picker.open({ anchor: input });
    });
  });
  
    
  modal.addEventListener('click', (e) => {
    const wrap = e.target.closest('.evt-time-wrap');
    if (!wrap) return;
  
    const input = wrap.querySelector('input[data-picker="time-list"]');
    if (!input) return;
  
    e.preventDefault();
    e.stopPropagation();
  
    console.log('[time-list] trigger click:', input.id);
  
    const picker = getTimeListPicker();
    if (!picker) return;
  
    // 🔥 타깃 지정
    picker.__target = input;
  
    // 🔥 현재 값 → picker state 동기화
    const [h, m] = (input.value || '09:00')
      .split(':')
      .map(v => parseInt(v, 10));
  
    picker.setTime({
      hour: isNaN(h) ? 9 : h,
      minute: isNaN(m) ? 0 : m
    });
  
    // 🔥 선택 콜백
    picker.onSelect = ({ hour, minute }) => {
  
      console.log('[time-list] TIME PICKED:', hour, minute);
  
      const hh = String(hour).padStart(2, '0');
      const mm = String(minute).padStart(2, '0');
  
      input.value = `${hh}:${mm}`;
  
      requestAnimationFrame(() => {
        normalizeStartEnd(
          input.id.includes('start') ? 'start' : 'end'
        );
      });
  
      picker.close();   // 🔥 반드시 이걸 써야 함
    };
  
    // 🔥 핵심: AdminPicker의 open 시스템 사용
    picker.open({ anchor: input });
  
    // 🔥 active 값 스크롤
    requestAnimationFrame(() => {
      picker.__scrollToActive?.();
    });
  });





  modal.addEventListener('change', (e) => {

    if (e.target.id === 'event-start-date')
      normalizeStartEnd('start');
  
    if (e.target.id === 'event-end-date')
      normalizeStartEnd('end');
  
    if (e.target.id === 'event-start-time')
      normalizeStartEnd('start');
  
    if (e.target.id === 'event-end-time')
      normalizeStartEnd('end');
  });
  




})();