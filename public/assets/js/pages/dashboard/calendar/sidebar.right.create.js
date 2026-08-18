import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
  'use strict';
  console.log('[sidebar.right.create] loaded');
  const CreateState = window.__TASK_CREATE_STATE__ = {
    dueDate: null,
    hasTime: false,
    listId: null,
    listName: null
  };

  let metaDateEl, metaDateChip, addInput;
  let picker, unbindOutside;
  let pickerOpen = false;
  let metaListChip, listDd;
  let listOpen = false;
  let addBtn;


  document.addEventListener('DOMContentLoaded', () => {
    addInput = document.getElementById('right-list-add-input');

    metaDateEl   = document.getElementById('filter-meta-date');
    metaDateChip = document.getElementById('right-list-meta-date-chip');

    metaListChip = document.getElementById('right-list-meta-list-chip');
    listDd       = document.getElementById('right-list-list-dd');

    const listLabel = document.getElementById('right-list-meta-list');
    if (listLabel) {
      listLabel.textContent = 'Inbox';
    }

    addBtn = document.getElementById('right-list-add-btn');

    addInput?.addEventListener('input', () => {
      const hasText = addInput.value.trim().length > 0;
      addBtn?.classList.toggle('is-hidden', !hasText);
    });

    CreateState.dueDate = new Date();
    CreateState.hasTime = false;
    syncMetaDate();

    initCreateDatePicker();
    bindCreateInput();
    bindDateChip();
    bindListChip();
  });


  document.addEventListener('calendar:ready', () => {
    renderTaskLists();
  });

  function initCreateDatePicker() {
    const el = document.getElementById('base-picker');
    if (!el) return;

    picker = AdminPicker.create({
      type: 'datetime',
      container: el
    });

    const clearBtn = el.querySelector('[data-action="clear"], .picker-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', e => {
        e.stopPropagation();

        picker.setDate?.(null);
        picker.setTime?.(null);

        CreateState.dueDate = null;
        CreateState.hasTime = false;

        syncMetaDate();
      });
    }

    picker.subscribe((_, date) => {
      CreateState.dueDate = date || null;
      CreateState.hasTime = !!date && isTimeEnabled(el);

      syncMetaDate();
    });

  }

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;

    if (pickerOpen) {
      closePicker();
    }

    if (listOpen) {
      closeListDd();
    }
  });

  function bindDateChip() {
    if (!metaDateChip) return;

    metaDateChip.addEventListener('click', e => {
      e.stopPropagation();

      if (pickerOpen) {
        closePicker();
      } else {
        openPicker();
      }
    });

  }

  function openPicker() {
    if (!picker || !metaDateChip) return;

    picker.open({
      anchor: metaDateChip,
      placement: 'left',
      offset: 8
    });

    pickerOpen = true;
  }

  function closePicker() {
    picker?.close?.();
    pickerOpen = false;
  }

  function renderTaskLists() {
    if (!listDd || !window.CalendarStore) return;

    const wrap = listDd.querySelector('.task-dd-lists');
    if (!wrap) return;

    const lists = CalendarStore.getCalendars?.() || [];

    wrap.innerHTML = '';

    const taskLists = lists.filter(cal => cal.type === 'task');

    if (!taskLists.length) {
      wrap.innerHTML = `
        <div class="right-list-empty" style="padding:12px;text-align:center;">
          목록 없음
        </div>
      `;
      return;
    }

    if (!CreateState.listId) {
      const first = taskLists[0];
      selectList({
        id: String(first.calendar_id),
        name: first.name
      });
    }

    taskLists.forEach(cal => {
      const id   = String(cal.calendar_id);
      const name = cal.name || 'Unnamed';

      const isInbox = String(name).toLowerCase() === 'inbox';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'right-list-dd-item task-dd-item';
      btn.dataset.listId = id;
      btn.dataset.listName = name;

      btn.innerHTML = `
        <i class="bi ${isInbox ? 'bi-inbox' : 'bi-folder'}"></i>
        <span>${name}</span>
      `;

      btn.addEventListener('click', e => {
        e.stopPropagation();
        selectList({ id, name });
        closeListDd();
      });

      wrap.appendChild(btn);
    });
  }


  function selectList({ id, name }) {
    CreateState.listId   = id;
    CreateState.listName = name;

    const icon  = metaListChip.querySelector('i.bi');
    const label = metaListChip.querySelector('#right-list-meta-list');

    const isInbox = String(name).toLowerCase() === 'inbox';

    if (icon) {
      icon.className = 'bi ' + (isInbox ? 'bi-inbox' : 'bi-folder');
    }
    if (label) {
      label.textContent = name;
    }

    closeListDd();
  }


  function bindListChip() {
    if (!metaListChip) return;

    metaListChip.addEventListener('click', e => {
      e.stopPropagation();
      listOpen ? closeListDd() : openListDd();
    });
  }



  function openListDd() {
    if (!listDd || !metaListChip || listOpen) return;

    const rect = metaListChip.getBoundingClientRect();

    listDd.style.top = rect.bottom + 6 + 'px';
    listDd.style.left = rect.left + 'px';
    listDd.style.minWidth = rect.width + 'px';

    listDd.classList.remove('is-hidden');
    listOpen = true;

    setTimeout(() => {
      document.addEventListener('mousedown', handleOutsideList, true);
    }, 0);
  }

  function closeListDd() {
    if (!listOpen) return;

    listDd.classList.add('is-hidden');
    listOpen = false;

    document.removeEventListener('mousedown', handleOutsideList, true);
  }




  function handleOutsideList(e) {
    if (
      e.target.closest('#right-list-meta-list-chip') ||
      e.target.closest('#right-list-list-dd')
    ) return;

    closeListDd();
  }

  function bindCreateInput() {

    function submitTask() {
      const title = addInput.value.trim();
      if (!title) return;

      const payload = {
        title,
        calendar_id: CreateState.listId,
        due: buildDuePayload(),
        tzid: 'Asia/Seoul'
      };

      console.log('[CREATE PAYLOAD]', payload);

      createTask(payload);

      addInput.value = '';
      addBtn?.classList.add('is-hidden');
    }

    addInput?.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitTask();
      }
    });

    addBtn?.addEventListener('click', submitTask);
  }



  function buildDuePayload() {

    if (!CreateState.dueDate) return null;

    const d = CreateState.dueDate;

    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    if (!CreateState.hasTime) {
      return `${y}${m}${day}`;
    }

    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');

    return `${y}${m}${day}T${h}${min}00`;
  }

  function isTimeEnabled(root) {
    const chk = root.querySelector('input[type="checkbox"]');
    return !!chk?.checked;
  }

  function syncMetaDate() {
    if (!metaDateEl) return;

    if (!CreateState.dueDate) {
      metaDateEl.textContent = '기한 시간 추가';
      return;
    }

    metaDateEl.textContent = CreateState.hasTime
      ? formatDateTime(CreateState.dueDate)
      : formatDate(CreateState.dueDate);
  }

  function formatDate(d) {
    return (
      d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

  function formatDateTime(d) {
    return (
      formatDate(d) + ' ' +
      String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0')
    );
  }


async function createTask(payload) {

  if (createTask.__running) return;
  createTask.__running = true;

  try {

    const res = await window.CalendarAPI.createTask(payload);

    if (!res.success) {
      console.error('[TASK CREATE FAILED]', res);
      return;
    }

  } catch (err) {
    console.error('[TASK CREATE ERROR]', err);
  } finally {
    createTask.__running = false;
  }
}

if (window.CalendarStore?.subscribe) {
  CalendarStore.subscribe(() => {
    renderTaskLists();
  });
}


})();
