export function createCardModalModule({
    API,
    AdminPicker,
    formatAmount,
    formModule,
    state,
}) {
    function initModal() {
        const modalEl = document.getElementById('cardModal');
        if (!modalEl) return;

        state.cardModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('cardExcelModal');
        if (excelEl) {
            state.excelModal = new bootstrap.Modal(excelEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            formModule.resetCardForm();
            formModule.setModalTitle('카드 정보');
            window.isNewCard = false;
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.deferCardModalControls();
        });
    }

    async function fetchCardDetail(cardId) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(cardId)}`);
        const json = await res.json();

        if (!json.success || !json.data) {
            throw new Error(json.message || '카드 상세 조회에 실패했습니다.');
        }

        return json.data;
    }

    function openCreateModal() {
        formModule.resetCardForm();
        window.isNewCard = true;
        formModule.setModalTitle('카드 신규 등록');
        state.cardModal?.show();
        formModule.deferCardModalControls();
    }

    async function openCardEditModal(rowData) {
        if (!rowData?.id) return;

        formModule.resetCardForm();
        window.isNewCard = false;
        formModule.setModalTitle('카드 정보 수정');

        const deleteBtn = document.getElementById('btnDeleteCard');
        if (deleteBtn) deleteBtn.style.display = '';

        const idEl = formModule.getIdEl();
        if (idEl) idEl.value = rowData.id;

        const delFile = formModule.getDeleteCardFileEl();
        const fileInput = document.getElementById('modal_card_file') || document.querySelector('#cardForm [name="card_file"]');
        if (delFile) delFile.value = '0';
        if (fileInput) fileInput.value = '';
        state.cardModal?.show();

        try {
            const [data] = await Promise.all([
                fetchCardDetail(rowData.id),
                formModule.prepareCardModalControls(),
            ]);

            const nextIdEl = formModule.getIdEl();
            if (nextIdEl) nextIdEl.value = data.id;
            fillModal(data);
        } catch (error) {
            console.error(error);
            formModule.notify('error', error.message || '서버 오류가 발생했습니다.');
        }
    }

    function fillModal(data) {
        const form = formModule.getForm();
        if (!form) return;

        Object.keys(data).forEach((key) => {
            if (key === 'id' || key === 'card_file') return;

            const el = form.querySelector(`[name="${key}"]`);
            if (!el || el.type === 'file') return;

            let value = data[key] ?? '';
            if (key === 'limit_amount' && value !== '') {
                value = formatAmount(value);
            }

            el.value = value;
        });

        setSelect2Initial('#cardClientSelect', data.client_id, data.client_name);
        setSelect2Initial('#cardAccountSelect', data.account_id, data.account_name);
        formModule.renderCardFile(data);
    }

    function setSelect2Initial(selector, id, text) {
        if (!id) return;

        const el = document.querySelector(selector);
        if (!el) return;

        const option = new Option(text || id, id, true, true);
        el.append(option);
        AdminPicker.setSelect2Value(selector, id);
    }

    function bindModalEvents($, getTable, parseNumber) {
        $(document).off('submit', '#cardForm');

        $(document).on('submit', '#cardForm', function (event) {
            event.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const errorMessage = formModule.validateCardForm(formData, parseNumber);
            if (errorMessage) {
                formModule.notify('warning', errorMessage);
                return;
            }

            const expiryYear = String(formData.get('expiry_year') || '').trim();
            const expiryMonth = String(formData.get('expiry_month') || '').trim();
            const limitAmountRaw = String(formData.get('limit_amount') || '').trim();
            const limitAmount = limitAmountRaw === '' ? 0 : parseNumber(limitAmountRaw);

            formData.set('expiry_year', expiryYear);
            formData.set('expiry_month', expiryMonth);
            formData.set('limit_amount', String(limitAmount));

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
                        formModule.notify('error', res.message || '저장에 실패했습니다.');
                        return;
                    }

                    state.cardModal?.hide();
                    getTable()?.ajax.reload(null, false);
                    formModule.notify('success', '저장되었습니다.');
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류가 발생했습니다.');
                })
                .always(() => {
                    if (btn) btn.disabled = false;
                });
        });

        $('#btnDeleteCard').off('click').on('click', () => {
            const id = formModule.getIdEl()?.value || '';
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done((res) => {
                    if (res.success) {
                        formModule.notify('success', '삭제되었습니다.');
                        getTable()?.ajax.reload(null, false);
                        state.cardModal?.hide();
                    } else {
                        formModule.notify('error', res.message || '삭제에 실패했습니다.');
                    }
                })
                .fail(() => {
                    formModule.notify('error', '서버 오류가 발생했습니다.');
                });
        });
    }

    return {
        initModal,
        fetchCardDetail,
        openCreateModal,
        openCardEditModal,
        bindModalEvents,
    };
}
