// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.mini.js '
import { createPickerCore } from './ui.state.js';
import { renderCalendar } from './ui.base.js';

export function createMiniPicker({ container }) {
  const picker = createPickerCore({ time: false });

  // 🔥 panel 생성
  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';

  // 🔥 calendar 영역
  const cal = document.createElement('div');
  cal.className = 'admin-picker__calendar';

  panel.appendChild(cal);
  container.appendChild(panel);

  renderCalendar({
    picker,
    container: cal,
    options: {
      showFooter: true
    }
  });

  return picker;
}
