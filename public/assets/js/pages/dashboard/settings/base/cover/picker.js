export function createCoverYearMonthPicker({ AdminPicker, getDateStartInput, getDateEndInput }) {
    let picker = null;
    let bound = false;

    function ensureContainer() {
        let container = document.getElementById('year-month-picker');
        if (container) return container;

        let root = document.querySelector('.picker-root');
        if (!root) {
            root = document.createElement('div');
            root.className = 'picker-root';
            document.body.appendChild(root);
        }

        container = document.createElement('div');
        container.id = 'year-month-picker';
        container.className = 'picker is-hidden';
        root.appendChild(container);
        return container;
    }

    function init() {
        if (picker) return picker;
        const container = ensureContainer();
        picker = AdminPicker.create({
            type: 'year-month',
            container,
            options: {
                yearMin: new Date().getFullYear() - 50,
                yearMax: new Date().getFullYear() + 5,
            },
        });
        picker.subscribe(() => {
            const input = picker.__target;
            const selected = picker.getState?.().date;
            if (!input || !(selected instanceof Date) || Number.isNaN(selected.getTime())) return;
            input.value = format(selected);
            normalizeRange(input.name === 'dateStart' ? 'start' : 'end');
            picker.close();
        });
        picker.onClear = () => {
            if (picker.__target) picker.__target.value = '';
            picker.close();
        };
        return picker;
    }

    function parse(value) {
        const raw = String(value || '').trim();
        const ym = raw.match(/^(\d{4})-(\d{1,2})(?:-\d{1,2})?$/);
        if (ym) {
            const month = parseInt(ym[2], 10) - 1;
            if (month >= 0 && month <= 11) return new Date(parseInt(ym[1], 10), month, 1);
        }
        const year = raw.match(/^(\d{4})$/);
        return year ? new Date(parseInt(year[1], 10), 0, 1) : null;
    }

    function format(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    }

    function normalizeRange(type) {
        const start = getDateStartInput();
        const end = getDateEndInput();
        if (!start?.value || !end?.value) return;
        const startDate = parse(start.value);
        const endDate = parse(end.value);
        if (!startDate || !endDate) return;
        if (type === 'start' && startDate > endDate) end.value = start.value;
        if (type === 'end' && endDate < startDate) start.value = end.value;
    }

    function open(input) {
        try {
            if (!input) return;
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('inputmode', 'none');
            input.readOnly = true;
            const instance = init();
            instance.__target = input;
            const selected = parse(input.value);
            if (selected) {
                if (typeof instance.setYearMonth === 'function') instance.setYearMonth(selected);
                else instance.setView(selected.getFullYear(), selected.getMonth());
            } else instance.clearDate?.();
            instance.open({ anchor: input });
        } catch (error) {
            console.error('[cover] year-month picker open failed:', error);
        }
    }

    function bind() {
        if (bound) return;
        bound = true;
        const selector = 'input.year-input, input[name="dateStart"], input[name="dateEnd"]';
        document.querySelectorAll(selector).forEach((input) => {
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('inputmode', 'none');
            input.readOnly = true;
        });
        const resolveInput = (event) => {
            const direct = event.target.closest(selector);
            if (direct) return direct;
            return event.target.closest('.date-icon')?.closest('.date-input')?.querySelector('input') || null;
        };
        for (const eventName of ['pointerdown', 'click']) {
            document.addEventListener(eventName, (event) => {
                const input = resolveInput(event);
                if (!input) return;
                event.preventDefault();
                event.stopPropagation();
                open(input);
            }, true);
        }
        document.addEventListener('focusin', (event) => {
            const input = event.target.closest(selector);
            if (input) open(input);
        });
    }

    return { bind };
}
