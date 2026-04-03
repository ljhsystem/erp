// 📄 /public/assets/js/pages/dashboard/calendar/sidebar.right.list.js
(() => {
  'use strict';
  console.log('[sidebar.right.list] loaded');
  const BUILD = '2026-01-RIGHT-LIST-V1';
  if (window.__RIGHT_LIST_BUILD__ === BUILD) return;
  window.__RIGHT_LIST_BUILD__ = BUILD;

  const FilterState = window.__TASK_FILTER_STATE__ = window.__TASK_FILTER_STATE__ || {
    type: 'today',        // today | next7 | date | list
    date: null,           // YYYY-MM-DD
    listId: 'inbox',
    listName: 'Inbox',
    completedOpen: false
  };

  // =====================================================
  // DOM
  // =====================================================
  let panel, listWrap, dd, btnDue, btnClose, inputAdd;
  let chipDate, chipList, metaDate, metaList, listDD;

  let openedRowMenu = null;

  // =====================================================
  // Data
  // =====================================================
  let allTasks = [];

  // =====================================================
  // Boot
  // =====================================================
  document.addEventListener('DOMContentLoaded', () => {
    panel     = document.getElementById('right-list-panel');
    listWrap  = panel?.querySelector('.right-list-task-list') || null;

    btnDue    = document.getElementById('right-list-btn-due');
    btnClose  = document.getElementById('right-list-btn-close');
    dd        = document.getElementById('right-list-dd');

    inputAdd  = document.getElementById('right-list-add-input');

    chipDate  = document.getElementById('right-list-meta-date-chip');
    chipList  = document.getElementById('right-list-meta-list-chip');
    metaDate  = document.getElementById('right-list-meta-date');
    metaList  = document.getElementById('right-list-meta-list');
    listDD    = document.getElementById('right-list-list-dd');

    if (!panel || !listWrap) {
      console.warn('[right.list] panel DOM missing');
      return;
    }

    bindUI();
    syncMetaChips();
    render();
  });

  // =====================================================
  // Public API (기존 TaskPanel 유지)
  // =====================================================
  window.TaskPanel = window.TaskPanel || {};

  window.TaskPanel.setData = function (payload = {}) {
    const { tasks = [] } = payload;
    allTasks = Array.isArray(tasks) ? tasks : [];
    render();
  };

  window.TaskPanel.refresh = function (nextFilterState) {
    if (nextFilterState) {
      Object.assign(FilterState, nextFilterState);
    }
  
    // 🔥 현재 Store 기준으로 tasks 다시 가져오기
    const tasks = window.CalendarStore?.getTasks
    ? CalendarStore.getTasks()
    : allTasks;
  
    allTasks = Array.isArray(tasks) ? tasks : [];
  
    syncMetaChips();
    render();
  };
  
  // =====================================================
  // Events (list -> edit / delete / completed clear)
  // =====================================================
  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }

  // =====================================================
  // UI Bindings
  // =====================================================
  function bindUI() {
    // 닫기(우측 패널 전체 닫기)
    btnClose?.addEventListener('click', () => {
      emit('rightpanel:close');
    });
 
    // ESC로 닫기
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      closeAllRowMenus();      
    });

    // 바깥 클릭 시 열려 있는 row 메뉴 닫기
    document.addEventListener('pointerdown', (e) => {
      if (!openedRowMenu) return;

      // 메뉴 내부 클릭이면 무시
      if (openedRowMenu.contains(e.target)) return;

      closeRowMenu(openedRowMenu);
    });

    // 메타칩(날짜/목록) 클릭 훅 (필요 시 filter.js나 picker.js에서 받도록 이벤트 emit)
    chipDate?.addEventListener('click', (e) => {
      e.stopPropagation();
      emit('rightlist:pickDate', { from: 'metaChip' });
    });

    listDD?.addEventListener('click', (e) => e.stopPropagation());

    // “작업 추가” (엔터 입력 훅만, 실제 생성은 별도 서비스에서 처리하도록 이벤트)
    inputAdd?.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      const title = (inputAdd.value || '').trim();
      if (!title) return;

      emit('task:create', {
        title,
        filter: { ...FilterState }
      });

      inputAdd.value = '';
    });

    // edit 패널에서 “뒤로”를 눌렀을 때 list가 다시 보이도록 이벤트를 받음
    window.addEventListener('rightedit:back', () => {
      openListPanel();
    });

    // 외부에서 필터가 바뀌면 meta chip 동기화
    window.addEventListener('taskfilter:changed', (e) => {
      if (e?.detail) Object.assign(FilterState, e.detail);
      syncMetaChips();
      render();
    });

    // 우측 패널 열기/닫기 외부 제어
    window.addEventListener('rightpanel:openList', () => openListPanel());
  }

  // =====================================================
  // Panel Toggle Helpers
  // =====================================================
  function openListPanel() {
    const edit = document.getElementById('task-panel');
    panel?.classList.remove('is-collapsed');
    edit?.classList.add('is-collapsed');
  }

  // =====================================================
  // Render
  // =====================================================
  function render() {
    if (!listWrap) return;

    const tasks = filterTasks(allTasks);

    listWrap.innerHTML = '';
    if (!tasks.length) {
      renderEmpty();
      return;
    }

    const todayStart = new Date();
    todayStart.setHours(0, 0, 0, 0);

    const upcoming = [];
    const overdue = [];
    const completed = [];

    tasks.forEach(t => {
      if (isCompleted(t)) {
        completed.push(t);
        return;
      }

      const d = parseTaskDate(t?.due_iso);

      // 기한 없음 -> 앞으로
      if (!d) {
        upcoming.push(t);
        return;
      }

      // 오늘 00:00 이전 -> 지연
      if (d < todayStart) {
        overdue.push(t);
        return;
      }

      upcoming.push(t);
    });

    if (upcoming.length) renderSection('앞으로의 작업', upcoming, { overdue: false });
    if (overdue.length)  renderSection('지연', overdue, { overdue: true });
    if (completed.length) renderCompleted(completed);
  }

  function renderEmpty() {
    listWrap.innerHTML = `
      <div class="right-list-empty">
        <div class="right-list-empty-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div class="right-list-empty-text">작업 없음</div>
      </div>
    `;
  }

  function renderSection(title, list, opt = {}) {
    const sec = document.createElement('div');
    sec.className = 'right-list-section';

    sec.innerHTML = `
      <div class="right-list-section-title ${opt.overdue ? 'is-overdue' : ''}">
        ${escapeHtml(title)}
      </div>
    `;

    list.forEach(t => sec.appendChild(renderRow(t, opt)));
    listWrap.appendChild(sec);
  }

  function renderRow(task, opt = {}) {
    const overdue = !!opt.overdue;

    const row = document.createElement('div');
    row.className = 'right-list-row';

    const dueText = task?.due_iso ? formatDueText(task.due_iso) : '';

    const completed = isCompleted(task);

    row.innerHTML = `
      <div class="right-list-item">
        <button type="button"
                class="right-list-check-circle ${completed ? 'is-completed' : ''}"
                aria-label="완료 토글">
        </button>
    
        <span class="right-list-title ${completed ? 'is-completed' : ''}">
          ${escapeHtml(task.title)}
        </span>
      </div>
    
      <div class="right-list-row-actions">
        <span class="right-list-due ${overdue ? 'is-overdue' : ''}">
          ${escapeHtml(dueText)}
        </span>
    
        <button type="button" class="right-list-more-btn" aria-label="더보기">
          <i class="bi bi-three-dots"></i>
        </button>
    
        <div class="right-list-more-menu">
          <button type="button" class="right-list-menu-edit">편집</button>
          <button type="button" class="right-list-menu-delete is-delete">삭제</button>
        </div>
      </div>
    `;


   
    bindRowMenu(row, task);
    bindCheckToggle(row, task);
    return row;
  }

  function bindCheckToggle(row, task) {
    const btn = row.querySelector('.right-list-check-circle');
    if (!btn) return;
  
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
  
      const isNowCompleted = !isCompleted(task);
  
      emit('task:toggleComplete', {
        uid: task.uid,
        calendar_id: getTaskCalendarId(task),
        completed: isNowCompleted
      });
    });
  }

  function bindRowMenu(row, task) {
    const moreBtn = row.querySelector('.right-list-more-btn');
    const menu    = row.querySelector('.right-list-more-menu');
    const editBtn = row.querySelector('.right-list-menu-edit');
    const delBtn  = row.querySelector('.right-list-menu-delete');

    if (!moreBtn || !menu) return;

    // 메뉴 기본 숨김은 CSS로 처리(표시 제어는 row.is-menu-open)
    menu.style.display = 'none';

    moreBtn.addEventListener('click', (e) => {
      e.stopPropagation();

      // 🔥 이미 열린 메뉴가 있고, 그게 내가 아니면 닫기
      if (openedRowMenu && openedRowMenu !== row) {
        closeRowMenu(openedRowMenu);
      }

      const willOpen = !row.classList.contains('is-menu-open');

      if (willOpen) {
        row.classList.add('is-menu-open');
        menu.style.display = 'block';
        openedRowMenu = row;
      } else {
        closeRowMenu(row);
      }
    });


    // 편집
    editBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      closeRowMenu(row);

      /* =================================================
      * 1️⃣ 작업목록 전체 전달 (Inbox 제외)
      * ================================================= */
      const lists = window.CalendarStore?.getCalendars?.()
        ?.filter(c => {
          if (c.type !== 'task') return false;

          // 🔥 Inbox calendar 제거
          const name = String(c.name || c.title || '').toLowerCase();
          return name !== 'inbox';
        }) || [];

      emit('tasklists:set', { lists });

      /* =================================================
      * 2️⃣ 작업 전달 (🔥 Inbox calendar_id 정규화)
      * ================================================= */
      const rawId =
        task.calendar_id ??
        task.extendedProps?.calendar_id ??
        '';

      const normalizedTask = {
        ...task,
        calendar_id:
          String(rawId).toLowerCase() === 'inbox'
            ? ''            // 🔥 Inbox는 항상 빈 값
            : String(rawId)
      };

      emit('rightpanel:openEdit', { task: normalizedTask });

      /* =================================================
      * 3️⃣ 패널 전환
      * ================================================= */
      const listPanel = document.getElementById('right-list-panel');
      const editPanel = document.getElementById('task-panel');

      listPanel?.classList.remove('is-open');
      editPanel?.classList.remove('is-collapsed');
      editPanel?.classList.add('is-open');
    }); 
    
    // 삭제
    delBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      closeRowMenu(row);

      if (!confirm('이 작업을 삭제할까요?')) return;
      emit('task:delete', { task });
    });
  }
  
  function closeRowMenu(row) {
    if (!row) return;
  
    row.classList.remove('is-menu-open');
  
    const menu = row.querySelector('.right-list-more-menu');
    if (menu) menu.style.display = 'none';
  
    if (openedRowMenu === row) {
      openedRowMenu = null;
    }
  }

  
  function closeAllRowMenus() {
    closeRowMenu(openedRowMenu);
  }


  

  // =====================================================
  // Completed (토글 + 모두 제거)
  // =====================================================
  function renderCompleted(tasks) {
    if (!tasks.length) return;

    if (typeof FilterState.completedOpen !== 'boolean') {
      FilterState.completedOpen = false;
    }

    const wrap = document.createElement('div');
    wrap.className = 'right-list-completed';
    wrap.classList.toggle('is-open', !!FilterState.completedOpen);
    wrap.classList.toggle('is-collapsed', !FilterState.completedOpen);

    const header = document.createElement('div');
    header.className = 'right-list-completed-header';
    header.innerHTML = `
    <div class="right-list-completed-left">
      <i class="bi bi-chevron-down completed-toggle-icon"></i>
      <span>완료됨 (${tasks.length})</span>
    </div>
    <button type="button" class="right-list-completed-clear">모두 제거</button>
  `;
  

    const list = document.createElement('div');
    list.className = 'right-list-completed-list';

    tasks.forEach(t => {
      const row = document.createElement('div');
      row.className = 'right-list-row right-list-completed-row'; // 구조 통일 권장
    
      row.innerHTML = `
        <div class="right-list-item is-completed">
        <button type="button"
                class="right-list-check-circle is-completed"
                aria-label="완료 토글">
        </button>
          <span class="right-list-title is-completed">
            ${escapeHtml(t.title)}
          </span>
        </div>
    
        <div class="right-list-row-actions">
          <span class="right-list-due is-completed">
          ${escapeHtml(formatDueText(t.completed_at || t.due_iso))}
          </span>
    
          <button type="button" class="right-list-more-btn" aria-label="더보기">
            <i class="bi bi-three-dots"></i>
          </button>
    
          <div class="right-list-more-menu">
            <button type="button" class="right-list-menu-edit">편집</button>
            <button type="button" class="right-list-menu-delete is-delete">삭제</button>
          </div>
        </div>
      `;
    
      bindRowMenu(row, t);
      bindCheckToggle(row, t);   // 🔥 이 줄 추가
      list.appendChild(row);
    });
    

    // 토글
    header.addEventListener('click', (e) => {
      if (e.target.closest('.right-list-completed-clear')) return;

      FilterState.completedOpen = !FilterState.completedOpen;

      wrap.classList.toggle('is-open', FilterState.completedOpen);
      wrap.classList.toggle('is-collapsed', !FilterState.completedOpen);
    });

    // 모두 제거 (실제 삭제는 외부에서 처리하도록 이벤트)
    header.querySelector('.right-list-completed-clear')?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (!confirm('완료된 작업을 모두 제거할까요?')) return;
      emit('task:completedClear', { filter: { ...FilterState } });
    });

    wrap.appendChild(header);
    wrap.appendChild(list);
    listWrap.appendChild(wrap);
  }

  // =====================================================
  // Filter Logic (기능 유지)
  // =====================================================
  function filterTasks(tasks) {
    const F = FilterState;
  
    const start = new Date();
    start.setHours(0, 0, 0, 0);
  
    const endToday = new Date(start.getTime() + 86400000 - 1);
  
    const end7 = new Date(start);
    end7.setDate(end7.getDate() + 7);
    end7.setHours(23, 59, 59, 999);
  
    return tasks.filter(t => {
  
      // 🔥 완료된 것은 항상 통과
      if (isCompleted(t)) return true;
  
      if (F.type === 'today') {
        return isInRange(t, start, endToday);
      }
  
      if (F.type === 'next7') {
        return isInRange(t, start, end7);
      }
  
      if (F.type === 'date') {
        return getTaskDateLocal(t) === F.date;
      }
  
      if (F.type === 'list') {
        if (F.listId === 'inbox') {
          return !getTaskCalendarId(t);
        }
        return String(getTaskCalendarId(t)) === String(F.listId);
      }
  
      return true;
    });
  }

  function isInRange(t, from, to) {
    const d = parseTaskDate(t?.due_iso);
    if (!d) return false;
    return d >= from && d <= to;
  }

  // =====================================================
  // Meta Chips
  // =====================================================
  function syncMetaChips() {
    if (!metaDate || !metaList) return;

    // 날짜 chip
    if (FilterState.type === 'today') metaDate.textContent = '오늘';
    else if (FilterState.type === 'next7') metaDate.textContent = '다음 7일';
    else if (FilterState.type === 'date') metaDate.textContent = FilterState.date || '';
    else metaDate.textContent = '';

    // 목록 chip
    metaList.textContent = FilterState.listName || 'Inbox';
  }

  // =====================================================
  // Utils
  // =====================================================
  function getTaskCalendarId(t) {
    return t.calendar_id ?? t.extendedProps?.calendar_id ?? null;
  }

  function isCompleted(t) {
    const status =
      t.status ??
      t.extendedProps?.status ??
      null;
  
    const percent =
      t.percent_complete ??
      t.extendedProps?.percent_complete ??
      t.extendedProps?.raw?.PERCENT_COMPLETE ??
      null;
  
    return (
      status === 'COMPLETED' ||
      String(percent) === '100'
    );
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;',
      '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  function parseTaskDate(iso) {
    if (!iso) return null;

    // YYYY-MM-DD or YYYY-MM-DDTHH:mm...
    if (iso.includes('-')) {
      const d = new Date(iso);
      return isNaN(d) ? null : d;
    }

    // CalDAV: YYYYMMDDTHHmmss
    if (/^\d{8}T\d{6}$/.test(iso)) {
      const y  = iso.slice(0, 4);
      const m  = iso.slice(4, 6);
      const d  = iso.slice(6, 8);
      const hh = iso.slice(9, 11);
      const mm = iso.slice(11, 13);
      const ss = iso.slice(13, 15);
      const dt = new Date(`${y}-${m}-${d}T${hh}:${mm}:${ss}`);
      return isNaN(dt) ? null : dt;
    }

    return null;
  }

  function getTaskDateLocal(t) {
    const d = parseTaskDate(t?.due_iso);
    if (!d) return null;
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

  function formatDateTime(iso) {
    if (!iso) return '';
    const d = parseTaskDate(iso) || new Date(iso);
    if (!(d instanceof Date) || isNaN(d)) return '';

    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0') + ' ' +
      String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0')
    );
  }

  function formatDueText(iso) {
    if (!iso) return '';
  
    const d = parseTaskDate(iso);
    if (!(d instanceof Date) || isNaN(d)) return '';
  
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
  
    const base = `${y}-${m}-${day}`;
  
    // 🔥 시간 있는 경우만 시간 표시
    if (hasTimeComponent(iso)) {
      const hh = String(d.getHours()).padStart(2, '0');
      const mm = String(d.getMinutes()).padStart(2, '0');
      return `${base} ${hh}:${mm}`;
    }
  
    return base;
  }

  window.addEventListener('task:toggleComplete', (e) => {
    const { uid, calendar_id, completed } = e.detail;
  
    window.CalendarAPI.toggleTaskComplete(uid, calendar_id, completed);
  });

  function getTaskUidSafe(task) {
    return task?.uid || null;
  }
  
  window.addEventListener('task:delete', async (e) => {
    try {
      const task = e?.detail?.task;
      const uid = getTaskUidSafe(task);
  
      if (!uid) {
        console.warn('[right.list] task:delete - uid missing', task);
        alert('삭제 실패: UID가 없습니다.');
        return;
      }
  
      // ✅ 여기서 실제 삭제 호출
      await window.CalendarAPI.deleteTask(uid);
      console.log('DELETE UID:', uid);
  
    } catch (err) {
      console.error('[right.list] deleteTask failed', err);
      alert(err?.message || '삭제 중 오류가 발생했습니다.');
    }
  });


  function hasTimeComponent(iso) {
    if (!iso) return false;
  
    // YYYY-MM-DD → 날짜-only
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) return false;
  
    // YYYYMMDD → 날짜-only
    if (/^\d{8}$/.test(iso)) return false;
  
    const d = parseTaskDate(iso);
    if (!d) return false;
  
    return d.getHours() !== 0 || d.getMinutes() !== 0;
  }



  window.addEventListener('task:completedClear', async (e) => {
    try {
      const tasks = allTasks.filter(t => isCompleted(t));
      if (!tasks.length) return;
  
      const uids = tasks
        .map(getTaskUidSafe)
        .filter(Boolean);
  
      await window.CalendarAPI.deleteTasksBulk(uids);
  
    } catch (err) {
      console.error('[right.list] completedClear failed', err);
      alert(err?.message || '완료된 작업 삭제 중 오류가 발생했습니다.');
    }
  });

})();
