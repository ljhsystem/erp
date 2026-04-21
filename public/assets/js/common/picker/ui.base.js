export function renderCalendar({ picker, container, options = {} }) {
  const currentYear = new Date().getFullYear();

  const opt = {
    weekStart: 0,
    showFooter: true,
    hideClear: false,
    yearMin: currentYear - 100,
    yearMax: currentYear + 20,
    ...options,
  };

  function getView() {
    const state = picker.getState();

    if (typeof state.viewYear === 'number' && typeof state.viewMonth === 'number') {
      return { y: state.viewYear, m: state.viewMonth };
    }

    if (state.date instanceof Date) {
      return {
        y: state.date.getFullYear(),
        m: state.date.getMonth(),
      };
    }

    const now = new Date();
    return { y: now.getFullYear(), m: now.getMonth() };
  }

  const weekdayLabels = opt.weekStart === 1
    ? ['월', '화', '수', '목', '금', '토', '일']
    : ['일', '월', '화', '수', '목', '금', '토'];

  function fmtYmd(date) {
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function getMonthMatrix(y, m) {
    const first = new Date(y, m, 1);
    const offset = opt.weekStart === 1
      ? (first.getDay() === 0 ? 6 : first.getDay() - 1)
      : first.getDay();

    const start = new Date(y, m, 1 - offset);
    const cells = [];

    for (let i = 0; i < 42; i += 1) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);
      cells.push({ date, outside: date.getMonth() !== m });
    }

    const weeks = [];
    for (let i = 0; i < cells.length; i += 7) {
      weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
  }

  function buildHeader() {
    const { y, m } = getView();

    const header = document.createElement('div');
    header.className = 'picker-cal-header';

    const ym = document.createElement('div');
    ym.className = 'picker-ym';

    const yearSel = document.createElement('select');
    yearSel.className = 'picker-year';
    yearSel.setAttribute('aria-label', '연도 선택');
    for (let i = opt.yearMin; i <= opt.yearMax; i += 1) {
      yearSel.add(new Option(String(i), String(i)));
    }
    yearSel.value = String(y);

    const monthSel = document.createElement('select');
    monthSel.className = 'picker-month';
    monthSel.setAttribute('aria-label', '월 선택');
    for (let i = 0; i < 12; i += 1) {
      monthSel.add(new Option(`${i + 1}월`, String(i)));
    }
    monthSel.value = String(m);

    ym.append(yearSel, monthSel);

    const nav = document.createElement('div');
    nav.className = 'picker-nav';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'picker-nav-btn';
    prev.setAttribute('aria-label', '이전 달');
    prev.textContent = '‹';

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'picker-nav-btn';
    next.setAttribute('aria-label', '다음 달');
    next.textContent = '›';

    prev.onclick = () => {
      if (m === 0) picker.setView(y - 1, 11);
      else picker.setView(y, m - 1);
    };

    next.onclick = () => {
      if (m === 11) picker.setView(y + 1, 0);
      else picker.setView(y, m + 1);
    };

    yearSel.onchange = () => picker.setView(parseInt(yearSel.value, 10), m);
    monthSel.onchange = () => picker.setView(y, parseInt(monthSel.value, 10));

    nav.append(prev, next);
    header.append(ym, nav);
    return header;
  }

  function buildWeekHeader() {
    const week = document.createElement('div');
    week.className = 'picker-weekdays';

    weekdayLabels.forEach((label) => {
      const item = document.createElement('div');
      item.className = 'picker-weekday';
      item.textContent = label;
      week.appendChild(item);
    });

    return week;
  }

  function buildGrid() {
    const { y, m } = getView();
    const weeks = getMonthMatrix(y, m);
    const selected = picker.getState().date;
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const grid = document.createElement('div');
    grid.className = 'picker-cal-grid';

    weeks.forEach((week) => {
      week.forEach(({ date, outside }) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'picker-day';
        btn.textContent = String(date.getDate());

        if (outside) btn.classList.add('is-outside');

        const normalized = new Date(date);
        normalized.setHours(0, 0, 0, 0);

        if (fmtYmd(normalized) === fmtYmd(today)) {
          btn.classList.add('is-today');
        }

        if (selected && fmtYmd(selected) === fmtYmd(normalized)) {
          btn.classList.add('is-selected');
        }

        btn.onclick = (event) => {
          event.preventDefault();
          event.stopPropagation();
          picker.setDate(new Date(
            date.getFullYear(),
            date.getMonth(),
            date.getDate()
          ));
        };

        grid.appendChild(btn);
      });
    });

    return grid;
  }

  function buildFooter() {
    if (!opt.showFooter) return null;

    const footer = document.createElement('div');
    footer.className = 'picker-cal-footer';

    const todayBtn = document.createElement('button');
    todayBtn.type = 'button';
    todayBtn.className = 'picker-btn';
    todayBtn.textContent = '오늘';
    todayBtn.onclick = (event) => {
      event.preventDefault();
      event.stopPropagation();
      const today = new Date();
      picker.setView(today.getFullYear(), today.getMonth());
      picker.setDate(today);
    };
    footer.appendChild(todayBtn);

    if (!opt.hideClear) {
      const clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'picker-btn';
      clearBtn.textContent = '지우기';
      clearBtn.onclick = (event) => {
        event.preventDefault();
        event.stopPropagation();
        picker.clearDate();
      };
      footer.appendChild(clearBtn);
    }

    return footer;
  }

  let raf = 0;

  function render() {
    container.innerHTML = '';

    const wrap = document.createElement('div');
    wrap.className = 'picker-inner';
    wrap.onclick = (event) => event.stopPropagation();

    wrap.append(buildHeader(), buildWeekHeader(), buildGrid());

    const footer = buildFooter();
    if (footer) {
      wrap.appendChild(footer);
    }

    container.appendChild(wrap);
  }

  function scheduleRender() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      raf = 0;
      render();
    });
  }

  picker.subscribe(scheduleRender);
  render();
}
