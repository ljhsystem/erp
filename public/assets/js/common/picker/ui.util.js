// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.util.js '
export function bindOutsideClick(targetEl, onClose, ignoreEls = []) {
  function handler(e) {
    if (!targetEl) return;

    // picker 내부 클릭
    if (targetEl.contains(e.target)) return;

    // input, icon 등 예외 요소 클릭
    for (const el of ignoreEls) {
      if (el && (el === e.target || el.contains?.(e.target))) {
        return;
      }
    }

    onClose?.();
    document.removeEventListener('pointerdown', handler, true);
  }

  document.addEventListener('pointerdown', handler, true);

  return () => document.removeEventListener('pointerdown', handler, true);
}