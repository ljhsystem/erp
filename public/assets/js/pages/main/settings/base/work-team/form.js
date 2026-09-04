export function formatDateInputValue(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 4) return digits;
    if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
}

export function normalizeDateInputValue(value, notify) {
    const formatted = formatDateInputValue(value);
    const match = formatted.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return formatted;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        notify?.('warning', '올바른 날짜를 입력하세요.');
        return '';
    }

    return formatted;
}

export function formatDate(date) {
    if (!date) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function normalizeStartEnd(type) {
    const start = document.querySelector('input[name="dateStart"]');
    const end = document.querySelector('input[name="dateEnd"]');
    if (!start || !end || !start.value || !end.value) return;
    if (type === 'start' && start.value > end.value) end.value = start.value;
    if (type === 'end' && end.value < start.value) start.value = end.value;
}

export function fillFormValue(form, name, value) {
    if (value == null || String(value).trim() === '') return;
    const field = form.elements.namedItem(name);
    if (!field || field instanceof RadioNodeList) return;
    field.value = value;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
