import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { bindBizNumberInput, bindNumberInput, formatDateInputValue } from '/public/assets/js/common/format.js';
import { LEDGER_DATA_CREATE_API as API, fetchJson } from '../data-create/core/api.js';
import { notify } from '/public/assets/js/common/notification.js';
import {
    applyDateTimeToPicker,
    formatPickerDate,
    formatPickerDateTime,
    normalizeDateTimeInputValue,
    normalizeTimeInputValue,
    pad2,
} from '/public/assets/js/common/values.js';

function normalizeReadinessSummaryKeyword(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

export function createReadinessPickerControls() {
    let summaryTimer = null;
    let summaryItems = [];
    let summaryActiveIndex = -1;
    let summaryAbort = null;
    let summaryInput = null;
    let summaryDocumentBound = false;

    function closeReadinessDatePickers() {
        document.querySelectorAll('.readiness-date-picker-host').forEach((host) => {
            host.__pickerInstance?.close?.();
        });
    }

    function bindReadinessDateInput(input, modal) {
        if (!input || input.dataset.dateInputBound === 'true') return;
        const normalize = () => {
            input.value = input.dataset.valueKind === 'datetime'
                ? normalizeDateTimeInputValue(input.value)
                : formatDateInputValue(input.value);
        };
        input.addEventListener('focus', () => closeReadinessDatePickers(modal));
        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);
        input.dataset.dateInputBound = 'true';
    }

    function bindReadinessDatePicker(button, modal) {
        if (!button || button.dataset.datePickerBound === 'true') return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const control = button.closest('.readiness-date-control');
            const input = control?.querySelector('input[data-value-kind="date"], input[data-value-kind="datetime"]');
            if (!input) return;
            const keepTime = input.dataset.valueKind === 'datetime';

            closeReadinessDatePickers(modal);
            const host = document.createElement('div');
            host.className = 'readiness-date-picker-host is-hidden';
            document.body.appendChild(host);

            const picker = AdminPicker.create({ type: keepTime ? 'datetime' : 'today', container: host });
            host.__pickerInstance = picker;
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => host.remove(), 0);
            };
            applyDateTimeToPicker(picker, input.value, keepTime);
            picker.subscribe((state, finalDate) => {
                if (!(finalDate instanceof Date) || Number.isNaN(finalDate.getTime())) return;
                input.value = keepTime && state?.timeEnabled
                    ? formatPickerDateTime(finalDate)
                    : formatPickerDate(finalDate);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                if (!keepTime) picker.close?.();
            });

            picker.open({ anchor: button });
        });
        button.dataset.datePickerBound = 'true';
    }

    function bindReadinessTimeInput(input, modal) {
        if (!input || input.dataset.timeInputBound === 'true') return;
        input.addEventListener('focus', () => closeReadinessDatePickers(modal));
        input.addEventListener('blur', () => {
            input.value = normalizeTimeInputValue(input.value);
        });
        input.dataset.timeInputBound = 'true';
    }

    function bindReadinessTimePicker(button, modal) {
        if (!button || button.dataset.timePickerBound === 'true') return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const control = button.closest('.readiness-date-control');
            const input = control?.querySelector('input[data-value-kind="time"]');
            if (!input) return;

            closeReadinessDatePickers(modal);
            const host = document.createElement('div');
            host.className = 'readiness-date-picker-host is-hidden';
            document.body.appendChild(host);

            const picker = AdminPicker.create({ type: 'time-list', container: host, options: { step: 10, rows: 8 } });
            host.__pickerInstance = picker;
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => host.remove(), 0);
            };
            const currentTime = normalizeTimeInputValue(input.value);
            if (currentTime) {
                const [hour, minute] = currentTime.split(':').map((item) => Number(item));
                picker.setTime?.({ hour, minute, meridiem: hour >= 12 ? 'PM' : 'AM' });
            }
            picker.subscribe((state) => {
                if (typeof state?.hour !== 'number' || typeof state?.minute !== 'number') return;
                input.value = `${pad2(state.hour)}:${pad2(state.minute)}`;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                picker.close?.();
            });
            picker.open({ anchor: button });
        });
        button.dataset.timePickerBound = 'true';
    }

    function closeReadinessSummaryAutocomplete() {
        summaryItems = [];
        summaryActiveIndex = -1;
        document.querySelectorAll('#seedRowReadinessModal .summary-autocomplete-list').forEach((list) => {
            list.innerHTML = '';
            list.classList.add('d-none');
        });
    }

    function setReadinessSummaryAutocompleteActive(index) {
        const list = summaryInput
            ?.closest('.summary-autocomplete-wrap')
            ?.querySelector('.summary-autocomplete-list');
        if (!list || summaryItems.length === 0) return;

        const maxIndex = summaryItems.length - 1;
        summaryActiveIndex = index < 0 ? maxIndex : (index > maxIndex ? 0 : index);

        list.querySelectorAll('.summary-autocomplete-item').forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === summaryActiveIndex);
        });
    }

    function applyReadinessSummaryAutocompleteItem(index) {
        const item = summaryItems[index];
        if (!item || !summaryInput) return;
        summaryInput.value = item.summary_text || '';
        summaryInput.dispatchEvent(new Event('input', { bubbles: true }));
        closeReadinessSummaryAutocomplete();
    }

    function renderReadinessSummaryAutocomplete(input, items = []) {
        const list = input
            ?.closest('.summary-autocomplete-wrap')
            ?.querySelector('.summary-autocomplete-list');
        if (!input || !list || input.disabled || input.readOnly) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        summaryInput = input;
        summaryItems = items.filter((item) => String(item.summary_text || '').trim() !== '');
        summaryActiveIndex = -1;

        if (summaryItems.length === 0) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        list.innerHTML = summaryItems.map((item, index) => `
            <button type="button"
                    class="summary-autocomplete-item"
                    role="option"
                    data-index="${index}"
                    title="${String(item.summary_text || '').replaceAll('"', '&quot;')}">
                ${String(item.summary_text || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')}
            </button>
        `).join('');
        list.classList.remove('d-none');
    }

    async function searchReadinessSummaryAutocomplete(input) {
        const normalizedKeyword = normalizeReadinessSummaryKeyword(input?.value || '');
        if (normalizedKeyword.length < 2 || !input || input.disabled || input.readOnly) {
            closeReadinessSummaryAutocomplete();
            return;
        }

        if (summaryAbort) {
            summaryAbort.abort();
        }
        summaryAbort = new AbortController();

        try {
            const json = await fetchJson(`${API.evidenceSummarySearch}?q=${encodeURIComponent(normalizedKeyword)}`, {
                signal: summaryAbort.signal,
            });

            if (normalizeReadinessSummaryKeyword(input.value) !== normalizedKeyword) return;
            renderReadinessSummaryAutocomplete(input, Array.isArray(json.items) ? json.items : []);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            console.error('[ledger-data-create] evidence summary autocomplete failed', error);
            notify('error', '원본 전표적요 추천 목록을 불러오지 못했습니다.');
            closeReadinessSummaryAutocomplete();
        }
    }

    function queueReadinessSummaryAutocompleteSearch(input) {
        if (summaryTimer) {
            clearTimeout(summaryTimer);
        }

        summaryTimer = setTimeout(() => {
            void searchReadinessSummaryAutocomplete(input);
        }, 220);
    }

    function bindReadinessSummaryAutocomplete(input) {
        if (!input || input.dataset.summaryAutocompleteBound === 'true') return;
        const list = input.closest('.summary-autocomplete-wrap')?.querySelector('.summary-autocomplete-list');

        input.addEventListener('input', () => {
            summaryInput = input;
            queueReadinessSummaryAutocompleteSearch(input);
        });
        input.addEventListener('focus', () => {
            summaryInput = input;
            queueReadinessSummaryAutocompleteSearch(input);
        });
        input.addEventListener('keydown', (event) => {
            if (!list || list.classList.contains('d-none')) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setReadinessSummaryAutocompleteActive(summaryActiveIndex + 1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setReadinessSummaryAutocompleteActive(summaryActiveIndex - 1);
                return;
            }
            if (event.key === 'Enter' && summaryActiveIndex >= 0) {
                event.preventDefault();
                applyReadinessSummaryAutocompleteItem(summaryActiveIndex);
                return;
            }
            if (event.key === 'Escape') {
                closeReadinessSummaryAutocomplete();
            }
        });

        list?.addEventListener('mousedown', (event) => {
            const item = event.target.closest('.summary-autocomplete-item');
            if (!item) return;
            event.preventDefault();
            summaryInput = input;
            applyReadinessSummaryAutocompleteItem(Number(item.dataset.index || -1));
        });

        if (!summaryDocumentBound) {
            document.addEventListener('mousedown', (event) => {
                if (event.target.closest('#seedRowReadinessModal .summary-autocomplete-wrap')) return;
                closeReadinessSummaryAutocomplete();
            });
            summaryDocumentBound = true;
        }

        input.dataset.summaryAutocompleteBound = 'true';
    }

    function initReadinessValueInputs(modal) {
        modal.querySelectorAll('[data-value-kind="amount"]').forEach((input) => {
            bindNumberInput(input);
        });
        modal.querySelectorAll('[data-value-kind="business-number"]').forEach((input) => {
            bindBizNumberInput(input);
        });
        modal.querySelectorAll('input[data-value-kind="date"], input[data-value-kind="datetime"]').forEach((input) => {
            bindReadinessDateInput(input, modal);
        });
        modal.querySelectorAll('input[data-value-kind="time"]').forEach((input) => {
            bindReadinessTimeInput(input, modal);
        });
        modal.querySelectorAll('.readiness-date-picker-btn').forEach((button) => {
            bindReadinessDatePicker(button, modal);
        });
        modal.querySelectorAll('.readiness-time-picker-btn').forEach((button) => {
            bindReadinessTimePicker(button, modal);
        });
        modal.querySelectorAll('[data-readiness-summary-autocomplete="1"]').forEach((input) => {
            bindReadinessSummaryAutocomplete(input);
        });
    }

    return {
        closeReadinessDatePickers,
        closeReadinessSummaryAutocomplete,
        initReadinessValueInputs,
    };
}
