import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
  'use strict';
  console.log('[sidebar.left.mini] loaded');
  const wrap = document.querySelector('.mini-calendar-wrap');
  if (!wrap || !window.AdminPicker) return;

  const container = document.createElement('div');
  container.className = 'mini-picker-container';

  wrap.innerHTML = '';
  wrap.appendChild(container);

  const picker = AdminPicker.create({
    type: 'base',
    container
  });

  window.__miniPicker = picker;

  picker.subscribe((state) => {
    if (!(state.date instanceof Date)) return;

    const d = new Date(state.date);

    d.setHours(12, 0, 0, 0);

    window.__calendar?.gotoDate?.(d);

    requestAnimationFrame(() => {
      setTimeout(() => {
        window.flashCalendarDate?.(d);
      }, 60);
    });
  });

})();
