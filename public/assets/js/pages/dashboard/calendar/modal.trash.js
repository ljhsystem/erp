// 📄 /assets/js/pages/dashboard/calendar/modal.trash.js
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

  /* =========================================================
   * Fetch
   * ========================================================= */
  async function fetchDeleted(type) {
    const url = type === 'event'
      ? `${BASE}/events-deleted`
      : `${BASE}/tasks-deleted`;

    const res = await fetch(url, { credentials: 'same-origin' });
    const json = await res.json();

    return json.success ? (json.data || []) : [];
  }

  /* =========================================================
   * Render
   * ========================================================= */
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
        <td>${row.deleted_by_name || row.deleted_by || ''}</td>
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

  /* =========================================================
   * Detail
   * ========================================================= */
  function hideDetail() {
    currentItem = null;
    detailArea.classList.remove('is-open');
  
    detailArea.querySelector('.trash-detail-empty')?.classList.remove('is-hidden');
    detailArea.querySelector('.trash-detail-content')?.classList.add('is-hidden');
  }

  function showDetail(data) {

    currentItem = data;
  
    detailArea.classList.add('is-open');
  
    // empty 영역 숨김
    detailArea.querySelector('.trash-detail-empty')?.classList.add('is-hidden');
    detailArea.querySelector('.trash-detail-content')?.classList.remove('is-hidden');
  
    detailTitle.textContent = data.title || '(제목 없음)';
  
    const rows = [];
  
    /* ===============================
       🔥 기본 정보
    =============================== */
  
    if (data.deleted_at)
      rows.push(['삭제일', data.deleted_at]);
  
    if (data.deleted_by_name || data.deleted_by)
      rows.push(['삭제자', data.deleted_by_name || data.deleted_by]);
  
    /* ===============================
       🔥 타입별 정보
    =============================== */
  
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
  
    /* ===============================
       🔥 raw_json 에서 description 추출
    =============================== */
  
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
  
    /* ===============================
       🔥 메타 렌더링
    =============================== */
  
    detailMeta.innerHTML = rows.map(r => `
      <div class="trash-meta-row">
        <span class="trash-meta-label">${r[0]}</span>
        <span class="trash-meta-value">${r[1]}</span>
      </div>
    `).join('');
  
    /* ===============================
       🔥 설명 렌더링 (있을 때만)
    =============================== */
  
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

  /* =========================================================
   * Restore
   * ========================================================= */
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

 /* =========================================================
 * Hard Delete (서버 전용)
 * ========================================================= */
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

  /* ===============================
   * 🔥 UI 동기화
   * =============================== */

  CalendarAPI.clearCache();

  if (isEvent) {

    CalendarAPI.forceRefetch();

  } else {

    // 1️⃣ 패널 데이터 재로딩
    await CalendarAPI.fetchTasksForPanel();

    // 2️⃣ 필터 유지
    window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});

    // 3️⃣ 달력 갱신
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

/* =========================================================
 * Row Click Handling
 * ========================================================= */
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

  /* =========================================================
   * Detail Buttons
   * ========================================================= */
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

  /* =========================================================
   * Bulk Restore
   * ========================================================= */
  btnRestoreSelected?.addEventListener('click', async () => {

    const uids = getCheckedUids();
    if (!uids.length) return alert('선택된 항목이 없습니다.');
  
    const type = currentType;
  
    await Promise.all(uids.map(uid => restore(uid, type)));
  
    CalendarAPI.clearCache();
  
    // 🔥 여기 추가 (TaskPanel 갱신)
    if (type === 'task') {
      await CalendarAPI.fetchTasksForPanel();
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
    }
  
    CalendarAPI.forceRefetch();
  
    await load(type);
  });

  /* =========================================================
   * Bulk Delete
   * ========================================================= */
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
  
  /* =========================================================
   * Tabs
   * ========================================================= */
  tabs.forEach(tab => {
    tab.addEventListener('click', async () => {
  
      tabs.forEach(t => t.classList.remove('is-active'));
      tab.classList.add('is-active');
  
      hideDetail();
  
      await load(tab.dataset.type);   // 🔥 currentType 말고 이게 더 정확
    });
  });

  /* =========================================================
   * Load
   * ========================================================= */
  async function load(type) {
    currentType = type;
    const list = await fetchDeleted(type);
    render(list);
  }

  /* =========================================================
   * Check All
   * ========================================================= */
  checkAll?.addEventListener('change', () => {
    tbody.querySelectorAll('input[type="checkbox"]')
      .forEach(cb => cb.checked = checkAll.checked);
  });

  /* =========================================================
   * Open
   * ========================================================= */
  btnTrash?.addEventListener('click', async () => {

    modal.classList.remove('is-hidden');
    currentType = 'event';

    tabs.forEach(tab => {
      tab.classList.toggle('is-active', tab.dataset.type === 'event');
    });

    hideDetail();
    await load('event');
  });

  /* =========================================================
   * Close
   * ========================================================= */
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




  /* =========================================================
  * Detail Outside Click Close
  * ========================================================= */
  document.addEventListener('click', (e) => {

    if (modal.classList.contains('is-hidden')) return;
    if (!detailArea.classList.contains('is-open')) return;

    // 디테일 영역 클릭은 무시
    if (e.target.closest('.trash-detail-area')) return;

    // 테이블 row 클릭은 무시 (이미 showDetail 호출됨)
    if (e.target.closest('#trash-table-body tr')) return;

    // 나머지 영역 클릭 시 닫기
    hideDetail();
  });






})();