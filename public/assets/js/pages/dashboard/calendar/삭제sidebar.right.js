// /public/assets/js/pages/dashboard/calendar/sidebar.right.js
import { AdminPicker }from '/public/assets/js/common/picker/admin_picker.js';
(() => 
  {'use strict';
  
  const BUILD = '2026-01-SIDEBAR-RIGHT-V4-FULLFIX';
  if (window.__CAL_SIDEBAR_RIGHT_BUILD__ === BUILD) return;
  window.__CAL_SIDEBAR_RIGHT_BUILD__ = BUILD;

  // DOM
  let panel, listWrap, btnClose, btnDue, dd;
  let ddDateItem, ddDateSub, btnDateOpen, btnListCreate;
  let metaDateEl, metaListEl, addInput;

  let unbindFilterPickerOutside = null;
  let unbindCreatePickerOutside = null;
  let filterDatePicker = null;
  let taskDatePicker   = null;

  let filterHoverInside = false;
  let filterHoverTimer = null;

  // ✅ 반드시 여기
  const CreateState = {
    dueDate: null
  };

  // =========================
  // State
  // =========================
  const State = {
    allTasks: [],        // ✅ 원본 (서버에서 받은 전체)
    taskLists: [],
    completedOpen: false,  
        filter: {
      type: 'today',     // today | next7 | date | list
      date: null,        // YYYY-MM-DD
      listId: 'inbox',   // ✅ 로직용 (calendar_id)
      listName: 'Inbox'  // ✅ UI용 (표시 이름)
    }
  };

  // =========================
  // Boot
  // =========================
  document.addEventListener('DOMContentLoaded', () => {
    panel       = document.getElementById('task-panel');
    listWrap    = panel?.querySelector('.task-list');
    btnClose    = document.getElementById('btn-task-close');
    btnDue      = document.getElementById('btn-task-due');
    dd          = document.getElementById('task-dd');

    ddDateItem  = dd?.querySelector('.task-dd-item.has-submenu[data-filter="date"]');
    ddDateSub   = ddDateItem?.querySelector('.task-dd-submenu');
    btnDateOpen = document.getElementById('btn-task-date-open');

    btnListCreate = document.getElementById('btn-task-list-create');

    metaDateEl  = document.getElementById('task-meta-date');
    metaListEl  = document.getElementById('task-meta-list');
    addInput    = document.getElementById('task-add-input');

    if (!panel || !listWrap || !btnDue || !dd) {
      console.error('[TaskPanel] DOM missing', { panel, listWrap, btnDue, dd });
      return;
    }

    bindUI();
    initFilterDatePicker();
    initCreateDatePicker();
    // 초기 렌더: 빈 상태
    State.allTasks = [];
    applyTaskFilterAndRender();
  });

  // =========================
  // UI Bindings
  // =========================
  function bindUI() {
    // 닫기
    btnClose?.addEventListener('click', () => {
      const shell = document.querySelector('.calendar-shell');
      if (!shell) return;

      window.toggleWithHorizontalResize?.(() => {
        shell.classList.add('right-collapsed');
      });
    });

    // 드롭다운 열기/닫기
    btnDue.addEventListener('click', (e) => {
      e.stopPropagation();
      const hidden = dd.classList.toggle('is-hidden');
      btnDue.setAttribute('aria-expanded', String(!hidden));
    });

    // ===============================
    // 필터 드롭다운: 바깥 클릭 시 닫기
    // ===============================
    document.addEventListener('click', (e) => {
      // 이미 닫혀 있으면 무시
      if (dd.classList.contains('is-hidden')) return;

      // 🔒 드롭다운 내부 클릭은 무시
      if (e.target.closest('#task-dd')) return;

      // 🔒 드롭다운 트리거 버튼 클릭은 무시
      if (e.target.closest('#btn-task-due')) return;

      // 🔒 작업목록 드롭다운 클릭은 무시시
      if (e.target.closest('#task-list-dd')) return;

      // 🔒 날짜 피커 영역 클릭은 무시
      if (e.target.closest('#task-filter-date-picker')) return;
      if (e.target.closest('#task-create-picker')) return;

      // ✅ 그 외 = 바깥 클릭
      closeDropdown();
    });

    
    
    

    // 드롭다운 내부 클릭은 전파 막기
    dd.addEventListener('click', (e) => {
      e.stopPropagation();
    
      const btn = e.target.closest('.task-dd-item');
      if (!btn) return;
    
      // 날짜 항목은 클릭 필터 금지
      if (btn.dataset.filter === 'date') return;
    
      const filter = btn.dataset.filter;
    
      // ✅ list id/name 가져오기
      const listId   = btn.dataset.listId || btn.dataset.list || null;
      const listName = btn.dataset.listName || btn.textContent?.trim() || null;
    
      /* 날짜 기반 */
      if (filter) {
        setActiveDropdownItem(btn);
    
        State.filter.type = filter;
        State.filter.date = null;
    
        // ✅ 날짜 필터는 리스트 유지(원하면 유지), 아니면 inbox로 초기화
        // 여기서는 유지하자: 사용자 입장에서 자연스러움
        // State.filter.listId, listName 그대로 둠
    
        updateDueLabelForFilter();
        updateMetaChips();
        closeDropdown();
        applyTaskFilterAndRender();
        return;
      }
    
      /* Inbox */
      if ((btn.dataset.list || '').toLowerCase() === 'inbox' || listId === 'inbox') {
        setActiveDropdownItem(btn);
    
        State.filter.type = 'inbox';
        State.filter.date = null;
        State.filter.listId = 'inbox';
        State.filter.listName = 'Inbox';
    
        updateDueLabelForFilter();
        updateMetaChips();
        closeDropdown();
        applyTaskFilterAndRender();
        return;
      }
    
      /* 기타 리스트 */
      if (listId) {
        setActiveDropdownItem(btn);
    
        State.filter.type = 'list';
        State.filter.date = null;
        State.filter.listId = listId;
        State.filter.listName = listName || listId;
    
        updateDueLabelForFilter();
        updateMetaChips();
        closeDropdown();
        applyTaskFilterAndRender();
        return;
      }
    });
    

      // 작업 목록 생성 버튼 (모달 훅)
      btnListCreate?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeDropdown();
        if (window.AppCore?.openTaskListCreateModal) {
          window.AppCore.openTaskListCreateModal();
        } else {
          // TODO: 네 모달 시스템에 맞게 연결
          console.log('[TaskPanel] TODO: open task list create modal');
        }
      });

      // 작업 추가 입력 (현재는 읽기 우선이므로 엔터 훅만 준비)
      addInput?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const title = String(addInput.value || '').trim();
        if (!title) return;

        // TODO: 생성 API 연결 예정
        console.log('[TaskPanel] TODO: create task', {
          title,
          filter: { ...State.filter }
        });

        addInput.value = '';
      });



      // ===============================
      // 🔥 특정 날짜 hover 시 즉시 날짜피커 오픈
      // ===============================
    
      //필터용 미니달력
      const filterPickerEl = document.getElementById('task-filter-date-picker');
      ddDateItem?.addEventListener('mouseenter', onFilterHoverEnter);
      ddDateItem?.addEventListener('mouseleave', onFilterHoverLeave);      
      filterPickerEl?.addEventListener('mouseenter', () => {
        filterHoverInside = true;
        clearTimeout(filterHoverTimer);
      });      
      filterPickerEl?.addEventListener('mouseleave', onFilterHoverLeave);
      
     
      
      //생성용 미니달력
      metaDateEl?.addEventListener('click', (e) => {
        e.stopPropagation();
      
        const pickerEl = document.getElementById('task-create-picker');
        if (!pickerEl) return;
      
        const willOpen = pickerEl.classList.contains('is-hidden');
      
        // 🔥 토글
        pickerEl.classList.toggle('is-hidden', !willOpen);
      
        if (!willOpen) {
          // 닫히는 경우
          unbindCreatePickerOutside?.();
          unbindCreatePickerOutside = null;
          return;
        }
      
        // 열리는 경우
        const rect = metaDateEl.getBoundingClientRect();
        pickerEl.style.position = 'fixed';
        pickerEl.style.left = `${rect.left - 320}px`;
        pickerEl.style.top  = `${rect.bottom + 4}px`;
      
        unbindCreatePickerOutside?.();
        unbindCreatePickerOutside = AdminPicker.bindOutsideClick(
          pickerEl,
          () => {
            pickerEl.classList.add('is-hidden');
            unbindCreatePickerOutside = null;
          }
        );
      });
      
      
      //작업목록 리스트
      const metaListChip = document.getElementById('task-meta-list-chip');
      const listDD = document.getElementById('task-list-dd');
      
      let unbindListOutside = null;

      metaListChip?.addEventListener('click', (e) => {
        e.stopPropagation();
      
        const willOpen = listDD.classList.contains('is-hidden');
      
        // 🔥 토글
        listDD.classList.toggle('is-hidden', !willOpen);
      
        if (!willOpen) {
          // 닫히는 경우
          unbindListOutside?.();
          unbindListOutside = null;
          return;
        }
      
        // 열리는 경우
        const rect = metaListChip.getBoundingClientRect();
        listDD.style.position = 'fixed';
        listDD.style.left = `${rect.left}px`;
        listDD.style.top  = `${rect.bottom + 6}px`;
      
        unbindListOutside?.();
        unbindListOutside = AdminPicker.bindOutsideClick(
          listDD,
          () => {
            listDD.classList.add('is-hidden');
            unbindListOutside = null;
          }
        );
      });
      




      // 목록 선택
      listDD?.addEventListener('click', (e) => {
        const btn = e.target.closest('.task-dd-item[data-list-id]');
        if (!btn) return;

        const listId   = btn.dataset.listId;
        const listName = btn.dataset.listName;

        // 🔥 생성용 상태 저장
        CreateState.listId   = listId;
        CreateState.listName = listName;

        // 🔥 Meta chip UI 갱신
        metaListEl.textContent = listName;

        const iconEl = document
          .getElementById('task-meta-list-chip')
          ?.querySelector('i.bi');

        if (iconEl) {
          iconEl.className =
            'bi ' + (listName.toLowerCase() === 'inbox'
              ? 'bi-inbox'
              : 'bi-folder');
        }

        listDD.classList.add('is-hidden');

        console.log('선택됨:', listName);
        setTimeout(() => {
          console.log('1초 후 metaList:', metaListEl.textContent);
        }, 1000);
        


      });

      
      

      
  }


//필터용 날짜 피커
  function initFilterDatePicker() {
    const container = document.getElementById('task-filter-date-picker');
    if (!container) return;
  
    filterDatePicker = AdminPicker.create({
      type: 'date',
      container
    });
  
    filterDatePicker.subscribe((state, finalDate) => {
      const date = finalDate || state?.selectedDate;
      if (!date) return;
  
      const ymd = formatDateLocal(finalDate);
  
      State.filter.type = 'date';
      State.filter.date = ymd;
      State.filter.list = null;
  
      updateDueLabelForFilter();
      updateMetaChips();
      applyTaskFilterAndRender();
      // ✅ 날짜 선택 후 즉시 닫기
      closeDropdown();
    });
  }

//생성용 날짜 피커
  function initCreateDatePicker() {
    const container = document.getElementById('task-create-picker');
    if (!container) return;
  
    taskDatePicker = AdminPicker.create({
      type: 'datetime',
      container
    });
  
    taskDatePicker.subscribe((state, finalDate) => {

      // =========================
      // 🔥 지우기 버튼 클릭
      // =========================
      if (!finalDate) {
        CreateState.dueDate = null;
    
        // 날짜 표시 제거
        metaDateEl.textContent = '기한 시간 추가'; // 또는 '' 도 가능
    
    
        return;
      }
    
      // =========================
      // 날짜 선택 시
      // =========================
      CreateState.dueDate = finalDate;
    
      const timeOn = !!(
        state &&
        (state.timeEnabled || state.enableTime || state.withTime)
      );
    
      metaDateEl.textContent = timeOn
        ? formatDateTimeLocal(finalDate)
        : formatDateLocal(finalDate);
    });
    
  }
  

  function openFilterDatePicker() {
    const pickerEl = document.getElementById('task-filter-date-picker');
    if (!pickerEl) return;
  
    pickerEl.classList.remove('is-hidden');
  
    const rect = ddDateItem.getBoundingClientRect();
    pickerEl.style.position = 'fixed';
    pickerEl.style.left = `${Math.max(12, rect.left - 320)}px`;
    pickerEl.style.top  = `${rect.top}px`;
  
    unbindFilterPickerOutside?.();
    unbindFilterPickerOutside = AdminPicker.bindOutsideClick(
      pickerEl,
      closeFilterDatePicker
    );
  }
  
  function closeFilterDatePicker() {
    const pickerEl = document.getElementById('task-filter-date-picker');
    if (!pickerEl) return;
  
    pickerEl.classList.add('is-hidden');
    unbindFilterPickerOutside?.();
    unbindFilterPickerOutside = null;
  }
  
  function onFilterHoverEnter() {
    filterHoverInside = true;
    clearTimeout(filterHoverTimer);
    openFilterDatePicker();
  }
  
  function onFilterHoverLeave() {
    filterHoverInside = false;
    filterHoverTimer = setTimeout(() => {
      if (!filterHoverInside) {
        closeFilterDatePicker();
      }
    }, 120);
  }
  






    
    


    



    function closeDropdown() {
      dd.classList.add('is-hidden');
      btnDue?.setAttribute('aria-expanded', 'false');
      ddDateSub?.classList.add('is-hidden');
    
      document.getElementById('task-filter-date-picker')?.classList.add('is-hidden');
    }
    
    
    

  // function toggleDateSubmenu() {
  //   if (!ddDateSub) return;
  //   ddDateSub.classList.toggle('is-hidden');
  // }

  function setActiveDropdownItem(activeBtn) {
    dd.querySelectorAll('.task-dd-item').forEach((x) => x.classList.remove('is-active'));
    activeBtn.classList.add('is-active');
  }

  // =========================
  // Filter + Label + Meta
  // =========================
  function updateDueLabelForFilter() {
    const label = document.querySelector('.task-dd-label');
    if (!label) return;
  
    const { type, listName, date } = State.filter;
  
    if (type === 'inbox') { label.textContent = 'Inbox'; return; }
    if (type === 'list')  { label.textContent = listName || '작업목록'; return; }
    if (type === 'today') { label.textContent = '오늘'; return; }
    if (type === 'next7') { label.textContent = '다음 7일'; return; }
    if (type === 'date')  { label.textContent = date || ''; return; }
  }
  
  


  function updateMetaChips() {    
  if (metaListEl) metaListEl.textContent = prettyListName(State.filter.list || 'inbox');
    // meta-date는 "기준일" 표시만: today/next7/date에 맞춰 표현
    if (!metaDateEl) return;

    if (State.filter.type === 'today') {
      metaDateEl.textContent = formatDateLocal(new Date());
      return;
    }
    if (State.filter.type === 'next7') {
      metaDateEl.textContent = formatDateLocal(new Date());
      return;
    }
    if (State.filter.type === 'date') {
      metaDateEl.textContent = State.filter.date || '';
      return;
    }
    // list 모드에서는 날짜칩은 오늘 기준 유지 (네 스샷처럼)
    metaDateEl.textContent = formatDateLocal(new Date());
  }

  function prettyListName(listKey) {
    if (!listKey) return 'Inbox';
    if (listKey === 'inbox') return 'Inbox';
    return listKey;
  }

  // =========================
  // Data -> Filter -> Render
  // =========================
  function applyTaskFilterAndRender() {
    const filtered = filterTasks(State.allTasks);
    renderTasks(filtered);
    updateDropdownCounts(State.allTasks);
  }

  function filterTasks(tasks = []) {
    let list = [...tasks];
    const today = formatDateLocal(new Date());
  
    // 🔥 Inbox = 전체 (아무 필터도 적용하지 않음)
    if (State.filter.type === 'inbox') {
      return list;
    }
  
    if (State.filter.type === 'today') {
      return list.filter(t => t.due_iso?.slice(0, 10) === today);
    }
  
    if (State.filter.type === 'next7') {
      const end = new Date();
      end.setDate(end.getDate() + 7);
  
      return list.filter(t => {
        if (!t.due_iso) return false;
        const d = new Date(t.due_iso);
        return d >= new Date(today) && d <= end;
      });
    }
  
    if (State.filter.type === 'date') {
      return list.filter(t =>
        t.due_iso?.slice(0, 10) === State.filter.date
      );
    }
  
    if (State.filter.type === 'list') {
      return list.filter(t =>
        String(t.calendar_id) === String(State.filter.listId)
      );
    }    
    
  
    return list;
  }
  
  function updateDropdownCounts(all) {
    if (!dd) return;
    all = Array.isArray(all) ? all : [];
  
    const todayStr = formatDateLocal(new Date());
  
    const start = new Date();
    start.setHours(0, 0, 0, 0);
  
    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    end.setHours(23, 59, 59, 999);
  
    /* =========================
       오늘
    ========================= */
    const cToday = all.filter(t => {
      if (!isCountable(t)) return false;
      return getTaskDateLocal(t) === todayStr;
    }).length;
  
    /* =========================
       다음 7일
    ========================= */
    const cNext7 = all.filter(t => {
      if (!isCountable(t)) return false;
      const dStr = getTaskDateLocal(t);
      if (!dStr) return false;
      const d = new Date(dStr);
      return d >= start && d <= end;
    }).length;
  
    /* =========================
       작업목록별 집계
    ========================= */
    const byList = {};
    all.forEach(t => {
      const key = String(t?.calendar_id || 'inbox');
      byList[key] = (byList[key] || 0) + 1;
    });
  
    /* =========================
       DOM 반영
    ========================= */
    const setCount = (selector, n) => {
      const el = dd.querySelector(selector);
      if (!el) return;
      const em = el.querySelector('em');
      if (em) em.textContent = `(${n})`;
    };
  
    setCount('[data-filter="today"]', cToday);
    setCount('[data-filter="next7"]', cNext7);
  
    const inboxCount = all.filter(t => {
      if (!isCountable(t)) return false;
      return String(t?.calendar_id || '').toLowerCase() === 'inbox';
    }).length;
  
    setCount('[data-list="inbox"]', inboxCount);
  
    dd.querySelectorAll('.task-dd-item[data-list]').forEach(btn => {
      const key = String(btn.dataset.list || '');
      const em = btn.querySelector('em');
      if (em) em.textContent = `(${byList[key] || 0})`;
    });
  
    console.log('[COUNT DEBUG]', {
      total: all.length,
      today: cToday,
      next7: cNext7,
      byList
    });
  }


  // =========================
  // Render
  // =========================
  function renderTasks(tasks = []) {
    listWrap.innerHTML = '';

    const today = formatDateLocal(new Date());

    const upcoming = [];
    const overdue = [];
    const completed = [];

    (tasks || []).forEach((t) => {
      if (isCompletedTask(t)) {
        completed.push(t);
        return;
      }

      const due = t?.due_iso ? t.due_iso.slice(0, 10) : null;
      if (due && due < today) overdue.push(t);
      else upcoming.push(t);
    });

    if (!upcoming.length && !overdue.length && !completed.length) {
      renderEmpty();
      return;
    }

    if (upcoming.length) renderSection('앞으로의 작업', upcoming);
    if (overdue.length) renderSection('지연', overdue, { overdue: true });
    if (completed.length) renderCompletedBlock(completed);
  }

  function renderSection(title, tasks, opts = {}) {
    const sec = document.createElement('div');
    sec.className = 'task-section';

    sec.innerHTML = `
      <div class="task-group-title ${opts.overdue ? 'is-overdue' : ''}">
        ${title}
      </div>
    `;

    tasks.forEach((t) => sec.appendChild(renderTaskItem(t, !!opts.overdue)));
    listWrap.appendChild(sec);
  }

  function renderTaskItem(task, isOverdue = false) {
    const row = document.createElement('div');
    row.className = 'task-row';

    row.innerHTML = `
      <div class="task-item">
        <span class="task-check-circle"></span>
        <span class="task-title">${escapeHtml(task?.title || '')}</span>
      </div>

      <div class="task-meta-right">
        <span class="task-due ${isOverdue ? 'is-overdue' : ''}">
          ${formatDateTime(task?.due_iso)}
        </span>
      </div>
    `;

    // 현재 단계: 읽기 전용 (완료 클릭/체크 처리 X)
    return row;
  }

  // =========================
  // Completed (읽기 전용 + 토글)
  // =========================
  function renderCompletedBlock(tasks) {
    const wrap = document.createElement('div');
    wrap.className = `task-completed ${State.completedOpen ? 'is-open' : 'is-collapsed'}`;

    const header = document.createElement('div');
    header.className = 'task-completed-header';

    header.innerHTML = `
      <div class="task-completed-left">
        <span class="task-completed-title">완료됨 (${tasks.length})</span>
      </div>
      <button type="button" class="task-completed-clear">모두 제거</button>
    `;

    const list = document.createElement('div');
    list.className = 'task-completed-list';

    tasks.forEach((t) => {
      const row = document.createElement('div');
      row.className = 'task-row is-completed';

      row.innerHTML = `
        <span class="task-check-circle is-completed"></span>
        <span class="task-title is-completed">${escapeHtml(t?.title || '')}</span>
        <span class="task-due">${formatDateTime(t?.completed_at || t?.due_iso)}</span>
      `;

      list.appendChild(row);
    });

    // 헤더 클릭: 토글
    header.addEventListener('click', (e) => {
      if (e.target.closest('.task-completed-clear')) return;
      State.completedOpen = !State.completedOpen;
      wrap.classList.toggle('is-open', State.completedOpen);
      wrap.classList.toggle('is-collapsed', !State.completedOpen);
    });

    // 모두 제거: 현재는 훅만 (읽기 우선)
    header.querySelector('.task-completed-clear')?.addEventListener('click', (e) => {
      e.stopPropagation();
      console.log('[TaskPanel] TODO: clear completed tasks');
    });

    wrap.appendChild(header);
    wrap.appendChild(list);
    listWrap.appendChild(wrap);
  }

  function renderEmpty() {
    listWrap.innerHTML = `
      <div class="task-empty">
        <div class="task-empty-icon">
          <i class="bi bi-file-earmark-text"></i>
        </div>
        <div class="task-empty-text">작업 없음</div>
      </div>
    `;
  }

  // =========================
  // Utils
  // =========================
  function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';

    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0') + ' ' +
      String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0')
    );
  }
  function formatDateLocal(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  
  function formatDateTimeLocal(d) {
    const ymd = formatDateLocal(d);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${ymd} ${hh}:${mm}`;
  }
  

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (m) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[m]));
  }

  
  function isCompletedTask(t) {
    if (!t) return false;
    if (t.completed === true) return true;
    if (t.status === 'COMPLETED') return true;
    if (String(t['percent-complete'] || t.percent_complete) === '100') return true;
    return false;
  }

  function renderTaskListFilter() {
    if (!dd) return;
  
    dd.querySelectorAll('.task-dd-item[data-list]').forEach(el => el.remove());
  
    const title = [...dd.querySelectorAll('.task-dd-group-title')]
      .find(el => el.textContent.includes('작업 목록'));
    if (!title) return;
  
    let insertAfter = title;
  
    State.taskLists.forEach(list => {
      const id = String(list.calendar_id || '');
      if (!id) return;
  
      const name = list.displayname || list.name || '이름없음';
  
      const count = State.allTasks.filter(
        t => String(t.calendar_id) === id
      ).length;
  
      const isInbox =
        (list.displayname || list.name || '').toLowerCase() === 'inbox';
  
      const icon = isInbox ? 'bi-inbox' : 'bi-folder';
  
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'task-dd-item';
      btn.dataset.list = id;
  
      btn.innerHTML = `
        <i class="bi ${icon}"></i>
        <span>${name} <em>(${count})</em></span>
      `;
  
      insertAfter.after(btn);
      insertAfter = btn;
    });
  }
  

  function renderTaskListCreateDropdown() {
    const listDD = document.getElementById('task-list-dd');
    if (!listDD) return;
  
    // 기존 항목 제거
    listDD.querySelectorAll('.task-dd-item[data-list-id]')
      .forEach(el => el.remove());
  
    State.taskLists.forEach(list => {
      const id   = String(list.calendar_id || '');
      if (!id) return;
  
      const name = list.displayname || list.name || '이름없음';
      const isInbox = name.toLowerCase() === 'inbox';
      const icon = isInbox ? 'bi-inbox' : 'bi-folder';
  
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'task-dd-item';
      btn.dataset.listId = id;
      btn.dataset.listName = name;
  
      btn.innerHTML = `
        <i class="bi ${icon}"></i>
        <span>${name}</span>
      `;
  
      listDD.appendChild(btn);
    });
  }
  
  

  // =========================
  // Count Policy
  // =========================
  const INCLUDE_COMPLETED_IN_COUNTS = false;

  // 카운트에 포함할 작업인지 판단
  function isCountable(t) {
    return INCLUDE_COMPLETED_IN_COUNTS ? true : !isCompletedTask(t);
  }

  // 작업의 "로컬 기준 날짜(YYYY-MM-DD)" 반환
  function getTaskDateLocal(t) {
    const iso = t?.due_iso;
    if (!iso) return null;

    // 날짜만 있는 경우
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso;

    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return null;

    return formatDateLocal(d);
  }

  
  
  // =========================
  // External API (다른 파일에서 호출)
  // =========================
  window.TaskPanel = {
    // ✅ 원본 전체 tasks 세팅 후 현재 필터로 렌더
    setData(payload = {}) {
      const { tasks = [], lists = [] } = payload;
    
      State.allTasks  = Array.isArray(tasks) ? tasks : [];
      State.taskLists = Array.isArray(lists) ? lists : [];
    
       // 🔥 필터 드롭다운
      renderTaskListFilter();

      // 🔥 작업 추가용 드롭다운
      renderTaskListCreateDropdown();

      // 🔥 기본 선택값 (Inbox)
      State.filter.listId   = 'inbox';
      State.filter.listName = 'Inbox';
      CreateState.listId    = 'inbox';
      CreateState.listName  = 'Inbox';

      metaListEl.textContent = 'Inbox';

      updateDueLabelForFilter();
      updateMetaChips();
      applyTaskFilterAndRender();
    },

    // ✅ 패널 열기
    open(tasks = null) {
      const shell = document.querySelector('.calendar-shell');
      if (!shell) return;

      window.toggleWithHorizontalResize?.(() => {
        shell.classList.remove('right-collapsed');
      });

      if (Array.isArray(tasks)) {
        State.allTasks = tasks;
        applyTaskFilterAndRender();
      }
    },

    close() {
      const shell = document.querySelector('.calendar-shell');
      if (!shell) return;

      window.toggleWithHorizontalResize?.(() => {
        shell.classList.add('right-collapsed');
      });
    },

    // ✅ 특정 날짜가 선택됐을 때 외부에서 호출 (flatpickr 콜백 연결용)
    pickDate(dateStr) {
      State.filter.type = 'date';
      State.filter.date = dateStr;
      updateDueLabelForFilter();
      updateMetaChips();

      // 드롭다운에서 date 항목 active 처리
      const dateBtn = dd?.querySelector('.task-dd-item.has-submenu[data-filter="date"]');
      if (dateBtn) setActiveDropdownItem(dateBtn);

      closeDropdown();
      applyTaskFilterAndRender();
    },

    // (선택) 네가 flatpickr를 여기서 열고 싶으면 외부에서 override
    openDatePicker: null
  };

})();
