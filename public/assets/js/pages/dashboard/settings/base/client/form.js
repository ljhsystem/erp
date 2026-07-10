export const CLIENT_OPTIONAL_CODE_SELECT_IDS = ['modal_client_type', 'modal_client_category', 'modal_trade_category', 'modal_tax_type', 'modal_payment_term'];

export function createClientFormModule({
    AdminPicker,
    NumberFormat,
    initCodeSelectControls,
    checkBusinessStatus,
    API,
}) {
    const {
        onlyNumber = (value) => String(value ?? '').replace(/\D/g, ''),
        formatBizNumber,
        formatCorpNumber,
        formatMobile,
        formatPhone,
        formatAccountNumber,
        loadBankAccountFormatRegistry,
        unformatAccountNumber,
    } = NumberFormat;

    let todayPicker = null;
    let rrnVisible = false;
    let clientModalControlsPromise = null;
    let defaultAccountRows = [];

    const notify = (type, message) => {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error') {
            alert(message);
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function applyClientOptionalCodeSelects(scope = document) {
        const root = scope || document;
        CLIENT_OPTIONAL_CODE_SELECT_IDS.forEach((id) => {
            const select = root.querySelector?.(`#${id}`) || document.getElementById(id);
            if (!select) return;

            const currentValue = select.value;
            let emptyOption = select.querySelector('option[value=""]');
            if (!emptyOption) {
                emptyOption = document.createElement('option');
                emptyOption.value = '';
                select.insertBefore(emptyOption, select.firstChild);
            }
            emptyOption.textContent = '선택(없음)';

            select.value = currentValue || '';

            if (window.jQuery?.fn?.select2) {
                const $select = window.jQuery(select);
                const modalParent = $select.closest('.modal');
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                $select.select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                    language: 'ko',
                    dropdownParent: modalParent.length ? modalParent : window.jQuery(document.body),
                });
                $select.off('select2:select.clientOptionalNone');
            }
        });
    }

    function closeClientModalSelect2() {
        const modalEl = document.getElementById('clientModal');
        if (!modalEl || !window.jQuery?.fn?.select2) return;
        modalEl.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
            window.jQuery(select).select2('close');
        });
    }

    function clearClientModalSelectDisplays() {
        const modalEl = document.getElementById('clientModal');
        if (!modalEl) return;
        modalEl.querySelectorAll('select.select2-hidden-accessible, select[data-code-group], #modal_default_account_id').forEach((select) => {
            select.value = '';
            if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                window.jQuery(select).val(null).trigger('change.select2');
            }
        });
    }

    function validateClientSubTypeRules(form) {
        const subType = String(form.querySelector('[name="client_type"]')?.value || '').trim();
        const companyName = String(form.querySelector('[name="company_name"]')?.value || '').trim();
        const businessNumber = String(form.querySelector('[name="business_number"]')?.value || '').replace(/\D/g, '');
        if (subType === 'BUSINESS' && !companyName) {
            notify('error', '사업자 거래처는 상호를 입력해야 합니다.');
            return false;
        }
        if (subType === 'BUSINESS' && !businessNumber) {
            notify('error', '사업자 거래처는 사업자등록번호를 입력해야 합니다.');
            return false;
        }
        return true;
    }

    function applyClientSubTypeRules(scope = document) {
        const root = scope || document;
        const clientType = String(root.querySelector?.('[name="client_type"]')?.value || '').trim();
        const fields = [
            root.querySelector?.('[name="business_number"]'),
            root.querySelector?.('[name="company_name"]'),
        ];
        fields.forEach((field) => {
            if (field) field.required = clientType === 'BUSINESS';
        });
    }

    function bindClientQuickFormatters(form) {
        if (!form || form.dataset.quickFormatBound === 'true') return;
        form.dataset.quickFormatBound = 'true';
        form.addEventListener('input', (event) => {
            const field = event.target;
            const type = field?.dataset?.format;
            if (type === 'biz') field.value = formatBizNumber?.(field.value) || field.value;
            if (type === 'phone' || type === 'fax') field.value = formatPhone?.(field.value) || field.value;
            if (type === 'mobile') field.value = formatMobile?.(field.value) || field.value;
        });
    }

    function mapBusinessStatusLabel(value) {
        const raw = String(value || '').trim();
        const statusMap = { 계속사업자: '정상', 정상: '정상', 휴업자: '휴업', 휴업: '휴업', 폐업자: '폐업', 폐업: '폐업' };
        return statusMap[raw] || raw;
    }

    async function checkBusinessStatusWithForm({ form, button, statusInput, statusText, prefix = '' }) {
        const input = form?.querySelector('[name="business_number"]') || document.getElementById('modal_business_number');
        const bizNo = String(input?.value || '').replace(/\D/g, '');
        if (!bizNo) {
            notify('warning', `${prefix}사업자번호를 입력하세요.`);
            input?.focus?.();
            return;
        }
        if (bizNo.length !== 10) {
            notify('warning', `${prefix}사업자번호는 10자리입니다.`);
            input?.focus?.();
            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = '조회중...';
        }

        try {
            const res = await checkBusinessStatus(bizNo);
            const info = res?.data?.data?.[0];
            if (!res || res?.data?.status_code !== 'OK') {
                notify('error', `${prefix}사업자 상태 조회에 실패했습니다.`);
                return;
            }
            if (!info) {
                notify('warning', '조회 결과가 없습니다.');
                return;
            }
            if (info.b_stt) {
                const mapped = mapBusinessStatusLabel(info.b_stt);
                if (statusInput) statusInput.value = mapped;
                if (statusText) statusText.textContent = `상태: ${info.b_stt}`;
                notify('success', `사업자 상태: ${info.b_stt}`);
                return;
            }
            if (info.tax_type) {
                if (statusInput) statusInput.value = '';
                if (statusText) statusText.textContent = info.tax_type;
                notify('warning', info.tax_type);
                return;
            }
            notify('warning', '사업자 상태를 확인할 수 없습니다.');
        } catch (error) {
            console.error(error);
            notify('error', `${prefix}사업자 상태 조회에 실패했습니다.`);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = '상태확인';
            }
        }
    }

    function getClientModalBankName() {
        const bankSelect = document.getElementById('modal_bank_name');
        if (!bankSelect) return '';
        const selectedText = bankSelect.options?.[bankSelect.selectedIndex]?.textContent || '';
        return [bankSelect.value, selectedText].filter(Boolean).join(' ');
    }

    function formatClientAccountNumberInput(input, options = {}) {
        if (!input) return;
        input.value = formatAccountNumber?.(input.value, getClientModalBankName()) || input.value;
        void loadBankAccountFormatRegistry?.({ force: options.forceReload === true }).then(() => {
            input.value = formatAccountNumber?.(input.value, getClientModalBankName()) || input.value;
        });
    }

    function bindClientBankAccountFormatting(modalEl = document.getElementById('clientModal')) {
        const bankSelect = modalEl?.querySelector('#modal_bank_name');
        const accountInput = modalEl?.querySelector('#modal_account_number');
        if (!bankSelect || !accountInput) return;
        const reformat = () => {
            formatClientAccountNumberInput(accountInput, { forceReload: true });
            window.requestAnimationFrame?.(() => formatClientAccountNumberInput(accountInput));
        };
        if (bankSelect.dataset.accountFormatNativeBound !== 'true') {
            bankSelect.addEventListener('change', reformat);
            bankSelect.dataset.accountFormatNativeBound = 'true';
        }
        if (window.jQuery?.fn?.select2) {
            window.jQuery(bankSelect)
                .off('select2:select.clientBankFormat select2:clear.clientBankFormat')
                .on('select2:select.clientBankFormat select2:clear.clientBankFormat', reformat);
        }
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

    function bindDateIconPicker() {
        if (document.__clientDateIconPickerBound) return;
        document.__clientDateIconPickerBound = true;
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

    function bindBizStatusButton() {
        const button = document.getElementById('btnCheckBizStatus');
        if (!button || button.dataset.bizStatusBound === 'true') return;
        button.dataset.bizStatusBound = 'true';
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            await checkBusinessStatusWithForm({
                form: document.getElementById('client-edit-form'),
                button,
                statusInput: document.getElementById('modal_business_status'),
            });
        });
    }

    function maskRrn(rrn) {
        const clean = String(rrn || '').replace(/\D/g, '');
        if (clean.length <= 6) return clean;
        return `${clean.substring(0, 6)}-********`;
    }

    function formatRrn(rrn) {
        const clean = String(rrn || '').replace(/\D/g, '');
        if (clean.length <= 6) return clean;
        return `${clean.substring(0, 6)}-${clean.substring(6)}`;
    }

    function resetRrnVisibility() {
        rrnVisible = false;
        const toggleBtn = document.querySelector('.toggle-rrn');
        const icon = toggleBtn?.querySelector('i');
        icon?.classList.remove('bi-eye-slash');
        icon?.classList.add('bi-eye');
    }

    function bindRrnInputEvents($) {
        $(document)
            .off('input.clientRrn focus.clientRrn blur.clientRrn', '#modal_rrn')
            .off('click.clientToggleRrn', '.toggle-rrn')
            .on('input.clientRrn', '#modal_rrn', function () {
                const raw = onlyNumber($(this).val()).substring(0, 13);
                $(this).data('real', raw);
                this.dataset.real = raw;
                $(this).val(rrnVisible ? formatRrn(raw) : maskRrn(raw));
            })
            .on('focus.clientRrn', '#modal_rrn', function () {
                const raw = onlyNumber($(this).data('real') || '');
                $(this).val(rrnVisible ? formatRrn(raw) : maskRrn(raw));
            })
            .on('blur.clientRrn', '#modal_rrn', function () {
                if (rrnVisible) return;
                $(this).val(maskRrn(onlyNumber($(this).data('real') || '')));
            })
            .on('click.clientToggleRrn', '.toggle-rrn', function () {
                const $input = $('#modal_rrn');
                const icon = this.querySelector('i');
                const realVal = onlyNumber($input.data('real') || '');
                rrnVisible = !rrnVisible;
                $input.val(rrnVisible ? formatRrn(realVal) : maskRrn(realVal));
                icon?.classList.replace(rrnVisible ? 'bi-eye' : 'bi-eye-slash', rrnVisible ? 'bi-eye-slash' : 'bi-eye');
            });
    }

    function initDefaultAccountSelectState(select, value, items) {
        if (window.jQuery?.fn?.select2) {
            AdminPicker.select2(select, {
                dropdownParent: window.jQuery('#clientModal'),
                width: '100%',
            });
            AdminPicker.reloadSelect2(select, items, 'id', 'text', value || '');
            return;
        }
        select.innerHTML = '<option value="">선택(없음)</option>' + items
            .map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`).join('');
        select.value = value;
    }

    function bindDefaultAccountSelect(select) {
        if (!select || select.dataset.defaultAccountBound === '1') return;
        select.dataset.defaultAccountBound = '1';
        select.addEventListener('change', () => {
            if (select.value !== '__add__') select.dataset.previousValue = select.value || '';
        });
    }

    async function initDefaultAccountSelect(selectedValue = '') {
        const select = document.getElementById('modal_default_account_id');
        if (!select) return;
        if (!defaultAccountRows.length) {
            try {
                const response = await fetch(API.ACCOUNTS, { cache: 'no-store' });
                const json = await response.json();
                defaultAccountRows = (json.data || []).filter((row) => Number(row.is_active ?? 1) === 1 && Number(row.is_posting ?? 1) === 1);
            } catch (error) {
                console.error('[client] default account load failed', error);
                defaultAccountRows = [];
            }
        }
        const value = String(selectedValue || select.value || '').trim();
        const items = defaultAccountRows.map((row) => ({
            id: String(row.id || ''),
            text: row.full_path ? `[${row.full_path}]` : [row.account_code, row.account_name].filter(Boolean).join(' - '),
        })).filter((item) => item.id !== '');
        delete select.dataset.quickAddEnabled;
        select.dataset.previousValue = value;
        bindDefaultAccountSelect(select);
        initDefaultAccountSelectState(select, value, items);
        select.dataset.previousValue = value;
    }

    async function prepareClientModalControls(selectedDefaultAccountId = '') {
        const modalEl = document.getElementById('clientModal');
        await loadBankAccountFormatRegistry?.();
        await initCodeSelectControls(modalEl);
        applyClientOptionalCodeSelects(modalEl);
        bindClientBankAccountFormatting(modalEl);
        await initDefaultAccountSelect(selectedDefaultAccountId);
    }

    function preloadClientModalControls(selectedDefaultAccountId = '') {
        if (!clientModalControlsPromise) {
            clientModalControlsPromise = prepareClientModalControls(selectedDefaultAccountId).catch((error) => {
                console.error('[client] modal controls preload failed', error);
                clientModalControlsPromise = null;
            });
        }
        return clientModalControlsPromise;
    }

    return {
        API, CLIENT_OPTIONAL_CODE_SELECT_IDS, onlyNumber, formatBizNumber, formatCorpNumber, formatMobile, formatPhone,
        formatAccountNumber, unformatAccountNumber, escapeHtml, notify, applyClientOptionalCodeSelects, closeClientModalSelect2,
        clearClientModalSelectDisplays, validateClientSubTypeRules, applyClientSubTypeRules, bindClientQuickFormatters,
        checkBusinessStatusWithForm, bindClientBankAccountFormatting, bindAdminDateInputs, bindDateIconPicker,
        normalizeDateInputValue, preloadClientModalControls, prepareClientModalControls, bindBizStatusButton,
        bindRrnInputEvents, resetRrnVisibility, maskRrn, formatRrn,
    };
}
