export function parseDayOfMonthValue(value) {
  const normalized = String(value ?? '').trim();
  if (!/^\d{1,2}$/.test(normalized)) return null;
  const day = Number(normalized);
  return day >= 1 && day <= 31 ? day : null;
}

export function normalizeDayOfMonthInputValue(value) {
  return String(value ?? '').replace(/\D/g, '').slice(0, 2);
}

export function createDayOfMonthPicker({
  container,
  title: titleText = '일자 선택',
  ariaLabel = '월 내 일자 선택',
} = {}) {
  if (!container) throw new Error('[DayOfMonthPicker] container is required');
  container.classList.add('picker', 'is-day-of-month');
  const listeners = [];
  let selectedDay = null;
  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel day-of-month-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', ariaLabel);
  const title = document.createElement('div');
  title.className = 'day-of-month-title';
  title.textContent = titleText;
  const grid = document.createElement('div');
  grid.className = 'day-of-month-grid';
  grid.setAttribute('role', 'grid');
  grid.setAttribute('aria-label', ariaLabel);
  panel.append(title, grid);
  container.appendChild(panel);

  function sync() {
    grid.querySelectorAll('[data-day]').forEach(button => {
      const active = Number(button.dataset.day) === selectedDay;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }
  function setDay(value, { emit = false } = {}) {
    const day = parseDayOfMonthValue(value);
    selectedDay = day;
    sync();
    if (emit && day !== null) listeners.forEach(listener => listener(day));
    return day !== null;
  }
  for (let day = 1; day <= 31; day += 1) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'day-of-month-item';
    button.textContent = `${day}일`;
    button.dataset.day = String(day);
    button.setAttribute('role', 'gridcell');
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', () => setDay(day, { emit: true }));
    button.addEventListener('keydown', event => {
      const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      if (!(event.key in moves)) return;
      event.preventDefault();
      const target = Math.min(31, Math.max(1, day + moves[event.key]));
      grid.querySelector(`[data-day="${target}"]`)?.focus();
    });
    grid.appendChild(button);
  }
  const picker = {
    subscribe(listener) {
      if (typeof listener !== 'function') return () => {};
      listeners.push(listener);
      return () => {
        const index = listeners.indexOf(listener);
        if (index >= 0) listeners.splice(index, 1);
      };
    },
    setDay,
    getDay: () => selectedDay,
    focusActive: () => (grid.querySelector('.day-of-month-item.is-active') || grid.querySelector('.day-of-month-item'))?.focus(),
    __scrollToActive: () => {},
  };
  sync();
  return picker;
}
