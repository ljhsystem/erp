(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppDate) {
        window.AppDate = AppCore.AppDate;
        return;
    }

    function normalizeDateValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';

        const digits = raw.replace(/\D/g, '').slice(0, 8);
        if (digits.length !== 8) {
            return raw;
        }

        const year = Number(digits.slice(0, 4));
        const month = Number(digits.slice(4, 6));
        const day = Number(digits.slice(6, 8));
        const date = new Date(year, month - 1, day);

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            return raw;
        }

        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`;
    }

    function formatDate(date, delimiter = '-') {
        const target = date instanceof Date ? date : new Date(date);
        if (Number.isNaN(target.getTime())) return '';

        const year = target.getFullYear();
        const month = String(target.getMonth() + 1).padStart(2, '0');
        const day = String(target.getDate()).padStart(2, '0');
        return [year, month, day].join(delimiter);
    }

    function addDays(baseDate, days) {
        const date = new Date(baseDate);
        date.setDate(date.getDate() + Number(days || 0));
        return date;
    }

    function periodRange(type, now = new Date()) {
        const today = new Date(now);
        let start = new Date(today);
        let end = new Date(today);

        switch (type) {
            case 'today':
                break;
            case 'yesterday':
                start = addDays(today, -1);
                end = new Date(start);
                break;
            case '3days':
                start = addDays(today, -3);
                break;
            case '7days':
                start = addDays(today, -7);
                break;
            case '15days':
                start = addDays(today, -15);
                break;
            case '1month':
                start = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                break;
            case '3months':
                start = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());
                break;
            case '6months':
                start = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());
                break;
            case 'thisYear':
                start = new Date(today.getFullYear(), 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                break;
            case 'lastYear':
                start = new Date(today.getFullYear() - 1, 0, 1);
                end = new Date(today.getFullYear() - 1, 11, 31);
                break;
            case '3years':
                start = new Date(today.getFullYear() - 2, 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                break;
            case '5years':
                start = new Date(today.getFullYear() - 4, 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                break;
            case '10years':
                start = new Date(today.getFullYear() - 9, 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                break;
            default:
                return null;
        }

        return { start, end };
    }

    const AppDate = {
        normalizeDateValue,
        formatDate,
        addDays,
        periodRange
    };

    AppCore.AppDate = AppDate;
    window.AppDate = AppDate;
})();
