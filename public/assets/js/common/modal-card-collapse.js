export function bindModalCardCollapses(modalElement, options = {}) {
    if (!modalElement || !window.bootstrap?.Collapse) {
        return { reset: () => {} };
    }

    const selector = options.selector || '[data-ui-modal-card-collapse]';
    const entries = [];

    modalElement.querySelectorAll(selector).forEach((button) => {
        if (button.dataset.modalCardCollapseBound === '1') return;

        const targetSelector = String(button.getAttribute('data-bs-target') || '').trim();
        if (!targetSelector.startsWith('#')) return;

        const target = modalElement.querySelector(targetSelector);
        if (!target) return;

        const collapse = window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });
        const syncState = () => {
            const expanded = target.classList.contains('show');
            button.classList.toggle('collapsed', !expanded);
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            window.requestAnimationFrame(() => {
                window.bootstrap.Modal.getInstance(modalElement)?.handleUpdate?.();
            });
        };

        button.removeAttribute('data-bs-toggle');
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (target.classList.contains('show')) {
                collapse.hide();
                return;
            }
            collapse.show();
        });
        target.addEventListener('shown.bs.collapse', syncState);
        target.addEventListener('hidden.bs.collapse', syncState);
        syncState();
        button.dataset.modalCardCollapseBound = '1';
        entries.push({ collapse, syncState, target });
    });

    const reset = () => {
        entries.forEach(({ collapse, syncState, target }) => {
            if (target.classList.contains('show')) {
                collapse.hide();
                return;
            }
            syncState();
        });
    };

    if (options.resetOnShow === true) {
        modalElement.addEventListener('show.bs.modal', reset);
    }

    return { reset };
}
