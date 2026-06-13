export function createCardFormModule({
    AdminPicker,
    initNumberInputs,
    openClientQuickCreate,
    API,
}) {
    let todayPicker = null;
    let selectInitialized = false;
    let cardModalControlsPromise = null;

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
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resetCardForm() {
        const form = getForm();
        if (form) form.reset();

        const idEl = getIdEl();
        if (idEl) idEl.value = '';

        const deleteBtn = document.getElementById('btnDeleteCard');
        if (deleteBtn) deleteBtn.style.display = 'none';

        AdminPicker.clearSelect2('#cardClientSelect', true);
        AdminPicker.clearSelect2('#cardAccountSelect', true);

        const activeEl = form?.querySelector('[name="is_active"]');
        const deleteFileEl = getDeleteCardFileEl();
        if (activeEl) activeEl.value = '1';
        if (deleteFileEl) deleteFileEl.value = '0';

        resetCardImageUI();
    }

    function bindUIEvents() {
        const btnRemoveCardFile = document.getElementById('btnDeleteCardFile') || document.getElementById('btnRemoveCardFile');
        if (!btnRemoveCardFile || btnRemoveCardFile.dataset.bound === '1') return;

        btnRemoveCardFile.dataset.bound = '1';
        btnRemoveCardFile.addEventListener('click', () => {
            markCardFileDeleted();
        });
    }

    function bindGlobalEvents() {
        if (document.__cardGlobalBound) return;
        document.__cardGlobalBound = true;

        document.addEventListener('input', (event) => {
            const input = event.target;
            if (!input?.matches?.('[name="expiry_year"], [name="expiry_month"]')) return;

            if (input.name === 'expiry_year') {
                input.value = String(input.value || '').replace(/\D/g, '').slice(0, 4);
            }

            if (input.name === 'expiry_month') {
                input.value = String(input.value || '').replace(/\D/g, '').slice(0, 2);
            }
        });
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
        if (document.__cardDateIconPickerBound) return;
        document.__cardDateIconPickerBound = true;

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

        if (type === 'start' && start.value > end.value) end.value = start.value;
        if (type === 'end' && end.value < start.value) start.value = end.value;
    }

    function prepareCardModalControls() {
        if (!cardModalControlsPromise) {
            cardModalControlsPromise = Promise.resolve()
                .then(() => {
                    initNumberInputs('#cardForm .number-input');
                    if (!selectInitialized) {
                        initSelectPickers();
                        selectInitialized = true;
                    }
                })
                .catch((error) => {
                    cardModalControlsPromise = null;
                    throw error;
                });
        }

        return cardModalControlsPromise;
    }

    function deferCardModalControls() {
        const run = () => {
            prepareCardModalControls().catch((error) => {
                console.error('[card] modal controls prepare failed', error);
                notify('error', '카드 입력 항목 준비 중 오류가 발생했습니다.');
            });
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => window.setTimeout(run, 0));
            return;
        }

        window.setTimeout(run, 0);
    }

    function validateCardForm(formData, parseNumber) {
        const cardName = String(formData.get('card_name') || '').trim();
        const cardNumber = String(formData.get('card_number') || '').trim();
        const expiryYear = String(formData.get('expiry_year') || '').trim();
        const expiryMonth = String(formData.get('expiry_month') || '').trim();
        const limitAmountRaw = String(formData.get('limit_amount') || '').trim();
        const limitAmount = limitAmountRaw === '' ? 0 : parseNumber(limitAmountRaw);

        if (!cardName) return '카드명은 필수입니다.';
        if (cardNumber && !/^[0-9-]+$/.test(cardNumber)) return '카드번호는 숫자와 하이픈만 입력할 수 있습니다.';
        if (expiryYear && !/^\d{4}$/.test(expiryYear)) return '유효기간 연도는 4자리 숫자로 입력해주세요.';
        if (expiryMonth && !/^(0[1-9]|1[0-2])$/.test(expiryMonth)) return '유효기간 월은 01부터 12까지 입력해주세요.';
        if (limitAmount < 0) return '한도금액은 0 이상이어야 합니다.';

        return '';
    }

    function initCardImageUpload() {
        const drop = getCardFileDropEl();
        const input = getCardFileInputEl();
        const text = getCardFileTextEl();
        if (!drop || !input || !text || drop.dataset.uploadBound === '1') return;

        drop.dataset.uploadBound = '1';
        drop.dataset.original = drop.dataset.original || '0';

        const renderFile = (file) => {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!allowed.includes(ext)) {
                notify('warning', '카드 이미지는 PDF, JPG, PNG 파일만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

            if (file.size > 10 * 1024 * 1024) {
                notify('warning', '카드 이미지 파일은 10MB 이하만 업로드할 수 있습니다.');
                input.value = '';
                return false;
            }

            const message = drop.dataset.original === '1'
                ? '저장하면 기존 카드 이미지가 교체됩니다.'
                : '저장하면 카드 이미지가 등록됩니다.';

            text.innerHTML = `
                <strong>카드 이미지</strong><br>
                (${shortenFileName(file.name, 20)})<br>
                <span class="text-primary">${message}</span>
            `;

            return true;
        };

        drop.addEventListener('click', () => input.click());
        input.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (!file) return;
            renderFile(file);
        });

        drop.addEventListener('dragover', (event) => event.preventDefault());
        drop.addEventListener('drop', (event) => {
            event.preventDefault();

            const file = event.dataTransfer.files[0];
            if (!file) return;

            input.files = event.dataTransfer.files;
            renderFile(file);
        });
    }

    function renderCardFile(data) {
        const text = getCardFileTextEl();
        const drop = getCardFileDropEl();
        const filePath = data.card_file || '';
        if (!text) return;

        if (filePath) {
            const path = encodeURIComponent(filePath);
            if (drop) drop.dataset.original = '1';

            text.innerHTML = `
                <div class="upload-guide">파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)</div>
                <div class="file-line"><strong>카드 이미지 등록됨</strong></div>
                <div class="file-links">
                    <a href="/api/file/preview?path=${path}" target="_blank" class="file-link-open">미리보기</a>
                    <span class="file-divider">|</span>
                    <a href="javascript:void(0)" id="btnDeleteCardFileInline" class="file-link-delete">삭제</a>
                </div>
            `;

            const btnDelete = document.getElementById('btnDeleteCardFileInline');
            if (btnDelete) {
                btnDelete.onclick = (event) => {
                    event.stopPropagation();
                    markCardFileDeleted();
                };
            }
            return;
        }

        resetCardImageUI();
    }

    function markCardFileDeleted() {
        if (!confirm('카드 이미지를 삭제하시겠습니까?')) return;

        const input = getCardFileInputEl();
        const del = getDeleteCardFileEl();
        const drop = getCardFileDropEl();
        const text = getCardFileTextEl();

        if (input) input.value = '';
        if (del) del.value = '1';
        if (drop) drop.dataset.original = '0';

        if (text) {
            text.innerHTML = `
                <div class="upload-guide">파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)</div>
                <div class="file-status text-danger">카드 이미지가 삭제되며 저장 후 반영됩니다.</div>
            `;
        }
    }

    function resetCardImageUI() {
        const input = getCardFileInputEl();
        const del = getDeleteCardFileEl();
        const list = getCardFileListEl();
        const text = getCardFileTextEl();
        const drop = getCardFileDropEl();

        if (input) input.value = '';
        if (del) del.value = '0';
        if (list) list.innerHTML = '';
        if (drop) drop.dataset.original = '0';
        if (text) text.innerHTML = '파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)';
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

    function initSelectPickers() {
        const modalParent = window.jQuery ? window.jQuery('#cardModal') : null;
        if (modalParent && modalParent.length === 0) return;

        AdminPicker.select2Ajax('#cardClientSelect', {
            url: API.CLIENT_SEARCH,
            placeholder: '카드사 검색',
            includeCommonAdd: true,
            minimumInputLength: 0,
            dropdownParent: modalParent,
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                    is_active: 1,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];
                return {
                    results: rows.map((row) => ({
                        id: String(row.id ?? ''),
                        text: row.text || row.client_name || row.company_name || '',
                    })).filter((row) => row.id !== ''),
                };
            },
        });

        const $client = window.jQuery('#cardClientSelect');
        $client.off('select2:select.cardClient');
        $client.on('select2:select.cardClient', () => {});

        const clientSelect = document.getElementById('cardClientSelect');
        clientSelect?.removeEventListener?.('picker:add', clientSelect.__cardClientPickerAdd);
        if (clientSelect) {
            clientSelect.__cardClientPickerAdd = () => {
                window.jQuery(clientSelect).val(null).trigger('change');
                window.jQuery(clientSelect).select2('close');
                openCardClientQuickCreate('');
            };
            clientSelect.addEventListener('picker:add', clientSelect.__cardClientPickerAdd);
        }

        AdminPicker.select2Ajax('#cardAccountSelect', {
            url: API.BANK_ACCOUNT_SEARCH,
            placeholder: '결제계좌 검색',
            minimumInputLength: 0,
            dropdownParent: modalParent,
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];
                return {
                    results: rows.map((row) => ({
                        id: String(row.id ?? ''),
                        text: row.text || `${row.account_name || ''}${row.bank_name ? ` (${row.bank_name})` : ''}`,
                    })).filter((row) => row.id !== ''),
                };
            },
        });
    }

    function openCardClientQuickCreate(defaultName = '') {
        openClientQuickCreate({
            select: document.getElementById('cardClientSelect'),
            initialValues: {
                client_name: defaultName,
            },
            onSuccess() {
                notify('success', '거래처가 등록되었습니다.');
            },
            getOptionText(values) {
                return values.client_name || '';
            },
        });
    }

    function getForm() {
        return document.getElementById('cardForm');
    }

    function getIdEl() {
        return document.getElementById('modal_card_id') || document.querySelector('#cardForm [name="id"]');
    }

    function getCardFileInputEl() {
        return document.getElementById('modal_card_file') || document.querySelector('#cardForm [name="card_file"]');
    }

    function getDeleteCardFileEl() {
        return document.getElementById('delete_card_file') || document.querySelector('#cardForm [name="delete_card_file"]');
    }

    function getCardFileListEl() {
        return document.getElementById('cardFileList') || document.getElementById('cardPreview');
    }

    function getCardFileTextEl() {
        return document.getElementById('cardUploadText') || document.getElementById('cardImageText');
    }

    function getCardFileDropEl() {
        return document.getElementById('cardUpload') || document.getElementById('cardImageUpload');
    }

    function setModalTitle(title) {
        const titleEl = document.getElementById('cardModalLabel') || document.querySelector('#cardModal .modal-title');
        if (titleEl) titleEl.textContent = title;
    }

    return {
        notify,
        escapeHtml,
        resetCardForm,
        bindUIEvents,
        bindGlobalEvents,
        initAdminDatePicker,
        bindAdminDateInputs,
        bindDateIconPicker,
        prepareCardModalControls,
        deferCardModalControls,
        validateCardForm,
        initCardImageUpload,
        renderCardFile,
        getForm,
        getIdEl,
        getDeleteCardFileEl,
        setModalTitle,
    };
}
