// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/bootstrap.js'
(() => {
  'use strict';
  console.log('[bootstrap] loaded');

  if (!window.__CAL_SYNC_INTERVAL__) {
    window.__CAL_SYNC_INTERVAL__ = setInterval(() => {
      forceSyncAndRefresh();
    }, 60000);
  }

  window.__CALENDAR_BOOTSTRAP_LOADED__ = true;

  window.CalendarContext = window.CalendarContext || {
    externalAccounts: {}
  };

  window.CalendarUtils = window.CalendarUtils || {};

  async function loadCalendarUserContext() {
    try {
      const res = await fetch(
        '/api/user/external-accounts/get?service_key=synology',
        { credentials: 'same-origin' }
      );

      const json = await res.json();

      if (json.success && json.data?.is_connected) {
        window.CalendarContext.externalAccounts.synology = {
          external_login_id: json.data.external_login_id || null,
          is_connected: true
        };
      } else {
        window.CalendarContext.externalAccounts.synology = {
          external_login_id: null,
          is_connected: false
        };
      }

    } catch (e) {
      console.warn('[calendar] failed to load synology account', e);
      window.CalendarContext.externalAccounts.synology = {
        external_login_id: null,
        is_connected: false
      };
    }
  }


  CalendarUtils.toLocalInputValue = function (d = new Date()) {
    const pad = n => String(n).padStart(2, '0');
    return (
      d.getFullYear() + '-' +
      pad(d.getMonth() + 1) + '-' +
      pad(d.getDate()) + 'T' +
      pad(d.getHours()) + ':' +
      pad(d.getMinutes())
    );
  };

  document.addEventListener('DOMContentLoaded', async () => {

    // 🔹 우측 패널 기본 닫힘
    const shell = document.querySelector('.calendar-shell');
    const panel = document.getElementById('task-panel');

    if (shell && panel) {
      shell.classList.add('right-collapsed');
      panel.classList.add('is-collapsed');
    }

    try {

      await loadCalendarUserContext();

      const res = await CalendarAPI.getCalendars();

      const list = Array.isArray(res)
        ? res
        : Array.isArray(res?.data)
          ? res.data
          : [];

      const cleanList = list.filter(c => {
        if (
          c.type !== 'calendar' &&
          c.type !== 'task' &&
          c.type !== 'todo' &&
          c.type !== 'VTODO'
        ) return false;

        return true;
      });


      window.CalendarContext.calendars = cleanList;
      CalendarStore.setCalendars(cleanList);


      list.forEach(c => {
        const id    = String(c.calendar_id || c.id || '');
        const color = String(c.color || c.calendar_color || '');
        if (id && color) {
          CalendarStore.setCalendarColor(id, color);
        }
      });

      requestAnimationFrame(() => {
        const cal = window.__calendar;
        if (cal) {
          cal.getEvents().forEach(ev => {
            ev.setProp('backgroundColor', '');
          });
          cal.render();
        }
      });

      CalendarStore.setActiveCalendars(
        new Set(
          cleanList
            .filter(x => x.type === 'calendar')
            .map(x => String(x.calendar_id))
        )
      );

      CalendarStore.setActiveTasks(
        new Set(
          cleanList
            .filter(x => x.type === 'task')
            .map(x => String(x.calendar_id))
        )
      );

      CalendarStore.subscribe(snapshot => {
        renderFilterLists(snapshot.calendars);

        window.TaskPanel?.setData?.({
          tasks: snapshot.tasks || [],
          lists: snapshot.calendars || []
        });
      });

      document.dispatchEvent(
        new CustomEvent('calendar:ready', { detail: cleanList })
      );

      setTimeout(() => {
        const snapshot = CalendarStore?.getSnapshot?.();
        if (!snapshot) return;

        window.TaskPanel?.setData?.({
          tasks: snapshot.tasks || [],
          lists: snapshot.calendars || []
        });
      }, 0);

    } catch (e) {
      console.error('[calendar.bootstrap] init failed', e);
    }
  });

  window.toggleWithHorizontalResize = function (toggle) {
    const cal = window.__calendar;
    toggle();
    if (!cal) return;

    const start = performance.now();
    const DURATION = 260;

    (function step(now) {
      cal.updateSize();
      if (now - start < DURATION) requestAnimationFrame(step);
    })(start);
  };

window.toggleRightPanel = function (open) {
  const shell = document.querySelector('.calendar-shell');
  const root  = document.querySelector('.calendar-right-panel'); // 🔥 추가
  const listPanel = document.getElementById('right-list-panel');
  const editPanel = document.getElementById('task-panel');

  if (!shell || !root) return;

  window.toggleWithHorizontalResize?.(() => {
    if (open) {
      shell.classList.remove('right-collapsed');

      root.classList.add('is-open');

      listPanel?.classList.remove('is-collapsed');
      editPanel?.classList.add('is-collapsed');

    } else {
      shell.classList.add('right-collapsed');

      root.classList.remove('is-open'); // 🔥 닫기

      listPanel?.classList.add('is-collapsed');
      editPanel?.classList.add('is-collapsed');
    }
  });
};

document.addEventListener('calendar:quick:create', (e) => {
  const { type, date } = e.detail || {};

  window.AppCore?.openQuickModal({
    mode: type === 'task' ? 'task' : 'event',
    date: date || null
  });
});

document.addEventListener('calendar:task-edit', (e) => {
  const { date, value } = e.detail || {};

  const modal = document.getElementById('modal-task-edit');
  if (!modal) {
    console.warn('[bootstrap] modal-task-edit not found');
    return;
  }

  modal.classList.remove('is-hidden');

  document.dispatchEvent(
    new CustomEvent('modal:task-edit:open', {
      detail: {
        date: date || null,
        value: value || null
      }
    })
  );
});


let __SYNC_RUNNING__ = false;

async function forceSyncAndRefresh() {

  if (__SYNC_RUNNING__) {
    console.warn('[calendar] sync skipped - already running');
    return;
  }

  __SYNC_RUNNING__ = true;

  try {

  await fetch('/api/dashboard/calendar/cache-rebuild', {
    method: 'POST',
    credentials: 'same-origin'
  });

  window.__calendar?.refetchEvents();

  } catch (e) {
    console.error('[calendar] force sync failed', e);
  } finally {
    __SYNC_RUNNING__ = false;
  }
}

document.addEventListener('calendar:force-sync', forceSyncAndRefresh);

if (!window.__CAL_SYNC_INTERVAL__) {
  window.__CAL_SYNC_INTERVAL__ = setInterval(() => {
    forceSyncAndRefresh();
  }, 60000);
}


window.CalendarGlobalRefresh = async function () {

  const cal = window.__calendar;

  if (cal && typeof cal.refetchEvents === 'function') {
    cal.refetchEvents();
  }

};


})();
