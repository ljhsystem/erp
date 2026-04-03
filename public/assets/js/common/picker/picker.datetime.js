// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.datetime.js '
import { createPickerCore } from './ui.state.js';
import { renderCalendar } from './ui.base.js';
import { renderTime } from './ui.time.js';

export function createDateTimePicker({ container }) {

  // 🔥🔥🔥 핵심: 이미 생성됐으면 재사용
  const existing = container.querySelector('.admin-picker__panel');
  if (existing) {
    return container.__pickerInstance;
  }

  const picker = createPickerCore({
    time: true,
    timeEnabled: false   // 기본 체크 해제
  });

  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';

  const cal = document.createElement('div');
  cal.className = 'admin-picker__calendar';

  const time = document.createElement('div');
  time.className = 'admin-picker__time';

  panel.append(cal, time);
  container.appendChild(panel);

  renderCalendar({
    picker,
    container: cal,
    options: { showFooter: false }
  });

  renderTime({
    picker,
    container: time,
    step: 15
  });

  // 🔥 인스턴스 캐싱
  container.__pickerInstance = picker;

  return picker;
}
