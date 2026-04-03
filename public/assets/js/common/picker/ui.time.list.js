// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.time.list.js '
// 📄 /public/assets/js/common/picker/ui.time.list.js

export function renderTimeList({
  picker,
  container,
  step = 15,
  rows = 7
}) {
  if (!picker || !container) return;

  container.innerHTML = '';
  container.classList.add('time-list');

  const list = document.createElement('div');
  list.className = 'time-list-inner';
  container.appendChild(list);

  const times = [];

  for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += step) {
      const hh = String(h).padStart(2, '0');
      const mm = String(m).padStart(2, '0');
      times.push({ h, m, label: `${hh}:${mm}` });
    }
  }

  times.forEach(t => {
    const item = document.createElement('div');
    item.className = 'time-item';
    item.textContent = t.label;

    item.addEventListener('mousedown', e => e.preventDefault());

    item.addEventListener('click', () => {
      picker.setTime({
        hour: t.h,
        minute: t.m,
        meridiem: t.h >= 12 ? 'PM' : 'AM'
      });
    });

    list.appendChild(item);
  });

  /* ===============================
   * State → UI Sync
   * =============================== */
  function sync({ scroll = false } = {}) {
    const s = picker.getState();

    const activeLabel =
      typeof s.hour === 'number' && typeof s.minute === 'number'
        ? `${String(s.hour).padStart(2, '0')}:${String(s.minute).padStart(2, '0')}`
        : null;

    let activeEl = null;

    list.querySelectorAll('.time-item').forEach(el => {
      const on = el.textContent === activeLabel;
      el.classList.toggle('is-active', on);
      if (on) activeEl = el;
    });

    // 🔥 열릴 때만 스크롤
    if (scroll && activeEl) {
      requestAnimationFrame(() => {
        activeEl.scrollIntoView({
          block: 'center',
          behavior: 'auto'
        });
      });
    }
  }

  // 🔑 picker에서 호출할 수 있게 hook 노출
  picker.__scrollToActive = () => sync({ scroll: true });

  // 🔄 state 변경 시에는 스크롤 X
  picker.subscribe(() => sync());

  // 최초 렌더 1회 (열렸을 때 대비)
  sync({ scroll: true });

  /* ===============================
   * Height Clamp
   * =============================== */
  const ITEM_H = 32;
  list.style.maxHeight = `${rows * ITEM_H}px`;
  list.style.overflowY = 'auto';
}
