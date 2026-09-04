import { formatDateDisplay } from '/public/assets/js/common/format.js';

export function createCoverSystemInfo({ containerSelector, resolveMetaColumn }) {
    function fieldLabel(key, fallback) {
        return resolveMetaColumn(key, fallback)?.displayName || fallback;
    }

    function render(data = {}) {
        const container = document.querySelector(containerSelector);
        if (!container) return;
        const fields = [
            { key: 'id', fallback: 'ID' },
            { key: 'sort_no', fallback: '순번' },
            { key: 'created_at', fallback: '생성일시', type: 'datetime' },
            { key: 'created_by_name', fallback: '생성자' },
            { key: 'updated_at', fallback: '수정일시', type: 'datetime' },
            { key: 'updated_by_name', fallback: '수정자' },
            { key: 'deleted_at', fallback: '삭제일시', type: 'datetime' },
            { key: 'deleted_by_name', fallback: '삭제자' },
        ];
        container.replaceChildren(...fields.map((field) => {
            const item = document.createElement('div');
            item.className = 'cover-system-info-field';
            const label = document.createElement('span');
            label.className = 'cover-system-info-label';
            label.textContent = fieldLabel(field.key, field.fallback);
            const value = document.createElement('span');
            value.className = 'cover-system-info-value';
            const raw = field.type === 'datetime'
                ? formatDateDisplay(data[field.key])
                : data[field.key];
            value.textContent = raw === null || raw === undefined ? '' : String(raw);
            item.append(label, value);
            return item;
        }));
    }

    return { render };
}
