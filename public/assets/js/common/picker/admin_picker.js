import { createDatePicker } from './picker.base.js';
import { createMiniPicker } from './picker.mini.js';
import { createDateTimePicker } from './picker.datetime.js';
import { createTodayPicker } from './picker.today.js';
import { createYearMonthPicker } from './picker.yearmonth.js';
import { createTimeListPicker } from './picker.time.list.js';
import { bindOutsideClick } from './ui.util.js';
import { createAccountPicker } from './picker.account.js';
import { PickerSelect2 } from './picker.select2.js';

let activePopupPicker = null;

function withPopup(picker, container) {
  let unbindOutside = null;
  let removeEscListener = null;
  let escStackHandler = null;

  picker.open = ({ anchor, offset = 8 } = {}) => {
    if (!anchor) return;

    if (activePopupPicker && activePopupPicker !== picker) {
      activePopupPicker.close();
    }

    container.classList.add('picker');
    container.classList.remove('is-hidden');
    container.style.position = 'fixed';
    container.style.zIndex = '10020';
    container.style.opacity = '0';
    container.style.pointerEvents = 'none';
    container.style.background = '#ffffff';
    container.style.border = '1px solid rgba(15, 23, 42, 0.12)';
    container.style.borderRadius = '12px';
    container.style.boxShadow = '0 18px 48px rgba(15, 23, 42, 0.18)';
    container.style.overflow = 'visible';

    const anchorRect = anchor.getBoundingClientRect();

    container.style.setProperty(
      '--picker-anchor-width',
      `${Math.round(anchorRect.width)}px`
    );

    requestAnimationFrame(() => {
      const popupRect = container.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      let left = anchorRect.left;
      let top = anchorRect.bottom + offset;

      if (top + popupRect.height > vh - 8) {
        top = anchorRect.top - popupRect.height - offset;
      }

      if (top < 8) {
        top = Math.min(anchorRect.bottom + offset, vh - popupRect.height - 8);
      }

      if (left + popupRect.width > vw - 8) {
        left = vw - popupRect.width - 8;
      }

      if (left < 8) {
        left = 8;
      }

      container.style.left = `${Math.round(left)}px`;
      container.style.top = `${Math.round(top)}px`;
      container.style.opacity = '1';
      container.style.pointerEvents = '';
    });

    const panel = container.querySelector('.admin-picker__panel');
    if (panel) {
      panel.style.background = '#ffffff';
      panel.style.opacity = '1';
      panel.style.border = '1px solid rgba(15, 23, 42, 0.08)';
      panel.style.borderRadius = '12px';
      panel.style.boxShadow = '0 18px 48px rgba(15, 23, 42, 0.12)';
      panel.style.position = 'relative';
      panel.style.zIndex = '10021';
      panel.style.overflow = 'visible';
    }

    unbindOutside?.();
    window.setTimeout(() => {
      unbindOutside?.();
      unbindOutside = bindOutsideClick(container, () => picker.close(), [anchor]);
    }, 0);

    removeEscListener?.();
    const escHandler = (event) => {
      if (event.key !== 'Escape') return;
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      picker.close();
    };

    if (escStackHandler && window.ESCStack) {
      window.ESCStack.remove(escStackHandler);
    }
    escStackHandler = () => picker.close();
    if (window.ESCStack) {
      window.ESCStack.push(escStackHandler);
    }

    window.addEventListener('keydown', escHandler, true);
    removeEscListener = () => window.removeEventListener('keydown', escHandler, true);
    activePopupPicker = picker;
  };

  picker.close = () => {
    container.classList.add('is-hidden');
    container.style.opacity = '';
    container.style.pointerEvents = '';
    container.style.background = '';
    container.style.border = '';
    container.style.borderRadius = '';
    container.style.boxShadow = '';
    container.style.overflow = '';

    const panel = container.querySelector('.admin-picker__panel');
    if (panel) {
      panel.style.background = '';
      panel.style.opacity = '';
      panel.style.border = '';
      panel.style.borderRadius = '';
      panel.style.boxShadow = '';
      panel.style.position = '';
      panel.style.zIndex = '';
      panel.style.overflow = '';
    }

    unbindOutside?.();
    unbindOutside = null;

    removeEscListener?.();
    removeEscListener = null;

    if (escStackHandler && window.ESCStack) {
      window.ESCStack.remove(escStackHandler);
    }
    escStackHandler = null;

    if (activePopupPicker === picker) {
      activePopupPicker = null;
    }
  };

  return picker;
}

function create({ type, container, options = {} }) {
  if (container.__pickerInstance) {
    return container.__pickerInstance;
  }

  let picker;

  switch (type) {
    case 'today':
      picker = createTodayPicker({ container });
      break;

    case 'year-month':
    case 'yearmonth':
    case 'month':
      picker = createYearMonthPicker({ container, ...options });
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
  reloadSelect2,
};

window.AdminPicker = AdminPicker;
export { AdminPicker };
