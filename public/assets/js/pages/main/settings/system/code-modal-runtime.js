import { actorDisplay } from '/public/assets/js/common/actor.js';
import { formatDateDisplay } from '/public/assets/js/common/format.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';

export function initCodeModalRuntime(modalElement) {
    if (!modalElement) return { reset: () => {} };
    if (modalElement.__codeModalCardCollapses) return modalElement.__codeModalCardCollapses;
    modalElement.__codeModalCardCollapses = bindModalCardCollapses(modalElement, { resetOnShow: true });
    return modalElement.__codeModalCardCollapses;
}

export function renderCodeSystemInfo(data = {}) {
    const container = document.getElementById('codeSystemInfoFields');
    if (!container) return;
    const fields = [
        ['id', 'ID'],
        ['sort_no', '순번'],
        ['created_at', '생성일시', 'datetime'],
        ['created_by', '생성자', 'actor'],
        ['updated_at', '수정일시', 'datetime'],
        ['updated_by', '수정자', 'actor'],
    ];
    container.replaceChildren(...fields.map(([key, labelText, type]) => {
        const item = document.createElement('div');
        item.className = 'code-system-info-field';
        const label = document.createElement('span');
        label.className = 'code-system-info-label';
        label.textContent = labelText;
        const value = document.createElement('span');
        value.className = 'code-system-info-value';
        const raw = type === 'actor'
            ? actorDisplay(data, key)
            : (type === 'datetime' ? formatDateDisplay(data[key]) : data[key]);
        value.textContent = raw == null || raw === '(알 수 없음)' ? '' : String(raw);
        item.append(label, value);
        return item;
    }));
}
