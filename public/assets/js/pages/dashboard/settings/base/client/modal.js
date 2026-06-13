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
    let clientSharedModalRoot = null;

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

    function getClientQuickValues(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    function bringClientQuickModalToFront(modalEl) {
        if (!modalEl) return;
        const modalZIndex = 20000;
        const backdropZIndex = modalZIndex - 10;
        modalEl.style.zIndex = String(modalZIndex);
        modalEl.querySelector('.modal-dialog')?.style.setProperty('z-index', String(modalZIndex + 1));
        const latestBackdrop = document.querySelectorAll('.modal-backdrop');
        latestBackdrop[latestBackdrop.length - 1]?.style.setProperty('z-index', String(backdropZIndex));
    }

    function bindClientDetailFromQuickOnce() {
        const form = document.getElementById('client-edit-form');
        const modalEl = document.getElementById('clientModal');
        if (!form || !modalEl || clientDetailQuickBound) return;
        clientDetailQuickBound = true;
        modalEl.addEventListener('hidden.bs.modal', () => {
            clientQuickState = null;
        });
        form.addEventListener('submit', async (event) => {
            if (!clientQuickState) return;
            event.preventDefault();
            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            const formData = new FormData(form);
            if (!formData.get('is_active')) formData.set('is_active', '1');
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
        bindClientDetailFromQuickOnce();
        clientQuickState = options;
        form.reset();
        setClientFormValue(form, 'id', '');
        setClientFormValue(form, 'client_name', values.client_name || '');
        setClientFormValue(form, 'company_name', values.company_name || '');
        setClientFormValue(form, 'business_number', values.business_number || '');
        setClientFormValue(form, 'ceo_name', values.ceo_name || '');
        setClientFormValue(form, 'phone', values.phone || '');
        setClientFormValue(form, 'is_active', '1');
        const titleEl = document.getElementById('clientModalLabel');
        if (titleEl) titleEl.textContent = '거래처 상세 등록';
        const deleteButton = document.getElementById('btnDeleteClient');
        if (deleteButton) deleteButton.style.display = 'none';
        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
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
        });
    }

    function openClientQuickCreate(options = {}) {
        ensureClientQuickModalElements(options);
        const modalEl = document.getElementById('clientQuickModal');
        const form = modalEl?.querySelector('[data-role="quick-create-form"]');
        const bodyEl = modalEl?.querySelector('[data-role="quick-create-body"]');
        const titleEl = modalEl?.querySelector('[data-role="quick-create-title"]');
        const detailButton = modalEl?.querySelector('[data-role="quick-create-detail"]');
        if (!modalEl || !form || !bodyEl) return notify('error', '거래처 빠른등록 모달을 찾을 수 없습니다.');
        bindClientQuickModalOnce();
        clientQuickState = options;
        const values = options.initialValues || {};
        if (titleEl) titleEl.textContent = options.title || '거래처 빠른 등록';
        bodyEl.innerHTML = `
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">거래처명</span><input type="text" class="form-control form-control-sm" name="client_name" value="${String(values.client_name || '').replace(/"/g, '&quot;')}" required></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">상호</span><input type="text" class="form-control form-control-sm" name="company_name" value="${String(values.company_name || '').replace(/"/g, '&quot;')}"></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">사업자등록번호</span><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="business_number" data-format="biz" value="${formatBizNumber?.(String(values.business_number || '')) || String(values.business_number || '').replace(/"/g, '&quot;')}"><button type="button" class="btn btn-outline-primary" data-action="quick-biz-status">상태확인</button></div><input type="hidden" name="business_status" value="${String(values.business_status || '').replace(/"/g, '&quot;')}"><span class="form-text" data-role="quick-biz-status-text"></span></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">대표자명</span><input type="text" class="form-control form-control-sm" name="ceo_name" value="${String(values.ceo_name || '').replace(/"/g, '&quot;')}"></label>
            <label class="form-label w-100"><span class="fw-bold d-block mb-1">전화번호</span><input type="text" class="form-control form-control-sm" name="phone" data-format="phone" value="${formatPhone?.(String(values.phone || '')) || String(values.phone || '').replace(/"/g, '&quot;')}"></label>
        `;
        applyClientSubTypeRules(form);
        if (detailButton) detailButton.hidden = !document.getElementById('clientModal') && typeof options.openDetail !== 'function';
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
    }

    function initModal() {
        const modalEl = document.getElementById('clientModal');
        state.clientModal = modalEl ? new bootstrap.Modal(modalEl, { focus: false }) : null;
        state.excelModal = document.getElementById('clientExcelModal') ? new bootstrap.Modal(document.getElementById('clientExcelModal')) : null;
        if (!modalEl) return;
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
        });
        formModule.bindDateIconPicker();
        modalEl.addEventListener('shown.bs.modal', () => formModule.bindAdminDateInputs());
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
            if (formData.get('default_account_id') === '__none__') formData.set('default_account_id', '');
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
        $('#btnDeleteClient').off('click').on('click', function () {
            const id = $('#modal_client_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;
            $.post(API.DELETE, { id }).done((res) => {
                if (!res.success) return alert(res.message || '삭제에 실패했습니다.');
                notify('success', '삭제가 완료되었습니다.');
                state.clientTable?.ajax.reload(null, false);
                state.clientModal?.hide();
            });
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
