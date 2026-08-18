import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

(() => {
  'use strict';

  console.log('[modal.task.edit] loaded');
  window.AppCore = window.AppCore || {};
  const $ = id => document.getElementById(id);




  const ALARM_PRESETS = [
    { key:'at',  label:'이벤트 시', minutes:0 },
    { key:'m5',  label:'시작 5분 전', minutes:5 },
    { key:'m10', label:'시작 10분 전', minutes:10 },
    { key:'m30', label:'시작 30분 전', minutes:30 },
    { key:'h1',  label:'시작 1시간 전', minutes:60 },
    { key:'h2',  label:'시작 2시간 전', minutes:120 },
    { key:'h6',  label:'시작 6시간 전', minutes:360 },
    { key:'h12', label:'시작 12시간 전', minutes:720 },
    { key:'d1',  label:'시작 1일 전', minutes:1440 },
    { key:'d2',  label:'시작 2일 전', minutes:2880 },
    { key:'d3',  label:'시작 3일 전', minutes:4320 },
    { key:'d5',  label:'시작 5일 전', minutes:7200 },
    { key:'d7',  label:'시작 7일 전', minutes:10080 },
    { key:'d14', label:'시작 14일 전', minutes:20160 }
  ];


  let editAlarmKeys = [];
  const MAX_ALARMS = 5;

  let alarmDropdownIndex = null;
  let taskListDropdownOpen = false;
  let isCreateMode = false;
  let isPickerOpen = false;

  const pad2 = n => String(n).padStart(2, '0');

  function formatDisplay(d) {
    if (!(d instanceof Date) || isNaN(d)) return '';
    return (
      d.getFullYear() + '-' +
      pad2(d.getMonth() + 1) + '-' +
      pad2(d.getDate()) + ' ' +
      pad2(d.getHours()) + ':' +
      pad2(d.getMinutes())
    );
  }

  function parseDisplay(v) {
    if (!v) return null;
    const m = String(v).match(
      /^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?$/
    );
    if (!m) return null;
    return new Date(
      +m[1],
      +m[2] - 1,
      +m[3],
      m[4] ? +m[4] : 0,
      m[5] ? +m[5] : 0,
      0,
      0
    );
  }

  function parseSynology(v) {
    if (!v) return '';

    const str = String(v);

    const dt = str.match(/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})/);
    if (dt) {
      return `${dt[1]}-${dt[2]}-${dt[3]} ${dt[4]}:${dt[5]}`;
    }

    const d = str.match(/^(\d{4})(\d{2})(\d{2})$/);
    if (d) {
      return `${d[1]}-${d[2]}-${d[3]}`;
    }

    return '';
  }

  function setValue(id, v) {
    const el = $(id);
    if (el) el.value = v ?? '';
  }

  function renderEditTaskListDropdown(currentId) {
    const dropdown = document.getElementById('tasklist-dropdown-edit-modal');
    if (!dropdown) return;

    dropdown.innerHTML = '';

    const lists = (window.CalendarContext?.calendars || [])
    .filter(c => c.type === 'task' || c.supports_task === true);

    if (!lists.length) {
      dropdown.innerHTML =
        `<div style="padding:10px 12px;color:#64748b;">작업 목록 없음</div>`;
      return;
    }

    lists.forEach(tl => {
      const id = String(
        tl.calendar_id ??
        tl.id ??
        tl.collection_id ??
        ''
      );
      const name = tl.name || tl.title || '작업목록';
      const color =
        tl.admin_calendar_color ||
        CalendarStore.getCalendarColor?.(id) ||
        tl.color ||
        tl.calendar_color ||
        '#94a3b8';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'evt-cal-item';
      if (String(currentId) === id) btn.classList.add('is-active');

      btn.innerHTML = `
        <span class="evt-cal-color" style="background:${color}"></span>
        <span class="evt-cal-name">${name}</span>
      `;

      btn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();

        const headerBtn = document.getElementById('tasklist-btn-edit-modal')
        const dropdown  = document.getElementById('tasklist-dropdown-edit-modal');
        const hiddenInput = document.getElementById('task-list');

        const mode = headerBtn?.dataset.readonly === 'true' ? 'edit' : 'create';
        const currentId = hiddenInput?.value;

        if (mode === 'edit' && id !== currentId) {
          AppCore.notify(
            'warn',
            '기존 작업은 작업목록을 변경할 수 없습니다.'
          );

          hideDropdown(dropdown);
          taskListDropdownOpen = false;
          return;
        }

        if (id === currentId) {
          dropdown.hidden = true;
          taskListDropdownOpen = false;
          return;
        }

        document.querySelector('#tasklist-btn-edit-modal .evt-cal-color')
          .style.backgroundColor = color;

        document.querySelector('#tasklist-btn-edit-modal .evt-cal-name')
          .textContent = name;

        hiddenInput.value = id;

        hideDropdown(dropdown);
        taskListDropdownOpen = false;
      });


      dropdown.appendChild(btn);
    });
  }


  function showDropdown(el) {
    if (!el) return;
    el.hidden = false;
    el.classList.remove('is-hidden');
    el.style.display = 'block';
  }

  function hideDropdown(el) {
    if (!el) return;
    el.hidden = true;
    el.classList.add('is-hidden');
    el.style.display = 'none';
  }

  function isDropdownVisible(el) {
    if (!el) return false;
    if (el.hidden) return false;
    if (el.classList.contains('is-hidden')) return false;
    const ds = getComputedStyle(el).display;
    return ds !== 'none';
  }

  function bindEditTaskListSelectorOnce() {
    const btn = document.getElementById('tasklist-btn-edit-modal')
    const dropdown = document.getElementById('tasklist-dropdown-edit-modal');
    if (!btn || !dropdown) return;

    if (btn.__taskListBound) return;
    btn.__taskListBound = true;

    hideDropdown(dropdown);
    taskListDropdownOpen = false;

    btn.addEventListener('click', e => {
      console.log('BTN CLICK');

      e.preventDefault();
      e.stopPropagation();

      if (taskListDropdownOpen) {
        hideDropdown(dropdown);
        taskListDropdownOpen = false;
        return;
      }

      const currentId =
        document.getElementById('task-list')?.value || '';

      renderEditTaskListDropdown(currentId);

      taskListDropdownOpen = true;
      showDropdown(dropdown);
    });
  }

  function ensureDateTimePicker() {
    if (window.__EDIT_DATETIME_PICKER__) return window.__EDIT_DATETIME_PICKER__;

    const container = document.getElementById('datetime-picker');
    if (!container) return null;

    const picker = AdminPicker.create({
      type: 'datetime',
      container
    });

    picker.subscribe((state, date) => {
      const input = picker.__target;
      if (!input || !(date instanceof Date)) return;

      if (state.timeEnabled === true) {
        input.value =
          date.getFullYear() + '-' +
          pad2(date.getMonth() + 1) + '-' +
          pad2(date.getDate()) + ' ' +
          pad2(date.getHours()) + ':' +
          pad2(date.getMinutes());
      } else {
        input.value =
          date.getFullYear() + '-' +
          pad2(date.getMonth() + 1) + '-' +
          pad2(date.getDate());
      }
    });

    window.__EDIT_DATETIME_PICKER__ = picker;
    return picker;
  }


  function openTaskEditDateTimePicker(input) {
    const picker = ensureDateTimePicker();
    if (!picker) return;

    picker.__target = input;

    const value = input.value?.trim() || '';
    const hasTime = /\d{2}:\d{2}/.test(value);

    picker.open({ anchor: input });
    isPickerOpen = true;

    const d = parseDisplay(value);
    if (d) picker.setDate(d);

    setTimeout(() => {
      picker.toggleTime(hasTime);

    }, 0);
  }



  function closeTaskEditModal(reason = 'cancel') {

    const modal = document.getElementById('modal-task-edit');
    if (!modal) return;

    if (reason === 'cancel' && isCreateMode) {
      if (window.__quickTempEvent) {
        window.__quickTempEvent.remove();
        window.__quickTempEvent = null;
      }
    }

    if (reason === 'cancel' && !isCreateMode && modal.__originalSnapshot) {

      const snap = modal.__originalSnapshot;
      const cal  = window.__calendar;
      const ev   = cal?.getEventById(snap.id);

      if (ev) {
        ev.setProp('title', snap.title);
        ev.setStart(snap.start);
        ev.setEnd(snap.end);
        ev.setAllDay(snap.allDay);

        if (snap.extendedProps) {
          Object.keys(snap.extendedProps).forEach(k => {
            ev.setExtendedProp(k, snap.extendedProps[k]);
          });
        }
      }

      modal.__originalSnapshot = null;
    }

    modal.classList.add('is-hidden');
    document.body.classList.remove('is-modal-open');

    isCreateMode = false;

  }


  function isDateTimePickerOpen() {
    return isPickerOpen === true;
  }

  function closeDateTimePicker() {
    const picker = window.__EDIT_DATETIME_PICKER__;
    if (picker && typeof picker.close === 'function') {
      picker.close();
    }
    isPickerOpen = false;
  }


  function renderAlarmRows() {
    const list = document.getElementById('task-edit-alarm-list');
    const dropdown = document.getElementById('task-edit-alarm-dropdown');
    if (!list || !dropdown) return;

    list.innerHTML = '';
    dropdown.innerHTML = '';

    editAlarmKeys.forEach((key, index) => {
      const preset = ALARM_PRESETS.find(p => p.key === key);
      if (!preset) return;

      const row = document.createElement('div');
      row.className = 'alarm-row';
      row.style.display = 'flex';
      row.style.gap = '6px';
      row.style.marginBottom = '6px';

      const trigger = document.createElement('div');
      trigger.className = 'task-alarm-trigger';
      trigger.textContent = preset.label;
      trigger.tabIndex = 0;

      trigger.addEventListener('mousedown', e => {
        e.preventDefault();
        e.stopPropagation();

        const opened = toggleAlarmDropdown(trigger, dropdown, index);
        if (!opened) return;

        dropdown.innerHTML = '';

        ALARM_PRESETS.forEach(p => {
          if (editAlarmKeys.includes(p.key) && p.key !== key) return;

          const opt = document.createElement('div');
          opt.className = 'task-alarm-option';
          opt.textContent = p.label;

          if (p.key === key) opt.classList.add('is-active');

          opt.onmousedown = ev => {
            ev.preventDefault();
            ev.stopPropagation();

            editAlarmKeys[index] = p.key;
            dropdown.hidden = true;
            alarmDropdownIndex = null;
            renderAlarmRows();
          };

          dropdown.appendChild(opt);
        });
      });

      const del = document.createElement('button');
      del.type = 'button';
      del.textContent = '−';
      del.className = 'shint-btn shint-btn--ghost';
      del.onclick = () => {
        editAlarmKeys.splice(index, 1);
        renderAlarmRows();
      };

      row.append(trigger, del);
      list.appendChild(row);
    });

    document.getElementById('task-edit-alarm-add').disabled =
      editAlarmKeys.length >= MAX_ALARMS;
  }




  function buildAlarmTrigger(preset) {
    const min = preset.minutes;

    if (min === 0) return 'PT0S';

    if (min % 60 === 0) {
      return `-PT${min / 60}H`;
    }

    return `-PT${min}M`;
  }








  function openAlarmDropdown(triggerEl, dropdownEl) {
    const r = triggerEl.getBoundingClientRect();

    dropdownEl.style.position = 'fixed';
    dropdownEl.style.left = `${Math.round(r.left)}px`;
    dropdownEl.style.top  = `${Math.round(r.bottom + 6)}px`;

    const w = Math.round(r.width);
    dropdownEl.style.width =
    dropdownEl.style.minWidth =
    dropdownEl.style.maxWidth = `${w}px`;

    dropdownEl.style.maxHeight = '220px';
    dropdownEl.style.overflowY = 'auto';

    dropdownEl.hidden = false;
  }



  function toggleAlarmDropdown(triggerEl, dropdownEl, index) {

    if (!dropdownEl.hidden && alarmDropdownIndex === index) {
      dropdownEl.hidden = true;
      alarmDropdownIndex = null;
      return false;
    }

    alarmDropdownIndex = index;

    const r = triggerEl.getBoundingClientRect();
    const w = Math.round(r.width);

    dropdownEl.style.position = 'fixed';
    dropdownEl.style.left = `${Math.round(r.left)}px`;
    dropdownEl.style.top  = `${Math.round(r.bottom + 6)}px`;
    dropdownEl.style.width =
    dropdownEl.style.minWidth =
    dropdownEl.style.maxWidth = `${w}px`;

    dropdownEl.hidden = false;
    return true;
  }


  function toCalDavDateTime(v) {
    if (!v) return null;

    const m = String(v).match(
      /^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?$/
    );
    if (!m) return v;

    const [, y, mo, d, h, mi] = m;

    if (!h || !mi) {
      return `${y}${mo}${d}`;
    }

    return `${y}${mo}${d}T${h}${mi}00`;
  }

function formatDateForDisplay(dueDate) {
  if (!(dueDate instanceof Date)) return '';
  const y = dueDate.getFullYear();
  const m = String(dueDate.getMonth() + 1).padStart(2, '0');
  const d = String(dueDate.getDate()).padStart(2, '0');
  const h = String(dueDate.getHours()).padStart(2, '0');
  const min = String(dueDate.getMinutes()).padStart(2, '0');

  return `${y}-${m}-${d} ${h}:${min}`;
}

AppCore.openTaskEditModal = function (input) {
  console.log('💥 FINAL INPUT:', input);
  taskListDropdownOpen = false;

  const dropdown = document.getElementById('tasklist-dropdown-edit-modal');
  if (dropdown) {
    dropdown.hidden = true;
  }



  let raw = null;

  isCreateMode = input?.__mode === 'create'
  || (!input?.uid && !input?.id);



  const listBtn = document.getElementById('tasklist-btn-edit-modal')

  if (listBtn) {
    if (!isCreateMode) {
      listBtn.dataset.readonly = 'true';
    } else {
      delete listBtn.dataset.readonly;
    }
  }

  const modal = $('modal-task-edit');
  if (!modal) return;

  const titleEl = modal.querySelector('.shint-modal__title');
  if (titleEl) {
    if (isCreateMode) {
      titleEl.textContent = '작업 생성';
    } else {
      titleEl.textContent = '작업 편집';
    }
  }

  let title = '', desc = '', uid = '', listId = '', due = '';

  if (input.extendedProps) {
    raw = input.extendedProps.raw || {};

    title = input.title || '';
    uid   = input.id || '';

    desc =
      raw.DESCRIPTION?.value ||
      input.extendedProps.description ||
      '';

    listId =
      input.extendedProps.calendar_id || '';

    if (raw?.raw?.DUE?.params?.VALUE === 'DATE') {

      const v = raw.raw.DUE.value;
      due = v ? parseSynology(v) : '';
    }
    else if (raw?.due_iso) {
      const iso = String(raw.due_iso);

      if (/T00:00(:00)?$/.test(iso)) {
        due = iso.substring(0, 10);
      }
      else if (iso.includes('T')) {
        due = iso.replace('T', ' ').substring(0, 16);
      }
      else {
        due = iso.substring(0, 10);
      }
    }
    else if (raw?.due) {
      const parsed = parseSynology(raw.due);

      if (parsed && parsed.endsWith(' 00:00')) {
        due = parsed.substring(0, 10);
      } else {
        due = parsed;
      }
    }
    else if (input?.start instanceof Date) {
      const y = input.start.getFullYear();
      const m = pad2(input.start.getMonth() + 1);
      const d = pad2(input.start.getDate());

      if (input.allDay === true) {
        due = `${y}-${m}-${d}`;
      } else {
        const h = pad2(input.start.getHours());
        const mi = pad2(input.start.getMinutes());
        due = `${y}-${m}-${d} ${h}:${mi}`;
      }
    }
    else {
      due = '';
    }

  } else {
    title = input.title || '';
    uid   = input.uid || '';

    desc = input.description || input.desc || '';
    listId = input.listId || '';

    if (input.due instanceof Date) {

      if (input.allDay === true) {
        due =
          input.due.getFullYear() + '-' +
          pad2(input.due.getMonth() + 1) + '-' +
          pad2(input.due.getDate());
      } else {

        due = formatDisplay(input.due);
      }

    } else if (typeof input.due === 'string') {
      const str = input.due.trim();

      if (/00:00(:00)?$/.test(str)) {
        due = str.substring(0, 10);
      }

      else if (str.includes(':')) {
        due = str.replace('T', ' ').substring(0, 16);
      }

      else {
        due = str.substring(0, 10);
      }
    }
  }

  setValue('task-title', title);
  setValue('task-desc', desc);
  setValue('task-uid', uid);
  setValue('task-due', due);


  let alarms = [];

  if (Array.isArray(input.alarms)) {
    alarms = input.alarms;
  }

  else {
    const rawJson = input.extendedProps?.raw?.raw_json;
    if (rawJson) {
      try {
        const raw = typeof rawJson === 'string'
          ? JSON.parse(rawJson)
          : rawJson;
        alarms = Array.isArray(raw.alarms) ? raw.alarms : [];
      } catch {
        alarms = [];
      }
    }
  }

  editAlarmKeys = [];

  alarms.forEach(a => {
    if (!a?.trigger) return;

    const preset = ALARM_PRESETS.find(
      p => buildAlarmTrigger(p) === a.trigger
    );

    if (preset) editAlarmKeys.push(preset.key);
  });

  editAlarmKeys = Array.from(new Set(editAlarmKeys)).slice(0, MAX_ALARMS);
  renderAlarmRows();

  modal.classList.remove('is-hidden');

  document.getElementById('task-list').value = listId || '';

  modal.classList.remove('is-hidden');

  requestAnimationFrame(() => {
    bindEditTaskListSelectorOnce();
  });

  requestAnimationFrame(() => {
    const lists = window.CalendarContext?.calendars || [];
    const tl = lists.find(x =>
      String(
        x.calendar_id ??
        x.id ??
        x.collection_id ??
        ''
      ) === String(listId)
    );

    if (!tl) return;

    document.querySelector('#tasklist-btn-edit-modal .evt-cal-name')
      .textContent = tl.name || tl.title;

      const id = String(
        tl.calendar_id ??
        tl.id ??
        tl.collection_id ??
        ''
      );

      document.querySelector('#tasklist-btn-edit-modal .evt-cal-color')
        .style.backgroundColor =
          tl.admin_calendar_color ||
          CalendarStore.getCalendarColor?.(id) ||
          tl.color ||
          tl.calendar_color ||
          '#94a3b8';
  });

  requestAnimationFrame(() => {
    const inputEl = $('task-due');
    if (inputEl) {
      inputEl.onclick = e => {
        e.preventDefault();
        e.stopPropagation();
        openTaskEditDateTimePicker(inputEl);
      };
    }
  });

  if (!isCreateMode && input?.id) {
    const cal = window.__calendar;
    const ev  = cal?.getEventById('task_' + input.id);

    if (ev) {
      modal.__originalSnapshot = ev.toPlainObject();
    }
  }
};




  document.getElementById('task-edit-alarm-add')
  ?.addEventListener('click', () => {
    if (editAlarmKeys.length >= MAX_ALARMS) return;

    const available = ALARM_PRESETS
    .map(p => p.key)
    .filter(k => !editAlarmKeys.includes(k));


    if (!available.length) return;

    editAlarmKeys.push(available[0]);
    renderAlarmRows();
  });


  document.addEventListener('click', e => {
    if (e.target.closest('#modal-task-edit [data-close="modal"]')) {
      e.preventDefault();
      closeTaskEditModal('cancel');
    }
  });


  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;

    const modal = document.getElementById('modal-task-edit');
    if (!modal || modal.classList.contains('is-hidden')) return;

    if (isDateTimePickerOpen()) {
      e.preventDefault();
      e.stopPropagation();
      closeDateTimePicker();
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    closeTaskEditModal('cancel');

  }, true);

  document.getElementById('task-form')
?.addEventListener('submit', async e => {
  e.preventDefault();

  let uid = document.getElementById('task-uid')?.value || '';
  uid = uid.replace(/^task_/, '').trim();

  const title = document.getElementById('task-title')?.value || '';

const dueEl = document.getElementById('task-due');
const picker = window.__EDIT_DATETIME_PICKER__;

if (dueEl && picker && picker.__target === dueEl) {
  const before = (dueEl.value || '').trim();

  const wantTime = /\d{2}:\d{2}/.test(before);

  const final = picker.getFinalDate?.();
  if (final instanceof Date && !isNaN(final)) {
    if (wantTime) {

      dueEl.value = formatDisplay(final);
    } else {

      dueEl.value =
        final.getFullYear() + '-' +
        pad2(final.getMonth() + 1) + '-' +
        pad2(final.getDate());
    }
  }
}

  const due = document.getElementById('task-due')?.value || '';

  const listId = document.getElementById('task-list')?.value || '';
  const description = document.getElementById('task-desc')?.value || '';

  if (!title.trim()) {
    alert('작업 제목을 입력하세요.');
    return;
  }

  const dueDateObj = parseDisplay(due);

  const alarms = editAlarmKeys
    .map(key => {
      const preset = ALARM_PRESETS.find(p => p.key === key);
      if (!preset) return null;
      return {
        trigger: buildAlarmTrigger(preset),
        action: 'DISPLAY'
      };
    })
    .filter(Boolean);

  try {
    const dueCalDav = toCalDavDateTime(due);

    const lists = window.CalendarContext?.calendars || [];
    const selectedList = lists.find(x =>
      String(
        x.calendar_id ??
        x.id ??
        x.collection_id ??
        ''
      ) === String(listId)
    );

    const collectionHref =
    selectedList?.collection_href ||
    selectedList?.href ||
    '';

    if ((isCreateMode || !uid) && !collectionHref) {
      console.error('[task.create] collection missing', {
        listId,
        selectedList
      });
      alert('작업목록 경로를 찾을 수 없습니다.');
      return;
    }

    let newUid = uid;

    if (isCreateMode || !uid) {

      const res = await CalendarAPI.createTask({
        title,
        due: dueCalDav,
        tzid: 'Asia/Seoul',
        calendar_id: listId,
        collection_href: collectionHref,
        description,
        alarms
      });

      newUid = (res?.data?.uid || '').replace(/^task_/, '').trim();

      if (!newUid) {
        throw new Error('createTask: uid not returned');
      }


      setValue('task-uid', newUid);

      isCreateMode = false;

    } else {
      // 수정
      await CalendarAPI.updateTask(uid, {
        title,
        due: dueCalDav,
        tzid: 'Asia/Seoul',
        calendar_id: listId,
        description,
        alarms,
        collection_href: collectionHref
      });
    }

    console.log('🔥 FINAL UID:', newUid);


    const cal = window.__calendar;


    let fcEvent = cal?.getEventById('task_' + newUid);

    if (fcEvent) {
      fcEvent.setProp('title', '📝 ' + title);

      if (dueDateObj instanceof Date) {
        fcEvent.setStart(dueDateObj);
      } else {
        fcEvent.setStart(null);
      }

      fcEvent.setExtendedProp('description', description);
      fcEvent.setExtendedProp('calendar_id', listId);
      fcEvent.setExtendedProp('alarms', alarms);

      const prevRaw = fcEvent.extendedProps?.raw || {};

      let parsedRawJson = {};
      try {
        parsedRawJson = prevRaw.raw_json
          ? JSON.parse(prevRaw.raw_json)
          : {};
      } catch {
        parsedRawJson = {};
      }

      parsedRawJson.alarms = alarms;

      const newRaw = {
        ...prevRaw,
        alarms,
        raw_json: JSON.stringify(parsedRawJson)
      };

      fcEvent.setExtendedProp('alarms', alarms);
      fcEvent.setExtendedProp('raw', newRaw);

      if (selectedList) {
        const id = String(
          selectedList.calendar_id ??
          selectedList.id ??
          selectedList.collection_id ??
          ''
        );

        const color =
          selectedList.admin_calendar_color ||
          CalendarStore.getCalendarColor?.(id) ||
          selectedList.color ||
          selectedList.calendar_color ||
          '#94a3b8';

        fcEvent.setProp('backgroundColor', color);
        fcEvent.setProp('borderColor', color);
      }

      fcEvent.setExtendedProp('__refresh', Date.now());
    }

    closeTaskEditModal('save');

  } catch (err) {
    console.error(err);
    alert('작업 저장 실패');
  }
});




document.addEventListener('click', e => {
  const list = document.getElementById('tasklist-dropdown-edit-modal');
  const btn  = document.getElementById('tasklist-btn-edit-modal')

  if (!list) return;
  if (!taskListDropdownOpen) return;

  if (btn?.contains(e.target)) return;
  if (list.contains(e.target)) return;

  hideDropdown(list);
  taskListDropdownOpen = false;
});








})();
