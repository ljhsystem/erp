export function createProjectFormModule({
    AdminPicker,
    initCodeSelectControls,
    formatAmount,
    unformatAmount,
}) {
    let todayPicker = null;
    let globalBound = false;
    let projectModalControlsPromise = null;

    const notify = (type, message) => {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error') alert(message);
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function disconnectProjectClientTypeCodeSelect(scope = document) {
        const root = scope || document;
        const select = root.querySelector?.('#modal_client_type') || document.getElementById('modal_client_type');
        if (!select) return;
        select.removeAttribute('data-code-group');
        if (window.jQuery?.fn?.select2) {
            const $select = window.jQuery(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
        }
    }

    function initExternal() {
        window.KakaoAddress?.bind?.();
    }

    function initProjectValueInputs(api) {
        document.querySelectorAll('.js-project-value-input[data-field][list]').forEach((input) => {
            if (input.dataset.projectValueBound === '1') return;
            input.dataset.projectValueBound = '1';
            let timer = null;
            const scheduleLoad = () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => {
                    refreshProjectValueOptions(api, input);
                }, 180);
            };
            input.addEventListener('input', scheduleLoad);
            input.addEventListener('focus', () => refreshProjectValueOptions(api, input));
        });
    }

    async function refreshProjectValueOptions(api, input) {
        const field = input.dataset.field || '';
        const listId = input.getAttribute('list');
        const list = listId ? document.getElementById(listId) : null;
        if (!field || !list) return;

        const params = new URLSearchParams({ field, q: input.value || '', limit: '20' });
        try {
            const response = await fetch(`${api.PROJECT_VALUE_SEARCH}?${params.toString()}`, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            const rows = Array.isArray(json?.data) ? json.data : [];
            list.innerHTML = '';
            rows.forEach((row) => {
                const value = String(row?.value ?? row?.text ?? row?.id ?? '').trim();
                if (!value) return;
                const option = document.createElement('option');
                option.value = value;
                list.appendChild(option);
            });
        } catch (error) {
            console.error('[project] value autocomplete failed', error);
        }
    }

    function bindGlobalEvents() {
        if (globalBound) return;
        globalBound = true;
        document.addEventListener('input', (event) => {
            const type = event.target?.dataset?.format;
            if (type === 'amount') {
                event.target.value = formatAmount(unformatAmount(event.target.value));
            }
        });
    }

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;
        const container = document.getElementById('today-picker');
        if (!container) return null;
        todayPicker = AdminPicker.create({ type: 'today', container });
        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;
            input.value = formatDate(date);
            normalizeStartEnd(input.name === 'dateStart' ? 'start' : 'end');
            todayPicker.close();
        });
        return todayPicker;
    }

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach((input) => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';
            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value);
            });
        });
    }

    function bindDateIconPicker() {
        if (document.__projectDateIconPickerBound) return;
        document.__projectDateIconPickerBound = true;
        document.addEventListener('click', (event) => {
            const icon = event.target.closest('.date-icon');
            if (!icon) return;
            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date, input[name="dateStart"], input[name="dateEnd"]') : null;
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
        picker.clearDate?.();
        input.value = normalizeDateInputValue(input.value);
        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const date = new Date(input.value);
            if (!Number.isNaN(date.getTime())) picker.setDate(date);
        }
        picker.open({ anchor: input });
    }

    function formatDateInputValue(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
    }

    function normalizeDateInputValue(value) {
        const formatted = formatDateInputValue(value);
        const match = formatted.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return formatted;
        const [year, month, day] = match.slice(1).map(Number);
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            notify('warning', '올바른 날짜를 입력하세요.');
            return '';
        }
        return formatted;
    }

    function formatDate(date) {
        if (!date) return '';
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('input[name="dateStart"]');
        const end = document.querySelector('input[name="dateEnd"]');
        if (!start || !end || !start.value || !end.value) return;
        if (type === 'start' && start.value > end.value) end.value = start.value;
        if (type === 'end' && end.value < start.value) start.value = end.value;
    }

    function preloadProjectModalControls() {
        if (!projectModalControlsPromise) {
            projectModalControlsPromise = prepareProjectModalControls()
                .catch((error) => {
                    console.error('[project] modal controls preload failed', error);
                    projectModalControlsPromise = null;
                });
        }
        return projectModalControlsPromise;
    }

    async function prepareProjectModalControls() {
        const modalEl = document.getElementById('projectModal');
        disconnectProjectClientTypeCodeSelect(modalEl);
        await initCodeSelectControls(modalEl);
    }

    function validateProjectForm(formData) {
        const projectName = String(formData.get('project_name') || '').trim();
        const contractDate = String(formData.get('contract_date') || '').trim();
        const startDate = String(formData.get('start_date') || '').trim();
        const completionDate = String(formData.get('completion_date') || '').trim();
        const amount = String(formData.get('initial_contract_amount') || '').trim();

        if (!projectName) return '프로젝트명은 필수입니다.';

        const datePattern = /^\d{4}-\d{2}-\d{2}$/;
        if (contractDate && !datePattern.test(contractDate)) return '계약일자는 YYYY-MM-DD 형식이어야 합니다.';
        if (startDate && !datePattern.test(startDate)) return '착공일자는 YYYY-MM-DD 형식이어야 합니다.';
        if (completionDate && !datePattern.test(completionDate)) return '준공일자는 YYYY-MM-DD 형식이어야 합니다.';
        if (startDate && completionDate && startDate > completionDate) return '준공일자는 착공일자보다 빠를 수 없습니다.';
        if (amount && !/^-?\d+$/.test(amount)) return '최초 계약금액은 숫자만 입력할 수 있습니다.';

        return '';
    }

    return {
        notify,
        escapeHtml,
        disconnectProjectClientTypeCodeSelect,
        initExternal,
        initProjectValueInputs,
        bindGlobalEvents,
        initAdminDatePicker,
        bindAdminDateInputs,
        bindDateIconPicker,
        normalizeDateInputValue,
        preloadProjectModalControls,
        prepareProjectModalControls,
        validateProjectForm,
    };
}
