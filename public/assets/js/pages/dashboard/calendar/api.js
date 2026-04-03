// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/api.js'
(() => {
  'use strict';

  if (window.__CALENDAR_API_LOADED__) {
    console.warn('[api] duplicate load blocked');
    return;
  }
  window.__CALENDAR_API_LOADED__ = true;

  console.log('[api] loaded once');


  const BASE = '/api/dashboard/calendar';

  let __CACHE_REBUILD_TRIGGERED__ = false;

  /* =========================================================
   * Cache (UI 성능 전용)
   * ========================================================= */
  const cache = new Map();
  const TTL = 0; // 🔥 캐시 비활성화

  /* =========================================================
   * Utils
   * ========================================================= */
  async function fetchJson(url, opts = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...opts });
  
    const text = await res.text();
    //console.log('[RAW]', text);   // 🔥 여기서 범인 확정
  
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      throw new Error('Invalid JSON response');
    }
  
    if (!res.ok || json.success === false) {
      throw new Error(json.message || `HTTP ${res.status}`);
    }
  
    return json;
  }
  
  
  /* =========================================================
  * Core Fetch (Events Only - 달력 전용)
  * ========================================================= */
  async function fetchAllReal({ start, end }) {

    const from = start instanceof Date
      ? start.toISOString().slice(0, 10)
      : start;
  
    const to = end instanceof Date
      ? end.toISOString().slice(0, 10)
      : end;
  
    const [eventsRes, tasksRes] = await Promise.all([
      fetchJson(`${BASE}/events-all?` + new URLSearchParams({ start: from, end: to })),
      fetchJson(`${BASE}/tasks-panel`)
    ]);
  
    /* =====================================================
     * EVENT NORMALIZE
     * ===================================================== */
    const events = (Array.isArray(eventsRes?.data) ? eventsRes.data : []).map(ev => {
  
      const classNames = ev.classNames || [];
  
      /* ---------------------------------
       Synology Deleted
       --------------------------------- */
       if (Number(ev.synology_exists) === 0) {

        classNames.push('event-synology-deleted');
      
        ev.backgroundColor = ev.backgroundColor || '#ef4444';
        ev.borderColor     = ev.borderColor     || '#ef4444';
      }
  
      /* ---------------------------------
       ERP Created (raw_ics 존재)
       --------------------------------- */
      if (ev.extendedProps?.raw?.raw_ics) {
        classNames.push('event-erp-created');
      }
  
      /* ---------------------------------
       Recurring Event
       --------------------------------- */
      if (ev.rrule) {
        classNames.push('event-recurring');
      }
  
      ev.classNames = classNames;
  
      return ev;
    });
  
  
    /* =====================================================
     * TASK NORMALIZE
     * ===================================================== */
    const tasks = (Array.isArray(tasksRes?.data) ? tasksRes.data : []).map(t => {
  
      const classNames = t.classNames || [];
  
      /* ---------------------------------
       Synology Deleted
       --------------------------------- */
       if (Number(t.synology_exists) === 0) {
        classNames.push('task-synology-deleted');
      }
  
      /* ---------------------------------
       Completed
       --------------------------------- */
      if (t.status === 'COMPLETED') {
        classNames.push('task-completed');
      }
  
      t.classNames = classNames;
  
      return t;
    });
  
    //console.log('[EVENT RAW]', eventsRes?.data);
  
    return { events, tasks };
  }
  /* =========================================================
   * Background Refresh (동기화와 분리)
   * ========================================================= */
  async function refreshInBackground(key, start, end) {
    try {
      const data = await fetchAllReal({ start, end });
      cache.set(key, { time: Date.now(), data });
    } catch (e) {
      console.warn('[calendar.api] background refresh failed', e);
    }
  }


  function addOptimisticEvent(payload) {
    const cal = window.__calendar;
    if (!cal) return null;
  
    const tempId = '__optimistic__' + Date.now();
  
    const ev = cal.addEvent({
      id: tempId,
      title: payload.title || '(제목 없음)',
      start: payload.start,
      end: payload.end || payload.start,
      allDay: !!payload.allDay,
      backgroundColor: payload.event_color || '#94a3b8',
      borderColor: '#94a3b8',
      extendedProps: {
        __optimistic: true,
        calendar_id: payload.calendar_id,
        type: 'VEVENT'
      }
    });
  
    return ev;
  }
  
/* =========================================================
 * UI Sync Helper (🔥 TaskPanel + Store 동기화)
 * ========================================================= */
async function syncTasksUI() {
  try {
    const tasks = await window.CalendarAPI.fetchTasksForPanel();

    // Store는 fetchTasksForPanel에서 이미 세팅됨
    window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
  } catch (e) {
    console.warn('[CalendarAPI] syncTasksUI failed', e);
  }
}


  /* =========================================================
   * Public API
   * ========================================================= */
  window.CalendarAPI = {

    /* ---------- Read ---------- */
    getCalendars() {
      return fetchJson(`${BASE}/list`)
        .then(res => {
          if (res && res.success && Array.isArray(res.data)) return res.data;
          return [];
        });
    },
    

    /* ---------- Aggregate (🔥 핵심) ---------- */

    async fetchAll({ start, end }) {

      let data = await fetchAllReal({ start, end });
    
      // 🔥 DB에 이벤트 없으면 sync 트리거
      if (!__CACHE_REBUILD_TRIGGERED__ && (!data.events || data.events.length === 0)) {
    
        __CACHE_REBUILD_TRIGGERED__ = true;
    
        await fetch(`${BASE}/cache-rebuild`, {
          method: 'POST',
          credentials: 'same-origin'
        }).catch(() => {});
    
        // sync 완료 대기
        await new Promise(r => setTimeout(r, 2000));
    
        data = await fetchAllReal({ start, end });
      }
    
      CalendarStore?.setTasks?.(data.tasks || []);
      window.TaskPanel?.refresh?.(window.__TASK_FILTER_STATE__ || {});
    
      return data;
    },
    

    clearCache() {
      cache.clear();
    },

    forceRefetch(hard = false) {
      cache.clear();
    
      // 🔥 전역 fetch 캐시 완전 제거
      window.__CAL_LAST_FETCH__ = null;
    
      scheduleRefetchEvents();
    },


    /* ---------- Write (CalDAV 먼저) ---------- */
    createEvent(payload) {
      const optimistic =
      payload.__from_quick === true
        ? addOptimisticEvent(payload)
        : null;
    
      return fetchJson(`${BASE}/event/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(res => {
        // 🔥 optimistic 제거
        optimistic?.remove();
    
        CalendarAPI.clearCache();
        CalendarAPI.forceRefetch();
        return res;
      })
      .catch(err => {
        optimistic?.remove();
        throw err;
      });
    },
    
    
    updateEvent(payload) {
      return fetchJson(`${BASE}/event/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(res => {
        // ❌ refetchEvents 절대 금지
        CalendarAPI.clearCache();
        CalendarAPI.forceRefetch();
        return res;
      });
    },
    
    

    deleteEvent(payload = {}) {

      const uid =
          payload.uid ||
          payload.id ||
          payload?.extendedProps?.uid ||
          payload?.extendedProps?.raw?.uid;
    
      console.log('[DELETE EVENT UID]', uid);
    
      if (!uid) {
        throw new Error('UID missing in deleteEvent');
      }
    
      return fetchJson(`${BASE}/event/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      }).then(res => {
    
        const cal = window.__calendar;
    
        if (cal) {
          const ev = cal.getEventById(uid);
          if (ev) ev.remove();   // 🔥 화면에서 즉시 제거
        }
    
        CalendarAPI.clearCache();
        window.__CAL_LAST_FETCH__ = null;
        CalendarAPI.forceRefetch();
    
        return res;
      });
    },

    hardDeleteEvent(uid) {
      return fetchJson(`${BASE}/event/hard-delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      }).then(res => {
    
        CalendarAPI.clearCache();
    
        // 🔥 추가
        window.__CAL_LAST_FETCH__ = null;
    
        CalendarAPI.forceRefetch();
    
        return res;
      });
    },

    createTask(payload) {
      return fetchJson(`${BASE}/task/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(async res => {
    
        // 🔥 1️⃣ 캐시 완전 제거
        CalendarAPI.clearCache();
        window.__CAL_LAST_FETCH__ = null;
    
        // 🔥 2️⃣ 달력 강제 재조회
        CalendarAPI.forceRefetch();
    
        // 🔥 3️⃣ 우측 패널 동기화
        await syncTasksUI();
    
        return res;
      });
    },
    

    updateTask(uid, payload) {
      return fetchJson(`${BASE}/task/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid, ...payload })
      }).then(async (res) => {
    
        // 🔥 완전 초기화
        CalendarAPI.clearCache();
        window.__CAL_LAST_FETCH__ = null;
    
        // 🔥 달력 강제 재조회
        CalendarAPI.forceRefetch();
    
        // 🔥 패널 동기화
        await syncTasksUI();
    
        return res;
      });
    },


    deleteTask(uid) {
      return fetchJson(`${BASE}/task/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      }).then(res => {
    
        CalendarAPI.clearCache();
    
        // 🔥 추가 (핵심)
        window.__CAL_LAST_FETCH__ = null;
    
        CalendarAPI.forceRefetch();
    
        requestAnimationFrame(() => {
          syncTasksUI();
        });
    
        return res;
      });
    },
    

    hardDeleteTask(uid) {
      return fetchJson(`${BASE}/task/hard-delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
      }).then(res => {
    
        CalendarAPI.clearCache();
    
        // 🔥 핵심 추가
        window.__CAL_LAST_FETCH__ = null;
    
        CalendarAPI.forceRefetch();
    
        requestAnimationFrame(() => {
          syncTasksUI();
        });
    
        return res;
      });
    },

    toggleTaskComplete(uid, calendarId, complete) {
      return fetchJson(`${BASE}/task/toggle-complete`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
              uid,
              calendar_id: calendarId,   // ✅ 추가
              completed: complete
          })
      }).then(res => {
          CalendarAPI.clearCache();
          CalendarAPI.forceRefetch();
          requestAnimationFrame(() => {
            syncTasksUI();
          });        
          return res;
      });
    },

    fetchTasksForPanel() {
      return fetchJson(`${BASE}/tasks-panel`)
        .then(res => {
          const tasks = Array.isArray(res?.data) ? res.data : [];
    
          CalendarStore?.setTasks?.(tasks);
    
          return tasks;
        });
    },

    deleteTasksBulk(uids = []) {
      return fetchJson(`${BASE}/task/delete-bulk`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uids })
      }).then(async res => {
    
        CalendarAPI.clearCache();
    
        await window.CalendarAPI.fetchTasksForPanel(); // 명시적 fetch
        window.TaskPanel?.refresh?.({});               // 필터 리셋
    
        scheduleRefetchEvents();
    
        return res;
      });
    }




  };

  // 🔥 refetchEvents() 폭주 방지 (1프레임/1회로 합치기)
let __refetchPending = false;
function scheduleRefetchEvents() {
  const cal = window.__calendar;
  if (!cal) return;

  if (__refetchPending) return;
  __refetchPending = true;

  requestAnimationFrame(() => {
    __refetchPending = false;
    cal.refetchEvents();
  });
}



})();

