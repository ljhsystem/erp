// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/admin_picker.js'
import { createDatePicker } from './picker.base.js';
import { createMiniPicker } from './picker.mini.js';
import { createDateTimePicker } from './picker.datetime.js';
import { createTodayPicker } from './picker.today.js';
import { createTimeListPicker } from './picker.time.list.js';
import { bindOutsideClick } from './ui.util.js';
import { createAccountPicker } from './picker.account.js';
import { PickerSelect2 } from './picker.select2.js';

function withPopup(picker, container) {
  let unbind = null;

  picker.open = ({ anchor, offset = 8 } = {}) => {
    if (!anchor) return;

    container.classList.remove('is-hidden');
    container.style.position = 'fixed';
    container.style.zIndex = 9999;
    container.style.opacity = '0';
    container.style.pointerEvents = 'none';

    const r = anchor.getBoundingClientRect();

    container.style.setProperty(
      '--picker-anchor-width',
      `${Math.round(r.width)}px`
    );

    requestAnimationFrame(() => {
      const p = container.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      let left = r.left;
      let top  = r.bottom + offset;

      if (top + p.height > vh - 8) {
        top = r.top - p.height - offset;
      }

      if (top < 8) {
        top = Math.min(r.bottom + offset, vh - p.height - 8);
      }

      if (left + p.width > vw - 8) {
        left = vw - p.width - 8;
      }
      if (left < 8) {
        left = 8;
      }

      container.style.left = `${Math.round(left)}px`;
      container.style.top  = `${Math.round(top)}px`;

      container.style.opacity = '1';
      container.style.pointerEvents = '';
    });

    unbind?.();
    unbind = bindOutsideClick(container, picker.close);

    /* =========================
      🔥 핵심 추가 (ESC 등록)
    ========================= */
    const closeHandler = () => picker.close();

    picker._escHandler = closeHandler;

    if(window.ESCStack){
      window.ESCStack.push(closeHandler);
    }    
  };

  picker.close = () => {
    container.classList.add('is-hidden');
    container.style.opacity = '';
    container.style.pointerEvents = '';
    unbind?.();
    unbind = null;
    /* =========================
      🔥 핵심 추가 (ESC 제거)
    ========================= */
    if(picker._escHandler && window.ESCStack){
      window.ESCStack.remove(picker._escHandler);
      picker._escHandler = null;
    }
  };

  return picker;
}

/* =====================================================
 * Picker Factory
 * ===================================================== */
function create({ type, container, options = {} }) {
  if (container.__pickerInstance) {
    return container.__pickerInstance;
  }

  let picker;

  switch (type) {
    case 'today':
      picker = createTodayPicker({ container });
      break;

    case 'datetime':
      picker = createDateTimePicker({ container });
      break;

    case 'mini':
      picker = createMiniPicker({ container });
      break;

    case 'time-list':
      picker = createTimeListPicker({ container, ...options });
      break;

    case 'account':
      picker = createAccountPicker({ container });
      break;

    default:
      picker = createDatePicker({ container });
  }

  picker = withPopup(picker, container);
  container.__pickerInstance = picker;

  return picker;
}

/* =====================================================
 * Select2 Wrapper
 * ===================================================== */
function select2(target, options = {}) {
  return PickerSelect2.create(target, options);
}

function select2Ajax(target, options = {}) {
  return PickerSelect2.createAjax(target, options);
}

function destroySelect2(target) {
  return PickerSelect2.destroy(target);
}

function setSelect2Value(target, value, trigger = true) {
  return PickerSelect2.setValue(target, value, trigger);
}

function clearSelect2(target, trigger = true) {
  return PickerSelect2.clearValue(target, trigger);
}

function reloadSelect2(target, items = [], valueKey = 'id', textKey = 'text', selectedValue = null) {
  return PickerSelect2.reloadOptions(target, items, valueKey, textKey, selectedValue);
}

const AdminPicker = {
  create,
  bindOutsideClick,

  select2,
  select2Ajax,
  destroySelect2,
  setSelect2Value,
  clearSelect2,
  reloadSelect2
};

window.AdminPicker = AdminPicker;
export { AdminPicker };