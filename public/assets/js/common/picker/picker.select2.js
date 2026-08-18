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
    return text === COMMON_ADD_OPTION_TEXT
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
        if (!openContainer.isConnected || !search.isConnected) return;
        if (!openContainer.classList.contains('select2-container--open')) return;
        if (!openContainer.contains(search)) return;
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

function redirectFocusFromHiddenSelect(select) {
    const $ = window.jQuery || window.$;
    if (!select || !$?.fn?.select2) {
        return;
    }

    if (!select.isConnected) {
        return;
    }

    if (!$(select).hasClass('select2-hidden-accessible')) {
        return;
    }

    if (document.activeElement !== select) {
        return;
    }

    const instance = $(select).data('select2');
    const container = instance?.$container?.get?.(0) || select.nextElementSibling;
    const selection = container?.querySelector('.select2-selection');

    select.blur?.();

    if (!selection) {
        return;
    }

    if (!selection.hasAttribute('tabindex')) {
        selection.setAttribute('tabindex', '0');
    }

    try {
        selection.focus({ preventScroll: true });
    } catch (error) {
        selection.focus?.();
    }
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

    modal.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
        select.blur?.();
        redirectFocusFromHiddenSelect(select);
    });
}

function normalizeOptions(options = {}) {
    return {
        width: '100%',
        language: 'ko',
        allowClear: false,
        dropdownAutoWidth: false,
        ...options
    };
}

const COMMON_EMPTY_OPTION_ID = '__none__';
const COMMON_ADD_OPTION_ID = '__add__';
const COMMON_ADD_OPTION_TEXT = '+ 추가';

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

function normalizeSortNo(value) {
    const parsed = Number(String(value ?? '').replace(/,/g, '').trim());
    return Number.isFinite(parsed) ? parsed : Number.MAX_SAFE_INTEGER;
}

function sortRowsBySortNo(rows = []) {
    return [...(Array.isArray(rows) ? rows : [])]
        .map((row, index) => ({ row, index }))
        .sort((left, right) => {
            const leftSortNo = normalizeSortNo(left.row?.sort_no);
            const rightSortNo = normalizeSortNo(right.row?.sort_no);
            if (leftSortNo !== rightSortNo) {
                return leftSortNo - rightSortNo;
            }
            return left.index - right.index;
        })
        .map((entry) => entry.row);
}

function sortAjaxPayloadBySortNo(payload) {
    if (Array.isArray(payload)) {
        return sortRowsBySortNo(payload);
    }

    if (!payload || typeof payload !== 'object') {
        return payload;
    }

    const next = { ...payload };
    if (Array.isArray(next.results)) {
        next.results = sortRowsBySortNo(next.results);
    }
    if (Array.isArray(next.data)) {
        next.data = sortRowsBySortNo(next.data);
    }
    if (Array.isArray(next.items)) {
        next.items = sortRowsBySortNo(next.items);
    }
    return next;
}

function withCommonAjaxOptions(result = {}, params = {}, options = {}) {
    const page = Number(params?.page || 1);
    const includeCommonAdd = options.includeCommonAdd !== false;
    const quickAddEnabled = options.quickAddEnabled === true || options.includeCommonAdd === true;
    const addText = options.commonAddText || COMMON_ADD_OPTION_TEXT;
    const sourceResults = Array.isArray(result?.results) ? result.results : [];
    const normalized = sortRowsBySortNo(sourceResults)
        .map(normalizeAjaxResultItem)
        .filter((item) => !isCommonAddOption(item));

    const results = normalized;

    if (page <= 1) {
        results.unshift({ id: COMMON_EMPTY_OPTION_ID, text: '선택(없음)', isEmpty: true });
    }

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

function shouldIncludeCommonAdd(el, options = {}) {
    return options.includeCommonAdd !== false
        && el?.dataset?.hideCommonAdd !== 'true';
}

function bindCommonSelect2Options($el) {
    if (!$el) return;

    $el.off('select2:select.commonPickerOptions')
        .on('select2:select.commonPickerOptions', function (event) {
            const selectedId = String(event.params?.data?.id ?? '').trim();
            if (selectedId === COMMON_EMPTY_OPTION_ID) {
                let emptyOption = Array.from(this.options || []).find((option) => option.value === '');
                if (!emptyOption) {
                    emptyOption = new Option('선택(없음)', '', true, true);
                    this.insertBefore(emptyOption, this.firstChild);
                }
                emptyOption.selected = true;
                event.params.data.id = '';
                event.params.data.text = '선택(없음)';
                window.jQuery(this).val('').trigger('change');
                return;
            }
            if (selectedId === COMMON_ADD_OPTION_ID) {
                if (this.dataset.quickAddEnabled !== 'true') {
                    return;
                }
                this.dispatchEvent(new CustomEvent('picker:add', {
                    bubbles: true,
                    detail: event.params?.data || {}
                }));
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
    if (el.dataset.select2FocusFixBound !== 'true') {
        el.dataset.select2FocusFixBound = 'true';
        el.addEventListener('focus', () => {
            redirectFocusFromHiddenSelect(el);
        });
    }
    bindCommonSelect2Options($el);
    $el.off('select2:open.pickerSearchFocusLocal')
        .on('select2:open.pickerSearchFocusLocal', focusOpenSelect2Search)
        .off('select2:close.pickerFocusFix')
        .on('select2:close.pickerFocusFix', () => {
            window.setTimeout(() => {
                redirectFocusFromHiddenSelect(el);
            }, 0);
        })
        .off('select2:closing.pickerFocusFix')
        .on('select2:closing.pickerFocusFix', () => {
            window.setTimeout(() => {
                redirectFocusFromHiddenSelect(el);
            }, 0);
        });

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
    const currentValue = el.value;
    let emptyOption = Array.from(el.options || [])
        .find((option) => option.value === '');

    Array.from(el.options || []).forEach((option) => {
        const value = String(option.value ?? '');
        if (value !== '') {
            option.remove();
        }
    });

    if (!emptyOption) {
        emptyOption = document.createElement('option');
        emptyOption.value = '';
        el.insertBefore(emptyOption, el.firstChild);
    }
    emptyOption.textContent = '선택(없음)';
    if (emptyOption !== el.firstElementChild) {
        el.insertBefore(emptyOption, el.firstChild);
    }

    sortRowsBySortNo(items).forEach(item => {
        if (String(item?.[valueKey] ?? '').trim() === '' || isCommonAddOption(item)) {
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

    if (selectedValue !== null && selectedValue !== undefined) {
        el.value = String(selectedValue ?? '');
    } else {
        el.value = currentValue;
    }
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
        returnEmptyOnError = true,
        includeCommonAdd,
        quickAddEnabled: optionQuickAddEnabled,
        commonAddText,
        ...rest
    } = options;
    const quickAddEnabled = optionQuickAddEnabled === true || includeCommonAdd === true || el.dataset.quickAddEnabled === 'true';

    if (!url) {
        throw new Error('[picker.select2] AJAX Select2는 url이 필요합니다.');
    }

    const configuredLanguage = rest.language && typeof rest.language === 'object'
        ? rest.language
        : {};
    const ajaxLanguage = {
        ...configuredLanguage,
        noResults: () => '검색 결과가 없습니다.',
        errorLoading: () => '검색 결과가 없습니다.',
        inputTooShort: (args = {}) => {
            const remaining = Math.max(0, Number(args.minimum || 0) - Number(args.input?.length || 0));
            return remaining > 0 ? `${remaining}자 이상 입력해 주세요.` : '검색어를 입력해 주세요.';
        }
    };

    const finalOptions = normalizeOptions({
        ...rest,
        language: ajaxLanguage,
        minimumInputLength,
        ajax: {
            url,
            type: method,
            delay,
            transport(params, success, failure) {
                const request = $.ajax(params);
                request.then((data) => {
                    const sortedData = sortAjaxPayloadBySortNo(data);
                    if (returnEmptyOnError && data?.success === false) {
                        console.warn('[picker.select2] AJAX search returned success=false. Fallback to empty results.', {
                            url,
                            response: sortedData
                        });
                        success({ results: [] });
                        return;
                    }
                    success(sortedData);
                }).catch((error) => {
                    if (returnEmptyOnError) {
                        console.warn('[picker.select2] AJAX search failed. Fallback to empty results.', {
                            url,
                            error
                        });
                        success({ results: [] });
                        return;
                    }
                    failure(error);
                });
                return request;
            },
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
                        includeCommonAdd,
                        quickAddEnabled,
                        commonAddText
                    });
                }

                const rows = sortRowsBySortNo(data?.results ?? data?.data ?? data?.items ?? []);

                return withCommonAjaxOptions({
                    results: rows.map(row => ({
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
                    }))
                }, params, {
                    includeCommonAdd,
                    quickAddEnabled,
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
