// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.time.list.js '
import { createPickerCore } from './ui.state.js';
import { renderTimeList } from './ui.time.list.js';

export function createTimeListPicker({
  container,
  step = 15,
  rows = 7
}) {
  if (!container) {
    throw new Error('[TimeListPicker] container is required');
  }

  // ✅ 타입 구분용 클래스 추가
  container.classList.add('is-time-list');

  const picker = createPickerCore({ time: true });

  const panel = document.createElement('div');
  panel.className = 'admin-picker__panel time-list-panel';
  container.appendChild(panel);

  renderTimeList({
    picker,
    container: panel,
    step,
    rows
  });

  return picker;
}
