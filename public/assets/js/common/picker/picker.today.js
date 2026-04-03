// 📄 /public/assets/js/common/picker/picker.today.js
import { createPickerCore } from './ui.state.js';
import { renderCalendar } from './ui.base.js';

export function createTodayPicker({ container }) {

  // 🔥 이미 생성돼 있으면 재사용
  if (container.__pickerInstance) {
    return container.__pickerInstance;
  }

  const picker = createPickerCore({ time: false });

  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';

  const cal = document.createElement('div');
  cal.className = 'admin-picker__calendar';

  panel.appendChild(cal);
  container.appendChild(panel);

  renderCalendar({
    picker,
    container: cal,
    options: {
      showFooter: true,
      hideClear: true
    }
  });

  // 🔥 반드시 캐싱
  container.__pickerInstance = picker;

  return picker;
}
