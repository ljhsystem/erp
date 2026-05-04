// 경로: PROJECT_ROOT . '/public/assets/js/common/notification.js'
(() => {
    'use strict';
  
    window.AppCore = window.AppCore || {};
  
    if (!window.AppCore.notify) {
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
    }

    const API = {
      list: '/api/system/notifications',
      read: '/api/system/notifications/read',
      readAll: '/api/system/notifications/read-all',
    };

    let bell = null;
    let badge = null;
    let dropdown = null;
    let listEl = null;
    let markAllReadBtn = null;

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    async function fetchJson(url, options = {}) {
      const response = await fetch(url, {
        ...options,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          ...(options.headers || {}),
        },
      });
      const json = await response.json().catch(() => ({}));
      if (!response.ok || json.success === false) {
        throw new Error(json.message || '알림 처리에 실패했습니다.');
      }
      return json;
    }

    function formatTime(value) {
      const text = String(value || '').replace('T', ' ');
      return text.length > 16 ? text.slice(0, 16) : text;
    }

    function setUnreadCount(count) {
      const unread = Number(count || 0);
      bell?.classList.toggle('has-unread', unread > 0);
      if (!badge) return;

      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.classList.toggle('d-none', unread <= 0);
    }

    function renderNotifications(list = []) {
      if (!listEl) return;

      if (!Array.isArray(list) || list.length === 0) {
        listEl.innerHTML = '<div class="notification-empty">알림이 없습니다.</div>';
        setUnreadCount(0);
        return;
      }

      const unreadCount = list.filter((item) => Number(item.is_read || 0) === 0).length;
      setUnreadCount(unreadCount);

      listEl.innerHTML = list.map((item) => {
        const unread = Number(item.is_read || 0) === 0;
        return `
          <button type="button"
                  class="notification-item${unread ? ' unread' : ''}"
                  data-id="${escapeHtml(item.id)}">
            <span class="notification-item-title">${escapeHtml(item.title || '알림')}</span>
            <span class="notification-item-message">${escapeHtml(item.message || '')}</span>
            <span class="notification-item-time">${escapeHtml(formatTime(item.created_at))}</span>
          </button>
        `;
      }).join('');
    }

    async function loadNotifications() {
      if (!bell || !listEl) return;

      try {
        const json = await fetchJson(API.list);
        renderNotifications(json.data || []);
        if (json.unread_count !== undefined) {
          setUnreadCount(json.unread_count);
        }
      } catch (error) {
        console.error('[notification] load failed', error);
      }
    }

    async function markAsRead(id) {
      if (!id) return;

      const body = new URLSearchParams();
      body.set('id', id);
      await fetchJson(API.read, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
      });
      await loadNotifications();
    }

    async function markAllAsRead() {
      await fetchJson(API.readAll, { method: 'POST' });
      await loadNotifications();
    }

    function bindNotificationUi() {
      bell = document.getElementById('notificationBell');
      badge = document.getElementById('notificationCount');
      dropdown = document.getElementById('notificationDropdown');
      listEl = document.getElementById('notificationList');
      markAllReadBtn = document.getElementById('markAllReadBtn');

      if (!bell || !dropdown || !listEl) return;

      bell.addEventListener('click', (event) => {
        event.stopPropagation();
        const isHidden = dropdown.classList.toggle('d-none');
        bell.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        if (!isHidden) {
          void loadNotifications();
        }
      });

      document.addEventListener('click', (event) => {
        if (dropdown.classList.contains('d-none')) return;
        if (event.target.closest('.nav-notification')) return;
        dropdown.classList.add('d-none');
        bell.setAttribute('aria-expanded', 'false');
      });

      listEl.addEventListener('click', (event) => {
        const item = event.target.closest('.notification-item');
        if (!item) return;
        void markAsRead(item.dataset.id || '');
      });

      markAllReadBtn?.addEventListener('click', () => {
        void markAllAsRead();
      });

      void loadNotifications();
    }

    window.AppCore.loadNotifications = loadNotifications;
    window.AppCore.renderNotifications = renderNotifications;
    window.AppCore.markNotificationAsRead = markAsRead;
    window.AppCore.markAllNotificationsAsRead = markAllAsRead;

    document.addEventListener('DOMContentLoaded', bindNotificationUi);
  
  })();
  
