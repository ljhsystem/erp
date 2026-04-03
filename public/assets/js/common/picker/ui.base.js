// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.base.js '
// 📅 공통 달력 UI 렌더러 (기본 / 미니 / 시간추가 달력 공용)

export function renderCalendar({ picker, container, options = {} }) {
  const currentYear = new Date().getFullYear();

  const opt = {
    weekStart: 0,
    showFooter: true,
    yearMin: currentYear - 100,
    yearMax: currentYear + 20,
    ...options
  };

  /* =========================================================
   * View Resolver (단일 진실)
   * ========================================================= */
  function getView() {
    const s = picker.getState();

    if (
      typeof s.viewYear === 'number' &&
      typeof s.viewMonth === 'number'
    ) {
      return { y: s.viewYear, m: s.viewMonth };
    }

    if (s.date instanceof Date) {
      return {
        y: s.date.getFullYear(),
        m: s.date.getMonth()
      };
    }

    const now = new Date();
    return { y: now.getFullYear(), m: now.getMonth() };
  }

  const weekdayLabels =
    opt.weekStart === 1
      ? ['월','화','수','목','금','토','일']
      : ['일','월','화','수','목','금','토'];

  function fmtYmd(d) {
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

  /* =========================================================
   * Month Matrix
   * ========================================================= */
  function getMonthMatrix(y, m) {
    const first = new Date(y, m, 1);
    const offset =
      opt.weekStart === 1
        ? (first.getDay() === 0 ? 6 : first.getDay() - 1)
        : first.getDay();

    const start = new Date(y, m, 1 - offset);
    const cells = [];

    for (let i = 0; i < 42; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      cells.push({ date: d, outside: d.getMonth() !== m });
    }

    const weeks = [];
    for (let i = 0; i < 42; i += 7) {
      weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
  }

  /* =========================================================
   * Header
   * ========================================================= */
  function buildHeader() {
    const { y, m } = getView();

    const header = document.createElement('div');
    header.className = 'picker-cal-header';

    const ym = document.createElement('div');
    ym.className = 'picker-ym';

    const yearSel = document.createElement('select');
    yearSel.className = 'picker-year';
    for (let i = opt.yearMin; i <= opt.yearMax; i++) {
      yearSel.add(new Option(String(i), String(i)));
    }
    yearSel.value = String(y);

    const monthSel = document.createElement('select');
    monthSel.className = 'picker-month';
    for (let i = 0; i < 12; i++) {
      monthSel.add(new Option(`${i + 1}월`, String(i)));
    }
    monthSel.value = String(m);

    ym.append(yearSel, monthSel);

    const nav = document.createElement('div');
    nav.className = 'picker-nav';

    const prev = document.createElement('button');
     prev.type = 'button';
     prev.className = 'picker-nav-btn';
    prev.textContent = '‹';
    
    const next = document.createElement('button');
     next.type = 'button';
     next.className = 'picker-nav-btn';
    next.textContent = '›';
    

    prev.onclick = () => {
      if (m === 0) picker.setView(y - 1, 11);
      else picker.setView(y, m - 1);
    };

    next.onclick = () => {
      if (m === 11) picker.setView(y + 1, 0);
      else picker.setView(y, m + 1);
    };

    yearSel.onchange = () =>
      picker.setView(parseInt(yearSel.value, 10), m);

    monthSel.onchange = () =>
      picker.setView(y, parseInt(monthSel.value, 10));

    nav.append(prev, next);
    header.append(ym, nav);
    return header;
  }

  /* =========================================================
   * Grid
   * ========================================================= */
  function buildGrid() {
    const { y, m } = getView();
    const weeks = getMonthMatrix(y, m);
    const selected = picker.getState().date;
    const today = new Date(); today.setHours(0,0,0,0);

    const grid = document.createElement('div');
    grid.className = 'picker-cal-grid';

    weeks.forEach(week => {
      week.forEach(({ date, outside }) => {
        const btn = document.createElement('button');
        btn.className = 'picker-day';
        btn.textContent = date.getDate();

        if (outside) btn.classList.add('is-outside');

        const d0 = new Date(date); d0.setHours(0,0,0,0);
        if (fmtYmd(d0) === fmtYmd(today)) btn.classList.add('is-today');

        if (
          selected &&
          fmtYmd(selected) === fmtYmd(d0)
        ) {
          btn.classList.add('is-selected');
        }

        btn.onclick = () =>
          picker.setDate(new Date(
            date.getFullYear(),
            date.getMonth(),
            date.getDate()
          ));

        grid.appendChild(btn);
      });
    });

    return grid;
  }

  /* =========================================================
   * Footer
   * ========================================================= */
  function buildFooter() {
    if (!opt.showFooter) return null;
  
    const footer = document.createElement('div');
    footer.className = 'picker-cal-footer';
  
    const todayBtn = document.createElement('button');
    todayBtn.type = 'button';
    todayBtn.className = 'picker-btn';
    todayBtn.textContent = '오늘';
    todayBtn.onclick = () => {
      const t = new Date();
      picker.setView(t.getFullYear(), t.getMonth());
      picker.setDate(t);
    };
  
    footer.appendChild(todayBtn);
  
    // ✅ 지우기 버튼은 옵션에 따라
    if (!opt.hideClear) {
      const clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'picker-btn';
      clearBtn.textContent = '지우기';
      clearBtn.onclick = () => picker.clearDate();
      footer.appendChild(clearBtn);
    }
  
    return footer;
  }
  
  

  /* =========================================================
   * Render
   * ========================================================= */
  let raf = 0;

  function render() {
    container.innerHTML = '';

    const wrap = document.createElement('div');
    wrap.className = 'picker-inner';
    wrap.onclick = e => e.stopPropagation();

    wrap.append(buildHeader(), buildGrid());

    const footer = buildFooter(); // ✅ 1번만 생성
    if (footer) wrap.appendChild(footer);

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
