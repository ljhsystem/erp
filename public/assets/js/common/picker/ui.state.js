// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.state.js'
export function createPickerCore(options = {}) {
  const state = {
    date: options.date ?? null,
    timeEnabled: options.timeEnabled ?? false,
    hour: options.hour ?? null,
    minute: options.minute ?? null,
    meridiem: options.meridiem ?? null,
    open: false,
  
    viewYear: (options.date instanceof Date)
      ? options.date.getFullYear()
      : (new Date()).getFullYear(),
  
    viewMonth: (options.date instanceof Date)
      ? options.date.getMonth()
      : (new Date()).getMonth(),
  };

  const listeners = [];

  function emit() {
    const s = getState();
    const final = getFinalDate();
    listeners.forEach(fn => fn(s, final));
  }

  function getState() {
    return { ...state };
  }

  function sameYmd(a, b) {
    if (!(a instanceof Date) || !(b instanceof Date)) return false;
    return a.getFullYear() === b.getFullYear() &&
           a.getMonth() === b.getMonth() &&
           a.getDate() === b.getDate();
  }

  function setDate(date) {
    // ✅ 같은 날짜면 emit 금지 (루프/과부하 핵심 차단)
    if (sameYmd(state.date, date)) return;

    state.date = date instanceof Date ? date : null;

    // ✅ 날짜 선택하면 view도 그 달로 맞춤(UX)
    if (state.date) {
      state.viewYear = state.date.getFullYear();
      state.viewMonth = state.date.getMonth();
    }

    emit();
  }

  function clearDate() {
    state.date = null;
    state.timeEnabled = false;
    state.hour = null;
    state.minute = null;
    state.meridiem = null;
    emit();
  }

  function setTime({ hour, minute, meridiem }) {
    state.hour = hour;
    state.minute = minute;
    state.meridiem = meridiem;
    emit();
  }

  function toggleTime(enabled) {
    state.timeEnabled = enabled;
    emit();
  }

  function getFinalDate() {
    if (!state.date) return null;
  
    const d = new Date(state.date);
  
    // 🔥 시간 체크가 꺼져있으면 날짜만 반환
    if (!state.timeEnabled) {
      return d;
    }
  
    // 🔥 시간값이 완전히 설정된 경우에만 적용
    if (
      state.hour == null ||
      state.minute == null ||
      state.meridiem == null
    ) {
      return d;
    }
  
    let h = state.hour;
    let m = state.minute;
    let mer = state.meridiem;
  
    if (mer === 'PM' && h < 12) h += 12;
    if (mer === 'AM' && h === 12) h = 0;
  
    d.setHours(h, m, 0, 0);
  
    return d;
  }

  function subscribe(fn) {
    if (typeof fn !== 'function') return () => {};
    listeners.push(fn);
    return () => {
      const i = listeners.indexOf(fn);
      if (i >= 0) listeners.splice(i, 1);
    };
  }

  // ✅ 추가: 월 이동 (같으면 emit 금지)
  function setView(y, m) {
    const yy = parseInt(y, 10);
    const mm = parseInt(m, 10);
    if (!Number.isFinite(yy) || !Number.isFinite(mm)) return;

    if (state.viewYear === yy && state.viewMonth === mm) return; // 🔥 과부하 차단

    state.viewYear = yy;
    state.viewMonth = mm;
    state.date = null;
    emit();
  }

  return {
    state,
    getState,
    setDate,
    clearDate,
    setTime,
    toggleTime,
    getFinalDate,
    subscribe,
    setView,           // ✅ 반드시 노출
  };
}
