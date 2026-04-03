// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/store.js'
(() => {
  'use strict';
  console.log('[store] loaded');
  if (window.CalendarStore) return;

  /* =====================================================
   * Internal State
   * ===================================================== */
  const state = {
    calendars: [],        // calendar list (raw)
    events: [],           // VEVENT
    tasks: [],            // VTODO

    // ✅ 활성 상태 (핵심)
    activeCalendars: new Set(), // calendar_id
    activeTasks: new Set(),      // task key
    calendarColors: new Map(), // 🔥 추가
    taskColors: new Map()      // 🔥 추가
  };

  const listeners = new Set();

  function emit() {
    listeners.forEach(fn => {
      try { fn(getSnapshot()); } catch (e) {}
    });
  }

  function getSnapshot() {
    return {
      calendars: [...state.calendars],
      events: [...state.events],
      tasks: [...state.tasks],
  
      activeCalendars: new Set(state.activeCalendars),
      activeTasks: new Set(state.activeTasks),
  
      // 🔥 이 두 줄 추가
      calendarColors: new Map(state.calendarColors),
      taskColors: new Map(state.taskColors)
    };
  } 

  
  /* =====================================================
   * Public API
   * ===================================================== */
  const Store = {

    /* ---------- subscribe ---------- */
    subscribe(fn) {
      listeners.add(fn);
      return () => listeners.delete(fn);
    },

    getSnapshot,

    /* ---------- calendars ---------- */
    setCalendars(list = []) {
      state.calendars = Array.isArray(list) ? list : [];
    
      // 🔥 핵심 1: 색상 Map 초기화
      state.calendarColors.clear();
    
      // 🔥 핵심 2: 활성 캘린더 초기화
      state.activeCalendars.clear();
    
      state.calendars.forEach(c => {
        const id = c.calendar_id ?? c.id;
        if (!id) return;
      
        const sid = String(id);
      
        // 🔥 calendar + task 모두 기본 활성
        state.activeCalendars.add(sid);
      
        if (c.calendar_color) {
          state.calendarColors.set(sid, String(c.calendar_color));
        }
      });
      
    
      emit();
    },
    

    getCalendars() {
      return [...state.calendars];
    },

    /* ---------- colors ---------- */
    setCalendarColor(id, color, { silent = false } = {}) {
      state.calendarColors.set(String(id), String(color));
      if (!silent) emit();
    },
    
    
    getCalendarColor(id) {
      return state.calendarColors.get(String(id));
    },

    /* ---------- collection href (🔥 핵심 추가) ---------- */
    getCollectionHref(id) {
      id = String(id);
      const cal = state.calendars.find(c =>
        String(c.calendar_id ?? c.id) === id
      );
      return cal?.href || null;
    },

    getTaskColor(id) {
      return state.taskColors.get(String(id));
    },

    /* ---------- calendar name (🔥 추가) ---------- */
    getCalendarName(id) {
      id = String(id);
      return (
        state.calendars.find(c =>
          String(c.calendar_id ?? c.id) === id
        )?.calendar_name
        ||
        state.calendars.find(c =>
          String(c.calendar_id ?? c.id) === id
        )?.name
        ||
        ''
      );
    },

    /* ---------- calendar toggle ---------- */
    toggleCalendar(id, enabled) {
      id = String(id);
      if (enabled) {
        state.activeCalendars.add(id);
      } else {
        state.activeCalendars.delete(id);
      }
      emit();
    },

    isCalendarActive(id) {
      return state.activeCalendars.has(String(id));
    },

    getActiveCalendarIds() {
      return Array.from(state.activeCalendars);
    },

    /* ---------- events ---------- */
    setEvents(list = []) {
      state.events = Array.isArray(list) ? list : [];
      emit();
    },

    /* ---------- tasks ---------- */
    setTasks(list = []) {
      state.tasks = Array.isArray(list) ? list : [];
      emit();
    },

    getTasks() {
      return [...state.tasks];
    },

    setTaskColor(taskUid, color, { silent = false } = {}) {
      state.taskColors.set(String(taskUid), String(color));
      if (!silent) emit();
    },
    
    clearTaskColor(taskUid, { silent = false } = {}) {
      state.taskColors.delete(String(taskUid));
      if (!silent) emit();
    },
    
    toggleTask(key, enabled) {
      key = String(key);
      if (enabled) state.activeTasks.add(key);
      else state.activeTasks.delete(key);
      emit();
    },    

    /* ---------- active calendars (🔥 필수) ---------- */
    setActiveCalendars(set) {
      const next = new Set(set || []);
    
      // 🔥 task 타입 캘린더는 기본 포함
      state.calendars
        .filter(c => c.type === 'task')
        .forEach(c => {
          const id = String(c.calendar_id || c.id);
          next.add(id);
        });
    
      state.activeCalendars = next;
      emit();
    },
    

    /* ---------- active tasks ---------- */
    setActiveTasks(set) {
      state.activeTasks = new Set(set || []);
      emit();
    },

    getActiveTaskIds() {
      return Array.from(state.activeTasks);
    },

    isTaskActive(id) {
      return state.activeTasks.has(String(id));
    },


    /* ---------- derived (🔥 핵심) ---------- */
    getFilteredEvents() {
      return state.events.filter(ev => {
        const calId = String(ev.extendedProps?.calendar_id || '');
        return state.activeCalendars.has(calId);
      });
    },

    getFilteredTasks() {
      return state.tasks.filter(t => {
        const calId = String(
          t.extendedProps?.calendar_id ??
          t.calendar_id ??
          ''
        );
    
        if (!state.activeCalendars.has(calId)) return false;
    
        if (state.activeTasks.size > 0) {
          const key = String(t.uid || t.id);
          return state.activeTasks.has(key);
        }
    
        return true;
      });
    },
    

    updateEvent(uid, patch) {
      let changed = false;
    
      state.events = state.events.map(e => {
        if (e.uid === uid || e.id === uid) {
          changed = true;
    
          return {
            ...e,
            ...patch,
            extendedProps: {
              ...(e.extendedProps || {}),
              ...(patch.extendedProps || {})
            }
          };
        }
        return e;
      });
    
      if (changed) emit();
    }
    

  };

  window.CalendarStore = Store;
})();
