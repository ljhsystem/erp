// 경로: PROJECT_ROOT . '/public/assets/js/common/notification.js'
(() => {
    'use strict';
  
    window.AppCore = window.AppCore || {};
  
    if (window.AppCore.notify) return;
  
    function createContainer() {
      let container = document.getElementById('app-notify-container');
      if (container) return container;
    
      container = document.createElement('div');
      container.id = 'app-notify-container';
      document.body.appendChild(container);
      return container;
    }
    
    function notify(type = 'info', message = '', opts = {}) {
    
      const duration = opts.duration ?? 2400;
      const container = createContainer();
    
      const el = document.createElement('div');
    
      // 🔥 핵심 수정
      el.className = `app-toast app-toast--${type}`;
    
      el.textContent = message;
    
      container.appendChild(el);
    
      requestAnimationFrame(() => {
        el.classList.add('is-show');
      });
    
      setTimeout(() => {
        el.classList.remove('is-show');
        setTimeout(() => el.remove(), 250);
      }, duration);
    }
  
    window.AppCore.notify = notify;
  
  })();
  