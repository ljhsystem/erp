import { actorDisplay } from '/public/assets/js/common/actor.js';

(() => {
  'use strict';

  const BASE = '/api/dashboard/calendar';

  const modal = document.getElementById('modal-trash');
  if (!modal) return;

  const tbody = document.getElementById('trash-table-body');
  const tabs = modal.querySelectorAll('.trash-tab');
  const closeBtn = document.getElementById('btn-trash-close');

  const detailArea = modal.querySelector('.trash-detail-area');
  const detailTitle = modal.querySelector('.trash-detail-title');
  const detailMeta = modal.querySelector('.trash-detail-meta');
  const detailDesc = modal.querySelector('.trash-detail-desc');

  const btnRestoreSelected = document.getElementById('btn-trash-restore-selected');
  const btnDeleteSelected  = document.getElementById('btn-trash-delete-selected');
  const btnDeleteAll       = document.getElementById('btn-trash-delete-all');

  const btnRestoreSingle = document.getElementById('btn-trash-restore-single');
  const btnDeleteSingle  = document.getElementById('btn-trash-delete-single');

  const checkAll = document.getElementById('trash-check-all');
  const btnTrash = document.getElementById('btn-trash');

  let currentType = 'event';
  let currentItem = null;

  async function fetchDeleted(type) {
    const url = type === 'event'
      ? `${BASE}/events-deleted`
      : `${BASE}/tasks-deleted`;

    const res = await fetch(url, { credentials: 'same-origin' });
    const json = await res.json();

    return json.success ? (json.data || []) : [];
  }

  function render(list) {
    tbody.innerHTML = '';
    checkAll.checked = false;

    if (!list.length) {
      tbody.innerHTML = `
        <tr class="trash-empty-row">
          <td colspan="5">삭제된 항목이 없습니다.</td>
        </tr>
      `;
      return;
    }

    list.forEach(row => {
      const tr = document.createElement('tr');
      tr.dataset.raw = JSON.stringify(row);

      tr.innerHTML = `
        <td><input type="checkbox" data-uid="${row.uid}"></td>
        <td class="trash-row-title">${row.title || ''}</td>
        <td>${row.deleted_at || ''}</td>
        <td>${actorDisplay(row, 'deleted_by')}</td>
        <td>
          <button class="btn btn-sm btn-trash-restore btn-restore" data-uid="${row.uid}">
            복원
          </button>
          <button class="btn btn-sm btn-trash-delete btn-delete" data-uid="${row.uid}">
            영구삭제
          </button>
        </td>
      `;

      tbody.appendChild(tr);
    });
  }

  function hideDetail() {
    currentItem = null;
    detailArea.classList.remove('is-open');

    detailArea.querySelector('.trash-detail-empty')?.classList.remove('is-hidden');
    detailArea.querySelector('.trash-detail-content')?.classList.add('is-hidden');
  }

  function showDetail(data) {

    currentItem = data;

    detailArea.classList.add('is-open');

    detailArea.querySelector('.trash-detail-empty')?.classList.add('is-hidden');
    detailArea.querySelector('.trash-detail-content')?.classList.remove('is-hidden');

    detailTitle.textContent = data.title || '(제목 없음)';

    const rows = [];


    if (data.deleted_at)
      rows.push(['삭제일', data.deleted_at]);

    const deletedBy = actorDisplay(data, 'deleted_by');
    if (deletedBy !== '(알 수 없음)')
      rows.push(['삭제자', deletedBy]);


    if (currentType === 'event') {

      if (data.dtstart)
        rows.push(['시작', data.dtstart]);

      if (data.dtend)
        rows.push(['종료', data.dtend]);

      if (data.status)
        rows.push(['상태', data.status]);

      if (data.location)
        rows.push(['위치', data.location]);

    } else {

      if (data.due)
        rows.push(['마감', data.due]);

      if (data.status)
        rows.push(['상태', data.status]);

      if (data.percent_complete !== undefined && data.percent_complete !== null)
        rows.push(['완료율', data.percent_complete + '%']);
    }

    let description = data.description || '';

    if (!description && data.raw_json) {
      try {
        const raw = JSON.parse(data.raw_json);

        description =
          raw.description ||
          raw.raw?.DESCRIPTION?.value ||
          raw.raw?.raw?.DESCRIPTION?.value ||
          '';
      } catch (e) {
        console.warn('raw_json parse failed');
      }
    }

    detailMeta.innerHTML = rows.map(r => `
      <div class="trash-meta-row">
        <span class="trash-meta-label">${r[0]}</span>
        <span class="trash-meta-value">${r[1]}</span>
      </div>
    `).join('');

    if (description) {
      detailDesc.innerHTML = `
        <div class="trash-desc-section">
          <div class="trash-desc-header">설명</div>
          <div class="trash-desc-text">${description}</div>
        </div>
      `;
    } else {
      detailDesc.innerHTML = '';
    }
  }

  async function restore(uid, type) {

    const url = type === 'event'
      ? `${BASE}/event/restore`
      : `${BASE}/task/restore`;

    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ uid })
    });
  }

async function hardDelete(uid, type) {

  const isEvent = type === 'event';

  const url = isEvent
    ? `${BASE}/event/hard-delete`
    : `${BASE}/task/hard-delete`;

  await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ uid })
  });

  CalendarAPI.clearCache();

  if (isEvent) {

    CalendarAPI.forceRefetch();

  } else {

    await CalendarAPI.fetchTasksForPanel();

    window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});

    if (typeof scheduleRefetchEvents === 'function') {
      scheduleRefetchEvents();
    } else {
      window.__calendar?.refetchEvents();
    }
  }

  hideDetail();
  await load(type);
}

  function getCheckedUids() {
    return Array.from(
      tbody.querySelectorAll('input[type="checkbox"]:checked')
    ).map(cb => cb.dataset.uid);
  }

tbody.addEventListener('click', async (e) => {

  const tr = e.target.closest('tr');
  if (!tr || !tr.dataset.raw) return;

  const data = JSON.parse(tr.dataset.raw);

  if (e.target.closest('input[type="checkbox"]')) return;

  if (e.target.closest('.btn-restore')) {

    const type = currentType;

    await restore(data.uid, type);

    CalendarAPI.clearCache();

    if (type === 'task') {
      await CalendarAPI.fetchTasksForPanel();
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
    }

    CalendarAPI.forceRefetch();

    await load(type);

    return;
  }

  if (e.target.closest('.btn-delete')) {

    if (!confirm('영구삭제하면 복구할 수 없습니다. 계속하시겠습니까?')) return;

    await hardDelete(data.uid, currentType);
    return;
  }

  showDetail(data);
});

  btnRestoreSingle?.addEventListener('click', async () => {
    if (!currentItem) return;

    const type = currentType;

    await restore(currentItem.uid, type);

    CalendarAPI.clearCache();

    if (type === 'task') {
      await CalendarAPI.fetchTasksForPanel();
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
    }

    CalendarAPI.forceRefetch();

    await load(type);
  });

  btnDeleteSingle?.addEventListener('click', async () => {

    if (!currentItem) return;

    if (!confirm('영구삭제하면 복구할 수 없습니다. 계속하시겠습니까?')) return;

    await hardDelete(currentItem.uid, currentType);
  });

  btnRestoreSelected?.addEventListener('click', async () => {

    const uids = getCheckedUids();
    if (!uids.length) return alert('선택된 항목이 없습니다.');

    const type = currentType;

    await Promise.all(uids.map(uid => restore(uid, type)));

    CalendarAPI.clearCache();

    if (type === 'task') {
      await CalendarAPI.fetchTasksForPanel();
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
    }

    CalendarAPI.forceRefetch();

    await load(type);
  });

  btnDeleteSelected?.addEventListener('click', async () => {

    const uids = getCheckedUids();
    if (!uids.length) return alert('선택된 항목이 없습니다.');

    if (!confirm('선택 항목을 영구삭제하시겠습니까?')) return;

    const type = currentType;

    await Promise.all(uids.map(uid => hardDelete(uid, type)));
  });


  btnDeleteAll?.addEventListener('click', async () => {

    if (!confirm('현재 탭의 모든 항목을 영구삭제하시겠습니까?')) return;

    const isEvent = currentType === 'event';

    const url = isEvent
      ? `${BASE}/event/hard-delete-all`
      : `${BASE}/task/hard-delete-all`;

    await fetch(url, {
      method: 'POST',
      credentials: 'same-origin'
    });

    if (isEvent) {

      CalendarAPI.clearCache();
      CalendarAPI.forceRefetch();

    } else {

      CalendarAPI.clearCache();
      await CalendarAPI.fetchTasksForPanel();
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});

      if (typeof scheduleRefetchEvents === 'function') {
        scheduleRefetchEvents();
      } else {
        window.__calendar?.refetchEvents();
      }
    }

    hideDetail();
    await load(currentType);
  });

  tabs.forEach(tab => {
    tab.addEventListener('click', async () => {

      tabs.forEach(t => t.classList.remove('is-active'));
      tab.classList.add('is-active');

      hideDetail();

      await load(tab.dataset.type);   // 🔥 currentType 말고 이게 더 정확
    });
  });

  async function load(type) {
    currentType = type;
    const list = await fetchDeleted(type);
    render(list);
  }

  checkAll?.addEventListener('change', () => {
    tbody.querySelectorAll('input[type="checkbox"]')
      .forEach(cb => cb.checked = checkAll.checked);
  });

  btnTrash?.addEventListener('click', async () => {

    modal.classList.remove('is-hidden');
    currentType = 'event';

    tabs.forEach(tab => {
      tab.classList.toggle('is-active', tab.dataset.type === 'event');
    });

    hideDetail();
    await load('event');
  });

  function close() {
    modal.classList.add('is-hidden');
    hideDetail();
  }

  closeBtn?.addEventListener('click', close);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('is-hidden')) {
      close();
    }
  });

  document.addEventListener('click', (e) => {

    if (modal.classList.contains('is-hidden')) return;
    if (!detailArea.classList.contains('is-open')) return;

    if (e.target.closest('.trash-detail-area')) return;

    if (e.target.closest('#trash-table-body tr')) return;

    hideDetail();
  });






})();
