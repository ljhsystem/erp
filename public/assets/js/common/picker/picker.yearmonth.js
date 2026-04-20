import { createPickerCore } from './ui.state.js';

export function createYearMonthPicker({ container, yearMin, yearMax } = {}) {
  console.log('[picker.yearmonth] create called', { container, yearMin, yearMax });

  const now = new Date();
  const picker = createPickerCore({ time: false });

  const minYear = Number.isFinite(Number(yearMin)) ? Number(yearMin) : now.getFullYear() - 20;
  const maxYear = Number.isFinite(Number(yearMax)) ? Number(yearMax) : now.getFullYear() + 20;

  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';

  const mount = document.createElement('div');
  mount.className = 'admin-picker__calendar';

  panel.appendChild(mount);
  container.appendChild(panel);

  const monthLabels = Array.from({ length: 12 }, (_, i) => `${i + 1}\uC6D4`);

  function getView() {
    const state = picker.getState();
    return {
      y: Number.isFinite(state.viewYear) ? state.viewYear : now.getFullYear(),
      m: Number.isFinite(state.viewMonth) ? state.viewMonth : now.getMonth()
    };
  }

  function selectMonth(year, month) {
    console.log('[picker.yearmonth] select month', { year, month: month + 1 });
    picker.setView(year, month);
    picker.setDate(new Date(year, month, 1));
  }

  function setYearMonth(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return;

    picker.state.date = new Date(date.getFullYear(), date.getMonth(), 1);
    picker.state.viewYear = date.getFullYear();
    picker.state.viewMonth = date.getMonth();
    scheduleRender();
  }

  function buildHeader() {
    const { y } = getView();

    const header = document.createElement('div');
    header.className = 'picker-cal-header picker-yearmonth-header';

    const ym = document.createElement('div');
    ym.className = 'picker-ym';

    const yearSel = document.createElement('select');
    yearSel.className = 'picker-year';

    for (let year = minYear; year <= maxYear; year += 1) {
      yearSel.add(new Option(String(year), String(year)));
    }

    yearSel.value = String(Math.min(maxYear, Math.max(minYear, y)));
    yearSel.onchange = () => picker.setView(parseInt(yearSel.value, 10), getView().m);

    ym.appendChild(yearSel);

    const nav = document.createElement('div');
    nav.className = 'picker-nav';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'picker-nav-btn';
    prev.textContent = '<';
    prev.onclick = () => {
      const view = getView();
      picker.setView(Math.max(minYear, view.y - 1), view.m);
    };

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'picker-nav-btn';
    next.textContent = '>';
    next.onclick = () => {
      const view = getView();
      picker.setView(Math.min(maxYear, view.y + 1), view.m);
    };

    nav.append(prev, next);
    header.append(ym, nav);

    return header;
  }

  function buildMonthGrid() {
    const { y } = getView();
    const selected = picker.getState().date;
    const today = new Date();

    const grid = document.createElement('div');
    grid.className = 'picker-yearmonth-grid';

    monthLabels.forEach((label, month) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'picker-month-cell';
      btn.textContent = label;

      if (today.getFullYear() === y && today.getMonth() === month) {
        btn.classList.add('is-today');
      }

      if (
        selected instanceof Date &&
        selected.getFullYear() === y &&
        selected.getMonth() === month
      ) {
        btn.classList.add('is-selected');
      }

      btn.onclick = () => selectMonth(y, month);
      grid.appendChild(btn);
    });

    return grid;
  }

  function buildFooter() {
    const footer = document.createElement('div');
    footer.className = 'picker-cal-footer';

    const currentBtn = document.createElement('button');
    currentBtn.type = 'button';
    currentBtn.className = 'picker-btn';
    currentBtn.textContent = '\uC774\uBC88 \uB2EC';
    currentBtn.onclick = () => selectMonth(now.getFullYear(), now.getMonth());

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'picker-btn';
    clearBtn.textContent = '\uC9C0\uC6B0\uAE30';
    clearBtn.onclick = () => picker.clearDate();

    footer.append(currentBtn, clearBtn);
    return footer;
  }

  let raf = 0;

  function render() {
    mount.innerHTML = '';

    const wrap = document.createElement('div');
    wrap.className = 'picker-inner picker-yearmonth';
    wrap.onclick = e => e.stopPropagation();

    wrap.append(buildHeader(), buildMonthGrid(), buildFooter());
    mount.appendChild(wrap);
  }

  function scheduleRender() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      raf = 0;
      render();
    });
  }

  picker.subscribe(scheduleRender);
  picker.setYearMonth = setYearMonth;
  render();

  console.log('[picker.yearmonth] ready');

  return picker;
}
