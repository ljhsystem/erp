// /public/assets/js/pages/dashboard/calendar/view.js
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
    allDayAlpha: 0.5, // 종일 배경색
    adminAlpha: 0.25, // 관리자 강조 강하게
    timeTextAuto: true,
    yiqThreshold: 180 // 대비 기준 낮춤
  };
  


  function normalizeEventForCalendar(ev) {
    const ex = ev.extendedProps || {};
    const raw = ex.raw || {};
  
    // ✅ 서버 응답 어디에 있든 href를 찾아서 고정
    // (백엔드가 어떤 키로 내려주든 여기서 한 번에 흡수)
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
  
    // 🔥 rrule 이벤트면 start 제거 (네 기존 로직 유지)
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
  
    //console.log('---------------------------');
    //console.log('[taskToEvent] rawStart:', rawStart);
    //console.log('[taskToEvent] original task:', task);
  
    if (!rawStart) return null;
  
    const raw = ex.raw || {};
    const rawIcs = raw.raw || {};
    
    const isDateOnly =
      rawIcs?.DUE?.params?.VALUE === 'DATE' ||
      /^\d{4}-\d{2}-\d{2}$/.test(rawStart);
  
    //console.log('[taskToEvent] isDateOnly:', isDateOnly);
  
    let startVal;
    let endVal = null;
    let allDayVal = false;
    
    if (isDateOnly) {
      startVal = String(rawStart).slice(0, 10);  // YYYY-MM-DD
      allDayVal = true;
    } else {
      startVal = String(rawStart).replace(' ', 'T');
      allDayVal = false;
    }
  
    //console.log('[taskToEvent] startVal:', startVal);
    //console.log('[taskToEvent] allDayVal:', allDayVal);
  
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
            type: 'VTODO',        // 🔥 이거 반드시 넣어야 함
            calendar_id: ex.calendar_id,
            completed,
            admin_calendar_color:
              ex.admin_calendar_color ||
              CalendarStore.getCalendarColor(String(ex.calendar_id || ''))
          }
      };
  
    //console.log('[taskToEvent] FINAL EVENT:', result);
    //console.log('---------------------------');
  
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
  
    // 🔹 컬러 세로바
    const colorBar = document.createElement('div');
    colorBar.className = 'syno-color-bar';
    colorBar.style.backgroundColor = calendarColor;
  
    // 🔹 반복/시간 영역
    const repeat = document.createElement('div');
    repeat.className = 'syno-repeat';
  
    // ===============================
    // 🔥 핵심 로직
    // ===============================
  
    const rrule = ev._def?.recurringDef;
  
    if (rrule) {
      // 반복 이벤트
      repeat.textContent = '매일'; // 필요하면 rrule 분석 가능
    } else if (ev.allDay) {
      // 종일 이벤트
      repeat.textContent = '종일';
    } else if (ev.start) {
      // 시간 이벤트
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
  
    // 🔹 제목
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
    
    // 🔥 admin 컬러 우선
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
    
    
    // 완료된 이벤트에 대해 배경 색상 처리
    if (isCompleted) {
      wrap.style.backgroundColor = '#f5f5f5'; // 완료된 이벤트는 회색 배경
    }
    
    // 관리자 색상 처리
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
    
    // 시간 이벤트, 멀티데이 이벤트, 종일 이벤트 구분하여 클래스 추가
    if (isTime) wrap.classList.add('is-time');  // 시간 이벤트
    if (multiDay) wrap.classList.add('is-multi-time'); // 멀티데이
    if (ev.allDay) wrap.classList.add('is-allday');  // 종일 이벤트
    
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
    
    // 시간 이벤트가 아니면 배경색을 제목 뒤에 적용
    const timeStr = isTime && ev.start ? ev.start.toTimeString().slice(0, 5) : ''; // 시간을 제목 앞에 추가    
    const icon = ex.type === 'VTODO' ? '📝' : ''; // 태스크일 경우 이모티콘 추가
    

    // ===============================
    // 🔥 배경 처리
    // ===============================
    if (ex.type === 'VTODO') {

      if (ev.allDay) {
        // 날짜-only 태스크 → 제목 뒤 배경
        const c = colorUtil(calendarColor, {
          alpha: CAL_COLOR_CONFIG.allDayAlpha
        });
        wrap.style.backgroundColor = c.rgba;

      } else {
        // 시간 있는 태스크 → 배경 없음
        wrap.style.backgroundColor = 'transparent';
      }

    } else {
      // VEVENT

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
    
    // 종일 이벤트의 경우, 제목 뒤 배경색을 원래 캘린더 색상으로 적용
    if (ev.allDay) {
        const c = colorUtil(calendarColor, {
            alpha: CAL_COLOR_CONFIG.allDayAlpha  // 투명도 적용
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
    
      lazyFetching: true,              // 🔥 추가
      progressiveEventRendering: true, // 🔥 추가
    
      // ❌ 이거 전역으로 true 두면 월간에서 FC가 시간칸을 만들어버림
      // displayEventTime: true,
    
      // ✅ 월간(dayGridMonth)만 시간 표시 끔 (우리가 eventContent에서 직접 표시)
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
  
      eventContent,  // 이벤트 콘텐츠 처리
  
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

        
        // 종일 이벤트 처리
        if (ev.allDay) {
          // 🔥 wrap에서 배경 처리하므로 여기서 건드리지 않는다
          return;
        }
  
        info.el.style.backgroundColor = '';
        info.el.style.borderColor = '';
  
        if (main) {
          main.style.backgroundColor = 'transparent';
        }
  
        // 시간 이벤트 텍스트 색상 처리
        if (CAL_COLOR_CONFIG.timeTextAuto) {
          const c = colorUtil(calendarColor);
          info.el.style.setProperty('--shint-text-color', c.text);
        }
      },
  

      // 이벤트 클릭 시 핸들러 등록
      eventClick(info) {
        info.jsEvent.preventDefault();  // 기본 클릭 이벤트 차단
        const ev = info.event;
        const ex = ev.extendedProps || {};  // extendedProps에서 type 정보 확인
  
        console.log('Event Clicked:', ev); // 클릭된 이벤트 정보 확인
        console.log('Event Type:', ex.type); // 이벤트 타입 확인
  
        // 태스크인지 이벤트인지 구분
        if (ex.type === 'VTODO') {
          console.log('Opening task view modal');
          // 태스크 모달 열기
          document.dispatchEvent(
            new CustomEvent('calendar:task:view', {
              detail: ev
            })
          );
        } else if (ex.type === 'VEVENT') {
          console.log('Opening event view modal');
          // 이벤트 모달 열기
          document.dispatchEvent(
            new CustomEvent('calendar:event:view', {
              detail: ev
            })
          );
        } else {
          console.warn('Unknown type:', ex.type); // type이 없거나 알 수 없는 경우 경고
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
      
          /* =============================
             1️⃣ 실시간 검색 (달력 내부 전용)
          ============================= */
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
      
          /* =============================
             2️⃣ 검색모드 키워드 (검색결과용)
          ============================= */
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
      
          /* =============================
             3️⃣ 캘린더 필터
          ============================= */
          if (Array.isArray(state.calendars) && state.calendars.length) {
            filtered = filtered.filter(ev => {
              const calId = String(ev.extendedProps?.calendar_id || '');
              return state.calendars.includes(calId);
            });
          }      
          
      
          /* =============================
             4️⃣ 기간 필터
          ============================= */
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
      

          /* =============================
             5️⃣ 색상 필터
          ============================= */
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

          /* =============================
            6️⃣ 결과 처리
          ============================= */

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

          // 🔥 먼저 Store 필터 적용
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

          // 🔥 마지막 fetch 결과 저장 (재사용용)
          window.__CAL_LAST_FETCH__ = finalEvents;

          setTimeout(() => {
            updateSearchButtonState();
          }, 0);

          // 🔥 검색 결과 개수 저장
          window.__CAL_LAST_RESULT_COUNT__ = finalEvents.length;

          // 🔥 empty 처리
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
  
        // 기존 임시 이벤트 제거
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

    // 최초 1회만 실행
    updateSearchButtonState();
    
    // 뷰 전환 시에만 실행
    calendar.on('datesSet', (info) => {

      updateSearchButtonState();   // 🔥 이 줄 추가
    
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
  
    // 🔥 검색 모드가 아닐 때 숨김
    if (!window.__CAL_SEARCH_MODE__) {
      btn.classList.add('is-hidden-search');
      btn.classList.remove('fc-button-active');
      return;
    }
  
    // 🔥 검색 모드면 무조건 노출
    btn.classList.remove('is-hidden-search');
  
    if (calendar.view.type === 'listMonth') {
      btn.classList.add('fc-button-active');
    } else {
      btn.classList.remove('fc-button-active');
    }
  }

  window.updateSearchButtonState = updateSearchButtonState;

// 기존 코드에서 발생할 수 있는 중복을 제거하고, 유지보수성이 높아졌습니다.

function destroyCalendar() {
  if (calendar) {
    calendar.destroy();
    calendar = null;
  }

  if (unsubscribe) {
    unsubscribe();
    unsubscribe = null;
  }

  // 🔥 이거 추가
  window.__CAL_LAST_FETCH__ = null;
}

  
//밝기 계산 함수
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



  
  /* =====================================================
   * Store → View 필터 반영
   * ===================================================== */
  function applyStoreFilter(snapshot) {
    if (!calendar) return;

    //console.log('🔥 snapshot.activeTasks:', snapshot.activeTasks);
    //console.log('🔥 snapshot.activeCalendars:', snapshot.activeCalendars);

    calendar.getEvents().forEach(ev => {
  
      const ex = ev.extendedProps || {};
      const calId = String(ex.calendar_id || '');
  
      let visible = true;
  
      // ===============================
      // VEVENT → activeCalendars
      // ===============================
      if (ex.type === 'VEVENT') {
        visible = snapshot.activeCalendars.has(calId);
      }
  
      // ===============================
      // VTODO → activeTasks
      // ===============================
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

  /* =====================================================
   * Drag / Resize
   * ===================================================== */
  async function onEventChange(info) {
    try {
      const ev = info.event;
      const ex = ev.extendedProps || {};
  
      // 🔥 Task는 Drag/Resize 금지
      if (ex.type === 'VTODO') {
        info.revert();
        return;
      }
  
      await CalendarAPI.updateEvent({
        uid: ev.id,
        href: ex.href || null,        // ✅ 추가
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
  
    cell.classList.remove('is-flash'); // 재트리거용
    void cell.offsetWidth;             // 🔥 reflow
    cell.classList.add('is-flash');
  
    // 안전 제거
    setTimeout(() => {
      cell.classList.remove('is-flash');
    }, 1300);
  }
  
/* =====================================================
 * Boot (READY 이벤트 놓치는 문제 해결)
 * ===================================================== */
function bootCalendarView() {
  // 이미 bootstrap이 끝났으면 즉시 생성
  if (window.CalendarContext?.calendars?.length) {
    createCalendar();
    return;
  }

  // 아직이면 ready 이벤트 대기
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
