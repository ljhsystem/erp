function ensureModalRoot() {
    let modal = document.getElementById('quickCreateModal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'quickCreateModal';
    modal.tabIndex = -1;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form data-role="quick-create-form">
                    <div class="modal-header">
                        <h5 class="modal-title" data-role="quick-create-title">빠른 생성</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body" data-role="quick-create-body"></div>
                    <div class="modal-footer">
                        <div class="me-auto text-danger small" data-role="quick-create-message"></div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        <button type="submit" class="btn btn-success btn-sm" data-role="quick-create-submit">저장</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

function getConfigs() {
    return {
        client: {
            title: '거래처 빠른 생성',
            api: '/api/settings/base-info/client/save',
            fields: [
                { name: 'client_name', label: '거래처명', required: true, placeholder: '거래처명을 입력하세요' },
                { name: 'business_number', label: '사업자번호', placeholder: '000-00-00000' },
                { name: 'phone', label: '전화번호', placeholder: '02-0000-0000' },
            ],
            getOptionText(values) {
                return values.client_name || '';
            },
        },
        project: {
            title: '프로젝트 빠른 생성',
            api: '/api/settings/base-info/project/save',
            fields: [
                { name: 'project_name', label: '프로젝트명', required: true, placeholder: '프로젝트명을 입력하세요' },
                { name: 'construction_name', label: '공사명', placeholder: '공사명을 입력하세요' },
            ],
            getOptionText(values) {
                return values.project_name || '';
            },
        },
        account: {
            title: '계좌 빠른 생성',
            api: '/api/settings/base-info/bank-account/save',
            fields: [
                { name: 'account_name', label: '계좌명', required: true, placeholder: '계좌명을 입력하세요' },
                { name: 'bank_name', label: '은행명', placeholder: '은행명을 입력하세요' },
                { name: 'account_number', label: '계좌번호', placeholder: '계좌번호를 입력하세요' },
                { name: 'account_holder', label: '예금주', placeholder: '예금주를 입력하세요' },
            ],
            getOptionText(values) {
                return values.account_name || '';
            },
        },
        card: {
            title: '카드 빠른 생성',
            api: '/api/settings/base-info/card/save',
            fields: [
                { name: 'card_name', label: '카드명', required: true, placeholder: '카드명을 입력하세요' },
                {
                    name: 'card_type',
                    label: '카드유형',
                    type: 'select',
                    required: true,
                    value: 'corporate',
                    options: [
                        { value: 'corporate', label: '법인' },
                        { value: 'personal', label: '개인' },
                        { value: 'virtual', label: '가상' },
                    ],
                },
                { name: 'card_number', label: '카드번호', placeholder: '0000-0000-0000-0000' },
            ],
            getOptionText(values) {
                return values.card_name || '';
            },
        },
    };
}

function renderField(field, context) {
    const value = typeof field.value === 'function' ? field.value(context) : (field.value ?? '');
    const required = field.required ? ' required' : '';
    const maxlength = field.maxlength ? ` maxlength="${field.maxlength}"` : '';
    const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';

    if (field.type === 'select') {
        const options = (field.options || []).map((option) => {
            const selected = String(option.value) === String(value) ? ' selected' : '';
            return `<option value="${escapeHtml(option.value)}"${selected}>${escapeHtml(option.label)}</option>`;
        }).join('');

        return `
            <label class="form-label w-100">
                <span class="fw-bold d-block mb-1">${escapeHtml(field.label)}</span>
                <select class="form-select form-select-sm" name="${escapeHtml(field.name)}"${required}>
                    ${options}
                </select>
            </label>
        `;
    }

    return `
        <label class="form-label w-100">
            <span class="fw-bold d-block mb-1">${escapeHtml(field.label)}</span>
            <input type="text"
                   class="form-control form-control-sm"
                   name="${escapeHtml(field.name)}"
                   value="${escapeHtml(value)}"${placeholder}${maxlength}${required}>
        </label>
    `;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function setSelectValue(selectEl, value, text) {
    if (!selectEl || !window.jQuery) {
        return;
    }

    const normalizedValue = String(value ?? '').trim();
    if (normalizedValue === '') {
        return;
    }

    const $select = window.jQuery(selectEl);
    $select.find(`option[value="${normalizedValue}"]`).remove();
    $select.append(new Option(text || normalizedValue, normalizedValue, true, true));
    $select.val(normalizedValue).trigger('change');
}

export function initQuickCreateButtons(bindings = []) {
    if (!Array.isArray(bindings) || bindings.length === 0) {
        return;
    }

    const configs = getConfigs();
    const modalEl = ensureModalRoot();
    const modal = new bootstrap.Modal(modalEl, { focus: false });
    const form = modalEl.querySelector('[data-role="quick-create-form"]');
    const titleEl = modalEl.querySelector('[data-role="quick-create-title"]');
    const bodyEl = modalEl.querySelector('[data-role="quick-create-body"]');
    const messageEl = modalEl.querySelector('[data-role="quick-create-message"]');
    const submitButton = modalEl.querySelector('[data-role="quick-create-submit"]');

    let activeBinding = null;
    let activeConfig = null;

    bindings.forEach((binding) => {
        if (!binding?.button || !binding?.type || !configs[binding.type]) {
            return;
        }

        binding.button.addEventListener('click', () => {
            activeBinding = binding;
            activeConfig = configs[binding.type];

            titleEl.textContent = activeConfig.title;
            messageEl.textContent = '';

            const context = typeof binding.getContext === 'function' ? binding.getContext() : {};
            bodyEl.innerHTML = activeConfig.fields.map((field) => renderField(field, context)).join('');

            if (typeof binding.applyDefaults === 'function') {
                binding.applyDefaults(form, context);
            }

            modal.show();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!activeBinding || !activeConfig) {
            return;
        }

        messageEl.textContent = '';
        submitButton.disabled = true;

        const formData = new FormData(form);
        if (typeof activeBinding.extendFormData === 'function') {
            activeBinding.extendFormData(formData);
        }

        try {
            const response = await fetch(activeConfig.api, {
                method: 'POST',
                body: formData,
            });
            const json = await response.json();

            if (!json.success) {
                messageEl.textContent = json.message || '저장에 실패했습니다.';
                return;
            }

            const values = Object.fromEntries(formData.entries());
            const optionText = typeof activeBinding.getOptionText === 'function'
                ? activeBinding.getOptionText(values)
                : activeConfig.getOptionText(values);

            setSelectValue(activeBinding.select, json.id ?? '', optionText);

            if (typeof activeBinding.onSuccess === 'function') {
                activeBinding.onSuccess(json, values);
            }

            modal.hide();
        } catch (error) {
            messageEl.textContent = error.message || '저장 중 오류가 발생했습니다.';
        } finally {
            submitButton.disabled = false;
        }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        form.reset();
        bodyEl.innerHTML = '';
        messageEl.textContent = '';
        activeBinding = null;
        activeConfig = null;
        submitButton.disabled = false;
    });
}
