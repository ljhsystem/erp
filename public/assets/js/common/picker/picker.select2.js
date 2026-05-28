// 경로: PROJECT_ROOT . '/public/assets/js/common/picker/picker.select2.js'

const AppEvents = window.AppEvents || {};
const onDocument = AppEvents.onDocument || ((type, handler, options = false) => {
    document.addEventListener(type, handler, options);
    return () => document.removeEventListener(type, handler, options);
});

const onJQDocument = AppEvents.onJQDocument || ((type, handler, options = false) => {
    const $ = window.jQuery || window.$;
    if (!$?.fn?.on) {
        return () => {};
    }

    $(document).on(type, handler, options);
    return () => $(document).off(type, handler, options);
});

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
let searchFocusBound = false;
let escapeCloseBound = false;
let keyboardAssistBound = false;
let searchFocusSeq = 0;

function focusOpenSelect2Search() {
    const seq = ++searchFocusSeq;
    const focus = () => {
        if (seq !== searchFocusSeq) return false;
        const search = document.querySelector('.select2-container--open .select2-search__field');
        if (!search) return false;
        search.focus?.();
        if (String(search.value || '') === '') {
            search.select?.();
        }
        return document.activeElement === search;
    };

    [0, 16, 50].forEach((delay) => {
        window.setTimeout(focus, delay);
    });
    window.requestAnimationFrame?.(focus);
}

function highlightedResult(openContainer) {
    return openContainer?.querySelector?.(
        '.select2-results__option--highlighted[aria-selected], .select2-results__option--highlighted'
    ) || null;
}

function selectableResults(openContainer) {
    return Array.from(openContainer?.querySelectorAll?.(
        '.select2-results__option[role="option"]:not(.select2-results__option--disabled)'
    ) || []).filter((option) => {
        const text = String(option.textContent || '').trim();
        return text !== '' && option.getAttribute('aria-disabled') !== 'true';
    });
}

function isCommonControlResult(option) {
    const text = String(option?.textContent || '').trim();
    return text === COMMON_NONE_OPTION_TEXT
        || text === COMMON_ADD_OPTION_TEXT
        || text === ''
        || /^\+\s*/.test(text);
}

function selectableDataResults(openContainer) {
    return selectableResults(openContainer).filter((option) => !isCommonControlResult(option));
}

function highlightResult(option) {
    if (!option) return false;
    const openContainer = option.closest?.('.select2-container--open');
    openContainer?.querySelectorAll?.('.select2-results__option--highlighted').forEach((item) => {
        item.classList.remove('select2-results__option--highlighted');
        item.setAttribute('aria-selected', 'false');
    });
    option.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, cancelable: true, view: window }));
    option.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true, cancelable: true, view: window }));
    option.classList.add('select2-results__option--highlighted');
    option.setAttribute('aria-selected', 'true');
    return true;
}

function selectResult(option) {
    if (!option) return false;
    highlightResult(option);
    option.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    option.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    return true;
}

function bindKeyboardAssist() {
    if (keyboardAssistBound) {
        return;
    }

    keyboardAssistBound = true;
    onDocument('keydown', (event) => {
        const search = event.target?.closest?.('.select2-container--open .select2-search__field');
        if (!search) return;

        searchFocusSeq += 1;
        if (event.key === 'Enter') {
            const openContainer = document.querySelector('.select2-container--open');
            if (!openContainer) return;

            const highlighted = highlightedResult(openContainer);
            if (highlighted && !isCommonControlResult(highlighted)) return;

            const options = selectableDataResults(openContainer);
            if (!options.length) return;

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            selectResult(options[0]);
            return;
        }

        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;

        window.setTimeout(() => {
            const openContainer = document.querySelector('.select2-container--open');
            if (!openContainer) return;

            const highlighted = highlightedResult(openContainer);
            const hasSearchTerm = String(search.value || '').trim() !== '';
            if (highlighted && (!hasSearchTerm || !isCommonControlResult(highlighted))) return;

            const options = selectableDataResults(openContainer);
            if (!options.length) return;
            const index = event.key === 'ArrowUp' ? options.length - 1 : 0;
            highlightResult(options[index]);
        }, 0);
    }, true);
}

function keepOpenSelect2SearchFocused(event) {
    const openContainer = document.querySelector('.select2-container--open');
    if (!openContainer) return;
    const search = openContainer.querySelector('.select2-search__field');
    if (!search) return;
    const target = event?.target;
    if (target === search || search.contains?.(target)) return;
    if (openContainer.contains(target)) return;

    window.setTimeout(() => {
        if (!document.querySelector('.select2-container--open')) return;
        search.focus?.();
    }, 0);
}

function bindSearchFocus() {
    if (searchFocusBound) {
        return;
    }

    const $ = window.jQuery || window.$;
    if (!$) {
        return;
    }

    searchFocusBound = true;
    if (onJQDocument) {
        onJQDocument('select2:open.pickerSearchFocus', focusOpenSelect2Search);
        onJQDocument('select2:close.pickerSearchFocus', () => {});
    }
    onDocument('focusin', keepOpenSelect2SearchFocused, true);
}

function bindEscapeClose() {
    if (escapeCloseBound) {
        return;
    }

    escapeCloseBound = true;

    const handler = (event) => {
        if (event.key !== 'Escape') return;
        if (!closeOpenSelect2Dropdowns()) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    };

    onDocument('keydown', handler, true);

}

function closeOpenSelect2Dropdowns() {
    if (!document.querySelector('.select2-container--open')) {
        return false;
    }

    const $ = window.jQuery || window.$;
    if (!$?.fn?.select2) {
        return false;
    }

    document.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
        try {
            $(select).select2('close');
        } catch (error) {
            console.warn('[picker.select2] Select2 close failed', error);
        }
    });

    return true;
}

function bindModalCleanup() {
    if (modalCleanupBound) {
        return;
    }

    modalCleanupBound = true;

    onDocument('hide.bs.modal', (event) => {
        closeSelect2InModal(event.target);
    }, true);

    onDocument('hidden.bs.modal', (event) => {
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

const COMMON_NONE_OPTION_ID = '__none__';
const COMMON_ADD_OPTION_ID = '__add__';
const COMMON_NONE_OPTION_TEXT = '선택(없음)';
const COMMON_ADD_OPTION_TEXT = '+추가';

function isCommonNoneOption(item = {}) {
    const id = String(item.id ?? '').trim();
    const text = String(item.text ?? '').trim();
    return id === '' || id === COMMON_NONE_OPTION_ID || text === COMMON_NONE_OPTION_TEXT;
}

function isCommonAddOption(item = {}) {
    const id = String(item.id ?? '').trim();
    const text = String(item.text ?? '').trim();
    return id === COMMON_ADD_OPTION_ID || /^\+\s*.*추가$/.test(text);
}

function normalizeAjaxResultItem(row = {}) {
    if (row && typeof row === 'object' && Object.prototype.hasOwnProperty.call(row, 'id') && Object.prototype.hasOwnProperty.call(row, 'text')) {
        return row;
    }

    return {
        ...row,
        id: row.id ?? row.code ?? row.value,
        text: row.text
            ?? row.display_name
            ?? row.name
            ?? row.label
            ?? row.project_name
            ?? row.client_name
            ?? row.account_name
            ?? row.bank_account_name
            ?? row.employee_name
            ?? row.card_name
            ?? ''
    };
}

function withCommonAjaxOptions(result = {}, params = {}, options = {}) {
    const page = Number(params?.page || 1);
    const hasSearchTerm = String(params?.term || '').trim() !== '';
    const includeCommonNone = options.includeCommonNone !== false && !hasSearchTerm;
    const includeCommonAdd = options.includeCommonAdd !== false;
    const quickAddEnabled = options.quickAddEnabled === true || options.includeCommonAdd === true;
    const noneText = options.commonNoneText || COMMON_NONE_OPTION_TEXT;
    const addText = options.commonAddText || COMMON_ADD_OPTION_TEXT;
    const sourceResults = Array.isArray(result?.results) ? result.results : [];
    const normalized = sourceResults
        .map(normalizeAjaxResultItem)
        .filter((item) => !isCommonNoneOption(item) && !isCommonAddOption(item));

    const results = page <= 1 && includeCommonNone
        ? [{ id: COMMON_NONE_OPTION_ID, text: noneText, isNone: true }, ...normalized]
        : normalized;

    if (page <= 1 && includeCommonAdd) {
        results.push({ id: COMMON_ADD_OPTION_ID, text: addText, isAdd: true, disabled: !quickAddEnabled });
    }

    return {
        ...result,
        results
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

function shouldIncludeCommonAdd(el, options = {}) {
    return options.includeCommonAdd !== false
        && el?.dataset?.hideCommonAdd !== 'true';
}

function hasQuickAddHandler(el, options = {}) {
    return options.quickAddEnabled === true
        || el?.dataset?.quickAddEnabled === 'true';
}

function ensureCommonStaticOptions(el, options = {}) {
    if (!el || el.multiple) {
        return;
    }

    Array.from(el.options || []).forEach((option) => {
        const item = { id: option.value, text: option.textContent };
        if (isCommonNoneOption(item) || isCommonAddOption(item)) {
            option.remove();
        }
    });

    el.insertBefore(new Option(COMMON_NONE_OPTION_TEXT, COMMON_NONE_OPTION_ID, false, false), el.firstChild);
    if (shouldIncludeCommonAdd(el, options)) {
        const option = new Option(COMMON_ADD_OPTION_TEXT, COMMON_ADD_OPTION_ID, false, false);
        option.disabled = !hasQuickAddHandler(el, options);
        el.appendChild(option);
    }
}

function bindCommonSelect2Options($el) {
    if (!$el) return;

    $el.off('select2:select.commonPickerOptions')
        .on('select2:select.commonPickerOptions', function (event) {
            const selectedId = String(event.params?.data?.id ?? '').trim();
            if (selectedId === COMMON_NONE_OPTION_ID) {
                window.setTimeout(() => {
                    $el.val('').trigger('change');
                }, 0);
                return;
            }
            if (selectedId === COMMON_ADD_OPTION_ID) {
                if (this.dataset.quickAddEnabled !== 'true') {
                    window.setTimeout(() => {
                        $el.val('').trigger('change');
                    }, 0);
                    return;
                }
                this.dispatchEvent(new CustomEvent('picker:add', {
                    bubbles: true,
                    detail: event.params?.data || {}
                }));
                window.setTimeout(() => {
                    $el.val('').trigger('change');
                }, 0);
            }
        });
}

function createSelect2(target, options = {}) {
    const $ = ensureJQuery();
    ensureSelect2($);
    bindModalCleanup();
    bindSearchFocus();
    bindEscapeClose();
    bindKeyboardAssist();

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) {
        console.warn('[picker.select2] 대상 요소를 찾을 수 없습니다.', target);
        return null;
    }

    const config = normalizeOptions(options);
    if (config.quickAddEnabled === true) {
        el.dataset.quickAddEnabled = 'true';
    }
    ensureEmptyOption(el, config.placeholder);
    ensureCommonStaticOptions(el, config);

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
    bindCommonSelect2Options($el);
    $el.off('select2:open.pickerSearchFocusLocal')
        .on('select2:open.pickerSearchFocusLocal', focusOpenSelect2Search);

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

function reloadOptions(target, items = [], valueKey = 'id', textKey = 'text', selectedValue = null, options = {}) {
    const $ = ensureJQuery();

    const el = typeof target === 'string'
        ? document.querySelector(target)
        : target;

    if (!el) return;

    const $el = $(el);

    $el.empty();
    $el.append(new Option(COMMON_NONE_OPTION_TEXT, COMMON_NONE_OPTION_ID, false, false));

    items.forEach(item => {
        if (isCommonNoneOption(item) || isCommonAddOption(item)) {
            return;
        }
        const option = new Option(
            item[textKey] ?? '',
            item[valueKey] ?? '',
            false,
            false
        );
        $el.append(option);
    });

    if (shouldIncludeCommonAdd(el, options)) {
        const option = new Option(COMMON_ADD_OPTION_TEXT, COMMON_ADD_OPTION_ID, false, false);
        option.disabled = !hasQuickAddHandler(el, options);
        $el.append(option);
    }

    if (selectedValue !== null && selectedValue !== undefined) {
        $el.val(selectedValue);
    }

    $el.trigger('change');
}

function createAjaxSelect2(target, options = {}) {
    const $ = ensureJQuery();
    ensureSelect2($);
    bindModalCleanup();
    bindSearchFocus();
    bindEscapeClose();
    bindKeyboardAssist();

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
        includeCommonNone,
        includeCommonAdd,
        quickAddEnabled: optionQuickAddEnabled,
        commonNoneText,
        commonAddText,
        ...rest
    } = options;
    const quickAddEnabled = optionQuickAddEnabled === true || includeCommonAdd === true || el.dataset.quickAddEnabled === 'true';

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
                    return withCommonAjaxOptions(processResults(data, params), params, {
                        includeCommonNone,
                        includeCommonAdd,
                        quickAddEnabled,
                        commonNoneText,
                        commonAddText
                    });
                }

                const rows = data?.data ?? data?.items ?? [];

                return withCommonAjaxOptions({
                    results: rows.map(row => ({
                        id: row.id ?? row.code ?? row.value,
                        text: row.text
                            ?? row.display_name
                            ?? row.name
                            ?? row.label
                            ?? row.project_name
                            ?? row.client_name
                            ?? row.account_name
                            ?? row.bank_account_name
                            ?? row.employee_name
                            ?? row.card_name
                            ?? ''
                    }))
                }, params, {
                    includeCommonNone,
                    includeCommonAdd,
                    quickAddEnabled,
                    commonNoneText,
                    commonAddText
                });
            }
        }
    });
    el.dataset.ajaxUrl = url;
    if (quickAddEnabled) {
        el.dataset.quickAddEnabled = 'true';
    }

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

bindEscapeClose();

export { PickerSelect2 };
