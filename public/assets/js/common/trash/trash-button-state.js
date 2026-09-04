export function findTrashButtons(modal = null) {
    const selectors = ['.dt-trash-btn', '[class*="trash-btn"]'];
    if (modal?.id) {
        selectors.push(`[data-bs-target="#${CSS.escape(modal.id)}"]`, `[data-trash-modal="#${CSS.escape(modal.id)}"]`);
    }
    if (modal?.dataset?.type) selectors.push(`.${CSS.escape(modal.dataset.type)}-trash-btn`);

    const matches = Array.from(document.querySelectorAll(selectors.join(',')));
    const labeled = Array.from(document.querySelectorAll('.dt-button, button, a')).filter(node => (
        node instanceof HTMLElement && String(node.textContent || '').trim() === '휴지통'
    ));
    return Array.from(new Set([...matches, ...labeled])).filter(node => node instanceof HTMLElement);
}

export function setTrashButtonState(button, hasTrash, count = 0) {
    if (!button) return;
    button.classList.toggle('btn-trash-has-data', hasTrash);
    button.setAttribute('aria-label', hasTrash ? `휴지통 ${count}건` : '휴지통');
    button.title = hasTrash ? `휴지통 ${count}건` : '휴지통';
}

export function markTrashButtonsHasData(count = 1) {
    findTrashButtons().forEach(button => setTrashButtonState(button, true, count));
}
