export function createBankAccountFormModule({
    AdminPicker,
    initCodeSelectControls,
    NumberFormat,
}) {
    const onlyNumber = NumberFormat.onlyNumber || ((value) => String(value ?? '').replace(/\D/g, ''));
    const { formatAccountNumber, loadBankAccountFormatRegistry } = NumberFormat;

    let todayPicker = null;
    let globalBound = false;
    let accountModalControlsPromise = null;

    const notify = (type, message) => {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error') {
            alert(message);
        }
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function bindUIEvents() {
        const btnRemoveBankBook =
            document.getElementById('btnDeleteBankBook') ||
            document.getElementById('btnRemoveBankBook');

        if (!btnRemoveBankBook || btnRemoveBankBook.dataset.bound === '1') return;

        btnRemoveBankBook.dataset.bound = '1';
        btnRemoveBankBook.addEventListener('click', () => {
            if (!confirm('통장사본 파일을 삭제하시겠습니까?')) return;
            markBankBookDeleted();
        });
    }

    function bindGlobalEvents() {
        if (globalBound) return;
        globalBound = true;

        document.addEventListener('input', onGlobalInput);
        document.addEventListener('change', onGlobalChange);
    }

    function onGlobalInput(event) {
        const type = event.target.dataset.format;
        if (!type) return;

        if (type === 'currency') {
            event.target.value = String(event.target.value || '').toUpperCase().slice(0, 10);
            return;
        }

        if (type === 'account_number') {
            formatAccountNumberInput(event.target);
        }
    }

    function onGlobalChange(event) {
        if (event.target.name !== 'bank_name') return;
        const form = event.target.closest('form');
        const input = form?.querySelector('[name="account_number"][data-format="account_number"]');
        formatAccountNumberInput(input, { forceReload: true });
    }

    function getAccountModalBankName(form = document.getElementById('accountForm')) {
        const bankSelect = form?.querySelector('[name="bank_name"]');
        if (!bankSelect) return '';

        const selectedText = bankSelect.options?.[bankSelect.selectedIndex]?.textContent || '';
        return [bankSelect.value, selectedText].filter(Boolean).join(' ');
    }

    function formatAccountNumberInput(input, options = {}) {
        if (!input) return;

        const form = input.closest('form') || document.getElementById('accountForm');
        const bankName = getAccountModalBankName(form);
        input.value = formatAccountNumber(input.value, bankName);

        void loadBankAccountFormatRegistry?.({ force: options.forceReload === true }).then(() => {
            const latestBankName = getAccountModalBankName(form);
            input.value = formatAccountNumber(input.value, latestBankName);
        });
    }

    function bindAccountBankFormatting(modalEl = document.getElementById('accountModal')) {
        const bankSelect = modalEl?.querySelector('#modal_bank_name, [name="bank_name"]');
        const accountInput = modalEl?.querySelector('[name="account_number"][data-format="account_number"]');
        if (!bankSelect || !accountInput) return;

        const reformatAccountNumber = () => {
            formatAccountNumberInput(accountInput, { forceReload: true });
            window.requestAnimationFrame?.(() => {
                formatAccountNumberInput(accountInput);
            });
        };

        if (bankSelect.dataset.accountFormatNativeBound !== 'true') {
            bankSelect.addEventListener('change', reformatAccountNumber);
            bankSelect.dataset.accountFormatNativeBound = 'true';
        }

        if (window.jQuery?.fn?.select2) {
            window.jQuery(bankSelect)
                .off('select2:select.accountBankFormat select2:clear.accountBankFormat')
                .on('select2:select.accountBankFormat select2:clear.accountBankFormat', reformatAccountNumber);
        }
    }

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;

        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({
            type: 'today',
            container,
        });

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
        if (document.__accountDateIconPickerBound) return;
        document.__accountDateIconPickerBound = true;

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
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
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

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            notify('warning', '올바른 날짜를 입력해주세요.');
            return '';
        }

        return formatted;
    }

    function formatDate(date) {
        if (!date) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('input[name="dateStart"]');
        const end = document.querySelector('input[name="dateEnd"]');
        if (!start || !end || !start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }
        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
    }

    function preloadAccountModalControls() {
        if (!accountModalControlsPromise) {
            accountModalControlsPromise = prepareAccountModalControls().catch((error) => {
                console.error('[bank-account] modal controls preload failed', error);
                accountModalControlsPromise = null;
            });
        }

        return accountModalControlsPromise;
    }

    async function prepareAccountModalControls() {
        await loadBankAccountFormatRegistry?.();
        await initCodeSelectControls(document.getElementById('accountModal'));
        bindAccountBankFormatting();
    }

    function validateAccountForm(formData) {
        const accountName = String(formData.get('account_name') || '').trim();
        const accountNumber = String(unformatAccountNumberValue(formData.get('account_number') || ''));
        const currency = String(formData.get('currency') || '').trim().toUpperCase();
        const bankName = String(formData.get('bank_name') || '').trim();
        const accountHolder = String(formData.get('account_holder') || '').trim();

        if (!accountName) return '계좌명은 필수입니다.';
        if (accountNumber && !/^\d+$/.test(accountNumber)) return '계좌번호는 숫자만 입력할 수 있습니다.';
        if (currency && !/^[A-Z]{3}$/.test(currency)) return '통화 코드는 3자리 영문으로 입력해주세요.';
        if (bankName.length > 100) return '은행명은 100자 이하로 입력해주세요.';
        if (accountHolder.length > 100) return '예금주는 100자 이하로 입력해주세요.';
        return '';
    }

    function initBankBookUpload() {
        const drop = getBankBookDropEl();
        const input = getBankBookInputEl();
        const text = getBankBookTextEl();
        if (!drop || !input || !text) return;

        if (!drop.dataset.original) {
            drop.dataset.original = '0';
        }

        function renderFile(file) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!allowed.includes(ext)) {
                notify('warning', '통장사본은 PDF, JPG, PNG 파일만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

            if (file.size > 10 * 1024 * 1024) {
                notify('warning', '통장사본 파일은 10MB 이하만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

            const hasOriginal = drop.dataset.original === '1';
            const message = hasOriginal
                ? '저장 시 기존 통장사본을 교체합니다.'
                : '저장 시 통장사본을 등록합니다.';

            const shortName = shortenFileName(file.name, 20);
            text.innerHTML = `
                <strong>통장사본</strong>
                <br>
                (${shortName})
                <br>
                <span class="text-primary">${message}</span>
            `;

            return true;
        }

        drop.addEventListener('click', () => input.click());
        input.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (!file) return;
            if (!renderFile(file)) {
                input.value = '';
            }
        });

        drop.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        drop.addEventListener('drop', (event) => {
            event.preventDefault();
            const file = event.dataTransfer.files[0];
            if (!file) return;

            input.files = event.dataTransfer.files;
            if (!renderFile(file)) {
                input.value = '';
            }
        });
    }

    function renderBankBook(data) {
        const list = getBankBookListEl();
        const text = getBankBookTextEl();
        const drop = getBankBookDropEl();
        if (!text) return;

        const filePath = data.bank_file || '';
        if (list) list.innerHTML = '';

        if (filePath) {
            const path = encodeURIComponent(data.bank_file);
            if (drop) drop.dataset.original = '1';

            text.innerHTML = `
                <div class="file-status">
                    <div class="upload-guide">
                        여기에 파일을 드래그하거나 클릭하여 업로드
                        <br>
                        (PDF, JPG, PNG)
                    </div>
                    <div class="file-line">
                        <strong>통장사본 등록됨</strong>
                    </div>
                    <div class="file-links">
                        <a href="javascript:void(0)" id="btnOpenBankCopy" class="file-link-open disabled">미리보기</a>
                        <span class="file-divider">|</span>
                        <a href="javascript:void(0)" id="btnDeleteBankBookInline" class="file-link-delete disabled">삭제</a>
                    </div>
                </div>
            `;

            const btnOpen = document.getElementById('btnOpenBankCopy');
            const btnDelete = document.getElementById('btnDeleteBankBookInline');

            if (btnOpen) {
                btnOpen.classList.remove('disabled');
                btnOpen.href = `/api/file/preview?path=${path}`;
                btnOpen.target = '_blank';
                btnOpen.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            }

            if (btnDelete) {
                btnDelete.classList.remove('disabled');
                btnDelete.onclick = (event) => {
                    event.stopPropagation();
                    if (!confirm('통장사본 파일을 삭제하시겠습니까?')) return;
                    markBankBookDeleted();
                };
            }
        } else {
            if (drop) drop.dataset.original = '0';
            text.innerHTML = `
                여기에 파일을 드래그하거나 클릭하여 업로드
                <br>
                (PDF, JPG, PNG)
            `;
        }
    }

    function markBankBookDeleted() {
        const input = getBankBookInputEl();
        const del = getDeleteBankBookEl();
        const list = getBankBookListEl();
        const drop = getBankBookDropEl();
        const text = getBankBookTextEl();

        if (input) input.value = '';
        if (del) del.value = '1';
        if (list) {
            list.dataset.original = '0';
            list.innerHTML = `
                <div class="file-item">
                    <span><strong>통장사본</strong></span>
                    <div class="file-status text-danger">
                        통장사본 파일이 삭제되며 저장 후 반영됩니다.
                    </div>
                </div>
            `;
        }
        if (drop) drop.dataset.original = '0';
        if (text) {
            text.innerHTML = `
                여기에 파일을 드래그하거나 클릭하여 업로드
                <br>
                (PDF, JPG, PNG)
            `;
        }
    }

    function resetBankBookUI() {
        const input = getBankBookInputEl();
        const del = getDeleteBankBookEl();
        const list = getBankBookListEl();
        const text = getBankBookTextEl();
        const drop = getBankBookDropEl();

        if (input) input.value = '';
        if (del) del.value = '0';
        if (list) list.innerHTML = '';
        if (drop) drop.dataset.original = '0';

        if (text) {
            text.innerHTML = `
                여기에 파일을 드래그하거나 클릭하여 업로드
                <br>
                (PDF, JPG, PNG)
            `;
        }
    }

    function getIdEl() {
        return (
            document.getElementById('account_id') ||
            document.getElementById('modal_account_id') ||
            document.querySelector('#accountForm [name="id"]')
        );
    }

    function getBankBookInputEl() {
        return (
            document.getElementById('modal_bank_file') ||
            document.querySelector('#accountForm [name="bank_file"]')
        );
    }

    function getDeleteBankBookEl() {
        return (
            document.getElementById('delete_bank_file') ||
            document.querySelector('#accountForm [name="delete_bank_file"]')
        );
    }

    function getBankBookListEl() {
        return (
            document.getElementById('bankBookList') ||
            document.getElementById('bankBookPreview')
        );
    }

    function getBankBookTextEl() {
        return (
            document.getElementById('bankBookText') ||
            document.getElementById('bankCopyText')
        );
    }

    function getBankBookDropEl() {
        return (
            document.getElementById('bankBookUpload') ||
            document.getElementById('bankCopyUpload')
        );
    }

    function shortenFileName(name, max = 20) {
        if (!name) return '';

        const lastDot = name.lastIndexOf('.');
        if (lastDot <= 0) {
            return name.length <= max ? name : `${name.substring(0, Math.max(1, max - 3))}...`;
        }

        const ext = name.substring(lastDot);
        const base = name.substring(0, lastDot);
        if (name.length <= max) return name;

        const keep = Math.max(1, max - ext.length - 3);
        return `${base.substring(0, keep)}...${ext}`;
    }

    function unformatAccountNumberValue(value) {
        return onlyNumber(String(value ?? ''));
    }

    return {
        notify,
        escapeHtml,
        bindUIEvents,
        bindGlobalEvents,
        initAdminDatePicker,
        bindAdminDateInputs,
        bindDateIconPicker,
        preloadAccountModalControls,
        prepareAccountModalControls,
        validateAccountForm,
        initBankBookUpload,
        renderBankBook,
        resetBankBookUI,
        getIdEl,
        getBankBookInputEl,
        getDeleteBankBookEl,
        bindAccountBankFormatting,
        unformatAccountNumberValue,
    };
}
