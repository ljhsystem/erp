// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/modal.event.edit.repeat.js'
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
(() => {
  'use strict';

  console.log('[RepeatModal] loaded');

  const modal = document.getElementById('modal-event-edit-repeat');
  if (!modal) return;

  const $ = id => modal.querySelector(id);

  // ✅ 🔥 반드시 여기
  const overlay = document.getElementById('repeat-modal-overlay');

  let state = {
    freq: 'DAILY',        // DAILY | WEEKLY | MONTHLY | YEARLY
    interval: 1,
  
    // WEEKLY / MONTHLY(weekday)
    byday: [],            // ['TH']
  
    // MONTHLY(weekday)
    bysetpos: null,       // 1 | 2 | 3 | 4 | -1
  
    // 종료
    count: null,          // number
    until: null           // YYYY-MM-DD
  };
  
  let todayPicker = null;

  // ============================
  // OPEN
  // ============================
  window.AppCore = window.AppCore || {};

  AppCore.openEventEditRepeatModal = function ({ rrule }) {
    resetUI();
  
    if (rrule) {
      parseRRuleToState(rrule);   // ⭐ 핵심
    }
  
    applyStateToUI();      // state → UI
    syncEndInputs();       // 🔥 종료 라디오/입력 동기화
    toggleByFreq();        // freq 기반 영역 표시
    updateSummary();       // 요약
    
  
    overlay?.classList.remove('is-hidden');
    modal.classList.remove('is-hidden');
    document.body.classList.add('is-repeat-modal-open');
  };
  




  const pad = n => String(n).padStart(2, '0');

  function formatDate(d) {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
  function ensureTodayPicker() {
    if (todayPicker) return todayPicker;
  
    const container = document.getElementById('today-picker');
    if (!container) return null;
  
    todayPicker = AdminPicker.create({
      type: 'today',
      container
    });
  
    todayPicker.subscribe((_, date) => {
      const input = todayPicker.__target;
      if (!input || !date) return;
      input.value = formatDate(date);
  
      // 🔥 날짜 선택 즉시 state 반영
      state.until = input.value;
      updateSummary();
    });
  
    return todayPicker;
  }
  
  const untilInput = $('#repeat-until');

  if (untilInput) {
    untilInput.addEventListener('mousedown', e => {
      if (untilInput.disabled) return;

      e.preventDefault();
      e.stopPropagation();
  
      const picker = ensureTodayPicker();
      const pickerEl = document.getElementById('today-picker');
      if (!picker || !pickerEl) return;
  
      // 🔥 항상 body로 포탈
      if (pickerEl.parentNode !== document.body) {
        document.body.appendChild(pickerEl);
      }
  
      picker.__target = untilInput;
      picker.open();
  
      requestAnimationFrame(() => {
        pickerEl.classList.remove('is-hidden');
        positionPickerBelowInput(pickerEl, untilInput, 8);
      });
    });
  }
  

  function positionPickerBelowInput(pickerEl, input, offset = 8) {
    const rect = input.getBoundingClientRect();
    const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
  
    let left = rect.left + scrollX;
    let top  = rect.bottom + scrollY + offset;
  
    pickerEl.style.position = 'absolute';
    pickerEl.style.left = `${Math.round(left)}px`;
    pickerEl.style.zIndex = '999999';
  
    const pickerH = pickerEl.offsetHeight || 320;
    const vh = window.innerHeight + scrollY;
  
    if (top + pickerH > vh - 8) {
      top = rect.top + scrollY - pickerH - offset;
    }
  
    pickerEl.style.top = `${Math.round(top)}px`;
  }
  





  // ============================
  // CLOSE
  // ============================
  modal.addEventListener('click', e => {
    if (e.target.closest('[data-close="repeat-modal"]')) {
      close();
    }
  });

  function close() {
    modal.classList.add('is-hidden');
    overlay?.classList.add('is-hidden');
  
    document.body.classList.remove('is-repeat-modal-open');
  }
  

  // ============================
  // SAVE
  // ============================
  $('#repeat-save')?.addEventListener('click', () => {
    const rrule = buildRRule();
    console.log('[RepeatModal] SAVE RRULE =', rrule);

    // 🔥 여기서 부모 모달에 반영
    AppCore.applyRepeatFromCustom?.(rrule);

    close();
  });


  function formatUntilForRRule(dateStr, allDay = true) {
    if (!dateStr) return null;
  
    const pure = dateStr.replace(/-/g, '');
  
    // 종일 이벤트
    if (allDay) {
      return pure;
    }
  
    // 시간 이벤트 → UTC 23:59:59Z
    return `${pure}T235959Z`;
  }
  

  // ============================
  // RRULE BUILD (1차 버전)
  // ============================
  function buildRRule() {
    let parts = [`FREQ=${state.freq}`, `INTERVAL=${state.interval}`];
  
    if (state.byday.length) {
      parts.push(`BYDAY=${state.byday.join(',')}`);
    }
  
    if (state.bysetpos) {
      parts.push(`BYSETPOS=${state.bysetpos}`);
    }
  
    if (state.count) {
      parts.push(`COUNT=${state.count}`);
    }
  
    if (state.until) {
      const untilVal = formatUntilForRRule(
        state.until,
        true // 🔥 여기서 allDay 여부를 나중에 부모에서 넘겨도 됨
      );
      parts.push(`UNTIL=${untilVal}`);
    }
  
    return parts.join(';');
  }
  

  function resetUI() {
    state.freq = 'DAILY';
    state.interval = 1;
    state.byday = [];
    state.bysetpos = null;
    state.count = null;
    state.until = null;
  
    $('#repeat-freq').value = 'DAILY';
    $('#repeat-interval').value = 1;
    $('#repeat-count').value = 1;
    $('#repeat-until').value = '';
  
    modal.querySelectorAll('input[name="repeat-end"]').forEach(r => {
      r.checked = r.value === 'none';
    });
  
    modal.querySelectorAll('input[name="monthly-type"]').forEach(r => {
      r.checked = r.value === 'date';
    });
  
    modal.querySelectorAll('#repeat-weekly-days input').forEach(c => {
      c.checked = false;
    });

    syncEndInputs(); // 🔥 반드시

  }
  
  

  function applyDefaultByFreq() {
    if (state.freq === 'WEEKLY') {
      state.byday = ['TH'];
      modal
        .querySelector('#repeat-weekly-days input[value="TH"]')
        ?.click();
    }
  
    if (state.freq === 'MONTHLY') {
      state.byday = [];
      state.bysetpos = null;
    }
  
    if (state.freq === 'DAILY' || state.freq === 'YEARLY') {
      state.byday = [];
      state.bysetpos = null;
    }
  }
  

  function parseRRuleToState(rrule) {
    if (!rrule) return;
  
    const map = {};
    rrule.split(';').forEach(p => {
      const [k, v] = p.split('=');
      if (k && v) map[k] = v;
    });
  
    state.freq     = map.FREQ || 'DAILY';
    state.interval = parseInt(map.INTERVAL || '1', 10);
  
    state.byday    = [];
    state.bysetpos = null;
    state.count = map.COUNT ? parseInt(map.COUNT, 10) : null;

    state.until = normalizeUntilFromRRule(map.UNTIL);
    
  
    // 🔥 MONTHLY: BYDAY=1TH 패턴 처리
    if (state.freq === 'MONTHLY' && map.BYDAY && !map.BYSETPOS) {
      const m = map.BYDAY.match(/^(-?\d)([A-Z]{2})$/);
      if (m) {
        state.bysetpos = parseInt(m[1], 10);
        state.byday    = [m[2]];
      }
    } else {
      if (map.BYDAY) {
        state.byday = map.BYDAY.split(',');
      }
      if (map.BYSETPOS) {
        state.bysetpos = parseInt(map.BYSETPOS, 10);
      }
    }
  }
  



  function applyStateToUI() {
    $('#repeat-freq').value = state.freq;
    $('#repeat-interval').value = state.interval;
  
    // 종료
    modal.querySelectorAll('input[name="repeat-end"]').forEach(r => {
      r.checked =
        (r.value === 'count' && state.count) ||
        (r.value === 'until' && state.until) ||
        (r.value === 'none'  && !state.count && !state.until);
    });
  
    if (state.count) $('#repeat-count').value = state.count;
    if (state.until) $('#repeat-until').value = state.until;
  
    // WEEKLY 요일
    modal.querySelectorAll('#repeat-weekly-days input').forEach(c => {
      c.checked = state.byday.includes(c.value);
    });
  
    // MONTHLY 기준
    modal.querySelectorAll('input[name="monthly-type"]').forEach(r => {
      r.checked =
        (r.value === 'weekday' && state.bysetpos) ||
        (r.value === 'date' && !state.bysetpos);
    });
    syncEndInputs(); // 🔥 반드시
  }
  

  function updateSummary() {
    const el = $('#repeat-summary');
    if (!el) return;
  
    const DAY_MAP = {
      MO: '월', TU: '화', WE: '수', TH: '목',
      FR: '금', SA: '토', SU: '일'
    };
  
    const POS_MAP = {
      1: '첫번째',
      2: '두번째',
      3: '세번째',
      4: '네번째',
      '-1': '마지막'
    };
  
    let text = '';
  
    /* =========================
       기본 빈도
    ========================= */
    if (state.freq === 'DAILY') {
      text = '날마다';
    }
  
    if (state.freq === 'WEEKLY') {
      const days = state.byday.map(d => DAY_MAP[d]).join(', ');
      text = days ? `매주, ${days}요일에` : '매주';
    }
  
    if (state.freq === 'MONTHLY') {
      if (state.bysetpos && state.byday.length) {
        text = `매월, ${POS_MAP[state.bysetpos]} ${DAY_MAP[state.byday[0]]}요일에`;
      } else {
        text = '매월';
      }
    }
  
    if (state.freq === 'YEARLY') {
      text = '매년';
    }
  
    /* =========================
       종료 조건
    ========================= */
    if (state.count) {
      parts.push(`COUNT=${state.count}`);
    }
    else if (state.until) {
      const untilVal = formatUntilForRRule(state.until, true);
      parts.push(`UNTIL=${untilVal}`);
    }
    
  
    el.textContent = text;
  }
  
  function normalizeUntilFromRRule(until) {
    if (!until) return null;
  
    const m = until.match(/^(\d{4})(\d{2})(\d{2})/);
    if (!m) return null;
  
    return `${m[1]}-${m[2]}-${m[3]}`;
  }

  
  function toggleByFreq() {
    const freq = state.freq;
  
    const monthly = $('#repeat-monthly-mode');
    const weekly  = $('#repeat-weekly-days');
    const endRow  = $('#repeat-end-row');
    const summary = $('#repeat-summary-row');
  
    // 전부 숨김
    monthly?.classList.add('is-hidden');
    weekly?.classList.add('is-hidden');
  
    // 항상 보임
    endRow?.classList.remove('is-hidden');
    summary?.classList.remove('is-hidden');
  
    if (freq === 'WEEKLY') {
      weekly?.classList.remove('is-hidden');
    }
  
    if (freq === 'MONTHLY') {
      monthly?.classList.remove('is-hidden');
    }
  
    // DAILY / YEARLY 는 weekly / monthly 절대 안 보임
  }
  
  

  function syncEndInputs() {
    const endNone  = modal.querySelector('input[name="repeat-end"][value="none"]');
    const endCount = modal.querySelector('input[name="repeat-end"][value="count"]');
    const endUntil = modal.querySelector('input[name="repeat-end"][value="until"]');
  
    const countInput = $('#repeat-count');
    const untilInput = $('#repeat-until');
  
    // 기본: 전부 비활성
    countInput.disabled = true;
    untilInput.disabled = true;
  
    countInput.classList.add('is-disabled');
    untilInput.classList.add('is-disabled');
  
    // 없음
    if (endNone.checked) {
      state.count = null;
      state.until = null;
    }
  
    // 이후
    if (endCount.checked) {
      countInput.disabled = false;
      countInput.classList.remove('is-disabled');
      state.count = parseInt(countInput.value, 10) || 1;
      state.until = null;
    }
  
    // 까지
    if (endUntil.checked) {
      untilInput.disabled = false;
      untilInput.classList.remove('is-disabled');
      state.until = untilInput.value || null;
      state.count = null;
    }
  }
  

  $('#repeat-freq').addEventListener('change', e => {
    state.freq = e.target.value;
  
    // freq 변경 시에는 UI 가시성만 제어
    applyDefaultByFreq();
    toggleByFreq();
    syncEndInputs();
    updateSummary();
  }); 


  $('#repeat-interval')?.addEventListener('change', e => {
    state.interval = parseInt(e.target.value, 10) || 1;
    updateSummary();
  });

  modal.querySelectorAll('#repeat-weekly-days input[type="checkbox"]')
  .forEach(chk => {
    chk.addEventListener('change', () => {
      state.byday = Array.from(
        modal.querySelectorAll('#repeat-weekly-days input:checked')
      ).map(i => i.value);
      updateSummary();
    });
  });


  modal.querySelectorAll('input[name="repeat-end"]').forEach(r => {
    r.addEventListener('change', () => {
      syncEndInputs();
      updateSummary();
    });
  });
  
  
  modal.querySelectorAll('input[name="monthly-type"]').forEach(radio => {
    radio.addEventListener('change', e => {
      if (e.target.value === 'date') {
        state.byday = [];
        state.bysetpos = null;
      } else {
        state.byday = ['TH'];     // 기본: 목요일
        state.bysetpos = 1;       // 첫번째
      }
      updateSummary();
    });
  });
  


})();
