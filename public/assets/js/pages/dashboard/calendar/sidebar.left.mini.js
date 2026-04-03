// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/sidebar.left.mini.js'
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
    type: 'base',   // ✅ 여기! base 말고 mini 추천 (UI/구조 분리)  base  mini  datetime
    container
  });

  window.__miniPicker = picker;

  picker.subscribe((state) => {
    if (!(state.date instanceof Date)) return;
  
    const d = new Date(state.date);
  
    // 🔥 타임존 안전 고정
    d.setHours(12, 0, 0, 0);
  
    window.__calendar?.gotoDate?.(d);
  
    requestAnimationFrame(() => {
      setTimeout(() => {
        window.flashCalendarDate?.(d);
      }, 60);
    });
  });
  
})();
