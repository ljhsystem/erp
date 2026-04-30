// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.select2.js'

function ensureJQuery() {
    const $ = window.jQuery || window.$;

    if (!$) {
        throw new Error('[picker.select2] jQuery가 먼저 로드되어야 합니다.');
    }

    return $;
}

function ensureSelect2($) {
    if (!$.fn || !$.fn.select2) {
        throw new Error('[picker.select2] Select2가 먼저 로드되어야 합니다.');
    }
}

let modalCleanupBound = false;

function bindModalCleanup() {
    if (modalCleanupBound) {
        return;
    }

    modalCleanupBound = true;

    document.addEventListener('hide.bs.modal', (event) => {
        closeSelect2InModal(event.target);
    }, true);

    document.addEventListener('hidden.bs.modal', (event) => {
        closeSelect2InModal(event.target);
    }, true);
}

function closeSelect2InModal(modal) {
    if (!modal?.querySelectorAll) {
        return;
    }

    const $ = window.jQuery || window.$;
    if (!$?.fn?.select2) {
        return;
    }

    modal.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
        try {
            $(select).select2('close');
        } catch (error) {
            console.warn('[picker.select2] Select2 닫기 실패', error);
        }
    });
}

function normalizeOptions(options = {}) {
    return {
        width: '100%',
        language: 'ko',
        allowClear: false,
        placeholder: '선택',
        dropdownAutoWidth: false,
        ...options
    };
}

function resolveDropdownParent(el, options = {}) {
    const $ = ensureJQuery();

    if (options.dropdownParent) {
        return options.dropdownParent;
    }

    const modal = el.closest('.modal');
    if (modal) {
        return $(modal);
    }

    return $(document.body);
}

function ensureEmptyOption(el, placeholder = '선택') {
    if (!el || el.multiple) {
        return;
    }

    const hasEmptyOption = Array.from(el.options || [])
        .some((option) => option.value === '');

    if (hasEmptyOption) {
        return;
    }

    el.insertBefore(new Option(placeholder || '선택', '', false, false), el.firstChild);
}

function createSelect2(target, options = {}) {
    const $ = ensureJQuery();
    ensureSelect2($);
    bindModalCleanup();

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) {
        console.warn('[picker.select2] 대상 요소를 찾을 수 없습니다.', target);
        return null;
    }

    const config = normalizeOptions(options);
    ensureEmptyOption(el, config.placeholder);

    const finalOptions = {
        ...config,
        width: '100%',
        allowClear: false,
        dropdownAutoWidth: false,
        dropdownParent: resolveDropdownParent(el, config)
    };

    const $el = $(el);

    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2(finalOptions);

    return $el;
}

function destroySelect2(target) {
    const $ = ensureJQuery();
    ensureSelect2($);

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) return;

    const $el = $(el);

    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }
}

function setValue(target, value, trigger = true) {
    const $ = ensureJQuery();

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) return;

    const $el = $(el);
    $el.val(value);

    if (trigger) {
        $el.trigger('change');
    }
}

function clearValue(target, trigger = true) {
    setValue(target, null, trigger);
}

function reloadOptions(target, items = [], valueKey = 'id', textKey = 'text', selectedValue = null) {
    const $ = ensureJQuery();

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) return;

    const $el = $(el);

    $el.empty();

    items.forEach(item => {
        const option = new Option(
            item[textKey] ?? '',
            item[valueKey] ?? '',
            false,
            false
        );
        $el.append(option);
    });

    if (selectedValue !== null && selectedValue !== undefined) {
        $el.val(selectedValue);
    }

    $el.trigger('change');
}

function createAjaxSelect2(target, options = {}) {
    const $ = ensureJQuery();
    ensureSelect2($);

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) {
        console.warn('[picker.select2] 대상 요소를 찾을 수 없습니다.', target);
        return null;
    }

    const {
        url,
        method = 'GET',
        delay = 250,
        minimumInputLength = 0,
        dataBuilder,
        processResults,
        ...rest
    } = options;

    if (!url) {
        throw new Error('[picker.select2] AJAX Select2는 url이 필요합니다.');
    }

    const finalOptions = normalizeOptions({
        ...rest,
        minimumInputLength,
        ajax: {
            url,
            type: method,
            delay,
            data(params) {
                if (typeof dataBuilder === 'function') {
                    return dataBuilder(params);
                }

                return {
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults(data, params) {
                if (typeof processResults === 'function') {
                    return processResults(data, params);
                }

                const rows = data?.data ?? data?.items ?? [];

                return {
                    results: rows.map(row => ({
                        id: row.id ?? row.code ?? row.value,
                        text: row.text ?? row.name ?? row.label ?? row.project_name ?? row.client_name ?? ''
                    }))
                };
            }
        }
    });

    return createSelect2(el, finalOptions);
}

const PickerSelect2 = {
    create: createSelect2,
    createAjax: createAjaxSelect2,
    destroy: destroySelect2,
    setValue,
    clearValue,
    reloadOptions
};

window.PickerSelect2 = PickerSelect2;

export { PickerSelect2 };
