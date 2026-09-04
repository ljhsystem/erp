import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { PickerSelect2 } from '/public/assets/js/common/picker/picker.select2.js';
import { bindNumberInput, formatAmount, parseNumber } from '/public/assets/js/common/format.js';
import { openClientQuickCreate } from '/public/assets/js/pages/main/settings/base/client.js';
import { openCodeQuickModal } from '/public/assets/js/pages/main/settings/system/code-select.js';

const DAY_NAMES = ['일', '월', '화', '수', '목', '금', '토'];
const number = value => Number(String(value ?? 0).replaceAll(',', '')) || 0;
const instanceKey = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
const dayIndex = value => { const [y, m, d] = String(value).split('-').map(Number); return new Date(y, m - 1, d).getDay(); };
const dayName = value => DAY_NAMES[dayIndex(value)];
const paymentAmount = day => number(day.daily_rate_amount)
    + number(day.taxable_additional_amount ?? day.allowance_amount)
    + number(day.non_taxable_additional_amount ?? day.non_taxable_amount);
const hasWorkdayInput = day => ['actual_work_minutes', 'daily_rate_amount', 'taxable_additional_amount', 'non_taxable_additional_amount']
    .some(key => number(day?.[key]) !== 0) || ['non_taxable_reason', 'calculation_note'].some(key => String(day?.[key] ?? '').trim() !== '');

const workMinutesText = value => {
    const minutes = Math.max(0, Math.trunc(number(value)));
    if (minutes === 0) return '미확인';
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return `${hours}시간${remainder > 0 ? ` ${remainder}분` : ''}`;
};

function decorateWorkMinuteEditors(host) {
    host.querySelectorAll('[data-column-key="actual_work_minutes"] .html-grid-editor-number').forEach(input => {
        const slot = input.closest('.html-grid-cell-editor-slot');
        if (!slot || slot.querySelector('.daily-income-work-minutes-display')) return;
        const display = document.createElement('span');
        display.className = 'daily-income-work-minutes-display';
        display.setAttribute('aria-live', 'polite');
        const update = () => { display.textContent = workMinutesText(input.value); };
        input.addEventListener('input', update);
        update();
        slot.append(display);
    });
}

function captureScrollState(host) {
    const elements = [];
    for (let node = host; node instanceof HTMLElement; node = node.parentElement) {
        if (node.scrollHeight > node.clientHeight || node.scrollWidth > node.clientWidth) {
            elements.push({ node, top: node.scrollTop, left: node.scrollLeft });
        }
    }
    const windowX = window.scrollX; const windowY = window.scrollY;
    return () => {
        elements.forEach(({ node, top, left }) => { node.scrollTop = top; node.scrollLeft = left; });
        window.scrollTo(windowX, windowY);
    };
}

export class DailyIncomeWorkerCardRegistry {
    constructor({ createItem, onChanged, onValidationError = () => {} }) { this.createItem = createItem; this.onChanged = onChanged; this.onValidationError = onValidationError; this.instances = new Map(); this.focused = new Map(); }
    destroy(key) { (this.instances.get(key) || []).forEach(grid => grid.destroy()); this.instances.delete(key); }
    destroyAll() { [...this.instances.keys()].forEach(key => this.destroy(key)); this.focused.clear(); }
    refresh() {}
    refreshTotals(groups = []) {
        groups.forEach(group => group.items.forEach(item => {
            const card = document.querySelector(`[data-daily-worker-key="${CSS.escape(item.client_key)}"]`);
            const footer = card?.querySelector('.daily-income-worker-totals');
            if (footer) this.updateTotals(footer, item);
            if (card) this.updateHeaderSummary(card, item);
        }));
    }
    normalizeSortNumbers(group) {
        group.items.forEach((item, index) => { item.sort_no = index + 1; });
    }
    duplicateFocused(key) {
        const state = this.focused.get(key); if (!state) return false;
        state.group.items.forEach(item => { item.collapsed = true; });
        const copy = state.copy(state.group.items[state.index]); copy.collapsed = false;
        state.group.items.splice(state.index + 1, 0, copy); state.render(); return true;
    }
    mount(host, group, options) {
        this.destroy(group.client_key); const grids = []; this.instances.set(group.client_key, grids);
        const render = ({ preserveScroll = false } = {}) => {
            const restoreScroll = preserveScroll ? captureScrollState(host) : null;
            host.querySelectorAll('select.select2-hidden-accessible').forEach(select => {
                select.dataset.pickerReady = 'false';
                PickerSelect2.destroy(select);
            });
            this.destroy(group.client_key); grids.length = 0; this.instances.set(group.client_key, grids); host.replaceChildren();
            this.normalizeSortNumbers(group);
            if (!group.items.length) { const empty = document.createElement('div'); empty.className = 'text-muted p-3'; empty.textContent = '작업자를 추가해 주세요.'; host.append(empty); }
            group.items.forEach((item, index) => this.renderCard({ host, group, item, index, options, grids, render }));
            if (restoreScroll) { restoreScroll(); requestAnimationFrame(restoreScroll); }
        };
        render();
    }
    renderCard({ host, group, item, index, options, grids, render }) {
        const card = document.createElement('article'); card.className = `daily-income-worker-card${item.collapsed ? ' is-collapsed' : ''}`; card.dataset.dailyWorkerKey = item.client_key;
        const workerName = options.workers.find(row => String(row.id) === String(item.worker_client_id))?.name || item.worker_name || '작업자 선택';
        const workerReference = options.workers.find(row => String(row.id) === String(item.worker_client_id));
        const clientTypeName = workerReference?.client_type_name || item.worker_client_type_name || workerReference?.client_type || item.worker_client_type || '거래처유형미등록';
        const typeName = options.workTypes.find(row => String(row.id) === String(item.work_type_code))?.name || item.work_type_name || '';
        const header = document.createElement('header'); header.className = 'daily-income-worker-card__header';
        const order = document.createElement('span'); order.className = 'daily-income-worker-order'; order.textContent = String(item.sort_no);
        const handle = document.createElement('span'); handle.className = 'daily-income-worker-drag-handle'; handle.title = options.readOnly ? '순번' : '드래그하여 순서 변경'; handle.setAttribute('aria-label', handle.title); handle.innerHTML = '<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>';
        const worker = document.createElement('button'); worker.type = 'button'; worker.className = 'daily-income-worker-select'; worker.textContent = workerName;
        worker.addEventListener('click', () => { this.focused.set(group.client_key, { group, index, copy: options.copyItem, render }); options.onSelect?.(group, item); });
        const clientType = document.createElement('span'); clientType.className = 'daily-income-worker-type'; clientType.textContent = clientTypeName;
        const workType = document.createElement('span'); workType.className = 'daily-income-worker-work-type'; workType.textContent = `공종: ${typeName || '미선택'}`;
        const description = document.createElement('span'); description.className = 'daily-income-worker-description'; description.textContent = `작업내용: ${String(item.work_description || '').trim() || '미입력'}`; description.title = description.textContent;
        const workdayCount = document.createElement('span'); workdayCount.className = 'daily-income-worker-summary daily-income-worker-summary--days';
        const averageRate = document.createElement('span'); averageRate.className = 'daily-income-worker-summary daily-income-worker-summary--rate';
        const paymentTotal = document.createElement('span'); paymentTotal.className = 'daily-income-worker-summary daily-income-worker-summary--payment';
        header.append(order, handle, worker, clientType, workType, description, workdayCount, averageRate, paymentTotal);
        if (item.calculation_state === 'loading') {
            const loading = document.createElement('span'); loading.className = 'daily-income-worker-calculation-state is-loading';
            loading.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>계산 중</span>'; header.append(loading);
        } else if (item.calculation_state === 'error') {
            const error = document.createElement('span'); error.className = 'daily-income-worker-calculation-state is-error';
            error.textContent = item.calculation_error || '계산 오류'; error.title = error.textContent; header.append(error);
        }
        if (options.isDuplicateWorker?.(group, item)) {
            const duplicate = document.createElement('span');
            duplicate.className = 'daily-income-worker-duplicate badge text-bg-warning';
            duplicate.textContent = '동일 Group 중복';
            duplicate.title = '동일 Group 작업자 중복 · 저장 불가';
            worker.append(' ', duplicate);
        }
        this.updateHeaderSummary(card, item);
        const copy = document.createElement('button'); copy.type = 'button'; copy.className = 'daily-income-worker-action'; copy.title = '작업자 복사'; copy.setAttribute('aria-label', copy.title); copy.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>'; copy.disabled = options.readOnly;
        copy.addEventListener('click', () => { const copiedItem = options.copyItem(item); group.items.forEach(row => { row.collapsed = true; }); copiedItem.collapsed = false; group.items.splice(index + 1, 0, copiedItem); this.normalizeSortNumbers(group); render(); options.onSelect?.(group, copiedItem); this.onChanged(); });
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'daily-income-worker-action is-danger'; remove.title = '작업자 삭제'; remove.setAttribute('aria-label', remove.title); remove.innerHTML = '<i class="fa-regular fa-trash-can" aria-hidden="true"></i>'; remove.disabled = options.readOnly;
        remove.addEventListener('click', () => { group.items.splice(index, 1); this.normalizeSortNumbers(group); render(); options.onDelete?.(group, index, item); });
        header.append(copy, remove);
        const fold = document.createElement('button'); fold.type = 'button'; fold.className = 'daily-income-worker-action'; fold.title = item.collapsed ? '펼치기' : '접기'; fold.setAttribute('aria-label', fold.title); fold.innerHTML = `<i class="fa-solid fa-chevron-${item.collapsed ? 'down' : 'up'}" aria-hidden="true"></i>`; fold.addEventListener('click', () => { options.onSelect?.(group, item); item.collapsed = !item.collapsed; card.classList.toggle('is-collapsed', item.collapsed); fold.title = item.collapsed ? '펼치기' : '접기'; fold.setAttribute('aria-label', fold.title); fold.innerHTML = `<i class="fa-solid fa-chevron-${item.collapsed ? 'down' : 'up'}" aria-hidden="true"></i>`; }); header.append(fold); card.append(header);
        card.addEventListener('click', event => {
            if (event.target.closest('button, select, input, textarea, .html-grid-cell')) return;
            this.focused.set(group.client_key, { group, index, copy: options.copyItem, render });
            options.onSelect?.(group, item);
        });
        if (!options.readOnly) {
            handle.draggable = true;
            handle.addEventListener('dragstart', event => { card.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', item.client_key); });
            card.addEventListener('dragover', event => { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; });
            card.addEventListener('drop', event => {
                event.preventDefault();
                const sourceKey = event.dataTransfer.getData('text/plain'); const sourceIndex = group.items.findIndex(row => row.client_key === sourceKey);
                if (sourceIndex < 0 || sourceIndex === index) return;
                const [moved] = group.items.splice(sourceIndex, 1); const targetIndex = sourceIndex < index ? index - 1 : index;
                group.items.splice(targetIndex, 0, moved); this.normalizeSortNumbers(group); render({ preserveScroll: true }); this.onChanged();
            });
            handle.addEventListener('dragend', () => card.classList.remove('is-dragging'));
        }
        const body = document.createElement('div'); body.className = 'daily-income-worker-card__body';
        const workdayBody = document.createElement('div'); workdayBody.className = 'daily-income-worker-workdays';
        const cardGrids = [];
        const renderWorkdays = ({ preserveScroll = false } = {}) => {
            const restoreScroll = preserveScroll ? captureScrollState(workdayBody) : null;
            cardGrids.splice(0).forEach(grid => {
                const registryIndex = grids.indexOf(grid);
                if (registryIndex >= 0) grids.splice(registryIndex, 1);
                grid.destroy();
            });
            workdayBody.replaceChildren();
            this.renderCalendar(workdayBody, group, item, options, renderWorkdays);
            this.renderGrid(workdayBody, group, item, options, cardGrids, renderWorkdays);
            this.renderTotals(workdayBody, item);
            this.updateHeaderSummary(card, item);
            grids.push(...cardGrids);
            const toggleAll = body.querySelector('[data-toggle-all-workdays]');
            if (toggleAll) {
                const allSelected = options.dates.length > 0 && options.dates.every(date => item.workdays.has(date));
                toggleAll.textContent = allSelected ? '전체선택해제' : '전체선택';
                toggleAll.classList.toggle('btn-outline-secondary', allSelected);
                toggleAll.classList.toggle('btn-outline-primary', !allSelected);
            }
            if (restoreScroll) { restoreScroll(); requestAnimationFrame(restoreScroll); }
        };
        this.renderFields(body, group, item, options, renderWorkdays);
        body.append(workdayBody);
        const institutionHost = document.createElement('section');
        institutionHost.className = 'daily-income-institution-detail';
        institutionHost.setAttribute('aria-label', '기관별 계산 상세');
        body.append(institutionHost);
        renderWorkdays();
        options.renderInstitutionDetails?.(item, institutionHost);
        card.append(body); host.append(card);
    }
    updateHeaderSummary(card, item) {
        const days = [...item.workdays.values()];
        const enteredRates = days
            .filter(day => day.daily_rate_amount !== null && day.daily_rate_amount !== undefined && day.daily_rate_amount !== '')
            .map(day => number(day.daily_rate_amount));
        const averageRate = enteredRates.length
            ? Math.round(enteredRates.reduce((sum, rate) => sum + rate, 0) / enteredRates.length)
            : null;
        const paymentTotal = days.reduce((sum, day) => sum + paymentAmount(day), 0);
        const workdayCount = card.querySelector('.daily-income-worker-summary--days');
        const averageRateLabel = card.querySelector('.daily-income-worker-summary--rate');
        const paymentTotalLabel = card.querySelector('.daily-income-worker-summary--payment');
        if (workdayCount) workdayCount.textContent = `일수 ${days.length}일`;
        if (averageRateLabel) averageRateLabel.textContent = `평균단가 ${averageRate === null ? '-' : `${formatAmount(averageRate)}원`}`;
        if (paymentTotalLabel) paymentTotalLabel.textContent = `지급액(세전) ${formatAmount(paymentTotal)}원`;
    }
    updateReferenceHeader(select, item, kind, options, selectedData = {}) {
        const card = select.closest('.daily-income-worker-card');
        if (!card) return;
        if (kind === 'worker') {
            const reference = options.workers.find(row => String(row.id) === String(item.worker_client_id));
            const workerName = item.worker_name || reference?.name || '작업자 선택';
            const clientTypeName = selectedData.client_type_name || selectedData.client_type
                || item.worker_client_type_name || item.worker_client_type
                || reference?.client_type_name || reference?.client_type || '거래처유형미등록';
            const workerLabel = card.querySelector('.daily-income-worker-select');
            const clientType = card.querySelector('.daily-income-worker-type');
            if (workerLabel) workerLabel.textContent = workerName;
            if (clientType) clientType.textContent = clientTypeName;
            return;
        }
        const workType = card.querySelector('.daily-income-worker-work-type');
        if (workType) workType.textContent = `공종: ${item.work_type_name || '미선택'}`;
    }
    renderFields(body, group, item, options, render) {
        const fields = document.createElement('div'); fields.className = 'daily-income-worker-fields';
        const appendSelect = (rows, value, label, kind, onChange) => {
            const wrap = document.createElement('label'); wrap.innerHTML = `<span>${label}</span>`; const select = document.createElement('select'); select.className = 'form-select form-select-sm'; select.id = `daily-income-${kind}-${item.client_key}`; select.append(new Option('선택(없음)', ''));
            const selected = rows.find(row => String(row.id) === String(value)); const selectedName = selected?.name || (kind === 'worker' ? item.worker_name : item.work_type_name); if (value && selectedName) select.append(new Option(selectedName, value, true, true)); select.disabled = options.readOnly;
            const commitSelection = (nextValue, text, selectedData = {}) => { onChange(nextValue, text, selectedData); this.updateReferenceHeader(select, item, kind, options, selectedData); if (kind === 'worker') options.onWorkerChanged?.(item); else options.onCalculationInputChanged?.(item); options.onSelect?.(group, item); this.onChanged({ immediate: true }); };
            select.addEventListener('change', () => { if (select.dataset.pickerReady !== 'true' || select.value === '__add__') return; const selectedData = window.jQuery?.fn?.select2 ? window.jQuery(select).select2('data')?.[0] : null; commitSelection(select.value, select.selectedOptions[0]?.textContent || '', selectedData || {}); }); wrap.append(select); fields.append(wrap);
            window.setTimeout(() => {
                const common = { includeCommonAdd: !options.readOnly, quickAddEnabled: !options.readOnly, minimumInputLength: 0 };
                let picker = null;
                if (kind === 'worker') {
                    picker = PickerSelect2.createAjax(select, { ...common, url: '/api/institution/income-data/daily-employment/options', dataBuilder: params => ({ option_type: 'worker', q: params.term || '', page: params.page || 1 }), processResults: data => ({ results: data.data?.results || [], pagination: { more: data.data?.has_more === true } }) });
                    select.addEventListener('picker:add', () => void openClientQuickCreate({ select, getOptionText: values => values.client_name || '', onSuccess: (result, values) => { item.worker_client_id = result.id || select.value; item.worker_name = values.client_name || select.selectedOptions[0]?.textContent || ''; item.worker_client_type = values.client_type || ''; item.worker_client_type_name = values.client_type_name || values.client_type || ''; this.updateReferenceHeader(select, item, kind, options, values); options.onWorkerChanged?.(item); options.onSelect?.(group, item); this.onChanged(); } }));
                } else {
                    picker = PickerSelect2.createAjax(select, { ...common, url: '/api/institution/income-data/daily-employment/options', dataBuilder: params => ({ option_type: 'work_type', q: params.term || '', page: params.page || 1 }), processResults: data => ({ results: data.data?.results || [], pagination: { more: data.data?.has_more === true } }) });
                    select.addEventListener('picker:add', () => void openCodeQuickModal({ codeGroup: 'WORK_TYPE', targetSelectId: select.id }));
                }
                picker?.off('select2:select.dailyIncomeWorkerHeader').on('select2:select.dailyIncomeWorkerHeader', event => {
                    const selectedData = event.params?.data || {};
                    const selectedId = String(selectedData.id ?? '').trim();
                    if (selectedId === '__add__') return;
                    const nextValue = selectedId === '__none__' ? '' : selectedId;
                    commitSelection(nextValue, selectedData.text || select.selectedOptions[0]?.textContent || '', selectedData);
                });
                select.dataset.pickerReady = 'true';
            }, 0);
        };
        appendSelect(options.workers, item.worker_client_id, '작업자 *', 'worker', (value, text, selectedData) => { item.worker_client_id = value; item.worker_name = value ? (selectedData.client_name || text) : ''; item.worker_client_type = value ? (selectedData.client_type || '') : ''; item.worker_client_type_name = value ? (selectedData.client_type_name || selectedData.client_type || '') : ''; }); appendSelect(options.workTypes, item.work_type_code, '공종 *', 'work-type', (value, text) => { item.work_type_code = value; item.work_type_name = value ? text : ''; });
        const descriptionField = document.createElement('div'); descriptionField.className = 'daily-income-worker-description-field';
        const desc = document.createElement('label'); desc.innerHTML = '<span>작업내용 *</span>'; const input = document.createElement('input'); input.className = 'form-control form-control-sm'; input.value = item.work_description || ''; input.disabled = options.readOnly; input.addEventListener('input', () => { item.work_description = input.value; const headerDescription = input.closest('.daily-income-worker-card')?.querySelector('.daily-income-worker-description'); if (headerDescription) { headerDescription.textContent = `작업내용: ${item.work_description.trim() || '미입력'}`; headerDescription.title = headerDescription.textContent; } options.onCalculationInputChanged?.(item); options.onSelect?.(group, item); this.onChanged(); }); desc.append(input); descriptionField.append(desc);
        const dateActions = document.createElement('div'); dateActions.className = 'daily-income-workday-selection-actions';
        const allDatesSelected = options.dates.length > 0 && options.dates.every(date => item.workdays.has(date));
        const toggleAll = document.createElement('button'); toggleAll.type = 'button'; toggleAll.dataset.toggleAllWorkdays = 'true'; toggleAll.className = `btn btn-sm ${allDatesSelected ? 'btn-outline-secondary' : 'btn-outline-primary'}`; toggleAll.textContent = allDatesSelected ? '전체선택해제' : '전체선택'; toggleAll.disabled = options.readOnly || options.dates.length === 0;
        toggleAll.addEventListener('click', async () => {
            const shouldClear = options.dates.length > 0 && options.dates.every(date => item.workdays.has(date));
            if (shouldClear) {
                if ([...item.workdays.values()].some(hasWorkdayInput) && !await options.confirmClearWorkdays?.(item.workdays.size)) return;
                item.workdays.clear(); item.workdayVisibleCount = 5;
            } else {
                const rate = number(item.daily_rate_amount);
                options.dates.forEach(date => { if (!item.workdays.has(date)) item.workdays.set(date, { client_key: instanceKey(), work_date: date, actual_work_minutes: options.defaultWorkMinutes ?? null, daily_rate_amount: rate || null, taxable_additional_amount: null, non_taxable_additional_amount: null }); });
            }
            options.onCalculationInputChanged?.(item); options.onSelect?.(group, item); render({ preserveScroll: true }); this.onChanged({ immediate: true });
        });
        dateActions.append(toggleAll); descriptionField.append(dateActions); fields.append(descriptionField); body.append(fields);
    }
    renderCalendar(body, group, item, options, render) {
        const section = document.createElement('section'); section.className = 'daily-income-workday-section'; const heading = document.createElement('div'); heading.className = 'daily-income-workday-heading';
        const monthLabel = /^\d{4}-\d{2}$/.test(options.month || '') ? `${options.month.slice(0, 4)}년 ${options.month.slice(5, 7)}월 귀속` : '귀속연월 미선택';
        heading.innerHTML = `<strong>${monthLabel}</strong><span aria-hidden="true">/</span><strong>근무일수 ${item.workdays.size}일</strong>`; section.append(heading);
        if (!options.month || options.dates.length === 0) {
            const guide = document.createElement('div'); guide.className = 'daily-income-workday-guide'; guide.textContent = '귀속연월을 먼저 선택하세요.'; section.append(guide); body.append(section); return;
        }
        const scroller = document.createElement('div'); scroller.className = 'daily-income-workday-calendar-scroll'; const calendar = document.createElement('div'); calendar.className = 'daily-income-workday-calendar'; calendar.style.setProperty('--daily-income-days', String(options.dates.length));
        options.dates.forEach(date => { const weekday = dayIndex(date); const button = document.createElement('button'); button.type = 'button'; button.className = `daily-income-day${item.workdays.has(date) ? ' is-selected' : ''}${weekday === 0 ? ' is-sunday' : ''}${weekday === 6 ? ' is-saturday' : ''}`; button.setAttribute('aria-pressed', item.workdays.has(date) ? 'true' : 'false'); button.innerHTML = `<strong>${Number(date.slice(-2))}</strong><small>${dayName(date)}</small>`; button.disabled = options.readOnly;
            button.addEventListener('click', async () => {
                if (item.workdays.has(date)) {
                    const day = item.workdays.get(date);
                    if (hasWorkdayInput(day) && typeof options.confirmRemoveWorkday === 'function' && !await options.confirmRemoveWorkday(date)) return;
                    item.workdays.delete(date);
                } else {
                    const rate = number(item.daily_rate_amount);
                    item.workdays.set(date, { client_key: instanceKey(), work_date: date, actual_work_minutes: options.defaultWorkMinutes ?? null, daily_rate_amount: rate || null, taxable_additional_amount: null, non_taxable_additional_amount: null });
                }
                options.onCalculationInputChanged?.(item); options.onSelect?.(group, item); render({ preserveScroll: true }); this.onChanged({ immediate: true });
            }); calendar.append(button); });
        scroller.append(calendar); section.append(scroller); body.append(section);
    }
    renderGrid(body, group, item, options, grids, render) {
        const bulk = document.createElement('div'); bulk.className = 'daily-income-workday-bulk';
        bulk.innerHTML = '<div class="daily-income-workday-bulk__title"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>선택근무일 일괄입력</span></div><label><span>근로시간(분)</span><input type="text" inputmode="numeric" placeholder="예: 480" data-bulk-minutes></label><label><span>적용단가</span><input type="text" inputmode="numeric" placeholder="금액 입력" data-bulk-rate></label><button type="button" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i> 일괄 적용</button>';
        bulk.querySelectorAll('input').forEach(input => { input.disabled = options.readOnly; bindNumberInput(input, { integerOnly: true }); }); bulk.querySelector('button').disabled = options.readOnly; bulk.querySelector('button').addEventListener('click', () => { const minutesInput = bulk.querySelector('[data-bulk-minutes]'); const rateInput = bulk.querySelector('[data-bulk-rate]'); const minutes = String(minutesInput.value).trim() === '' ? null : parseNumber(minutesInput.value); const rate = String(rateInput.value).trim() === '' ? null : parseNumber(rateInput.value); item.workdays.forEach(day => { if (minutes !== null) day.actual_work_minutes = minutes; if (rate !== null) day.daily_rate_amount = rate; }); options.onCalculationInputChanged?.(item); options.onSelect?.(group, item); this.onChanged(); render(); }); body.append(bulk);
        const host = document.createElement('div'); host.className = 'html-grid-host html-grid-variant-compact daily-income-workday-grid'; body.append(host);
        const allDays = [...item.workdays.values()].sort((a, b) => a.work_date.localeCompare(b.work_date));
        const visibleCount = Math.max(5, Number(item.workdayVisibleCount) || 5); item.workdayVisibleCount = visibleCount;
        const rows = allDays.slice(0, visibleCount).map(day => ({ rowId: day.work_date, rowState: options.readOnly ? 'readonly' : 'clean', values: { ...day, daily_rate_amount: number(day.daily_rate_amount) || null, taxable_additional_amount: number(day.taxable_additional_amount) || null, non_taxable_additional_amount: number(day.non_taxable_additional_amount) || null, weekday: dayName(day.work_date), payment_amount: paymentAmount(day) } }));
        const columns = [
            { key: 'work_date', label: '근무일', editable: false, width: 90 },
            { key: 'weekday', label: '요일', editable: false, width: 46 },
            { key: 'actual_work_minutes', label: '실제근로시간(휴게시간 제외)', type: 'number', formatter: 'actual-work-minutes', editor: 'number', meta: { editorOptions: { liveGrouping: true, maximumFractionDigits: 0, allowNegative: false } }, width: 190 },
            { key: 'daily_rate_amount', label: '단가', type: 'number', formatter: 'number', editor: 'number', meta: { editorOptions: { liveGrouping: true, maximumFractionDigits: 0, allowNegative: false } }, width: 112 },
            { key: 'taxable_additional_amount', label: '과세증감', type: 'number', formatter: 'number', editor: 'number', meta: { editorOptions: { liveGrouping: true, maximumFractionDigits: 0 } }, width: 108 },
            { key: 'non_taxable_additional_amount', label: '비과세증감', type: 'number', formatter: 'number', editor: 'number', meta: { editorOptions: { liveGrouping: true, maximumFractionDigits: 0 } }, width: 108 },
            { key: 'non_taxable_reason', label: '비과세 적용사유', editor: 'text', width: 150 },
            { key: 'calculation_note', label: '산정내역', editor: 'text', width: 215 },
            { key: 'payment_amount', label: '지급액', type: 'number', formatter: 'number', editable: false, width: 80 },
        ];
        const grid = createHtmlGrid({ host, gridId: `daily-workdays-${item.client_key}`, rows, keepHeaderWhenEmpty: true, columns, formatters: { 'actual-work-minutes': value => value == null || value === '' ? '과거자료 미확인' : formatAmount(value) }, emptyMessage: '선택된 근무일이 없습니다.', commitEditorsOnChange: true, capabilities: { addRow: false, deleteRow: false, reorder: false, selection: false, footer: false, keyboard: true, columnResize: true, columnHide: false, columnMove: false, clipboard: true } });
        grid.render(); decorateWorkMinuteEditors(host); grids.push(grid); grid.on('cell:changed', ({ row, columnKey }) => {
            const day = item.workdays.get(row.rowId); if (!day) return;
            if (columnKey === 'calculation_note') {
                const note = String(row.values[columnKey] ?? '').trim();
                if (note.length > 500) {
                    row.values[columnKey] = day.calculation_note ?? '';
                    grid.render();
                    this.onValidationError('산정내역은 500자 이하로 입력해 주세요.');
                    return;
                }
                day.calculation_note = note || null;
            } else day[columnKey] = row.values[columnKey];
            const calculationChanged = columnKey !== 'calculation_note';
            if (calculationChanged) options.onCalculationInputChanged?.(item);
            options.onSelect?.(group, item);
            const payment = paymentAmount(day); row.values.payment_amount = payment;
            const paymentCell = host.querySelector(`[data-row-id="${CSS.escape(row.rowId)}"][data-column-key="payment_amount"] .html-grid-cell-value`);
            if (paymentCell) paymentCell.textContent = formatAmount(payment);
            const footer = body.querySelector('.daily-income-worker-totals'); if (footer) this.updateTotals(footer, item);
            const card = body.closest('.daily-income-worker-card'); if (card) this.updateHeaderSummary(card, item);
            this.onChanged({ calculationChanged });
        });
        if (allDays.length > visibleCount) {
            const more = document.createElement('button'); more.type = 'button'; more.className = 'btn btn-outline-primary btn-sm daily-income-workday-more'; more.innerHTML = `<i class="fa-solid fa-plus" aria-hidden="true"></i> 날짜별 상세 ${Math.min(5, allDays.length - visibleCount)}건 더보기`;
            more.addEventListener('click', () => { item.workdayVisibleCount = visibleCount + 5; render({ preserveScroll: true }); }); body.append(more);
        }
    }
    renderTotals(body, item) {
        const footer = document.createElement('footer'); footer.className = 'daily-income-worker-totals'; this.updateTotals(footer, item); body.append(footer);
    }
    updateTotals(footer, item) {
        const days = [...item.workdays.values()];
        const base = days.reduce((sum, day) => sum + number(day.daily_rate_amount), 0);
        const taxable = days.reduce((sum, day) => sum + number(day.taxable_additional_amount ?? day.allowance_amount), 0);
        const nonTaxable = days.reduce((sum, day) => sum + number(day.non_taxable_additional_amount ?? day.non_taxable_amount), 0);
        const workMinutes = days.reduce((sum, day) => sum + number(day.actual_work_minutes), 0);
        const workHours = Math.floor(workMinutes / 60);
        const remainingMinutes = workMinutes % 60;
        const workTime = `${workHours}시간${remainingMinutes > 0 ? `${String(remainingMinutes).padStart(2, '0')}분` : ''}`;
        const gross = days.reduce((sum, day) => sum + paymentAmount(day), 0);
        const deduction = number(item.calculation?.summary?.total_deduction_amount);
        const net = item.calculation ? number(item.calculation.summary?.total_net_payment_amount) : gross - deduction;
        footer.innerHTML = `<div><span>근무일수</span><strong>${days.length}일</strong></div><div><span>근무시간</span><strong>${workTime}</strong></div><div><span>기본지급액</span><strong>${formatAmount(base)}원</strong></div><div><span>과세증감</span><strong>${formatAmount(taxable)}원</strong></div><div><span>비과세증감</span><strong>${formatAmount(nonTaxable)}원</strong></div><div><span>지급액(세전)</span><strong>${formatAmount(gross)}원</strong></div><div><span>원천징수</span><strong>${item.calculation ? `${formatAmount(deduction)}원` : '-'}</strong></div><div class="is-emphasis"><span>실지급액(세후)</span><strong>${item.calculation ? `${formatAmount(net)}원` : '-'}</strong></div>`;
    }
}
