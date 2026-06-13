import { formatDate, formatDateInputValue, normalizeDateInputValue } from './form.js';

export function createCompanyModalModule({ AdminPicker, notify }) {
    let todayPicker = null;

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;
        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({ type: 'today', container });
        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;
            input.value = formatDate(date);
            todayPicker.close();
        });
        return todayPicker;
    }

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach(input => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';
            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value, notify);
            });
        });

        document.addEventListener('click', function (event) {
            const icon = event.target.closest('.date-icon');
            if (!icon) return;
            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date') : null;
            if (!input) return;
            event.preventDefault();
            event.stopPropagation();
            openDatePickerForInput(input);
        }, true);
    }

    function openDatePickerForInput(input) {
        const picker = initAdminDatePicker();
        if (!picker) return;
        picker.__target = input;
        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }
        input.value = normalizeDateInputValue(input.value, notify);
        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const date = new Date(input.value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }
        picker.open({ anchor: input });
    }

    return {
        initAdminDatePicker,
        bindAdminDateInputs,
    };
}
