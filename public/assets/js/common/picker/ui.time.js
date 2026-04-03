// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.time.js '
export function renderTime({ picker, container, options = {} }) {
  const opt = {
    minuteStep: 5,
    ...options
  };

  container.innerHTML = `
    <div class="picker-time">
      <div class="picker-time-toggle">
        <input type="checkbox" class="time-toggle" />
        <span class="time-toggle-text">시간 추가</span>
      </div>

      <div class="time-fields">
        <select class="hour"></select>
        <span class="colon">:</span>
        <select class="minute"></select>
        <select class="meridiem">
          <option value="AM">AM</option>
          <option value="PM">PM</option>
        </select>
      </div>
    </div>
  `;

  const toggle = container.querySelector('.time-toggle');
  const hourSel = container.querySelector('.hour');
  const minSel  = container.querySelector('.minute');
  const merSel  = container.querySelector('.meridiem');
  const fields  = container.querySelector('.time-fields');

  for (let i = 1; i <= 12; i++) hourSel.add(new Option(String(i), String(i)));
  for (let i = 0; i < 60; i += opt.minuteStep) {
    const v = String(i).padStart(2, '0');
    minSel.add(new Option(v, String(i)));
  }

  function setEnabled(enabled) {
    fields.classList.toggle('is-disabled', !enabled);
    hourSel.disabled = !enabled;
    minSel.disabled  = !enabled;
    merSel.disabled  = !enabled;
  }

  // ✅ 체크박스 → state
  toggle.addEventListener('change', () => {
    if (toggle.checked) {
      picker.setTime({ hour: 9, minute: 0, meridiem: 'AM' });
      picker.toggleTime(true);
    } else {
      picker.toggleTime(false);
      picker.setTime({ hour: null, minute: null, meridiem: null });
    }
  });

  function sync() {
    const s = picker.getState();

    // 🔥 state → UI (항상 강제)
    toggle.checked = !!s.timeEnabled;
    setEnabled(!!s.timeEnabled);

    if (!s.timeEnabled) {
      hourSel.value = '';
      minSel.value  = '';
      merSel.value  = 'AM';
      return;
    }

    hourSel.value = String(s.hour ?? 9);
    minSel.value  = String(s.minute ?? 0);
    merSel.value  = String(s.meridiem ?? 'AM');
  }

  function pushTime() {
    if (!toggle.checked) return;

    picker.setTime({
      hour: parseInt(hourSel.value, 10),
      minute: parseInt(minSel.value, 10),
      meridiem: merSel.value
    });
  }

  hourSel.addEventListener('change', pushTime);
  minSel.addEventListener('change', pushTime);
  merSel.addEventListener('change', pushTime);

  picker.subscribe(sync);
  sync();
}
