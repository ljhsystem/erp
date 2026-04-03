// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.base.js '
import { createPickerCore } from './ui.state.js';
import { renderCalendar } from './ui.base.js';

export function createDatePicker({ container }) {
  const picker = createPickerCore({ time: false });

  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel';

  container.appendChild(panel);

  renderCalendar({
    picker,
    container: panel,
    options: { showFooter: false }
  });

  return picker;
}
