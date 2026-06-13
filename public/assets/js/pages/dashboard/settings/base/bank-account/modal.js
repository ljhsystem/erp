export function createBankAccountModalModule({
    API,
    formModule,
    formatAccountNumber,
    state,
}) {
    function initModal() {
        const modalEl = document.getElementById('accountModal');
        if (!modalEl) return;

        state.accountModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('accountExcelModal');
        if (excelEl) {
            state.excelModal = new bootstrap.Modal(excelEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('accountForm');
            if (form) form.reset();

            const idEl = formModule.getIdEl();
            if (idEl) idEl.value = '';

            const deleteBtn = document.getElementById('btnDeleteAccount');
            if (deleteBtn) deleteBtn.style.display = 'none';

            window.isNewAccount = false;

            const titleEl = document.querySelector('#accountModal .modal-title');
            if (titleEl) {
                titleEl.textContent = '怨꾩쥖 ?뺣낫';
            }

            formModule.resetBankBookUI();
        });

        formModule.bindDateIconPicker();

        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.bindAdminDateInputs();
        });
    }

    async function fetchAccountDetail(id) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || '계좌 상세 조회에 실패했습니다.');
        }
        return json.data;
    }

    async function openAccountEditModal(accountId) {
        if (!accountId) return;

        window.isNewAccount = false;

        const form = document.getElementById('accountForm');
        if (form) form.reset();

        const titleEl = document.querySelector('#accountModal .modal-title');
        if (titleEl) {
            titleEl.textContent = '계좌 정보 수정';
        }

        const deleteBtn = document.getElementById('btnDeleteAccount');
        if (deleteBtn) deleteBtn.style.display = '';

        const idEl = formModule.getIdEl();
        if (idEl) idEl.value = accountId;

        const delFile = formModule.getDeleteBankBookEl();
        const fileInput = formModule.getBankBookInputEl();
        if (delFile) delFile.value = '0';
        if (fileInput) fileInput.value = '';

        formModule.resetBankBookUI();
        state.accountModal?.show();

        try {
            const [data] = await Promise.all([
                fetchAccountDetail(accountId),
                formModule.prepareAccountModalControls(),
            ]);

            const nextIdEl = formModule.getIdEl();
            if (nextIdEl) nextIdEl.value = data.id;
            fillModal(data);
        } catch (error) {
            console.error(error);
            formModule.notify('error', error.message || '서버 오류');
        }
    }

    function fillModal(data) {
        Object.keys(data).forEach((key) => {
            if (key === 'id' || key === 'bank_file') return;

            const byId =
                document.getElementById(`account_${key}`) ||
                document.getElementById(`modal_${key}`);

            const byName = document.querySelector(`#accountForm [name="${key}"]`);
            const el = byId || byName;
            if (!el) return;

            if (el.type === 'file') {
                el.value = '';
                return;
            }

            if (key === 'account_number') {
                el.value = data[key] ?? '';
                return;
            }

            if (el.tagName === 'SELECT') {
                setSelectValueByCodeOrText(el, data[key] ?? '');
                return;
            }

            el.value = data[key] ?? '';
        });

        const accountNumberInput = document.querySelector('#accountForm [name="account_number"][data-format="account_number"]');
        if (accountNumberInput) {
            accountNumberInput.value = formatAccountNumber(accountNumberInput.value, '');
            formModule.bindAccountBankFormatting();
        }

        formModule.renderBankBook(data);
    }

    function setSelectValueByCodeOrText(select, value) {
        const raw = String(value ?? '').trim();
        const normalized = raw.toUpperCase();
        const option = Array.from(select.options || []).find((item) => {
            const optionValue = String(item.value ?? '').trim();
            const optionText = String(item.textContent ?? '').trim();
            return optionValue === raw ||
                optionValue.toUpperCase() === normalized ||
                optionText === raw ||
                optionText.toUpperCase() === normalized;
        });

        select.value = option?.value ?? raw;
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
        }
    }

    function bindModalEvents($, getTable) {
        $(document).off('submit', '#accountForm');
        $(document).on('submit', '#accountForm', function (event) {
            event.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const errorMessage = formModule.validateAccountForm(formData);
            if (errorMessage) {
                formModule.notify('warning', errorMessage);
                return;
            }

            formData.set('currency', String(formData.get('currency') || '').trim().toUpperCase() || 'KRW');
            formData.set('account_number', formModule.unformatAccountNumberValue(formData.get('account_number') || ''));

            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done((res) => {
                    if (!res.success) {
                        formModule.notify('error', res.message || '????ㅽ뙣');
                        return;
                    }

                    state.accountModal?.hide();
                    getTable()?.ajax.reload(null, false);
                    formModule.notify('success', '저장되었습니다.');
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류');
                })
                .always(() => {
                    if (btn) btn.disabled = false;
                });
        });

        $('#btnDeleteAccount').off('click').on('click', () => {
            const id = formModule.getIdEl()?.value || '';
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done((res) => {
                    if (res.success) {
                        formModule.notify('success', '삭제되었습니다.');
                        getTable()?.ajax.reload(null, false);
                        state.accountModal?.hide();
                    } else {
                        formModule.notify('error', res.message || '삭제에 실패했습니다.');
                    }
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류');
                });
        });
    }

    return {
        initModal,
        fetchAccountDetail,
        openAccountEditModal,
        bindModalEvents,
    };
}
