// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/calendar/modal.task.view.js'
(() => {
  'use strict';

  window.AppCore = window.AppCore || {};
  const $ = id => document.getElementById(id);

  /* ===============================
   * Modal helpers
   * =============================== */
  function show(m) { m?.classList.remove('is-hidden'); }
  function hide(m) { m?.classList.add('is-hidden'); }

  /* ===============================
   * Utils
   * =============================== */

  // 🔹 캘린더 ID → 이름
  function resolveCalendarName(calendarId) {
    if (!calendarId) return '';
  
    const list = CalendarStore?.getCalendars?.() || [];
    if (!Array.isArray(list)) return calendarId;
  
    const found = list.find(x =>
      String(x.calendar_id) === String(calendarId)
    );
  
    return found?.name || calendarId;
  }
  

  // 🔹 한국식 날짜 출력
  function formatKoreanDateTime(d) {
    if (!(d instanceof Date) || isNaN(d)) return '';

    return (
      d.getFullYear() + '. ' +
      (d.getMonth() + 1) + '. ' +
      d.getDate() + '. ' +
      String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0')
    );
  }








  
  /* ===============================
   * Task Extractor (🔥 핵심 수정)
   * =============================== */
  function extractTask(event) {

    const ep = event.extendedProps || {};
  
    let raw = ep.raw || {};
  
    // 🔥 현재 구조 대응
    if (raw.extendedProps?.raw) {
      raw = raw.extendedProps.raw;
    }
  
    const dueDate =
      event.start instanceof Date && !isNaN(event.start)
        ? event.start
        : null;

    const isDateOnly =
    raw?.raw?.DUE?.params?.VALUE === 'DATE' ||
    raw?.DUE?.params?.VALUE === 'DATE';

    return {
      uid:
      event.extendedProps?.uid ||
      String(event.id).replace(/^task_/, ''),
  
      title:
        event.title?.replace(/^📝\s*/, '') ||
        raw.summary ||
        raw.title ||
        '작업',
  
      calendarName: resolveCalendarName(ep.calendar_id),
  
      due: dueDate,

      isDateOnly, 
      
      description: raw.description || '',
  
      alarms: Array.isArray(raw.alarms)
        ? raw.alarms
        : [],
  
      completed:
        ep.completed === true ||
        raw.STATUS?.value === 'COMPLETED' ||
        raw.status === 'COMPLETED' ||
        raw.PERCENT_COMPLETE?.value == 100 ||
        raw.percent_complete == 100
    };
  }
  
  
  function formatAlarmText(alarm) {
    if (!alarm) return '';
  
    const trigger =
      alarm.TRIGGER?.value ||
      alarm.trigger ||
      '';
  
    // ✅ 이벤트 시
    if (trigger === 'PT0S' || trigger === '-PT0S') {
      return '이벤트 시';
    }
  
    const m = String(trigger).match(/-PT(\d+)([HM])/);
    if (!m) return '알림';
  
    const value = Number(m[1]);
    const unit  = m[2];
  
    if (unit === 'M') return `시작 ${value}분 전`;
    if (unit === 'H') return `시작 ${value}시간 전`;
  
    return '알림';
  }
  
  


  function normalizeEditAlarms(rawAlarms, dueDate) {
    if (!Array.isArray(rawAlarms) || !(dueDate instanceof Date)) return [];
  
    return rawAlarms.map(a => {
      const trigger = a.trigger || a.TRIGGER?.value || '';
      const m = String(trigger).match(/-PT(\d+)([MH])/);
      if (!m) return null;
  
      let minutes =
        m[2] === 'H'
          ? Number(m[1]) * 60
          : Number(m[1]);
  
      // 🔥 기준 날짜에서 실제 알람 시각 계산
      const alarmDate = new Date(dueDate.getTime() - minutes * 60000);
      const hour = alarmDate.getHours();
  
      // 🔥 Edit preset key 생성 규칙
      if (minutes === 0) {
        return `d0_${String(hour).padStart(2, '0')}00`;
      }
  
      if (minutes % 1440 === 0) {
        const days = minutes / 1440;
        return `d${days}_${String(hour).padStart(2, '0')}00`;
      }
  
      if (minutes % 10080 === 0) {
        const weeks = minutes / 10080;
        return `w${weeks}_${String(hour).padStart(2, '0')}00`;
      }
  
      return null;
    }).filter(Boolean);
  }
  







  /* ===============================
   * Open Task View Modal
   * =============================== */
  AppCore.openTaskViewModal = function (event) {

    const cal = window.__calendar;
  
    const fresh = cal?.getEventById(
      'task_' + String(event.id || event.extendedProps?.uid).replace(/^task_/, '')
    );
  
    if (fresh) {
      event = fresh;   // 🔥 최신 객체로 교체
    }


    console.log('🧪 FULL EVENT OBJECT:', event);
    console.log('🧪 EXTENDED PROPS:', event.extendedProps);
    console.log('🧪 RAW:', event.extendedProps?.raw);


    if (!event) return;

    const modal = $('modal-task-view');
    if (!modal) return;

    // 다른 모달 닫기
    hide($('modal-event-view'));
    hide($('modal-event-edit'));
    hide($('modal-task-edit'));
    hide($('modal-quick'));

    const task = extractTask(event);

    // ===============================
    // View 렌더링
    // ===============================
    $('task-view-title').textContent = task.title;
    $('task-view-list').textContent  = task.calendarName;

    if (task.due) {

      // 🔥 DATE 타입 여부 직접 판별
      const raw =
        event.extendedProps?.raw?.raw ||
        event.extendedProps?.raw ||
        {};
    
      const isDateOnly =
        raw?.DUE?.params?.VALUE === 'DATE';
    
      if (isDateOnly) {
        // 날짜만 출력
        $('task-view-due').textContent =
          task.due.getFullYear() + '. ' +
          (task.due.getMonth() + 1) + '. ' +
          task.due.getDate();
      } else {
        // 시간 포함 출력
        $('task-view-due').textContent =
          formatKoreanDateTime(task.due);
      }
    
    } else {
      $('task-view-due').textContent = '';
    }

    $('task-view-desc').textContent =
      task.description || '내용 없음';

    // ===============================
    // 🔔 알람 렌더링
    // ===============================
    const alarmRow = $('task-view-alarm-row');
    const alarmBox = $('task-view-alarms');

    if (alarmRow && alarmBox && Array.isArray(task.alarms) && task.alarms.length) {
      alarmBox.innerHTML = task.alarms
        .map(a => `🔔 ${formatAlarmText(a)}`)
        .join('<br>');

      alarmRow.style.display = '';
    } else if (alarmRow) {
      alarmRow.style.display = 'none';
    }

  // ===============================
  // 완료 토글
  // ===============================
  const toggleBtn = $('task-view-toggle');

  function renderStatus() {
    if (task.completed) {
      toggleBtn.textContent = '완료되지 않음으로 표시'; // 완료 시 텍스트
      toggleBtn.classList.add('is-completed'); // 완료 표시 클래스 추가
    } else {
      toggleBtn.textContent = '완료됨으로 표시'; // 미완료 시 텍스트
      toggleBtn.classList.remove('is-completed'); // 완료 표시 클래스 제거
    }
  }
  
  renderStatus();
  
  toggleBtn.onclick = async () => {
    try {
        const newStatus = !task.completed; // 상태 반전

        // 상태 변경을 위한 API 호출
        await CalendarAPI.toggleTaskComplete(
            task.uid.replace(/^task_/, ''), 
            event.extendedProps.calendar_id, 
            newStatus
        );

        // 서버에서 상태 변경 후 task.completed 값 갱신
        task.completed = newStatus;

        // 상태 변경 후 FullCalendar 이벤트 갱신
        const cal = window.__calendar;
        const fcEvent = cal?.getEventById('task_' + task.uid);

        if (fcEvent) {
            fcEvent.setExtendedProp('completed', newStatus);  // 상태 갱신

            // 상태에 따른 클래스 변경
            fcEvent.setProp(
                'classNames',
                newStatus ? ['fc-task-event', 'is-completed'] : ['fc-task-event']
            );
        }

        // 상태 렌더링 (UI 업데이트)
        renderStatus();  // UI 상태 갱신

        hide(modal);

    } catch (e) {
        alert('작업 상태 변경 실패');
        console.error(e);
    }
};
    

    // ===============================
    // 수정
    // ===============================
    $('task-view-edit').onclick = () => {

      // 🔥 Synology 원본 존재 확인
      const synologyExists =
        event.extendedProps?.synology_exists ?? 1;
    
      if (!synologyExists) {
    
        AppCore.notify(
          'warn',
          'Synology에서 삭제된 작업입니다. 수정할 수 없습니다.'
        );
    
        return;
      }
    
      hide(modal);
    
      AppCore.openTaskEditModal?.({
        __fromView: true,
    
        uid: task.uid,
        title: task.title,
        due: task.due,
    
        allDay: event.allDay === true,
    
        description: task.description,
        listId: event.extendedProps?.calendar_id || '',
    
        alarms: task.alarms || []
      });
    
    };
        

    // ===============================
    // 태스크 일반 삭제
    // ===============================
    $('task-view-delete').onclick = async () => {

      if (!confirm('이 작업을 삭제하시겠습니까?')) return;
    
      try {
    
        const uid = task.uid;   // 순수 uid
    
        // 🔥 서버 삭제 (Synology + Sync)
        await CalendarAPI.deleteTask(uid);
    
        // 🔥 최신 객체 조회
        const cal = window.__calendar;
        const fcEvent = cal?.getEventById('task_' + uid);
    
        if (fcEvent) {
          fcEvent.remove();
        }
    
        hide(modal);
    
      } catch (e) {
        console.error(e);
        alert('작업 삭제 실패');
      }
    
    };
        
    // ===============================
    // 태스크 완전 삭제
    // ===============================
    const hardBtn = $('task-view-hard-delete');
    if (hardBtn) {
      hardBtn.onclick = async () => {
        if (!confirm('이 작업을 완전히 삭제하시겠습니까? (복구 불가)')) return;
    
        try {
          await CalendarAPI.hardDeleteTask(task.uid);
    
          const cal = window.__calendar;
          const fcEvent = cal?.getEventById('task_' + task.uid);
          fcEvent?.remove();
    
          hide(modal);
    
        } catch (e) {
          alert('완전 삭제 실패');
          console.error(e);
        }
      };
    }


    show(modal);

    // 🔥 방금 열린 상태 표시 (클릭 닫기 방지)
    modal.__justOpened = true;
    setTimeout(() => {
      modal.__justOpened = false;
    }, 0);

  };



//뷰캘린더에서 쏘는걸 받음//
  document.addEventListener('calendar:task:view', (e) => {
    const ev = e.detail;
    if (!ev) return;
  
    AppCore.openTaskViewModal(ev);
  });




  function closeTaskViewModal() {
    const modal = document.getElementById('modal-task-view');
    if (!modal) return;
  
    modal.classList.add('is-hidden');
  }

  document.addEventListener('click', e => {
    const modal = document.getElementById('modal-task-view');
    if (!modal || modal.classList.contains('is-hidden')) return;
  
    // 🔥 방금 열린 클릭은 무시
    if (modal.__justOpened) return;
  
    // 닫기 버튼
    if (e.target.closest('#modal-task-view [data-close="modal"]')) {
      closeTaskViewModal();
      return;
    }
  
    // 카드 밖 클릭
    if (!e.target.closest('.shint-modal__card')) {
      closeTaskViewModal();
    }
  });
  

  
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
  
    const modal = document.getElementById('modal-task-view');
    if (!modal || modal.classList.contains('is-hidden')) return;
  
    e.preventDefault();
    e.stopPropagation();
    closeTaskViewModal();
  });
  



  
  
})();
