import {
    normalizeDayOfMonthInputValue,
    parseDayOfMonthValue,
} from '/public/assets/js/common/picker/picker.dayofmonth.js';

export function bindEmploymentPaymentDayPicker({ form, AdminPicker }) {
    const input = form?.elements?.payment_day;
    const container = document.getElementById('employment-contract-payment-day-picker');
    if (!input || !container || input.dataset.paymentDayPickerBound === '1') return null;
    input.dataset.paymentDayPickerBound = '1';
    const picker = AdminPicker.create({ type: 'day-of-month', container });
    const validate = () => {
        const day = parseDayOfMonthValue(input.value);
        input.setCustomValidity(input.value !== '' && day === null ? '급여지급일은 1일부터 31일 사이로 입력해 주세요.' : '');
        if (day !== null) {
            input.value = String(day);
            picker.setDay(day);
        }
        return day;
    };
    const open = () => {
        if (input.disabled) return;
        const day = validate();
        if (day !== null) picker.setDay(day);
        picker.open({ anchor: input });
    };
    picker.subscribe(day => {
        input.value = String(day);
        input.setCustomValidity('');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        picker.close();
    });
    input.addEventListener('input', () => {
        input.value = normalizeDayOfMonthInputValue(input.value);
        input.setCustomValidity('');
    });
    input.addEventListener('blur', validate);
    input.addEventListener('click', open);
    input.addEventListener('keydown', event => {
        if (!['ArrowDown', 'F4'].includes(event.key)) return;
        event.preventDefault();
        open();
        requestAnimationFrame(() => picker.focusActive());
    });
    input.closest('.modal')?.addEventListener('hidden.bs.modal', () => picker.close());
    return picker;
}
