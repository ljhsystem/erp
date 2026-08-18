(() => {
  'use strict';
  console.log('[view] loaded');
  if (window.CalendarView) return;
  window.CalendarView = true;

  window.__CAL_SEARCH_MODE__ = false;

  let calendar = null;
  let unsubscribe = null;

  window.flashCalendarDate = flashCalendarDate;


  const TEMP_EVENT_ID = '__quick_temp_event__';

  const CAL_COLOR_CONFIG = {
    allDayAlpha: 0.5,
    adminAlpha: 0.25,
    timeTextAuto: true,
    yiqThreshold: 180
  };



  function normalizeEventForCalendar(ev) {
    const ex = ev.extendedProps || {};
    const raw = ex.raw || {};

    const href =
      ex.href ||
      ex.caldav_href ||
      ex.object_href ||
      raw.href ||
      raw.caldav_href ||
      raw.object_href ||
      ev.href ||
      ev.caldav_href ||
      ev.object_href ||
      '';

      const next = {
        ...ev,
        extendedProps: {
          ...ex,
          href,
          admin_event_color:
            ex.admin_event_color ??
            ev.admin_event_color ??
            null
        }
      };

    if (next.rrule) {
      return {
        ...next,
        start: undefined
      };
    }

    return next;
  }



  function taskToEvent(task) {
    const ex = task.extendedProps || {};

    const rawStart =
      task.start ||
      task.due ||
      task.due_iso ||
      task.dtstart;

    if (!rawStart) return null;

    const raw = ex.raw || {};
    const rawIcs = raw.raw || {};

    const isDateOnly =
      rawIcs?.DUE?.params?.VALUE === 'DATE' ||
      /^\d{4}-\d{2}-\d{2}$/.test(rawStart);


    let startVal;
    let endVal = null;
    let allDayVal = false;

    if (isDateOnly) {
      startVal = String(rawStart).slice(0, 10);
      allDayVal = true;
    } else {
      startVal = String(rawStart).replace(' ', 'T');
      allDayVal = false;
    }


    const completed =
      ex.completed === true ||
      String(ex.status || '').toUpperCase() === 'COMPLETED' ||
      String(ex.raw?.status || '').toUpperCase() === 'COMPLETED';

      const result = {
        id: task.id,
        title: task.title,
        start: startVal,
        end: endVal,
        allDay: allDayVal,
        classNames: completed
          ? ['fc-task-event', 'is-completed']
          : ['fc-task-event'],
          extendedProps: {
            ...ex,
            type: 'VTODO',
            calendar_id: ex.calendar_id,
            completed,
            admin_calendar_color:
              ex.admin_calendar_color ||
              CalendarStore.getCalendarColor(String(ex.calendar_id || ''))
          }
      };

    return result;
  }

  function renderSynologyListEvent(arg) {
    const ev = arg.event;
    const ex = ev.extendedProps || {};

    const calendarColor =
      ex.admin_calendar_color ||
      CalendarStore.getCalendarColor(String(ex.calendar_id || '')) ||
      '#9ca3af';

    const wrapper = document.createElement('div');
    wrapper.className = 'syno-row';

    const colorBar = document.createElement('div');
    colorBar.className = 'syno-color-bar';
    colorBar.style.backgroundColor = calendarColor;

    const repeat = document.createElement('div');
    repeat.className = 'syno-repeat';

    const rrule = ev._def?.recurringDef;

    if (rrule) {

      repeat.textContent = '매일';
    } else if (ev.allDay) {

      repeat.textContent = '종일';
    } else if (ev.start) {

      const start = ev.start;
      const end   = ev.end;

      const pad = n => String(n).padStart(2, '0');

      const startStr =
        pad(start.getHours()) + ':' +
        pad(start.getMinutes());

      let endStr = '';

      if (end) {
        endStr =
          pad(end.getHours()) + ':' +
          pad(end.getMinutes());
      }

      repeat.textContent = endStr
        ? `${startStr}~${endStr}`
        : startStr;
    }

    const title = document.createElement('div');
    title.className = 'syno-title';
    title.textContent = ev.title;

    wrapper.appendChild(colorBar);
    wrapper.appendChild(repeat);
    wrapper.appendChild(title);

    return { domNodes: [wrapper] };
  }

  function eventContent(arg) {
    const ev = arg.event;
    const ex = ev.extendedProps || {};

    if (arg.view.type === 'listMonth') {
      return renderSynologyListEvent(arg);
    }

    const isCompleted =
      ex.completed === true ||
      String(ex.raw?.status || '').toUpperCase() === 'COMPLETED' ||
      String(ex.raw?.STATUS?.value || '').toUpperCase() === 'COMPLETED';

    const calendarColor =
      ex.admin_calendar_color ||
      CalendarStore.getCalendarColor(String(ex.calendar_id || '')) ||
      '#64748b';

    const adminColor =
      typeof ex.admin_event_color === 'string' &&
      ex.admin_event_color.startsWith('#')
        ? ex.admin_event_color
        : null;

    const multiDay = isMultiDay(ev);

    const isTimeEvent =
    !ev.allDay &&
    ev.start &&
    ev.start.getHours() + ev.start.getMinutes() > 0;

    const wrap = createEventWrap(
      isTimeEvent,
      multiDay,
      ev,
      ex,
      calendarColor,
      isCompleted
    );

    if (isCompleted) {
      wrap.style.backgroundColor = '#f5f5f5';
    }

    if (adminColor) {
      const dot = document.createElement('span');
      dot.className = 'shint-ev-dot';
      dot.style.backgroundColor = adminColor;
      wrap.appendChild(dot);
    }

    return { domNodes: [wrap], text: '' };
  }

  function createEventWrap(isTime, multiDay, ev, ex, calendarColor, isCompleted){
    const wrap = document.createElement('div');
    wrap.className = 'shint-ev';
    wrap.style.display = 'flex';
    wrap.style.alignItems = 'center';
    wrap.style.paddingLeft = isTime ? '6px' : '6px';

    if (isTime) wrap.classList.add('is-time');
    if (multiDay) wrap.classList.add('is-multi-time');
    if (ev.allDay) wrap.classList.add('is-allday');

    if (isTime) {
      const bar = document.createElement('span');
      bar.className = 'shint-ev-bar';

      const c = colorUtil(calendarColor, {
        alpha: CAL_COLOR_CONFIG.adminAlpha
      });

      bar.style.backgroundColor = c.rgba;

      wrap.appendChild(bar);
    }



    const title = document.createElement('span');
    title.className = 'shint-ev-title';

    if (isCompleted) {
      title.classList.add('is-completed');
    }

    const timeStr = isTime && ev.start ? ev.start.toTimeString().slice(0, 5) : '';
    const icon = ex.type === 'VTODO' ? '📝' : '';

    if (ex.type === 'VTODO') {

      if (ev.allDay) {
        const c = colorUtil(calendarColor, {
          alpha: CAL_COLOR_CONFIG.allDayAlpha
        });
        wrap.style.backgroundColor = c.rgba;

      } else {
        wrap.style.backgroundColor = 'transparent';
      }

    } else {


      if (ev.allDay) {
        const c = colorUtil(calendarColor, {
          alpha: CAL_COLOR_CONFIG.allDayAlpha
        });
        wrap.style.backgroundColor = c.rgba;
      } else {
        const c = colorUtil(calendarColor, {
          alpha: CAL_COLOR_CONFIG.adminAlpha
        });
        wrap.style.backgroundColor = c.rgba;
      }
    }

    let text = '';
    if (timeStr) {
      text += timeStr + ' ';
    }
    if (icon) {
      text += icon + ' ';
    }
    text += ev.title;
    title.textContent = text.trim();

    wrap.appendChild(title);

    if (ev.allDay) {
        const c = colorUtil(calendarColor, {
            alpha: CAL_COLOR_CONFIG.allDayAlpha
        });
        wrap.style.backgroundColor = c.rgba;
    }

    return wrap;
  }



  function isMultiDay(ev) {
    if (!ev.start || !ev.end) return false;

    const start = new Date(ev.start);
    const end   = new Date(ev.end);

    return start.toDateString() !== end.toDateString();
  }



  function createCalendar() {
    const el = document.getElementById('calendar');
    if (!el || !window.FullCalendar) return;

    calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      locale: 'ko',
      height: '100%',
      selectable: true,
      editable: true,
      dayMaxEvents: true,

      lazyFetching: true,
      progressiveEventRendering: true,

      views: {
        dayGridMonth: {
          displayEventTime: false
        },
        timeGridWeek: {
          displayEventTime: true
        },
        timeGridDay: {
          displayEventTime: true
        },
        listMonth: {
          listDayFormat: { day: 'numeric', weekday: 'short' }
        }
      },

      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'searchView,dayGridMonth,timeGridWeek,timeGridDay'
      },

      customButtons: {
        searchView: {
          text: 'search',
          click: function () {

            window.__CAL_SEARCH_MODE__ = true;

            calendar.batchRendering(() => {
              calendar.changeView('listMonth');
            });

            requestAnimationFrame(() => {
              updateSearchButtonState();
            });

          }
        }
      },

      eventContent,

      eventDidMount(info) {
        const ev = info.event;
        const ex = ev.extendedProps || {};

        const calId = String(ex.calendar_id || '');
        const calendarColor =
              ex.admin_calendar_color ||
              CalendarStore.getCalendarColor(calId) ||
              '#64748b';

        const adminColor =
          typeof ex.admin_event_color === 'string' &&
          ex.admin_event_color.startsWith('#')
            ? ex.admin_event_color
            : null;

        const main = info.el.querySelector('.fc-event-main');


        if (ev.allDay && ex.type === 'VTODO') {
          info.el.style.backgroundColor = 'transparent';
          info.el.style.border = 'none';
        }

        if (ev.allDay) {
          return;
        }

        info.el.style.backgroundColor = '';
        info.el.style.borderColor = '';

        if (main) {
          main.style.backgroundColor = 'transparent';
        }

        if (CAL_COLOR_CONFIG.timeTextAuto) {
          const c = colorUtil(calendarColor);
          info.el.style.setProperty('--shint-text-color', c.text);
        }
      },

      eventClick(info) {
        info.jsEvent.preventDefault();
        const ev = info.event;
        const ex = ev.extendedProps || {};

        console.log('Event Clicked:', ev);
        console.log('Event Type:', ex.type);

        if (ex.type === 'VTODO') {
          console.log('Opening task view modal');

          document.dispatchEvent(
            new CustomEvent('calendar:task:view', {
              detail: ev
            })
          );
        } else if (ex.type === 'VEVENT') {
          console.log('Opening event view modal');

          document.dispatchEvent(
            new CustomEvent('calendar:event:view', {
              detail: ev
            })
          );
        } else {
          console.warn('Unknown type:', ex.type);
        }
      },

      events: async (info, success, failure) => {

        try {

          const state = window.__CAL_FILTER_STATE__ || {};

          const liveKeyword =
            (window.__CAL_LIVE_KEYWORD__ || '').trim().toLowerCase();

          const rangeStart = window.__CAL_SEARCH_MODE__ && state.from
            ? new Date(state.from)
            : info.start;

          const rangeEnd = window.__CAL_SEARCH_MODE__ && state.to
            ? new Date(state.to + 'T23:59:59')
            : info.end;

          const { events } = await CalendarAPI.fetchAll({
            start: rangeStart,
            end: rangeEnd
          });


          let mergedEvents = Array.isArray(events) ? [...events] : [];

          const pending = window.__CAL_PENDING_EVENTS__;
          if (pending && pending.size) {
            const existingIds = new Set(
              mergedEvents.map(e => String(e.uid || e.id))
            );

            pending.forEach((pe, uid) => {
              if (!existingIds.has(String(uid))) {
                mergedEvents.push(pe);
              } else {
                pending.delete(uid);
              }
            });
          }

          let filtered = mergedEvents;

          if (liveKeyword) {
            filtered = filtered.filter(ev => {
              const ex = ev.extendedProps || {};
              const raw = ex.raw || {};

              const text = [
                ev.title,
                ex.description,
                raw.description,
                ex.location,
                raw.location
              ].filter(Boolean).join(' ').toLowerCase();

              return text.includes(liveKeyword);
            });
          }

          const keyword = (state.keyword || '').trim().toLowerCase();

          if (window.__CAL_SEARCH_MODE__ && keyword) {
            filtered = filtered.filter(ev => {
              const ex = ev.extendedProps || {};
              const raw = ex.raw || {};

              const text = [
                ev.title,
                ex.description,
                raw.description,
                ex.location,
                raw.location
              ].filter(Boolean).join(' ').toLowerCase();

              return text.includes(keyword);
            });
          }

          if (Array.isArray(state.calendars) && state.calendars.length) {
            filtered = filtered.filter(ev => {
              const calId = String(ev.extendedProps?.calendar_id || '');
              return state.calendars.includes(calId);
            });
          }


          if (state.from || state.to) {
            filtered = filtered.filter(ev => {
              const start = ev.start ? new Date(ev.start) : null;
              if (!start) return false;

              if (state.from) {
                const fromDate = new Date(state.from);
                if (start < fromDate) return false;
              }

              if (state.to) {
                const toDate = new Date(state.to);
                toDate.setHours(23,59,59,999);
                if (start > toDate) return false;
              }

              return true;
            });
          }

          if (Array.isArray(state.colors) && state.colors.length) {

            const selectedColors = state.colors
              .map(c => String(c).trim().toLowerCase());

            filtered = filtered.filter(ev => {

              const rawColor = ev.extendedProps?.admin_event_color;

              const eventColor =
                rawColor === null || rawColor === undefined
                  ? 'null'
                  : String(rawColor).trim().toLowerCase();

              return selectedColors.includes(eventColor);
            });
          }


          let  finalEvents = filtered.map(ev => {
            const normalized = normalizeEventForCalendar(ev);

            if (normalized.start && typeof normalized.start === 'string') {
              normalized.start = normalized.start.replace(' ', 'T');
            }

            if (normalized.end && typeof normalized.end === 'string') {
              normalized.end = normalized.end.replace(' ', 'T');
            }

            return normalized;
          });


          const snapshot = CalendarStore.getSnapshot();

          if (snapshot) {
            finalEvents = finalEvents.filter(ev => {
              const ex = ev.extendedProps || {};
              const calId = String(ex.calendar_id || '');

              if (ex.type === 'VEVENT') {
                return snapshot.activeCalendars.has(calId);
              }

              if (ex.type === 'VTODO') {
                return snapshot.activeTasks?.has(calId);
              }

              return true;
            });
          }

          success(finalEvents);

          window.__CAL_LAST_FETCH__ = finalEvents;

          setTimeout(() => {
            updateSearchButtonState();
          }, 0);

          window.__CAL_LAST_RESULT_COUNT__ = finalEvents.length;

          renderEmptyState(finalEvents.length);

          document.dispatchEvent(
            new CustomEvent('calendar:search:updated', {
              detail: {
                count: finalEvents.length
              }
            })
          );



        } catch (e) {
          console.warn('[calendar] fetchAll failed', e);
          failure(e);
        }
      },



      dateClick(info) {
        const cal = window.__calendar;
        if (!cal) return;

        window.__quickTempEvent?.remove();
        window.__quickTempEvent = null;

        const start = info.date;
        const end = new Date(start.getTime() + 60 * 60 * 1000);

        const tempEvent = cal.addEvent({
          id: '__quick_temp_event__',
          title: '(제목 없음)',
          start,
          end,
          allDay: info.allDay ?? false,
          backgroundColor: '#94a3b8',
          borderColor: '#94a3b8',
          extendedProps: {
            __temp: true,
            type: 'VEVENT'
          }
        });

        window.__quickTempEvent = tempEvent;

        document.dispatchEvent(
          new CustomEvent('calendar:quick:create', {
            detail: { event: tempEvent }
          })
        );
      },

      viewDidMount(info) {
        const fc = document.querySelector('.calendar-center .fc');
        if (!fc) return;

        if (info.view.type === 'listMonth') {
          fc.style.height = '100%';
        }
      },

      eventDrop: onEventChange,
      eventResize: onEventChange
    });

    calendar.render();




    window.__calendar = calendar;

    updateSearchButtonState();

    calendar.on('datesSet', (info) => {

      updateSearchButtonState();

      const searchBtn = document.querySelector('.fc-searchView-button');
      if (!searchBtn) return;

      if (
        window.__CAL_SEARCH_MODE__ &&
        info.view.type === 'listMonth'
      ) {
        searchBtn.classList.add('fc-button-active');
        document
          .querySelector('.fc-dayGridMonth-button')
          ?.classList.remove('fc-button-active');
      } else {
        searchBtn.classList.remove('fc-button-active');
      }

    });

    if (!unsubscribe) {
      unsubscribe = CalendarStore.subscribe(applyStoreFilter);
    }



  }

  function updateSearchButtonState() {

    const btn = document.querySelector('.fc-searchView-button');
    if (!btn || !calendar) return;

    if (!window.__CAL_SEARCH_MODE__) {
      btn.classList.add('is-hidden-search');
      btn.classList.remove('fc-button-active');
      return;
    }

    btn.classList.remove('is-hidden-search');

    if (calendar.view.type === 'listMonth') {
      btn.classList.add('fc-button-active');
    } else {
      btn.classList.remove('fc-button-active');
    }
  }

  window.updateSearchButtonState = updateSearchButtonState;

function destroyCalendar() {
  if (calendar) {
    calendar.destroy();
    calendar = null;
  }

  if (unsubscribe) {
    unsubscribe();
    unsubscribe = null;
  }

  window.__CAL_LAST_FETCH__ = null;
}


function colorUtil(hex, options = {}) {
  if (!hex || hex[0] !== '#') {
    return {
      hex: hex || null,
      rgb: null,
      rgba: hex || null,
      text: '#ffffff'
    };
  }

  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);

  const alpha = typeof options.alpha === 'number'
    ? options.alpha
    : 1;

  const threshold = typeof options.threshold === 'number'
    ? options.threshold
    : CAL_COLOR_CONFIG.yiqThreshold;

  const rgba = `rgba(${r}, ${g}, ${b}, ${alpha})`;

  const yiq = (r * 299 + g * 587 + b * 114) / 1000;
  const text = yiq >= threshold ? '#1f2937' : '#ffffff';

  return { hex, rgb: { r, g, b }, rgba, text };
}


  function applyStoreFilter(snapshot) {
    if (!calendar) return;


    calendar.getEvents().forEach(ev => {

      const ex = ev.extendedProps || {};
      const calId = String(ex.calendar_id || '');

      let visible = true;

      if (ex.type === 'VEVENT') {
        visible = snapshot.activeCalendars.has(calId);
      }

      if (ex.type === 'VTODO') {
        visible = snapshot.activeTasks?.has(calId);
      }

      ev.setProp('display', visible ? 'auto' : 'none');
    });
  }

  function toLocalString(d) {
    if (!d) return null;

    const pad = n => String(n).padStart(2, '0');

    return (
      d.getFullYear() + '-' +
      pad(d.getMonth() + 1) + '-' +
      pad(d.getDate()) + 'T' +
      pad(d.getHours()) + ':' +
      pad(d.getMinutes()) + ':' +
      pad(d.getSeconds())
    );
  }

  async function onEventChange(info) {
    try {
      const ev = info.event;
      const ex = ev.extendedProps || {};

      if (ex.type === 'VTODO') {
        info.revert();
        return;
      }

      await CalendarAPI.updateEvent({
        uid: ev.id,
        href: ex.href || null,
        start: toLocalString(ev.start),
        end: toLocalString(ev.end),
        allDay: ev.allDay
      });

    } catch (e) {
      console.error('[Drag/Resize Failed]', e);
      info.revert();
      alert('일정 변경 실패');
    }
  }




  function flashCalendarDate(date) {
    if (!window.__calendar) return;

    const ymd = date.toISOString().slice(0, 10);

    // dayGrid 기준
    const cell = document.querySelector(
      `.fc-daygrid-day[data-date="${ymd}"]`
    );

    if (!cell) return;

    cell.classList.remove('is-flash');
    void cell.offsetWidth;
    cell.classList.add('is-flash');

    setTimeout(() => {
      cell.classList.remove('is-flash');
    }, 1300);
  }

function bootCalendarView() {
  if (window.CalendarContext?.calendars?.length) {
    createCalendar();
    return;
  }

  document.addEventListener(
    'calendar:ready',
    () => createCalendar(),
    { once: true }
  );
}



function renderEmptyState(count) {

  const container = document.querySelector('.calendar-center');
  if (!container) return;

  let empty = document.getElementById('calendar-empty-state');

  if (!window.__CAL_SEARCH_MODE__ || count > 0) {
    if (empty) empty.remove();
    return;
  }

  if (!empty) {
    empty = document.createElement('div');
    empty.id = 'calendar-empty-state';
    empty.className = 'calendar-empty-state';
    container.appendChild(empty);
  }

  const state = window.__CAL_FILTER_STATE__ || {};

  empty.innerHTML = `
  <div class="empty-card">
    <div class="empty-icon">🔎</div>
    <div class="empty-title">검색 결과가 없습니다</div>
    <div class="empty-desc">
      ${conditionText()}
    </div>
  </div>
`;


}

function conditionText() {
  const state = window.__CAL_FILTER_STATE__ || {};

  const parts = [];

  if (state.keyword) parts.push(`"${state.keyword}"`);
  if (state.from || state.to)
    parts.push(`${state.from || '전체'} ~ ${state.to || '전체'}`);
  if (state.colors?.length)
    parts.push(`색상 ${state.colors.length}개`);

  return parts.length ? parts.join(' · ') : '전체 조건';
}





bootCalendarView();

document.addEventListener('calendar:destroy', () => {
  destroyCalendar();
});

window.addEventListener('beforeunload', destroyCalendar);



})();
