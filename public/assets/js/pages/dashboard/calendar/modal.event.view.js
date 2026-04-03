// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/calendar/modal.event.view.js'
(() => {
  'use strict';

  // =====================================================
  // Namespace
  // =====================================================
  window.AppCore = window.AppCore || {};

  // =====================================================
  // DOM
  // =====================================================
  const modal = document.getElementById('modal-view');
  if (!modal) return;

  const titleEl     = modal.querySelector('#view-title');
  const dotEl = modal.querySelector('#view-event-dot');

  const calendarEl  = modal.querySelector('#view-calendar-name');
  const ownerEl     = modal.querySelector('#view-owner');
  const periodEl    = modal.querySelector('#view-period');
  const locationEl  = modal.querySelector('#view-location');
  const descEl      = modal.querySelector('#view-description');
  const guestsEl = modal.querySelector('#view-guests');
  const alarmsEl = modal.querySelector('#view-alarms');
  const attachmentsEl = modal.querySelector('#view-attachments');

  const btnEdit   = modal.querySelector('#btn-edit');
  const btnDelete = modal.querySelector('#btn-delete');
  const btnHardDelete = modal.querySelector('#btn-hard-delete');
  const btnMore   = modal.querySelector('#btn-more');
  const moreMenu  = modal.querySelector('#view-more-menu');




  let currentEvent = null;

  // =====================================================
  // Utils
  // =====================================================
  const pad = n => String(n).padStart(2, '0');

  function formatDate(d, allDay = false) {
    if (!(d instanceof Date)) return '';
    const y = d.getFullYear();
    const m = pad(d.getMonth() + 1);
    const day = pad(d.getDate());

    if (allDay) return `${y}-${m}-${day}`;

    const h = pad(d.getHours());
    const min = pad(d.getMinutes());
    return `${y}-${m}-${day} ${h}:${min}`;
  }

  function getExValue(ex, key) {
    return (
      ex?.[key] ??
      ex?.raw?.[key] ??
      ex?.raw?.raw?.[key] ??
      null
    );
  }
  

  function openModal() {
    modal.classList.remove('is-hidden');
    document.body.classList.add('is-modal-open');
  }

  function closeModal() {
    modal.classList.add('is-hidden');
    document.body.classList.remove('is-modal-open');
    moreMenu?.classList.add('is-hidden');
    currentEvent = null;
  }

  function toggleVRow(targetEl, show) {
    if (!targetEl) return;
  
    const row =
      targetEl.classList.contains('shint-vrow')
        ? targetEl
        : targetEl.closest('.shint-vrow');
  
    if (!row) return;
  
    row.style.display = show ? '' : 'none';
  }
  
  function extractRRuleFromEvent(ev) {
    if (!ev) return null;
  
    // 1️⃣ 직접 rrule
    if (ev.rrule) return ev.rrule;
  
    // 2️⃣ extendedProps
    if (ev.extendedProps?.rrule) return ev.extendedProps.rrule;
  
    // 3️⃣ FullCalendar recurringDef (🔥 핵심)
    if (ev._def?.recurringDef?.typeData) {
      return ev._def.recurringDef.typeData;
    }
  
    return null;
  }
  

  // Edit 모달과 동일한 반복 포맷 사용
  function formatRRuleSafe(rrule) {
    if (!rrule?.rruleSet) return '';
  
    const ruleString = rrule.rruleSet.toString();
  
    const match = ruleString.match(/RRULE:(.+)/);
    if (!match) return '';
  
    const rule = match[1];
  
    const map = {};
    rule.split(';').forEach(p => {
      const [k, v] = p.split('=');
      if (k && v) map[k] = v;
    });
  
    const DAY = { MO:'월',TU:'화',WE:'수',TH:'목',FR:'금',SA:'토',SU:'일' };
    const POS = {
      '1':'첫번째',
      '2':'두번째',
      '3':'세번째',
      '4':'네번째',
      '-1':'마지막'
    };
  
    const interval = parseInt(map.INTERVAL || 1, 10);
  
    // 종료 조건
    let endText = '';
    if (map.UNTIL) {
      endText = `, ${map.UNTIL.slice(0,4)}-${map.UNTIL.slice(4,6)}-${map.UNTIL.slice(6,8)}까지`;
    }
    else if (map.COUNT) {
      endText = `, ${map.COUNT}회까지`;
    }
  
    // ===============================
    // DAILY
    // ===============================
    if (map.FREQ === 'DAILY') {
      return interval > 1
        ? `${interval}일마다${endText}`
        : `매일${endText}`;
    }
  
    // ===============================
    // WEEKLY
    // ===============================
    if (map.FREQ === 'WEEKLY') {
  
      let dayText = '';
  
      if (map.BYDAY) {
        const days = map.BYDAY
          .split(',')
          .map(d => DAY[d])
          .join(', ');
        dayText = ` (${days}요일)`;
      }
  
      return interval > 1
        ? `${interval}주마다${dayText}${endText}`
        : `매주${dayText}${endText}`;
    }
  
    // ===============================
    // MONTHLY - 날짜
    // ===============================
    if (map.FREQ === 'MONTHLY' && map.BYMONTHDAY) {
      return interval > 1
        ? `${interval}개월마다 ${map.BYMONTHDAY}일${endText}`
        : `매월 ${map.BYMONTHDAY}일${endText}`;
    }
  
    // ===============================
    // MONTHLY - N번째 요일
    // ===============================
    if (map.FREQ === 'MONTHLY' && map.BYSETPOS && map.BYDAY) {
      return interval > 1
        ? `${interval}개월마다 ${POS[map.BYSETPOS]} ${DAY[map.BYDAY]}요일${endText}`
        : `매월 ${POS[map.BYSETPOS]} ${DAY[map.BYDAY]}요일${endText}`;
    }
  
    if (map.FREQ === 'MONTHLY') {
      return interval > 1
        ? `${interval}개월마다${endText}`
        : `매월${endText}`;
    }
  
    // ===============================
    // YEARLY
    // ===============================
    if (map.FREQ === 'YEARLY') {
      return interval > 1
        ? `${interval}년마다${endText}`
        : `매년${endText}`;
    }
  
    return '';
  }
  
  

  // =====================================================
  // Core: Event View Open
  // =====================================================
  AppCore.openEventViewModal = function (ev) {
    if (!ev) return;

    currentEvent = ev;
    const ex = ev.extendedProps || {};
    const alarms =
    getExValue(ex, 'alarms') ||
    getExValue(ex, 'VALARM') ||
    [];
  


    // 제목
    titleEl.textContent = ev.title || '(제목 없음)';


    // 🔵 이벤트 컬러 점 (ERP 기준 단일 진실)
    const adminColor =
      ev.extendedProps?.admin_event_color || null;

    if (dotEl && adminColor) {
      dotEl.style.backgroundColor = adminColor;
      dotEl.style.display = 'inline-block';
    } else if (dotEl) {
      dotEl.style.display = 'none';
    }



    // 캘린더
    calendarEl.textContent =
    CalendarStore.getCalendarName(ex.calendar_id)
    || ex.calendar_id
    || '(캘린더 미매핑)';  


    // 담당자 (Synology 연결 계정 기준)
    const syno =
      window.CalendarContext?.externalAccounts?.synology;

    ownerEl.textContent =
      syno?.external_login_id
        ? `Synology · ${syno.external_login_id}`
        : '담당자';

    // 기간
    const start = ev.start;
    let end     = ev.end;
    const allDay = ev.allDay === true;

    if (start && end) {

      let displayEnd = end;

      // 🔥 allDay는 exclusive end 보정
      if (allDay) {
        displayEnd = new Date(end);
        displayEnd.setDate(displayEnd.getDate() - 1);
      }

      periodEl.textContent =
        `${formatDate(start, allDay)} ~ ${formatDate(displayEnd, allDay)}`;

    } else if (start) {
      periodEl.textContent = formatDate(start, allDay);
    } else {
      periodEl.textContent = '';
    }



    // ===== 반복 이벤트 표시 =====
    const repeatEl = modal.querySelector('#view-repeat');

    const rrule = extractRRuleFromEvent(ev);
    console.log('[RRULE FINAL]', rrule);
    
    const repeatText = formatRRuleSafe(rrule, ev);
    
    if (repeatEl && repeatText) {
      repeatEl.textContent = repeatText;
      repeatEl.classList.remove('is-hidden');
    } else if (repeatEl) {
      repeatEl.classList.add('is-hidden');
    }
    


    // 위치
    const location = getExValue(ex, 'location');
    locationEl.textContent = location || '';
    toggleVRow(document.getElementById('view-location'), !!location);
    
    

    // 설명
    const desc = getExValue(ex, 'description');
    descEl.textContent = desc || '';
    toggleVRow(document.getElementById('view-description-row'), !!desc);
    
    

    //게스트
    const attendees = ex.attendees || [];
    // ===== 게스트 =====
    guestsEl.replaceChildren();

    if (!attendees.length) {
      toggleVRow(document.getElementById('view-guests-row'), false);
    } else {
      attendees.forEach(g => {
        const row = document.createElement('div');
        row.className = 'shint-guest-row';
        row.textContent = g.cn || g.value || '참석자';
        guestsEl.appendChild(row);
      });
      toggleVRow(document.getElementById('view-guests-row'), true);
    }








    // ===== 알림 =====
    alarmsEl.replaceChildren();

    if (!alarms.length) {
      toggleVRow(document.getElementById('view-alarms-row'), false);
    } else {
      alarms.forEach(a => {
        const row = document.createElement('div');
        row.className = 'shint-alarm-row';
        row.textContent = formatAlarmTrigger(a.trigger, ev.start, ev.allDay);
        alarmsEl.appendChild(row);
      });
      toggleVRow(document.getElementById('view-alarms-row'), true);
    }








    // ===============================
    // 📎 첨부파일 (현재 이벤트 기준)
    // ===============================
    attachmentsEl.replaceChildren();

     const raw =
       ev.extendedProps?.raw?.raw ||
       ev.extendedProps?.raw ||
       ev.extendedProps ||
       {};

    const att = raw.ATTACH;

    if (!att || (Array.isArray(att) && att.length === 0)) {
      toggleVRow(document.getElementById('view-attachments-row'), false);
    } else {
      const list = Array.isArray(att) ? att : [att];

      list.forEach(att => {
        const url = att.value;
        const fileName = decodeURIComponent(
          att.params?.['X-SYNO-REL-URI']?.split('/').pop()
          || url.split('/').pop().split('?')[0]
        );

        const row = document.createElement('div');
        row.className = 'shint-file-row';

        const ico = document.createElement('span');
        ico.className = 'shint-file-ico';
        ico.textContent = getFileIconByName(fileName);

        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = fileName;

        row.append(ico, link);
        attachmentsEl.appendChild(row);
      });

      toggleVRow(document.getElementById('view-attachments-row'), true);
    }
    openModal();
  };




  
  
  

  function getFileIconByName(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
  
    switch (ext) {
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
      case 'webp':
        return '🖼️';   // 이미지
  
      case 'pdf':
        return '📄';   // PDF
  
      case 'xls':
      case 'xlsx':
      case 'csv':
        return '📊';   // 엑셀
  
      case 'doc':
      case 'docx':
        return '📝';   // 워드
  
      case 'ppt':
      case 'pptx':
        return '📽️';   // PPT
  
      case 'zip':
      case 'rar':
      case '7z':
        return '🗜️';   // 압축
  
      default:
        return '📎';   // 기타
    }
  }
  

  function formatRRule(rrule) {
    if (!rrule) return '';
  
    // 🔐 최종 안전장치
    if (typeof rrule !== 'string') {
      rrule = String(rrule);
    }
  
    if (rrule.includes('FREQ=DAILY')) {
      return '매일';
    }
  
    if (rrule.includes('FREQ=WEEKLY')) {
      const m = rrule.match(/BYDAY=([A-Z,]+)/);
      if (!m) return '매주';
  
      const dayMap = {
        MO:'월', TU:'화', WE:'수',
        TH:'목', FR:'금', SA:'토', SU:'일'
      };
  
      return `매주 ${m[1].split(',').map(d => dayMap[d]).join(', ')}`;
    }
  
    if (rrule.includes('FREQ=MONTHLY')) {
      return '매월';
    }
  
    if (rrule.includes('FREQ=YEARLY')) {
      return '매년';
    }
  
    return '';
  }
  
  
  




  const CREATE_ALARM_OPTIONS = [
    { value: 'PT0S',   label: '이벤트 시' },
    { value: '-PT5M',  label: '시작 5분 전' },
    { value: '-PT10M', label: '시작 10분 전' },
    { value: '-PT30M', label: '시작 30분 전' },
    { value: '-PT1H',  label: '시작 1시간 전' },
    { value: '-PT2H',  label: '시작 2시간 전' },
    { value: '-PT6H',  label: '시작 6시간 전' },
    { value: '-PT12H', label: '시작 12시간 전' },
    { value: '-P1D',   label: '시작 1일 전' },
    { value: '-P2D',   label: '시작 2일 전' },
    { value: '-P3D',   label: '시작 3일 전' },
    { value: '-P5D',   label: '시작 5일 전' },
    { value: '-P7D',   label: '시작 7일 전' },
    { value: '-P14D',  label: '시작 14일 전' }
  ];
  

  function diffDaysByDate(a, b) {
    const d1 = new Date(a.getFullYear(), a.getMonth(), a.getDate());
    const d2 = new Date(b.getFullYear(), b.getMonth(), b.getDate());
    return Math.round((d1 - d2) / (24 * 60 * 60 * 1000));
  }

  function formatAlarmTrigger(trigger, eventStart, allDay = false) {
    if (!trigger || !eventStart) return '알림';
  
    const start = eventStart instanceof Date ? eventStart : new Date(eventStart);
  
    // ✅ TRIGGER 부호 처리
    const isNeg = String(trigger).startsWith('-');
    const t = isNeg ? String(trigger).slice(1) : String(trigger);
  
    // ISO 8601 Duration 파싱: PnDTnHnM (대충 이 정도면 충분)
    const m = t.match(/P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?)?/);
    const days  = Number(m?.[1] || 0);
    const hours = Number(m?.[2] || 0);
    const mins  = Number(m?.[3] || 0);
  
    const offsetMs =
      days  * 24 * 60 * 60 * 1000 +
      hours * 60 * 60 * 1000 +
      mins  * 60 * 1000;
  
    // ✅ 음수면 start - offset, 양수면 start + offset
    const alarmTime = new Date(start.getTime() + (isNeg ? -offsetMs : offsetMs));
  
    const hh = String(alarmTime.getHours()).padStart(2, '0');
    const mm = String(alarmTime.getMinutes()).padStart(2, '0');
  
    // ✅ Synology 스타일: all-day + 양수 PT6H/PT7H => "06:00 당일"
    if (allDay && !isNeg && (hours || mins)) {
      return `${hh}:${mm} 당일`;
    }
  
    // 같은 날짜면 "당일"
    const sameDay =
      start.getFullYear() === alarmTime.getFullYear() &&
      start.getMonth() === alarmTime.getMonth() &&
      start.getDate() === alarmTime.getDate();
  
    if (sameDay) return `${hh}:${mm} 당일`;

    const diffDays = diffDaysByDate(start, alarmTime);
  
    if (diffDays >= 7) {
      return `시작 ${Math.floor(diffDays / 7)}주 전 ${hh}:${mm}`;
    }
    return `시작 ${diffDays}일 전 ${hh}:${mm}`;
  }
  
  
  function buildEventText(ev) {
    const ex = ev.extendedProps || {};
    const lines = [];
    const v = (label, value) =>
      lines.push(`${label}: ${value || '없음'}`);
  
    lines.push(`제목: ${ev.title || '없음'}`);
    lines.push('');
  
    v('캘린더', ex.calendar_name);
    v('담당자', document.getElementById('view-owner')?.textContent);
  
    v(
      '기간',
      ev.start && ev.end
        ? `${formatDate(ev.start, ev.allDay)} ~ ${formatDate(ev.end, ev.allDay)}`
        : '없음'
    );
  
    // ✅ 여기 추가
    // 반복
    const repeatText = formatRRuleSafe(ev);
    v('반복', formatRRule(rrule));

  
    v('위치', ex.location);
    v('설명', ex.description || ex.desc);
  
    const guests = (ex.attendees || [])
      .map(g => g.cn || g.value)
      .join(', ');
    v('게스트', guests);
  
    const att = ex.raw?.ATTACH;
    let files = '없음';
    if (att) {
      const list = Array.isArray(att) ? att : [att];
      files = list.map(a =>
        decodeURIComponent(
          a.params?.['X-SYNO-REL-URI']?.split('/').pop()
          || a.value.split('/').pop()
        )
      ).join(', ');
    }
    v('첨부파일', files);

    const alarms = (ex.alarms || [])
      .map(a => formatAlarmTrigger(a.trigger, ev.start, ev.allDay))
      .join(', ');
    v('알림', alarms);

    return lines.join('\n');
  }
  

  async function copyText() {
    if (!currentEvent) return;
  
    const text = buildEventText(currentEvent);
    await navigator.clipboard.writeText(text);
  
    alert('텍스트가 복사되었습니다.');
  }

  
  async function copyImage() {
    const card = modal.querySelector('.shint-modal__card--view');
  
    // ❌ 더보기 메뉴 숨기기
    moreMenu?.classList.add('is-hidden');
  
    const canvas = await html2canvas(card, {
      backgroundColor: '#ffffff', // 🔥 회색 제거
      scale: 2
    });
  
    canvas.toBlob(async blob => {
      const item = new ClipboardItem({ 'image/png': blob });
      await navigator.clipboard.write([item]);
      alert('이미지가 복사되었습니다.');
    });
  }
  







  

  // =====================================================
  // Actions
  // =====================================================
  btnEdit?.addEventListener('click', () => {

    if (!currentEvent) return;
  
    const ev = currentEvent;
  
    // 🔥 Synology 원본 존재 확인
    const synologyExists =
      ev.extendedProps?.synology_exists ?? 1;
  
    if (!synologyExists) {
  
      AppCore.notify(
        "warn",
        "Synology에서 삭제된 일정입니다. 수정할 수 없습니다."
      );
  
      return;
    }
  
    // 🔥 반복 추출
    const rrule = extractRRuleFromEvent(ev);
  
    closeModal();
  
    window.AppCore?.openEventEditModal?.({
      __mode: 'edit',
      id: ev.id,
      title: ev.title,
      start: ev.start,
      end: ev.end,
      allDay: ev.allDay,
      rrule: rrule,
      extendedProps: ev.extendedProps
    });
  
  });
  
/* ===============================
 * 🔥 일반 삭제
 * =============================== */
btnDelete?.addEventListener('click', async () => {

  if (!currentEvent) return;
  if (!confirm('이 일정을 삭제하시겠습니까?')) return;

  try {

    const ev = currentEvent;   // ✅ 이미 최신 객체
    const uid =
      ev.extendedProps?.raw?.uid ||
      ev.id;

    const isRecurring =
      !!ev.extendedProps?.rrule ||
      !!ev._def?.recurringDef;

    let payload = { uid };

    // 🔁 반복 이벤트 처리
    if (isRecurring) {

      const recurrenceId = ev.start
        ? ev.start.toISOString().slice(0,10).replaceAll('-', '')
        : null;

      const scope = await askRepeatScope();

      payload.scope = scope;
      payload.recurrence_id = recurrenceId;
    }

    // 🔥 서버 삭제
    await CalendarAPI.deleteEvent(payload);

    // 🔥 화면 즉시 제거
    ev.remove();

    closeModal();

  } catch (e) {
    console.error(e);
    alert('삭제 실패');
  }

});
  


/* ===============================
 * 🔥 완전 삭제
 * =============================== */
$('task-view-hard-delete').onclick = async () => {

  if (!confirm('이 작업을 완전히 삭제하시겠습니까?\n(복구 불가)')) return;

  try {

    await AppCore.API?.hardDeleteTask?.({
      uid: task.uid
    });

    // FullCalendar 제거
    const cal = window.__calendar;
    const fcEvent = cal?.getEventById('task_' + task.uid);
    fcEvent?.remove();

    hide(modal);

  } catch (e) {
    alert('완전 삭제 실패');
    console.error(e);
  }
};






  

  btnHardDelete?.addEventListener('click', async () => {
    if (!currentEvent) return;
  
    const ok = confirm(
      '⚠️ 이 이벤트를 완전히 삭제합니다.\n' +
      'Synology와 ERP DB에서 모두 제거되며 복구할 수 없습니다.\n\n' +
      '정말 삭제하시겠습니까?'
    );
    if (!ok) return;
  
    try {
      await CalendarAPI.hardDeleteEvent({
        uid: currentEvent.extendedProps?.raw?.uid || currentEvent.id,
        href: currentEvent.extendedProps?.href
      });     
  
      currentEvent.remove(); // FullCalendar 즉시 제거
      closeModal();
    } catch (e) {
      alert('완전 삭제 실패');
      console.error(e);
    }
  });
  











  moreMenu?.addEventListener('mousedown', (e) => {
    e.stopPropagation();
  });
  

  btnMore?.addEventListener('click', (e) => {
    e.stopPropagation();
    moreMenu?.classList.toggle('is-hidden');
  });

  moreMenu?.addEventListener('click', (e) => {
    const action = e.target.dataset.action;
    if (!action) return;
  
    if (action === 'copy-text') {
      copyText();
    }
  
    if (action === 'copy-image') {
      copyImage();
    }
  
    moreMenu.classList.add('is-hidden');
  });
  
  
  
  

  // =====================================================
  // Close handlers
  // =====================================================
  modal.querySelectorAll('[data-close="modal"]').forEach(btn => {
    btn.addEventListener('click', closeModal);
  });

  modal.addEventListener('mousedown', (e) => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });

  // =====================================================
  // Calendar → View Event Bridge
  // =====================================================
  document.addEventListener('calendar:event:view', (e) => {
    const ev = e.detail;
    if (!ev) return;
    AppCore.openEventViewModal(ev);
  });


// ===============================
// 🔥 더보기 바깥 클릭 시 닫기
// ===============================
document.addEventListener('mousedown', (e) => {
  if (!moreMenu || moreMenu.classList.contains('is-hidden')) return;

  // more 버튼이나 메뉴 내부 클릭은 무시
  if (
    e.target.closest('#btn-more') ||
    e.target.closest('#view-more-menu')
  ) {
    return;
  }

  moreMenu.classList.add('is-hidden');
});








  

})();
