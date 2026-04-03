// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/ui.util.js '
export function bindOutsideClick(targetEl, onClose) {
  function handler(e) {
    if (!targetEl) return;

    // ✅ 타겟 내부 클릭은 무시
    if (targetEl.contains(e.target)) return;

    onClose?.();
    document.removeEventListener('pointerdown', handler, true);
  }

  document.addEventListener('pointerdown', handler, true);
  return () => document.removeEventListener('pointerdown', handler, true);
}
