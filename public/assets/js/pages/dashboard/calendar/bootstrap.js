// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/bootstrap.js'
(() => {
  'use strict';
  console.log('[bootstrap] loaded');

  // =====================================================
  // ⛔ Bootstrap 중복 실행 방지
  // - SPA / partial reload 환경에서
  // - 같은 JS가 여러 번 로드되는 것을 방지
  // =====================================================
  if (!window.__CAL_SYNC_INTERVAL__) {
    window.__CAL_SYNC_INTERVAL__ = setInterval(() => {
      forceSyncAndRefresh();
    }, 60000);
  }
  
  window.__CALENDAR_BOOTSTRAP_LOADED__ = true;

  /* ============================
  * Calendar Global Context
  * ============================ */
  window.CalendarContext = window.CalendarContext || {
    externalAccounts: {}
  };

  /* ============================
   * Global Utils
   * ============================ */
  window.CalendarUtils = window.CalendarUtils || {};

  //시놀로지 계정정보 가져오기(Synology External Account)
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

  /* ============================
  * Init
  * ============================ */
  document.addEventListener('DOMContentLoaded', async () => {

    // 🔹 우측 패널 기본 닫힘
    const shell = document.querySelector('.calendar-shell');
    const panel = document.getElementById('task-panel');

    if (shell && panel) {
      shell.classList.add('right-collapsed');
      panel.classList.add('is-collapsed');
    }

    try {
      /* ============================
      * 0. Current User External Context
      * - Synology 외부 계정 정보 로드
      * ============================ */
      await loadCalendarUserContext();

      /* ============================
      * 1. Calendar list 로드
      * ============================ */
      const res = await CalendarAPI.getCalendars();

      // 🔥 API 반환 형태 양쪽 모두 대응
      const list = Array.isArray(res)
        ? res
        : Array.isArray(res?.data)
          ? res.data
          : [];
      

      /* =====================================================
      * 🔗 Calendar List Bridge (🔥 핵심)
      * ===================================================== */
      const cleanList = list.filter(c => {
        if (
          c.type !== 'calendar' &&
          c.type !== 'task' &&
          c.type !== 'todo' &&
          c.type !== 'VTODO'
        ) return false;
      
        return true; // 🔥 href 조건 제거
      });
      

      window.CalendarContext.calendars = cleanList;
      CalendarStore.setCalendars(cleanList);




      /* ============================
      * 3. Calendar 색상 초기화
      * ============================ */
      list.forEach(c => {
        const id    = String(c.calendar_id || c.id || '');
        const color = String(c.color || c.calendar_color || '');
        if (id && color) {
          CalendarStore.setCalendarColor(id, color);
        }
      });

      // 🔥🔥🔥 핵심: 색상 세팅 후 캘린더 강제 리렌더
      requestAnimationFrame(() => {
        const cal = window.__calendar;
        if (cal) {
          cal.getEvents().forEach(ev => {
            ev.setProp('backgroundColor', ''); // 트리거
          });
          cal.render();
        }
      });


      /* ============================
      * 4. 기본 활성 상태
      * ============================ */
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
      
      

      /* ============================
      * 5. Store → TaskPanel 브릿지
      * ============================ */
      CalendarStore.subscribe(snapshot => {
        renderFilterLists(snapshot.calendars);

        window.TaskPanel?.setData?.({
          tasks: snapshot.tasks || [],
          lists: snapshot.calendars || []
        });
      });

     

      /* ============================
      * 6. Calendar 준비 완료 신호
      * ============================ */
      document.dispatchEvent(
        new CustomEvent('calendar:ready', { detail: cleanList })
      );

      // 🔥 최초 1회 TaskPanel 강제 초기화
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


  /* ============================
   * Sidebar Toggle (공통 UI)
   * ============================ */
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

/* ============================
 * Right Task Panel Toggle (FIX)
 * ============================ */
window.toggleRightPanel = function (open) {
  const shell = document.querySelector('.calendar-shell');
  const root  = document.querySelector('.calendar-right-panel'); // 🔥 추가
  const listPanel = document.getElementById('right-list-panel');
  const editPanel = document.getElementById('task-panel');

  if (!shell || !root) return;

  window.toggleWithHorizontalResize?.(() => {
    if (open) {
      shell.classList.remove('right-collapsed');

      // 🔥🔥🔥 핵심
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


/* =====================================================
 * Quick Create → Quick Modal Bridge (🔥 필수)
 * ===================================================== */
document.addEventListener('calendar:quick:create', (e) => {
  const { type, date } = e.detail || {};

  window.AppCore?.openQuickModal({
    mode: type === 'task' ? 'task' : 'event',
    date: date || null
  });
});



/* =====================================================
 * Task Edit Modal Open Bridge (🔥 필수)
 * ===================================================== */
document.addEventListener('calendar:task-edit', (e) => {
  const { date, value } = e.detail || {};

  const modal = document.getElementById('modal-task-edit');
  if (!modal) {
    console.warn('[bootstrap] modal-task-edit not found');
    return;
  }

  // 1️⃣ Edit Modal 열기
  modal.classList.remove('is-hidden');

  // 2️⃣ Edit Modal open 신호 (🔥 AdminPicker 재바인딩 트리거)
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

  // 🔥 refetchEvents만 호출 (fetchAll이 setTasks까지 처리함)
  window.__calendar?.refetchEvents();

  } catch (e) {
    console.error('[calendar] force sync failed', e);
  } finally {
    __SYNC_RUNNING__ = false;
  }
}

document.addEventListener('calendar:force-sync', forceSyncAndRefresh);

// 🔥 최초 1회 즉시 동기화
//forceSyncAndRefresh();

// 🔥 중복 방지 interval
if (!window.__CAL_SYNC_INTERVAL__) {
  window.__CAL_SYNC_INTERVAL__ = setInterval(() => {
    forceSyncAndRefresh();
  }, 60000);
}



//전역 리프레시 함수 만들기
window.CalendarGlobalRefresh = async function () {

  const cal = window.__calendar;

  if (cal && typeof cal.refetchEvents === 'function') {
    cal.refetchEvents();
  }

  // 우측 태스크 패널도 다시
  // if (window.CalendarAPI?.fetchTasksForPanel) {
  //   await CalendarAPI.fetchTasksForPanel();
  // }

};


})();
