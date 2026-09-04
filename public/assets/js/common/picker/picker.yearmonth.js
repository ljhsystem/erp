import { createPickerCore } from './ui.state.js';

export function formatYearMonthValue(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export function parseYearMonthValue(value) {
  const match = String(value || '').trim().match(/^(\d{4})-(0[1-9]|1[0-2])$/);
  return match ? new Date(Number(match[1]), Number(match[2]) - 1, 1) : null;
}

export function normalizeYearMonthInputValue(value) {
  const digits = String(value ?? '').replace(/\D/g, '').slice(0, 6);
  if (digits.length <= 4) return digits;
  return `${digits.slice(0, 4)}-${digits.slice(4)}`;
}

export function formatYearMonthDisplay(value) {
  const date = value instanceof Date ? value : parseYearMonthValue(value);
  return date ? `${date.getFullYear()}년 ${String(date.getMonth() + 1).padStart(2, '0')}월` : '';
}

export function createYearMonthPicker({ container, yearMin, yearMax } = {}) {
  const now = new Date();
  const picker = createPickerCore({ time: false });
  const minYear = Number.isFinite(Number(yearMin)) ? Number(yearMin) : now.getFullYear() - 100;
  const maxYear = Number.isFinite(Number(yearMax)) ? Number(yearMax) : now.getFullYear() + 20;
  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';
  const mount = document.createElement('div');
  mount.className = 'admin-picker__calendar';
  panel.appendChild(mount);
  container.appendChild(panel);
  let mode = 'months';
  let yearPageStart = 0;
  let raf = 0;

  const clampYear = year => Math.min(maxYear, Math.max(minYear, Number(year)));
  const view = () => ({ y: clampYear(picker.state.viewYear), m: Number(picker.state.viewMonth) || 0 });
  function changeView(year, month = view().m) {
    picker.state.viewYear = clampYear(year);
    picker.state.viewMonth = Math.min(11, Math.max(0, Number(month) || 0));
    scheduleRender();
  }
  function selectMonth(year, month) {
    picker.setDate(new Date(clampYear(year), month, 1), { force: true });
  }
  function setYearMonth(value) {
    const date = value instanceof Date ? value : parseYearMonthValue(value);
    if (!date) return false;
    picker.state.date = new Date(date.getFullYear(), date.getMonth(), 1);
    picker.state.viewYear = date.getFullYear();
    picker.state.viewMonth = date.getMonth();
    mode = 'months';
    scheduleRender();
    return true;
  }
  function focusAfterRender(selector) {
    requestAnimationFrame(() => mount.querySelector(selector)?.focus());
  }

  function header() {
    const { y } = view();
    const element = document.createElement('div');
    element.className = 'picker-cal-header picker-yearmonth-header';
    const prev = document.createElement('button');
    prev.type = 'button'; prev.className = 'picker-nav-btn'; prev.textContent = '‹'; prev.setAttribute('aria-label', '이전 연도');
    prev.disabled = mode === 'months' ? y <= minYear : yearPageStart <= minYear;
    prev.onclick = () => mode === 'months' ? changeView(y - 1) : showYearPage(yearPageStart - 12);
    const title = document.createElement('button');
    title.type = 'button'; title.className = 'picker-year-title'; title.textContent = mode === 'months' ? `${y}년` : `${yearPageStart}–${Math.min(maxYear, yearPageStart + 11)}년`;
    title.setAttribute('aria-label', mode === 'months' ? '연도 선택 열기' : '월 선택으로 돌아가기');
    title.setAttribute('aria-expanded', mode === 'months' ? 'false' : 'true');
    title.onclick = () => { if (mode === 'months') showYearPage(y - (y % 12)); else { mode = 'months'; scheduleRender(); } };
    const next = document.createElement('button');
    next.type = 'button'; next.className = 'picker-nav-btn'; next.textContent = '›'; next.setAttribute('aria-label', '다음 연도');
    next.disabled = mode === 'months' ? y >= maxYear : yearPageStart + 11 >= maxYear;
    next.onclick = () => mode === 'months' ? changeView(y + 1) : showYearPage(yearPageStart + 12);
    element.append(prev, title, next);
    return element;
  }
  function showYearPage(start) {
    yearPageStart = clampYear(start);
    if (yearPageStart + 11 > maxYear) yearPageStart = Math.max(minYear, maxYear - 11);
    mode = 'years';
    scheduleRender();
    focusAfterRender('.picker-year-cell.is-current-view');
  }
  function monthGrid() {
    const { y } = view();
    const selected = picker.getState().date;
    const grid = document.createElement('div');
    grid.className = 'picker-yearmonth-grid'; grid.setAttribute('role', 'grid'); grid.setAttribute('aria-label', `${y}년 월 선택`);
    for (let month = 0; month < 12; month += 1) {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'picker-month-cell'; button.textContent = `${month + 1}월`; button.dataset.month = String(month);
      button.setAttribute('role', 'gridcell'); button.setAttribute('aria-label', `${y}년 ${month + 1}월`);
      if (now.getFullYear() === y && now.getMonth() === month) button.classList.add('is-today');
      if (selected instanceof Date && selected.getFullYear() === y && selected.getMonth() === month) { button.classList.add('is-selected'); button.setAttribute('aria-selected', 'true'); }
      button.onclick = () => selectMonth(y, month);
      button.onkeydown = event => {
        const moves = { ArrowLeft:-1, ArrowRight:1, ArrowUp:-4, ArrowDown:4 };
        if (!(event.key in moves)) return;
        event.preventDefault();
        const next = month + moves[event.key];
        if (next < 0) { changeView(y - 1, 11); focusAfterRender(`[data-month="${12 + next}"]`); }
        else if (next > 11) { changeView(y + 1, 0); focusAfterRender(`[data-month="${next - 12}"]`); }
        else mount.querySelector(`[data-month="${next}"]`)?.focus();
      };
      grid.appendChild(button);
    }
    return grid;
  }
  function yearGrid() {
    const { y } = view();
    const grid = document.createElement('div');
    grid.className = 'picker-year-grid'; grid.setAttribute('role', 'grid'); grid.setAttribute('aria-label', '연도 선택');
    for (let offset = 0; offset < 12; offset += 1) {
      const year = yearPageStart + offset;
      if (year > maxYear) break;
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'picker-year-cell'; button.textContent = `${year}년`; button.dataset.year = String(year);
      if (year === now.getFullYear()) button.classList.add('is-today');
      if (year === y) button.classList.add('is-current-view');
      button.onclick = () => { changeView(year); mode = 'months'; scheduleRender(); focusAfterRender(`[data-month="${view().m}"]`); };
      button.onkeydown = event => { const moves={ArrowLeft:-1,ArrowRight:1,ArrowUp:-4,ArrowDown:4}; if(!(event.key in moves))return;event.preventDefault();const target=year+moves[event.key];if(target<yearPageStart){showYearPage(yearPageStart-12);}else if(target>yearPageStart+11){showYearPage(yearPageStart+12);}focusAfterRender(`[data-year="${clampYear(target)}"]`); };
      grid.appendChild(button);
    }
    return grid;
  }
  function footer() {
    const element = document.createElement('div'); element.className = 'picker-cal-footer';
    const current = document.createElement('button'); current.type = 'button'; current.className = 'picker-btn'; current.textContent = '이번 달'; current.onclick = () => selectMonth(now.getFullYear(), now.getMonth());
    const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'picker-btn'; clear.textContent = '지우기'; clear.onclick = () => picker.clearDate();
    element.append(current, clear); return element;
  }
  function render() {
    mount.replaceChildren();
    const wrap = document.createElement('div'); wrap.className = 'picker-inner picker-yearmonth'; wrap.onclick = event => event.stopPropagation();
    wrap.append(header(), mode === 'months' ? monthGrid() : yearGrid(), footer()); mount.appendChild(wrap);
  }
  function scheduleRender() { if (raf) return; raf = requestAnimationFrame(() => { raf = 0; render(); }); }
  picker.subscribe(scheduleRender);
  picker.setYearMonth = setYearMonth;
  picker.parseYearMonth = parseYearMonthValue;
  picker.formatYearMonth = formatYearMonthValue;
  render();
  return picker;
}
