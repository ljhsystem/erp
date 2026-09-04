import {
    fetchDataTableMetaColumns,
    getCachedDataTableMetaColumns,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { formatDateDisplay } from '/public/assets/js/common/format.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

export function createClientModalModule({
    API,
    CLIENT_QUICK_API,
    NumberFormat,
    formModule,
    state,
    renderers,
}) {
    let clientQuickState = null;
    let clientQuickBound = false;
    let clientDetailQuickBound = false;
    let clientModalInitialized = false;
    let clientSharedModalRoot = null;
    let clientQuickModalContext = null;
    let clientDetailModalContext = null;
    const CLIENT_META_DOMAIN = 'client';
    const CLIENT_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#modal_client_name', key: 'client_name', fallback: 'Client Name' },
        { selector: '#modal_company_name', key: 'company_name', fallback: 'Company Name' },
        { selector: '#modal_registration_date', key: 'registration_date', fallback: 'Registration Date' },
        { selector: '#modal_client_grade', key: 'client_grade', fallback: 'Client Grade' },
        { selector: '#modal_is_active', key: 'is_active', fallback: 'Status' },
        { selector: '#modal_business_number', key: 'business_number', fallback: 'Business Number' },
        { selector: '#modal_rrn', key: 'rrn', fallback: 'Corporate / Resident Number' },
        { selector: '#modal_client_type', key: 'client_type', fallback: 'Client Type' },
        { selector: '#modal_business_type', key: 'business_type', fallback: 'Business Type' },
        { selector: '#modal_business_category', key: 'business_category', fallback: 'Business Category' },
        { selector: '#modal_business_status', key: 'business_status', fallback: 'Business Status' },
        { selector: '#modal_business_certificate', key: 'business_certificate', fallback: 'Business Certificate' },
        { selector: '#modal_rrn_image', key: 'rrn_image', fallback: 'ID Image' },
        { selector: '#modal_ceo_name', key: 'ceo_name', fallback: 'CEO Name' },
        { selector: '#modal_ceo_phone', key: 'ceo_phone', fallback: 'CEO Phone' },
        { selector: '#modal_manager_name', key: 'manager_name', fallback: 'Manager Name' },
        { selector: '#modal_manager_phone', key: 'manager_phone', fallback: 'Manager Phone' },
        { selector: '#modal_phone', key: 'phone', fallback: 'Phone' },
        { selector: '#modal_fax', key: 'fax', fallback: 'Fax' },
        { selector: '#modal_email', key: 'email', fallback: 'Email' },
        { selector: '#modal_address', key: 'address', fallback: 'Address' },
        { selector: '#modal_address_detail', key: 'address_detail', fallback: 'Address Detail' },
        { selector: '#modal_homepage', key: 'homepage', fallback: 'Homepage' },
        { selector: '#modal_client_category', key: 'client_category', fallback: 'Client Category' },
        { selector: '#modal_bank_name', key: 'bank_name', fallback: 'Bank Name' },
        { selector: '#modal_account_number', key: 'account_number', fallback: 'Account Number' },
        { selector: '#modal_account_holder', key: 'account_holder', fallback: 'Account Holder' },
        { selector: '#modal_trade_category', key: 'trade_category', fallback: 'Trade Category' },
        { selector: '#modal_tax_type', key: 'tax_type', fallback: 'Tax Type' },
        { selector: '#modal_payment_term', key: 'payment_term', fallback: 'Payment Term' },
        { selector: '#modal_bank_file', key: 'bank_file', fallback: 'Bank File' },
        { selector: '#modal_default_account_id', key: 'default_account_id', fallback: 'Default Account' },
        { selector: '#modal_item_category', key: 'item_category', fallback: 'Item Category' },
        { selector: '#modal_note', key: 'note', fallback: 'Note' },
        { selector: '#modal_memo', key: 'memo', fallback: 'Memo' },
    ]);
    const CLIENT_QUICK_MODAL_FIELD_POLICIES = Object.freeze([
        { key: 'client_name', fallback: '거래처명' },
        { key: 'company_name', fallback: '상호' },
        { key: 'business_number', fallback: '사업자등록번호' },
        { key: 'business_status', fallback: '사업자 상태' },
        { key: 'ceo_name', fallback: '대표자명' },
        { key: 'phone', fallback: '전화번호' },
    ]);

    const {
        notify,
        escapeHtml,
        applyClientSubTypeRules,
        validateClientSubTypeRules,
        bindClientQuickFormatters,
        checkBusinessStatusWithForm,
        closeClientModalSelect2,
        clearClientModalSelectDisplays,
        normalizeDateInputValue,
        prepareClientModalControls,
        preloadClientModalControls,
        resetRrnVisibility,
        maskRrn,
        onlyNumber,
        formatBizNumber,
        formatPhone,
        formatMobile,
        formatCorpNumber,
        formatAccountNumber,
        CLIENT_OPTIONAL_CODE_SELECT_IDS,
    } = formModule;
    const {
        renderCompanyNameHistory,
        renderBusinessCertificate,
        renderRrnImage,
        renderBankFile,
    } = renderers;

    function findClientModalTemplate(templateId = '') {
        const candidates = [templateId, 'client-modal-template', 'journal-client-modal-template'].filter(Boolean);
        for (const id of candidates) {
            const template = document.getElementById(id);
            if (template instanceof HTMLTemplateElement && template.content.querySelector('#clientQuickModal')) {
                return template;
            }
        }
        return Array.from(document.querySelectorAll('template')).find((template) => template.content.querySelector('#clientQuickModal')) || null;
    }

    function ensureClientQuickModalElements(options = {}) {
        if (document.getElementById('clientQuickModal')) return true;
        const template = findClientModalTemplate(options.templateId);
        if (!(template instanceof HTMLTemplateElement)) return false;
        clientSharedModalRoot?.remove?.();
        clientSharedModalRoot = document.createElement('div');
        clientSharedModalRoot.dataset.clientSharedModalRoot = '1';
        const fragment = template.content.cloneNode(true);
        if (document.getElementById('clientModal')) fragment.querySelector('#clientModal')?.remove();
        if (document.getElementById('clientQuickModal')) fragment.querySelector('#clientQuickModal')?.remove();
        clientSharedModalRoot.appendChild(fragment);
        document.body.appendChild(clientSharedModalRoot);
        return !!document.getElementById('clientQuickModal');
    }

    function setClientSelectValue(selectEl, value, text) {
        if (!selectEl || !window.jQuery || !value) return;
        const normalizedValue = String(value);
        const $select = window.jQuery(selectEl);
        $select.find(`option[value="${normalizedValue}"]`).remove();
        $select.append(new Option(text || normalizedValue, normalizedValue, true, true));
        $select.val(normalizedValue).trigger('change');
    }

    function setClientFormValue(form, name, value = '') {
        const field = form.querySelector(`[name="${name}"]`);
        if (field) field.value = value ?? '';
    }

    function currentClientMetaPolicy() {
        const metaColumns = getCachedDataTableMetaColumns({
            metaDomain: CLIENT_META_DOMAIN,
        });

        return metaColumns.reduce((accumulator, column) => {
            const key = String(column?.key || '').trim();
            if (!key) {
                return accumulator;
            }

            accumulator[key] = {
                label: String(column?.label || key).trim() || key,
                required: column?.required === true,
            };
            return accumulator;
        }, {});
    }

    function resolveClientMetaColumn(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        const meta = currentClientMetaPolicy();
        return meta[normalizedKey] || {
            label: String(fallback || normalizedKey).trim() || normalizedKey,
            required: false,
        };
    }

    function clientFieldLabel(key, fallback = '') {
        return resolveClientMetaColumn(key, fallback).label;
    }

    function clientFieldRequirement(key, fallback = '') {
        return resolveClientMetaColumn(key, fallback).required ? 'required' : 'none';
    }

    function clientFieldStarMarkup(key, fallback = '') {
        if (resolveClientMetaColumn(key, fallback).required) {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        return '';
    }

    function isClientFilePolicyKey(key) {
        return ['business_certificate', 'rrn_image', 'bank_file'].includes(String(key || '').trim());
    }

    function isFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateClientPolicyField(field, options = {}) {
        const mode = String(options.mode || 'detail').trim();
        const key = String(field?.key || '').trim();
        if (!key) return false;

        if (mode === 'quick') {
            return CLIENT_QUICK_MODAL_FIELD_POLICIES.some((item) => item.key === key);
        }

        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isFieldVisible(input);
    }

    function hasDetailClientFileValue(key) {
        const normalizedKey = String(key || '').trim();

        if (normalizedKey === 'business_certificate') {
            const input = document.getElementById('modal_business_certificate');
            const deleteFlag = document.getElementById('delete_business_certificate');
            const list = document.getElementById('bizCertList');
            const hasExisting = String(list?.dataset?.original || '0') === '1';
            return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
        }

        if (normalizedKey === 'rrn_image') {
            const input = document.getElementById('modal_rrn_image');
            const deleteFlag = document.getElementById('delete_rrn_image');
            const list = document.getElementById('rrnImageList');
            const hasExisting = String(list?.textContent || '').trim() !== '';
            return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
        }

        if (normalizedKey === 'bank_file') {
            const input = document.getElementById('modal_bank_file');
            const deleteFlag = document.getElementById('delete_bank_file');
            const drop = document.getElementById('bankCopyUpload');
            const hasExisting = String(drop?.dataset?.original || '0') === '1';
            return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
        }

        return false;
    }

    function validateClientRequiredPolicies(fields = [], values = {}, options = {}) {
        const mode = String(options.mode || 'detail').trim();

        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || clientFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateClientPolicyField(field, options)) {
                continue;
            }

            const label = clientFieldLabel(key, field?.fallback || key);
            if (isClientFilePolicyKey(key)) {
                const hasFile = mode === 'detail' ? hasDetailClientFileValue(key) : false;
                if (!hasFile) {
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

    function collectClientDetailValues(form, formData) {
        const values = {};

        CLIENT_MODAL_FIELD_POLICIES.forEach((field) => {
            const key = String(field?.key || '').trim();
            if (!key || isClientFilePolicyKey(key)) {
                return;
            }

            const fieldName = key;
            const input = form?.elements?.namedItem?.(fieldName) || document.getElementById(`modal_${fieldName}`);
            if (!input) {
                return;
            }

            values[key] = formData.get(fieldName) ?? input.value ?? '';
        });

        return values;
    }

    function collectClientQuickValuesForValidation(formData) {
        return {
            client_name: formData.get('client_name') ?? '',
            company_name: formData.get('company_name') ?? '',
            business_number: formData.get('business_number') ?? '',
            business_status: formData.get('business_status') ?? '',
            ceo_name: formData.get('ceo_name') ?? '',
            phone: formData.get('phone') ?? '',
        };
    }

    function findClientModalLabel(fieldSelector, root = document) {
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

    function applyClientModalPolicyLabels(root = document) {
        CLIENT_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findClientModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = clientFieldLabel(field.key, field.fallback);
            const starMarkup = clientFieldStarMarkup(field.key, field.fallback);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function getClientQuickValues(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    function resolveTopVisibleModal(excludeEl = null) {
        const visibleModals = Array.from(document.querySelectorAll('.modal.show'))
            .filter((modal) => modal !== excludeEl);

        if (visibleModals.length === 0) {
            return null;
        }

        return visibleModals[visibleModals.length - 1];
    }

    function resolveModalScrollContainer(modalEl) {
        if (!modalEl) return null;

        return modalEl.querySelector('.journal-modal-body')
            || modalEl.querySelector('.transaction-modal-body')
            || modalEl.querySelector('.modal-body');
    }

    function captureClientQuickModalContext(modalEl) {
        const parentModal = resolveTopVisibleModal(modalEl);
        const parentScrollContainer = resolveModalScrollContainer(parentModal);

        clientQuickModalContext = {
            parentModalId: String(parentModal?.id || '').trim(),
            bodyPaddingRight: document.body.style.paddingRight || '',
            bodyOverflow: document.body.style.overflow || '',
            parentScrollTop: parentScrollContainer ? parentScrollContainer.scrollTop : 0,
        };
    }

    function restoreClientQuickModalContext(modalEl) {
        const context = clientQuickModalContext;
        clientQuickModalContext = null;
        if (!context) return;

        const visibleSiblingModals = Array.from(document.querySelectorAll('.modal.show'))
            .filter((modal) => modal !== modalEl);
        if (visibleSiblingModals.length === 0) {
            return;
        }

        document.body.classList.add('modal-open');
        if (context.bodyPaddingRight !== '') {
            document.body.style.paddingRight = context.bodyPaddingRight;
        } else {
            document.body.style.removeProperty('padding-right');
        }

        if (context.bodyOverflow !== '') {
            document.body.style.overflow = context.bodyOverflow;
        } else {
            document.body.style.removeProperty('overflow');
        }

        const parentModal = context.parentModalId
            ? document.getElementById(context.parentModalId)
            : resolveTopVisibleModal(modalEl);
        const parentScrollContainer = resolveModalScrollContainer(parentModal);
        if (!parentScrollContainer) {
            return;
        }

        window.requestAnimationFrame(() => {
            parentScrollContainer.scrollTop = Number(context.parentScrollTop || 0);
        });
    }

    function bringClientQuickModalToFront(modalEl) {
        if (!modalEl) return;
        const modalZIndex = 20000;
        const backdropZIndex = modalZIndex - 10;
        const apply = () => {
            modalEl.style.setProperty('z-index', String(modalZIndex), 'important');
            modalEl.querySelector('.modal-dialog')?.style.setProperty('z-index', String(modalZIndex + 1), 'important');
            const latestBackdrop = document.querySelectorAll('.modal-backdrop');
            latestBackdrop[latestBackdrop.length - 1]?.style.setProperty('z-index', String(backdropZIndex), 'important');
        };

        apply();
        [0, 16, 50, 120].forEach((delay) => window.setTimeout(apply, delay));
    }

    function captureClientDetailModalContext(modalEl) {
        const quickParent = clientQuickModalContext?.parentModalId
            ? document.getElementById(clientQuickModalContext.parentModalId)
            : null;
        const parentModal = quickParent || resolveTopVisibleModal(modalEl);
        const parentScrollContainer = resolveModalScrollContainer(parentModal);
        clientDetailModalContext = {
            parentModalId: String(parentModal?.id || '').trim(),
            bodyPaddingRight: document.body.style.paddingRight || '',
            bodyOverflow: document.body.style.overflow || '',
            parentScrollTop: parentScrollContainer ? parentScrollContainer.scrollTop : 0,
        };
    }

    function restoreClientDetailModalContext(modalEl) {
        const context = clientDetailModalContext;
        clientDetailModalContext = null;
        if (!context || !document.querySelector('.modal.show')) return;
        document.body.classList.add('modal-open');
        if (context.bodyPaddingRight !== '') document.body.style.paddingRight = context.bodyPaddingRight;
        else document.body.style.removeProperty('padding-right');
        if (context.bodyOverflow !== '') document.body.style.overflow = context.bodyOverflow;
        else document.body.style.removeProperty('overflow');
        const parentModal = context.parentModalId ? document.getElementById(context.parentModalId) : null;
        const parentScrollContainer = resolveModalScrollContainer(parentModal);
        if (parentScrollContainer) {
            window.requestAnimationFrame(() => {
                parentScrollContainer.scrollTop = Number(context.parentScrollTop || 0);
            });
        }
        modalEl.style.removeProperty('z-index');
        modalEl.querySelector('.modal-dialog')?.style.removeProperty('z-index');
    }

    function bringClientDetailModalToFront(modalEl) {
        if (!modalEl) return;
        const siblingZIndexes = Array.from(document.querySelectorAll('.modal.show'))
            .filter((candidate) => candidate !== modalEl)
            .map((candidate) => Number.parseInt(window.getComputedStyle(candidate).zIndex, 10) || 0);
        const modalZIndex = Math.max(20020, ...siblingZIndexes.map((value) => value + 20));
        const apply = () => {
            modalEl.style.setProperty('z-index', String(modalZIndex), 'important');
            modalEl.querySelector('.modal-dialog')?.style.setProperty('z-index', String(modalZIndex + 1), 'important');
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops[backdrops.length - 1]?.style.setProperty('z-index', String(modalZIndex - 10), 'important');
        };
        apply();
        [0, 16, 50, 120].forEach((delay) => window.setTimeout(apply, delay));
    }

    function bindClientDetailFromQuickOnce() {
        const form = document.getElementById('client-edit-form');
        const modalEl = document.getElementById('clientModal');
        if (!form || !modalEl || clientDetailQuickBound) return;
        clientDetailQuickBound = true;
        modalEl.addEventListener('hidden.bs.modal', () => {
            clientQuickState = null;
            restoreClientDetailModalContext(modalEl);
        });
        form.addEventListener('submit', async (event) => {
            if (!clientQuickState) return;
            event.preventDefault();
            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            const formData = new FormData(form);
            if (!formData.get('is_active')) formData.set('is_active', '1');
            const requiredMessage = validateClientRequiredPolicies(
                CLIENT_MODAL_FIELD_POLICIES,
                collectClientDetailValues(form, formData),
                { mode: 'detail' }
            );
            if (requiredMessage) {
                notify('warning', requiredMessage);
                if (submitButton) submitButton.disabled = false;
                return;
            }
            if (!validateClientSubTypeRules(form)) {
                if (submitButton) submitButton.disabled = false;
                return;
            }
            try {
                const response = await fetch(CLIENT_QUICK_API, { method: 'POST', body: formData });
                const json = await response.json();
                if (!json.success) {
                    notify('error', json.message || '거래처 저장에 실패했습니다.');
                    return;
                }
                const values = Object.fromEntries(formData.entries());
                const optionText = clientQuickState.getOptionText?.(values, json) || values.client_name || '';
                setClientSelectValue(clientQuickState.select || clientQuickState.targetSelect, json.id ?? '', optionText);
                clientQuickState.onSuccess?.(json, values);
                bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).hide();
            } catch (error) {
                notify('error', error.message || '거래처 저장 중 오류가 발생했습니다.');
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });
    }

    function openClientDetailFromQuick(values = {}, options = {}) {
        if (typeof options.openDetail === 'function') {
            options.openDetail(values);
            return;
        }
        const modalEl = document.getElementById('clientModal');
        const form = document.getElementById('client-edit-form');
        if (!modalEl || !form) return notify('error', '거래처 상세 모달을 찾을 수 없습니다.');
        initModal();
        bindClientDetailFromQuickOnce();
        clientQuickState = options;
        captureClientDetailModalContext(modalEl);
        modalEl.addEventListener('shown.bs.modal', () => bringClientDetailModalToFront(modalEl), { once: true });
        openClientCreateDetailModal({ ...values, is_active: values.is_active || '1' });
        const titleEl = document.getElementById('clientModalLabel');
        if (titleEl) titleEl.textContent = '거래처 상세 등록';
        bringClientDetailModalToFront(modalEl);
    }

    function bindClientQuickModalOnce() {
        const modalEl = document.getElementById('clientQuickModal');
        const form = modalEl?.querySelector('[data-role="quick-create-form"]');
        if (!modalEl || !form || clientQuickBound) return;
        clientQuickBound = true;
        const bodyEl = modalEl.querySelector('[data-role="quick-create-body"]');
        const messageEl = modalEl.querySelector('[data-role="quick-create-message"]');
        const submitButton = modalEl.querySelector('[data-role="quick-create-submit"]');
        const detailButton = modalEl.querySelector('[data-role="quick-create-detail"]');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
        bindClientQuickFormatters(form);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!clientQuickState) return;
            if (messageEl) messageEl.textContent = '';
            if (submitButton) submitButton.disabled = true;
            if (detailButton) detailButton.disabled = true;
            const formData = new FormData(form);
            formData.set('business_number', String(formData.get('business_number') || '').replace(/\D/g, ''));
            formData.set('is_active', formData.get('is_active') || '1');
            const requiredMessage = validateClientRequiredPolicies(
                CLIENT_QUICK_MODAL_FIELD_POLICIES,
                collectClientQuickValuesForValidation(formData),
                { mode: 'quick' }
            );
            if (requiredMessage) {
                if (messageEl) messageEl.textContent = requiredMessage;
                if (submitButton) submitButton.disabled = false;
                if (detailButton) detailButton.disabled = false;
                return;
            }
            if (!validateClientSubTypeRules(form)) {
                if (submitButton) submitButton.disabled = false;
                if (detailButton) detailButton.disabled = false;
                return;
            }
            try {
                const response = await fetch(CLIENT_QUICK_API, { method: 'POST', body: formData });
                const json = await response.json();
                if (!json.success) {
                    if (messageEl) messageEl.textContent = json.message || '거래처 저장에 실패했습니다.';
                    return;
                }
                const values = Object.fromEntries(formData.entries());
                const optionText = clientQuickState.getOptionText?.(values, json) || values.client_name || '';
                setClientSelectValue(clientQuickState.select || clientQuickState.targetSelect, json.id ?? '', optionText);
                clientQuickState.onSuccess?.(json, values);
                modal.hide();
            } catch (error) {
                if (messageEl) messageEl.textContent = error.message || '거래처 저장 중 오류가 발생했습니다.';
            } finally {
                if (submitButton) submitButton.disabled = false;
                if (detailButton) detailButton.disabled = false;
            }
        });

        form.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action="quick-biz-status"]');
            if (!button) return;
            event.preventDefault();
            await checkBusinessStatusWithForm({
                form,
                button,
                statusInput: form.querySelector('[name="business_status"]'),
                statusText: form.querySelector('[data-role="quick-biz-status-text"]'),
            });
        });

        detailButton?.addEventListener('click', () => {
            if (!clientQuickState) return;
            const values = getClientQuickValues(form);
            modal.hide();
            openClientDetailFromQuick(values, clientQuickState);
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            form.reset();
            if (bodyEl) bodyEl.innerHTML = '';
            if (messageEl) messageEl.textContent = '';
            restoreClientQuickModalContext(modalEl);
        });
    }

    async function openClientQuickCreate(options = {}) {
        ensureClientQuickModalElements(options);
        const modalEl = document.getElementById('clientQuickModal');
        const form = modalEl?.querySelector('[data-role="quick-create-form"]');
        const bodyEl = modalEl?.querySelector('[data-role="quick-create-body"]');
        const titleEl = modalEl?.querySelector('[data-role="quick-create-title"]');
        const detailButton = modalEl?.querySelector('[data-role="quick-create-detail"]');
        if (!modalEl || !form || !bodyEl) return notify('error', '거래처 빠른등록 모달을 찾을 수 없습니다.');
        await fetchDataTableMetaColumns({ metaDomain: CLIENT_META_DOMAIN }).catch(() => []);
        bindClientQuickModalOnce();
        clientQuickState = options;
        const values = options.initialValues || {};
        if (titleEl) titleEl.textContent = options.title || '거래처 빠른 등록';
        const quickClientNameLabel = clientFieldLabel('client_name', '거래처명');
        const quickCompanyNameLabel = clientFieldLabel('company_name', '상호');
        const quickBusinessNumberLabel = clientFieldLabel('business_number', '사업자등록번호');
        const quickBusinessStatusLabel = clientFieldLabel('business_status', '사업자 상태');
        const quickCeoNameLabel = clientFieldLabel('ceo_name', '대표자명');
        const quickPhoneLabel = clientFieldLabel('phone', '전화번호');
        const quickClientNameStar = clientFieldStarMarkup('client_name');
        const quickCompanyNameStar = clientFieldStarMarkup('company_name');
        const quickBusinessNumberStar = clientFieldStarMarkup('business_number');
        const quickCeoNameStar = clientFieldStarMarkup('ceo_name');
        const quickPhoneStar = clientFieldStarMarkup('phone');
        bodyEl.innerHTML = `
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">${quickClientNameLabel}${quickClientNameStar ? ` ${quickClientNameStar}` : ''}</span><input type="text" class="form-control form-control-sm" name="client_name" value="${String(values.client_name || '').replace(/"/g, '&quot;')}" required></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">${quickCompanyNameLabel}${quickCompanyNameStar ? ` ${quickCompanyNameStar}` : ''}</span><input type="text" class="form-control form-control-sm" name="company_name" value="${String(values.company_name || '').replace(/"/g, '&quot;')}"></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">${quickBusinessNumberLabel}${quickBusinessNumberStar ? ` ${quickBusinessNumberStar}` : ''}</span><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="business_number" data-format="biz" value="${formatBizNumber?.(String(values.business_number || '')) || String(values.business_number || '').replace(/"/g, '&quot;')}"><button type="button" class="btn btn-outline-primary" data-action="quick-biz-status">${quickBusinessStatusLabel}</button></div><input type="hidden" name="business_status" value="${String(values.business_status || '').replace(/"/g, '&quot;')}"><span class="form-text" data-role="quick-biz-status-text"></span></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">${quickCeoNameLabel}${quickCeoNameStar ? ` ${quickCeoNameStar}` : ''}</span><input type="text" class="form-control form-control-sm" name="ceo_name" value="${String(values.ceo_name || '').replace(/"/g, '&quot;')}"></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">${quickPhoneLabel}${quickPhoneStar ? ` ${quickPhoneStar}` : ''}</span><input type="text" class="form-control form-control-sm" name="phone" data-format="phone" value="${formatPhone?.(String(values.phone || '')) || String(values.phone || '').replace(/"/g, '&quot;')}"></label>
        `;
        applyClientSubTypeRules(form);
        if (detailButton) detailButton.hidden = !document.getElementById('clientModal') && typeof options.openDetail !== 'function';
        captureClientQuickModalContext(modalEl);
        modalEl.addEventListener('shown.bs.modal', () => bringClientQuickModalToFront(modalEl), { once: true });
        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
        bringClientQuickModalToFront(modalEl);
    }

    function initClientQuickCreateButtons(bindings = []) {
        bindings.forEach((binding) => {
            if (!binding?.button || binding.button.dataset.clientQuickCreateBound === 'true') return;
            binding.button.dataset.clientQuickCreateBound = 'true';
            binding.button.addEventListener('click', () => openClientQuickCreate(binding));
        });
    }

    function resetClientFileInputs() {
        ['delete_business_certificate', 'delete_rrn_image', 'delete_bank_file'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '0';
        });
        ['modal_business_certificate', 'modal_rrn_image', 'modal_bank_file'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    function resolveSelectOptionValue(select, value) {
        const raw = String(value ?? '').trim();
        if (!select || raw === '') return raw;
        const options = Array.from(select.options || []);
        if (options.some((option) => String(option.value ?? '').trim() === raw)) return raw;
        return options.find((option) => String(option.textContent ?? '').trim() === raw)?.value || raw;
    }

    function fillModal(data) {
        Object.keys(data).forEach((key) => {
            if (['id', 'business_certificate', 'rrn_image', 'bank_file'].includes(key)) return;
            const el = document.getElementById(`modal_${key}`);
            if (!el) return;
            let value = data[key] ?? '';
            if (key === 'rrn') {
                const raw = onlyNumber(value);
                el.dataset.real = raw;
                resetRrnVisibility();
                setTimeout(() => {
                    el.value = maskRrn(raw);
                }, 0);
                return;
            }
            if (CLIENT_OPTIONAL_CODE_SELECT_IDS.includes(el.id) || el.id === 'modal_bank_name') {
                value = resolveSelectOptionValue(el, value);
            }
            if (el.dataset.format === 'biz') value = formatBizNumber(value);
            else if (el.dataset.format === 'corp') value = formatCorpNumber(value);
            else if (el.dataset.format === 'mobile') value = formatMobile(value);
            else if (el.dataset.format === 'phone' || el.dataset.format === 'fax') value = formatPhone(value);
            else if (el.dataset.format === 'account_number') value = formatAccountNumber?.(value, data.bank_name || '') || value;
            el.value = value;
            if ((CLIENT_OPTIONAL_CODE_SELECT_IDS.includes(el.id) || ['modal_bank_name', 'modal_default_account_id'].includes(el.id)) && window.jQuery?.fn?.select2 && window.jQuery(el).hasClass('select2-hidden-accessible')) {
                window.jQuery(el).val(value || null).trigger('change.select2');
            }
        });
        renderCompanyNameHistory(data.company_name_history || []);
        renderBusinessCertificate(data.business_certificate || '', notify);
        renderRrnImage(data.rrn_image || '', notify);
        renderBankFile(data.bank_file || '', notify);
        renderClientSystemInfo(data);
        applyClientModalPolicyLabels(document);
    }

    function renderClientSystemInfo(data = {}) {
        const container = document.getElementById('clientSystemInfoFields');
        if (!container) return;

        const fields = [
            { key: 'id', label: 'ID' },
            { key: 'sort_no', label: '순번' },
            { key: 'created_at', label: '생성일시', type: 'datetime' },
            { key: 'created_by_name', label: '생성자' },
            { key: 'updated_at', label: '수정일시', type: 'datetime' },
            { key: 'updated_by_name', label: '수정자' },
            { key: 'deleted_at', label: '삭제일시', type: 'datetime' },
            { key: 'deleted_by_name', label: '삭제자' },
        ];

        container.replaceChildren(...fields.map((field) => {
            const item = document.createElement('div');
            item.className = 'client-system-info-field';

            const label = document.createElement('span');
            label.className = 'client-system-info-label';
            label.textContent = field.label;

            const value = document.createElement('span');
            value.className = 'client-system-info-value';
            const rawValue = field.type === 'datetime'
                ? formatDateDisplay(data[field.key])
                : data[field.key];
            value.textContent = rawValue === null || rawValue === undefined ? '' : String(rawValue);

            item.append(label, value);
            return item;
        }));
    }

    function initModal() {
        const modalEl = document.getElementById('clientModal');
        if (!modalEl) return;
        state.clientModal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
        const excelModalEl = document.getElementById('clientExcelModal');
        state.excelModal = excelModalEl ? bootstrap.Modal.getOrCreateInstance(excelModalEl) : null;
        if (clientModalInitialized) return;
        clientModalInitialized = true;
        applyClientModalPolicyLabels(document);
        bindModalCardCollapses(modalEl, { resetOnShow: true });
        renderClientSystemInfo();
        modalEl.addEventListener('hide.bs.modal', closeClientModalSelect2);
        modalEl.addEventListener('hidden.bs.modal', () => {
            closeClientModalSelect2();
            document.getElementById('bizCertHelp')?.style.setProperty('display', 'block');
            document.getElementById('client-edit-form')?.reset();
            document.getElementById('bankCopyUpload')?.setAttribute('data-original', '0');
            document.getElementById('delete_bank_file')?.setAttribute('value', '0');
            document.getElementById('modal_business_certificate')?.setAttribute('value', '');
            document.getElementById('dropZoneTextBiz') && (document.getElementById('dropZoneTextBiz').innerHTML = '여기에 파일을 드래그하거나 클릭하여 선택하세요.<br>(PDF, JPG, PNG)');
            document.getElementById('certStatusIcon') && (document.getElementById('certStatusIcon').innerHTML = '');
            document.getElementById('modal_rrn_image')?.setAttribute('value', '');
            document.getElementById('delete_rrn_image')?.setAttribute('value', '0');
            document.getElementById('rrnImageList') && (document.getElementById('rrnImageList').innerHTML = '');
            document.getElementById('dropZoneTextRrn') && (document.getElementById('dropZoneTextRrn').innerHTML = '파일 선택 또는 클릭<br>(JPG, PNG)');
            const rrnField = document.getElementById('modal_rrn');
            if (rrnField) {
                rrnField.value = '';
                rrnField.dataset.real = '';
            }
            resetRrnVisibility();
            renderClientSystemInfo();
            applyClientModalPolicyLabels(document);
        });
        formModule.bindDateIconPicker();
        modalEl.addEventListener('shown.bs.modal', () => {
            formModule.bindAdminDateInputs();
            applyClientModalPolicyLabels(document);
        });
    }

    function openClientCreateDetailModal(initialValues = {}) {
        const form = document.getElementById('client-edit-form');
        if (form) form.reset();
        clearClientModalSelectDisplays();
        renderCompanyNameHistory([]);
        applyClientSubTypeRules(form || document);
        window.jQuery('#modal_client_id').val('');
        window.jQuery('#btnDeleteClient').hide();
        window.isNewClient = true;
        const titleEl = document.getElementById('clientModalLabel');
        if (titleEl) titleEl.textContent = '거래처 신규 등록';
        document.getElementById('bizCertList') && (document.getElementById('bizCertList').innerHTML = '');
        document.getElementById('bizCertHelp')?.style.setProperty('display', 'block');
        document.getElementById('modal_business_certificate') && (document.getElementById('modal_business_certificate').value = '');
        document.getElementById('delete_business_certificate') && (document.getElementById('delete_business_certificate').value = '0');
        document.getElementById('dropZoneTextBiz') && (document.getElementById('dropZoneTextBiz').innerHTML = '여기에 파일을 드래그하거나 클릭하여 선택하세요.<br>(PDF, JPG, PNG)');
        document.getElementById('certStatusIcon') && (document.getElementById('certStatusIcon').innerHTML = '');
        document.getElementById('bankCopyText') && (document.getElementById('bankCopyText').innerHTML = '여기에 파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)');
        document.getElementById('bankCopyUpload')?.setAttribute('data-original', '0');
        document.getElementById('delete_bank_file') && (document.getElementById('delete_bank_file').value = '0');
        document.getElementById('modal_bank_file') && (document.getElementById('modal_bank_file').value = '');
        document.getElementById('modal_rrn_image') && (document.getElementById('modal_rrn_image').value = '');
        document.getElementById('delete_rrn_image') && (document.getElementById('delete_rrn_image').value = '0');
        document.getElementById('rrnImageList') && (document.getElementById('rrnImageList').innerHTML = '');
        document.getElementById('dropZoneTextRrn') && (document.getElementById('dropZoneTextRrn').innerHTML = '파일 선택 또는 클릭<br>(JPG, PNG)');
        const dateEl = document.getElementById('modal_registration_date');
        if (dateEl) dateEl.value = new Date().toISOString().slice(0, 10);
        Object.entries(initialValues || {}).forEach(([key, value]) => {
            const field = document.getElementById(`modal_${key}`) || form?.elements?.namedItem?.(key);
            if (!field || value == null) return;
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        applyClientModalPolicyLabels(document);
        renderClientSystemInfo();
        state.clientModal?.show();
        void prepareClientModalControls().catch((error) => {
            console.error('[client] modal controls prepare failed', error);
            notify('error', '거래처 입력 항목 준비 중 오류가 발생했습니다.');
        });
    }

    function bindModalEvents($) {
        $(document).off('submit', '#client-edit-form');
        $(document).on('submit', '#client-edit-form', function (event) {
            event.preventDefault();
            const form = this;
            form.querySelectorAll('.admin-date').forEach((input) => {
                input.value = normalizeDateInputValue(input.value);
            });
            const formData = new FormData(form);
            formData.set('account_number', formModule.unformatAccountNumber?.(formData.get('account_number') || '') || '');
            const requiredMessage = validateClientRequiredPolicies(
                CLIENT_MODAL_FIELD_POLICIES,
                collectClientDetailValues(form, formData),
                { mode: 'detail' }
            );
            if (requiredMessage) {
                notify('warning', requiredMessage);
                return;
            }
            if (!validateClientSubTypeRules(form)) return;
            const rrnInput = document.getElementById('modal_rrn');
            if (rrnInput) {
                const realVal = onlyNumber(rrnInput.dataset.real || '');
                formData.set('rrn', realVal !== '' ? realVal : '');
            }
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done((res) => {
                if (!res.success) return notify('error', res.message || '저장에 실패했습니다.');
                state.clientModal?.hide();
                state.clientTable?.ajax.reload(null, false);
                notify('success', '저장이 완료되었습니다.');
            }).fail(() => {
                notify('error', '서버 오류가 발생했습니다.');
            }).always(() => {
                if (button) button.disabled = false;
            });
        });
        $('#btnDeleteClient').off('click').on('click', async function () {
            const id = $('#modal_client_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;
            try {
                await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '거래처를 휴지통으로 이동 중', trashChanged: true }, async () => {
                    const res = await $.post(API.DELETE, { id });
                    if (!res.success) throw new Error(res.message || '삭제에 실패했습니다.');
                    notify('success', '삭제가 완료되었습니다.');
                    await new Promise(resolve => state.clientTable?.ajax.reload(() => resolve(), false));
                    state.clientModal?.hide();
                });
            } catch (error) {
                notify('error', error.message || '삭제 중 오류가 발생했습니다.');
            }
        });
    }

    return {
        openClientQuickCreate,
        initClientQuickCreateButtons,
        initModal,
        openClientCreateDetailModal,
        bindModalEvents,
        fillModal,
        resetClientFileInputs,
        preloadClientModalControls,
        prepareClientModalControls,
    };
}
