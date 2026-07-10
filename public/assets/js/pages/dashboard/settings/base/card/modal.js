import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';

export function createCardModalModule({
    API,
    AdminPicker,
    formatAmount,
    formModule,
    state,
}) {
    let cardPolicyBound = false;

    const CARD_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.base-info.card.card-table.v1';
    const CARD_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#cardForm [name="card_name"]', key: 'card_name', fallback: '카드명' },
        { selector: '#cardForm [name="is_active"]', key: 'is_active', fallback: '상태' },
        { selector: '#cardForm [name="card_number"]', key: 'card_number', fallback: '카드번호' },
        { selector: '#cardForm [name="expiry_year"]', key: 'expiry_year', fallback: '유효기간(년)' },
        { selector: '#cardForm [name="expiry_month"]', key: 'expiry_month', fallback: '유효기간(월)' },
        { selector: '#cardForm [name="client_id"]', key: 'client_id', fallback: '카드사' },
        { selector: '#cardForm [name="account_id"]', key: 'account_id', fallback: '결제계좌' },
        { selector: '#cardForm [name="limit_amount"]', key: 'limit_amount', fallback: '한도금액' },
        { selector: '#cardForm [name="note"]', key: 'note', fallback: '비고' },
        { selector: '#cardForm [name="memo"]', key: 'memo', fallback: '메모' },
        { selector: '#modal_card_file', key: 'card_file', fallback: '카드 이미지' },
    ]);

    function currentCardPolicyState() {
        return readDataTableSettingsState(CARD_TABLE_SETTINGS_STORAGE_KEY, {
            userSettingPageKey: 'card',
        }) || {};
    }

    function cardFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentCardPolicyState(),
            normalizedKey
        );
    }

    function cardFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentCardPolicyState()
        );
    }

    function cardFieldStarMarkup(key) {
        const policy = cardFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function isCardFilePolicyKey(key) {
        return String(key || '').trim() === 'card_file';
    }

    function isCardFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateCardPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isCardFieldVisible(input);
    }

    function hasCardFileValue() {
        const input = formModule.getCardFileInputEl?.();
        const deleteFlag = formModule.getDeleteCardFileEl?.();
        const drop = document.getElementById('cardUpload') || document.getElementById('cardImageUpload');
        const hasExisting = String(drop?.dataset?.original || '0') === '1';
        return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
    }

    function collectCardDetailValues(form, formData) {
        const values = {};

        CARD_MODAL_FIELD_POLICIES.forEach((field) => {
            const key = String(field?.key || '').trim();
            const selector = String(field?.selector || '').trim();
            if (!key || !selector || isCardFilePolicyKey(key)) return;

            const input = form?.querySelector(selector) || document.querySelector(selector);
            if (!input) return;

            const fieldName = String(input.name || key).trim();
            values[key] = formData.get(fieldName) ?? input.value ?? '';
        });

        return values;
    }

    function validateCardRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || cardFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateCardPolicyField(field)) {
                continue;
            }

            const label = cardFieldLabel(key, field?.fallback || key);
            if (isCardFilePolicyKey(key)) {
                if (!hasCardFileValue()) {
                    return `${label} 항목은 필수입니다.`;
                }
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${label} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${label} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function findCardModalLabel(fieldSelector, root = document) {
        const field = root.querySelector(fieldSelector);
        if (!field) return null;

        if (field.id) {
            const labelByFor = root.querySelector(`label[for="${field.id}"]`);
            if (labelByFor) return labelByFor;
        }

        const column = field.closest('div[class*="col-"]');
        if (column) {
            const label = column.querySelector('label.form-label');
            if (label) return label;
        }

        return field.closest('label.form-label') || null;
    }

    function applyCardModalPolicyLabels(root = document) {
        CARD_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findCardModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = cardFieldLabel(field.key, field.fallback);
            const starMarkup = cardFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindCardPolicySync() {
        if (cardPolicyBound) return;
        cardPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== CARD_TABLE_SETTINGS_STORAGE_KEY) return;
            applyCardModalPolicyLabels(document);
        });
    }

    function initModal() {
        const modalEl = document.getElementById('cardModal');
        if (!modalEl) return;

        state.cardModal = new bootstrap.Modal(modalEl, { focus: false });

        const excelEl = document.getElementById('cardExcelModal');
        if (excelEl) {
            state.excelModal = new bootstrap.Modal(excelEl);
        }

        bindCardPolicySync();
        applyCardModalPolicyLabels(document);

        modalEl.addEventListener('hidden.bs.modal', () => {
            formModule.resetCardForm();
            formModule.setModalTitle('카드 정보');
            window.isNewCard = false;
            applyCardModalPolicyLabels(document);
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.deferCardModalControls();
            applyCardModalPolicyLabels(document);
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
        applyCardModalPolicyLabels(document);
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
        applyCardModalPolicyLabels(document);
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
        applyCardModalPolicyLabels(document);
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
            const requiredMessage = validateCardRequiredPolicies(
                CARD_MODAL_FIELD_POLICIES,
                collectCardDetailValues(form, formData)
            );
            if (requiredMessage) {
                formModule.notify('warning', requiredMessage);
                return;
            }

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
                        formModule.notify('success', '삭제했습니다.');
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
