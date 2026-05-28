export function notify(type, message, opts = {}) {
    const level = String(type || 'info').toLowerCase();
    const appCore = window.AppCore || {};
    const appNotify = window.AppNotify || appCore.AppNotify || {};

    if (typeof appCore.notify === 'function' && appCore.notify !== notify) {
        return appCore.notify(level, message, opts);
    }

    if (typeof appNotify.notify === 'function' && appNotify.notify !== notify) {
        return appNotify.notify(level, message, opts);
    }

    const duration = Number(opts.duration ?? 2400);
    const text = message == null ? '' : String(message);

    const container = document.getElementById('app-notify-container');
    const target = container || document.createElement('div');

    if (!container) {
        target.id = 'app-notify-container';
        document.body.appendChild(target);
    }

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast--${level}`;
    toast.textContent = text;
    target.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('is-show');
    });

    setTimeout(() => {
        toast.classList.remove('is-show');
        setTimeout(() => toast.remove(), 250);
    }, Number.isFinite(duration) ? duration : 2400);

    return undefined;
}

if (!window.notify) {
    window.notify = notify;
}

const AppCore = window.AppCore = window.AppCore || {};
const AppAjax = window.AppAjax || AppCore.AppAjax;
const AppLoading = window.AppLoading || AppCore.AppLoading;
const AppEvents = window.AppEvents || {};

const onDocument = AppEvents.onDocument || ((type, handler, options = false) => {
    document.addEventListener(type, handler, options);
    return () => document.removeEventListener(type, handler, options);
});

const DEFAULT_LOADING_MESSAGE = 'Loading...';
const DEFAULT_NOTIFY_DURATION = 2400;

function ensureContainer() {
    let container = document.getElementById('app-notify-container');
    if (container) return container;

    container = document.createElement('div');
    container.id = 'app-notify-container';
    document.body.appendChild(container);
    return container;
}

function ensureLoadingMessage(overlay) {
    let message = overlay.querySelector('.global-loading-message');
    if (message) return message;

    message = document.createElement('div');
    message.className = 'global-loading-message';
    message.textContent = DEFAULT_LOADING_MESSAGE;
    overlay.appendChild(message);
    return message;
}

function fallbackShowLoading(message = DEFAULT_LOADING_MESSAGE) {
    const overlay = document.getElementById('global-loading-overlay');
    if (!overlay) return;

    overlay.style.display = 'flex';
    overlay.setAttribute('aria-busy', 'true');
    overlay.setAttribute('aria-live', 'polite');
    ensureLoadingMessage(overlay).textContent = String(message || DEFAULT_LOADING_MESSAGE);
}

function fallbackHideLoading() {
    const overlay = document.getElementById('global-loading-overlay');
    if (!overlay) return;

    overlay.style.display = 'none';
    overlay.removeAttribute('aria-busy');
}

const AppNotify = window.AppNotify || {
    notify,
    showLoading: fallbackShowLoading,
    hideLoading: fallbackHideLoading,
    showGlobalLoading: fallbackShowLoading,
    hideGlobalLoading: fallbackHideLoading,
};

if (!window.AppNotify) {
    window.AppNotify = AppNotify;
}

if (!AppCore.AppNotify) {
    AppCore.AppNotify = AppNotify;
}

if (!AppCore.notify) {
    AppCore.notify = AppNotify.notify;
}

if (!AppCore.showLoading || !AppCore.hideLoading) {
    AppCore.showLoading = function (message = DEFAULT_LOADING_MESSAGE) {
        if (AppLoading?.show) {
            return AppLoading.show(message);
        }
        return fallbackShowLoading(message);
    };

    AppCore.hideLoading = function () {
        if (AppLoading?.hide) {
            return AppLoading.hide();
        }
        return fallbackHideLoading();
    };

    AppCore.showGlobalLoading = AppCore.showLoading;
    AppCore.hideGlobalLoading = AppCore.hideLoading;
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
    if (AppAjax?.fetchJson) {
        return AppAjax.fetchJson(url, options);
    }

    const response = await fetch(url, {
        ...options,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        throw new Error(json.message || 'Notification request failed.');
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
        listEl.innerHTML = '<div class="notification-empty">No notifications.</div>';
        setUnreadCount(0);
        return;
    }

    const unreadCount = list.filter((item) => Number(item.is_read || 0) === 0).length;
    setUnreadCount(unreadCount);

    listEl.innerHTML = list
        .map((item) => {
            const unread = Number(item.is_read || 0) === 0;
            const title = escapeHtml(item.title || 'Notification');
            const message = escapeHtml(item.message || '');
            const time = escapeHtml(formatTime(item.created_at));

            return `
                <button type="button"
                        class="notification-item${unread ? ' unread' : ''}"
                        data-id="${escapeHtml(item.id)}">
                    <span class="notification-item-title">${title}</span>
                    <span class="notification-item-message">${message}</span>
                    <span class="notification-item-time">${time}</span>
                </button>
            `;
        })
        .join('');
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

    onDocument('click', (event) => {
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

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', () => {
            void markAllAsRead();
        });
    }

    void loadNotifications();
}

window.AppCore.loadNotifications = loadNotifications;
window.AppCore.renderNotifications = renderNotifications;
window.AppCore.markNotificationAsRead = markAsRead;
window.AppCore.markAllNotificationsAsRead = markAllAsRead;

if (document.readyState === 'loading') {
    onDocument('DOMContentLoaded', bindNotificationUi, { once: true });
} else {
    bindNotificationUi();
}