(() => {
    'use strict';
    const API = { list: '/api/system/notifications/list', read: '/api/system/notifications/read', readAll: '/api/system/notifications/read-all' };
    const state = { page: 1, pageSize: 20, total: 0 };
    const list = document.getElementById('notificationCenterList');
    if (!list) return;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const request = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', ...options });
        const body = await response.json();
        if (!response.ok || !body.success) throw new Error(body.message || '알림을 처리하지 못했습니다.');
        return body;
    };
    const render = (items) => {
        if (!items.length) { list.innerHTML = '<div class="notifications-empty">표시할 알림이 없습니다.</div>'; return; }
        list.innerHTML = items.map((item) => {
            const tag = item.action_url ? 'a' : 'button';
            const target = item.action_url ? ` href="${escapeHtml(item.action_url)}"` : ' type="button"';
            return `<${tag}${target} class="list-group-item list-group-item-action notification-center-item ${Number(item.is_read) ? '' : 'is-unread'}" data-id="${escapeHtml(item.id)}"><span><span class="notification-center-title">${escapeHtml(item.title)}</span><br><span class="notification-center-message">${escapeHtml(item.message)}</span></span><span class="notification-center-meta">${escapeHtml(item.action_type)} · ${escapeHtml(item.created_at)}</span></${tag}>`;
        }).join('');
    };
    const load = async () => {
        try {
            const body = await request(`${API.list}?page=${state.page}&page_size=${state.pageSize}`);
            state.total = Number(body.data.total || 0); render(body.data.items || []);
            document.getElementById('notificationTotal').textContent = state.total;
            document.getElementById('notificationUnread').textContent = Number(body.data.unread_count || 0);
            const pages = Math.max(1, Math.ceil(state.total / state.pageSize));
            document.getElementById('notificationPage').textContent = `${state.page} / ${pages}`;
            document.getElementById('notificationPrev').disabled = state.page <= 1;
            document.getElementById('notificationNext').disabled = state.page >= pages;
        } catch (error) { list.innerHTML = `<div class="notifications-empty">${escapeHtml(error.message)}</div>`; }
    };
    list.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-id]'); if (!item || item.dataset.id.startsWith('approval:')) return;
        try { await request(API.read, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: item.dataset.id }) }); item.classList.remove('is-unread'); } catch (_) { return; }
    });
    document.getElementById('notificationReadAll').addEventListener('click', async () => { await request(API.readAll, { method: 'POST' }); await load(); document.dispatchEvent(new CustomEvent('notification:changed')); });
    document.getElementById('notificationPrev').addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    document.getElementById('notificationNext').addEventListener('click', () => { if (state.page * state.pageSize < state.total) { state.page += 1; load(); } });
    load();
})();
