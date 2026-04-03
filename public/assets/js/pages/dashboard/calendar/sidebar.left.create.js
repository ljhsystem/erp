// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/sidebar.left.create.js'
(() => {
  'use strict';

  console.log('[sidebar.left.create-split] loaded');

  function bindCreateSplit() {
    const btnMain  = document.getElementById('btn-create-event');
    const btnTog   = document.getElementById('btn-create-menu');
    const menu     = document.getElementById('create-menu');
    const wrap     = document.getElementById('create-split');

    if (!btnMain || !btnTog || !menu || !wrap) {
      console.warn('[csidebar.left.reate-split] DOM not ready');
      return false;
    }

    // 중복 바인딩 방지
    if (wrap.dataset.bound === '1') return true;
    wrap.dataset.bound = '1';

    const getBaseDate = () => {
      const d = window.__calendar?.getDate?.();
      return (d instanceof Date) ? d : new Date();
    };

    const fireQuickCreate = (type) => {
      document.dispatchEvent(
        new CustomEvent('calendar:quick:create', {
          detail: { type, date: getBaseDate() }
        })
      );
    };

    const open  = () => menu.classList.remove('is-hidden');
    const close = () => menu.classList.add('is-hidden');
    const isOpen = () => !menu.classList.contains('is-hidden');

    btnMain.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      close();
      fireQuickCreate('event');
    });

    btnTog.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      isOpen() ? close() : open();
    });

    menu.addEventListener('click', (e) => {
      const item = e.target.closest('.menu-item');
      if (!item) return;
      e.preventDefault();
      e.stopPropagation();
      close();
      fireQuickCreate(item.dataset.action);
    });

    document.addEventListener('mousedown', (e) => {
      if (wrap.contains(e.target) || menu.contains(e.target)) return;
      close();
    });
    

    console.log('[sidebar.left.create-split] bind OK');
    return true;
  }




  document.addEventListener('DOMContentLoaded', () => {
    bindCreateSplit();
  });  

  
  // ✅ 핵심: sidebar.list가 DOM 만든 후 쏘는 이벤트
  document.addEventListener('calendar:list:loaded', () => {
    bindCreateSplit();
  });

})();
