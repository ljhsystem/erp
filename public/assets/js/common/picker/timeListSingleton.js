// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/timeListSingleton.js '
import { AdminPicker } from './admin_picker.js';

let picker = null;

export function getTimeListPicker() {
  if (picker) return picker;

  const container = document.getElementById('time-list-picker');
  if (!container) return null;

  picker = AdminPicker.create({
    type: 'time-list',
    container,
    options: {
      step: 15,
      rows: 7
    }
  });

  // ✅ 공용 스크롤 API 연결 (🔥 이 줄 추가)
  attachTimeListScrollApi(picker);

  // 🔥 선택 시 input 반영
  picker.subscribe(state => {

    const input = picker.__target;
    if (!input) return;
  
    if (typeof state.hour === 'number' &&
        typeof state.minute === 'number') {
  
      const hh = String(state.hour).padStart(2, '0');
      const mm = String(state.minute).padStart(2, '0');
  
      if (typeof picker.onSelect === 'function') {
        picker.onSelect({
          hour: state.hour,
          minute: state.minute
        });
        return;
      }
  
      input.value = `${hh}:${mm}`;
      closeTimeListPicker();
    }
  });

  picker.onSelect = null;
  return picker;
}

export function closeTimeListPicker() {
  const el = document.getElementById('time-list-picker');
  if (!el) return;
  el.classList.add('is-hidden');
}

/* =========================================================
   ✅ 공용: "진짜 스크롤러" 찾고 값 위치로 스크롤
========================================================= */
function attachTimeListScrollApi(picker) {
  if (!picker || picker.__scrollApiAttached) return;
  picker.__scrollApiAttached = true;

  const getScroller = (root) => {
    if (!root) return null;

    // root 포함 + 하위 전부 후보로
    const nodes = [root, ...root.querySelectorAll('*')];

    let best = null;
    let bestOverflow = 0;

    for (const el of nodes) {
      // scroll 가능한지
      const sh = el.scrollHeight || 0;
      const ch = el.clientHeight || 0;
      const overflow = sh - ch;

      if (overflow <= 1) continue;

      // overflow-y 가 visible이어도 스크롤 되는 케이스가 있어 "실제 overflow량" 기준으로 선택
      if (overflow > bestOverflow) {
        bestOverflow = overflow;
        best = el;
      }
    }

    return best || root;
  };

  const findTarget = (root, value) => {
    const v = String(value || '').trim();
    if (!v) return null;

    // 텍스트 기반으로 찾기 (구조 변경에 가장 강함)
    const candidates = root.querySelectorAll('button, [role="option"], li, div, span');
    return Array.from(candidates).find(el => String(el.textContent).trim() === v) || null;
  };

  const scrollToValue = (value) => {
    const root = picker.container || document.getElementById('time-list-picker');
    if (!root) return false;
  
    const target = findTarget(root, value);
    if (!target) return false;
  
    const scroller = getScroller(root);
    if (!scroller) return false;
  
    // 🔥 target이 맨 위에 오도록
    scroller.scrollTop = target.offsetTop;
  
    return true;
  };

  const scrollWhenReady = (value, tries = 20) => {
    if (scrollToValue(value)) return;
    if (tries <= 0) return;
    requestAnimationFrame(() => scrollWhenReady(value, tries - 1));
  };

  // ✅ 외부에서 호출할 공용 API
  picker.scrollToValue = (value) => scrollWhenReady(value);
}
