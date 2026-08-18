import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn, actorDisplay } from '/public/assets/js/common/actor.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { bindNumberInput, formatNumber, parseNumber, percentToRate, rateToPercent } from '/public/assets/js/common/format.js?v=20260811-rate-5';
import { MatrixEditor } from '/public/assets/js/common/structured-field/matrix-editor.js?v=20260812-option-1';
import { BracketEditor } from '/public/assets/js/common/structured-field/bracket-editor.js?v=20260812-option-1';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { preparePeriodRenewalDraft } from './period-renewal.js';
import {
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/statutory-standards/list',
        DETAIL: '/api/settings/statutory-standards/detail',
        SAVE: '/api/settings/statutory-standards/save',
        DELETE: '/api/settings/statutory-standards/delete',
        REORDER: '/api/settings/statutory-standards/reorder',
        SOURCE_FILE: '/api/settings/statutory-standards/source-file',
    };
    const root = document.querySelector('.statutory-standards-page');
    if (!root) return;
    const bootstrapData = JSON.parse(root.dataset.bootstrap || '{}');
    const templates = bootstrapData.options?.standardTypes || [];
    const templatesByCode = new Map(templates.map(item => [String(item.code || '').trim(), item]));
    const standardMeta = bootstrapData.options?.standardColumns || [];
    const sourceMeta = bootstrapData.options?.sourceColumns || [];
    const sourceUploadPolicy = bootstrapData.options?.sourceUploadPolicy || null;
    const sourceFileAccept = String(sourceUploadPolicy?.allowed_ext || '')
        .split(',')
        .map(extension => extension.trim().toLowerCase())
        .filter(Boolean)
        .map(extension => `.${extension.replace(/^\./, '')}`)
        .join(',');
    const capabilities = bootstrapData.capabilities || {};
    const form = document.getElementById('standardForm');
    const modalElement = document.getElementById('standardModal');
    const modal = new bootstrap.Modal(modalElement);
    const modalCardCollapses = bindModalCardCollapses(modalElement, { resetOnShow: true });
    const typeSelect = form.elements.standard_type_code;
    const datePickerLayer = document.getElementById('statutory-standard-date-picker');
    let datePicker;
    let table;
    let sources = [];
    let values = {};
    let currentDetail = {};
    const structuredEditors = new Map();

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
    })[char]);
    const formatValue = (value, field = null) => {
        if (value === null || value === undefined || value === '') return '-';
        const fieldType = String(field?.type || '').toLowerCase();
        if (fieldType === 'rate') return `${formatNumber(rateToPercent(value))}%`;
        if (fieldType === 'amount') return `${formatNumber(value)}원`;
        if (fieldType === 'number') return formatNumber(value);
        if (fieldType === 'bracket') {
            const rowsKey = String(field?.object_storage?.rows_key || 'rows');
            const rows = Array.isArray(value) ? value : (Array.isArray(value?.[rowsKey]) ? value[rowsKey] : []);
            const rateColumn = (field?.columns || []).find(column => String(column?.type || '').toLowerCase() === 'rate');
            const rates = rateColumn
                ? rows.map(row => Number(row?.[rateColumn.code])).filter(Number.isFinite)
                : [];
            const rateRange = rates.length > 0
                ? `${formatNumber(rateToPercent(Math.min(...rates)))}%${rates.length > 1 ? `~${formatNumber(rateToPercent(Math.max(...rates)))}%` : ''}`
                : '';
            return `${field?.name || '구간'} ${rows.length}건${rateRange ? ` · 세율 ${rateRange}` : ''}`;
        }
        if (fieldType === 'matrix') {
            const rowsKey = String(field?.object_storage?.rows_key || 'rows');
            const rows = Array.isArray(value) ? value : (Array.isArray(value?.[rowsKey]) ? value[rowsKey] : []);
            const rateColumn = (field?.columns || []).find(column => String(column?.type || '').toLowerCase() === 'rate');
            const rates = rateColumn
                ? rows.map(row => Number(row?.[rateColumn.code])).filter(Number.isFinite)
                : [];
            const rateRange = rates.length > 0
                ? `${formatNumber(rateToPercent(Math.min(...rates)))}%${rates.length > 1 ? `~${formatNumber(rateToPercent(Math.max(...rates)))}%` : ''}`
                : '';
            return `${field?.name || '표'} ${rows.length}건${rateRange ? ` · 세율 ${rateRange}` : ''}`;
        }
        return String(value);
    };
    const template = code => templatesByCode.get(String(code || '').trim()) || null;
    const fieldsWithCurrentPresentation = (snapshotFields, currentFields) => {
        const currentByCode = new Map((currentFields || []).map(field => [String(field.code || ''), field]));
        return (snapshotFields || []).map(field => {
            const currentField = currentByCode.get(String(field.code || ''));
            if (!currentField) return field;
            const currentColumns = new Map((currentField.columns || []).map(column => [String(column.code || ''), column]));
            return {
                ...field,
                ...(currentField.ui ? { ui: currentField.ui } : {}),
                columns: (field.columns || []).map(column => {
                    const presentation = currentColumns.get(String(column.code || '')) || {};
                    return { ...column, ...Object.fromEntries(
                        ['name', 'hidden', 'default_value'].filter(key => Object.hasOwn(presentation, key)).map(key => [key, presentation[key]])
                    ) };
                }),
            };
        });
    };
    const metaByKey = (collection, key) => collection.find(item => item.key === key) || { key, label: key, required: false };
    const tableState = () => table?.__dtTableSettings?.getTableState?.() || {};
    const standardField = key => {
        const meta = metaByKey(standardMeta, key);
        const state = tableState();
        const savedPolicy = resolveDataTableColumnRequirementPolicy(meta, state);
        const requirementPolicy = meta.required ? 'required' : savedPolicy;
        return {
            ...meta,
            label: resolveDataTableColumnDisplayName(meta, state, meta.label || key),
            requirementPolicy,
            required: requirementPolicy === 'required',
        };
    };
    const sourceField = key => {
        const meta = metaByKey(sourceMeta, key);
        const requirementPolicy = meta.required ? 'required' : 'none';
        return { ...meta, requirementPolicy, required: requirementPolicy === 'required' };
    };
    const templateField = field => ({
        ...field,
        label: field.name || field.code || '',
        requirementPolicy: field.required ? 'required' : 'optional',
        required: Boolean(field.required),
    });
    const fieldLabelHtml = field => {
        const policy = String(field.requirementPolicy || 'none');
        const star = policy === 'required'
            ? '<span class="column-policy-star is-required" aria-label="필수">*</span>'
            : (policy === 'optional' ? '<span class="column-policy-star is-optional" aria-label="선택">*</span>' : '');
        return `${escapeHtml(field.label || field.name || field.code || field.key || '')}${star}`;
    };
    const requiredAttribute = field => field.required ? ' required' : '';

    function initSelect2(select, placeholder = '선택') {
        if (!select) return;
        AdminPicker.select2(select, {
            dropdownParent: window.jQuery(modalElement),
            placeholder,
            width: '100%',
            includeCommonAdd: false,
        });
    }

    function initModalSelect2Controls(rootElement = modalElement) {
        rootElement.querySelectorAll('select').forEach(select => {
            const placeholder = select === typeSelect ? '법정기준 종류 선택' : '선택';
            initSelect2(select, placeholder);
        });
    }

    function formatDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function formatDateInput(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
    }

    function initDatePicker() {
        if (datePicker || !datePickerLayer) return datePicker;
        if (datePickerLayer.parentElement !== document.body) document.body.appendChild(datePickerLayer);
        datePicker = AdminPicker.create({ type: 'today', container: datePickerLayer });
        datePicker.subscribe((_, selectedDate) => {
            if (!(selectedDate instanceof Date) || !datePicker.__target) return;
            datePicker.__target.value = formatDate(selectedDate);
            datePicker.__target.dispatchEvent(new Event('change', { bubbles: true }));
            datePicker.close?.();
        });
        return datePicker;
    }

    function openDatePicker(input) {
        const picker = initDatePicker();
        if (!picker || !input) return;
        picker.__target = input;
        picker.clearDate?.();
        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const selectedDate = new Date(`${input.value}T00:00:00`);
            if (!Number.isNaN(selectedDate.getTime())) picker.setDate?.(selectedDate);
        }
        picker.open({ anchor: input });
    }

    function bindCommonPickers() {
        initModalSelect2Controls();
        if (window.jQuery) {
            window.jQuery(typeSelect)
                .off('change.statutoryTemplate')
                .on('change.statutoryTemplate', () => {
                    values = {};
                    renderDynamic();
                    renderSystemInfo(currentDetail);
                });
        }
        modalElement.addEventListener('input', event => {
            if (event.target.matches('input.admin-date')) event.target.value = formatDateInput(event.target.value);
        });
        modalElement.addEventListener('click', event => {
            const icon = event.target.closest('.date-icon');
            if (!icon) return;
            event.preventDefault();
            openDatePicker(icon.closest('.date-input')?.querySelector('input.admin-date'));
        });
        modalElement.addEventListener('shown.bs.modal', () => initModalSelect2Controls());
    }

    function applyPhysicalFieldSettings() {
        document.querySelectorAll('[data-meta-label]').forEach(label => {
            const field = standardField(label.dataset.metaLabel);
            label.innerHTML = fieldLabelHtml(field);
            const input = form.elements[label.dataset.metaLabel];
            if (input) input.required = field.required;
        });
    }

    function applyModalCardVisibility() {
        const visibleColumns = new Set(tableState().visibleColumns || []);
        document.getElementById('standardValueCard')?.classList.toggle('d-none', !visibleColumns.has('value_data'));
    }

    function applyTableReferences() {
        const reference = metadata => {
            const first = metadata[0] || {};
            const comment = String(first.table_comment || first.table || '').trim();
            const tableName = String(first.table || '').trim();
            return comment && tableName ? `${comment}(${tableName})` : tableName;
        };
        const references = { standard: reference(standardMeta), source: reference(sourceMeta) };
        document.querySelectorAll('[data-table-reference]').forEach(element => {
            element.textContent = references[element.dataset.tableReference] || '';
        });
    }

    function resetSystemCard() {
        modalCardCollapses.reset();
    }

    function renderSystemInfo(data = {}) {
        const valueCardHidden = document.getElementById('standardValueCard')?.classList.contains('d-none');
        const hasInputField = key => {
            if (key === 'value_data') {
                return !valueCardHidden && (
                    document.querySelector('[data-value-key]') !== null
                    || document.querySelector('[data-matrix-field]') !== null
                    || structuredEditors.size > 0
                );
            }
            const input = form.elements.namedItem(key);
            return input instanceof HTMLElement
                && String(input.getAttribute('type') || '').toLowerCase() !== 'hidden'
                && input.closest('.d-none') === null;
        };
        const fields = standardMeta.filter(meta => !hasInputField(String(meta.key || '')));
        document.getElementById('statutorySystemInfoFields').innerHTML = fields.map(meta => {
            const key = String(meta.key || '');
            const field = standardField(key);
            let value = data[key];
            if (key.endsWith('_by')) value = actorDisplay(data, key);
            if (value && typeof value === 'object') {
                value = JSON.stringify(value, null, 2);
            }
            const hasValue = value !== null && value !== undefined && value !== '';
            return `<div class="statutory-system-field"><span class="statutory-system-label">${escapeHtml(field.label)}</span><span class="statutory-system-value${hasValue ? '' : ' text-muted'}">${escapeHtml(hasValue ? value : '저장 후 생성')}</span></div>`;
        }).join('');
    }

    async function init() {
        typeSelect.innerHTML = '<option value="">선택</option>' + templates.map(item =>
            `<option value="${escapeHtml(item.code)}">${escapeHtml(item.name)} / ${escapeHtml(item.code)}</option>`).join('');
        bindCommonPickers();
        table = await createDataTable({
            tableSelector: '#statutory-standard-table',
            api: API.LIST,
            columns: columns(),
            serverSide: true,
            redrawAfterInitialVisibility: false,
            deleteApi: API.DELETE,
            selectable: true,
            showCopyButton: true,
            deleteButton: Boolean(capabilities.delete),
            bulkDelete: true,
            rowReorder: true,
            showColumnVisibility: false,
            searchTableId: 'statutoryStandard',
            cellSearchFill: {
                fieldMap: ({ field }) => ({
                    standard_type_name: 'standard_type_code',
                    effective_from: 'effective_year',
                    effective_to: 'effective_year',
                })[field] || field,
                valueMap: ({ field, row, cell }) => {
                    if (field === 'standard_type_name') {
                        return row.standard_type_name || row.standard_type_code || '';
                    }
                    if (field === 'effective_from' || field === 'effective_to') {
                        return String(cell.data() || '').slice(0, 4);
                    }
                    return undefined;
                },
            },
            widthScopeSelector: '.settings-content-card',
            defaultOrder: [[3, 'desc']],
            tableSettings: {
                pageKey: 'dashboard.settings.statutory-standards',
                tableKey: 'statutory-standard-table',
                storageKey: 'datatable.settings.dashboard.settings.statutory-standards.v2',
                metaDomain: 'statutory-standard',
                tableLabel: '법정기준',
                title: '법정기준 테이블 설정',
                columnsImmediatelyBeforeActions: ['__period_status'],
            },
            buttons: capabilities.save ? [{
                text: '신규등록', className: 'btn btn-warning btn-sm', action: openCreate,
            }] : [],
        });
        table.on('init.dt draw.dt xhr.dt', () => {
            updateCount(table.page.info()?.recordsDisplay ?? 0);
        });
        document.querySelector('#statutory-standard-table tbody')?.addEventListener('dblclick', event => {
            if (event.target.closest('button, a, input, select, textarea, .reorder-handle')) return;
            const tableRow = event.target.closest('tr');
            const row = tableRow ? table.row(tableRow).data() : null;
            if (row?.id) openDetail(row.id);
        });
        bindRowReorder(table, {
            api: API.REORDER,
            onSuccess: () => table.ajax.reload(null, false),
            onError: json => window.alert(json?.message || '순서 저장 중 오류가 발생했습니다.'),
        });
        applyPhysicalFieldSettings();
        applyModalCardVisibility();
        applyTableReferences();
        document.addEventListener('datatable-settings:updated', event => {
            if (event.detail?.metaDomain === 'statutory-standard') {
                applyPhysicalFieldSettings();
                applyModalCardVisibility();
                renderSystemInfo(currentDetail);
            }
        });
        SearchForm({
            table,
            apiList: API.LIST,
            tableId: 'statutoryStandard',
            defaultSearchField: 'standard_type_code',
            dateOptions: [{ value: 'as_of_date', label: '기준일' }],
            initialCollapsed: true,
        });
        root.classList.remove('is-search-initializing');
        form.addEventListener('submit', save);
        document.getElementById('sourceAddButton').addEventListener('click', addSource);
        document.getElementById('standardRenewalButton')?.addEventListener('click', startPeriodRenewal);
        document.getElementById('standardDeleteButton')?.addEventListener('click', remove);
    }

    function updateCount(count) {
        const element = document.getElementById('statutoryStandardCount');
        if (element) element.textContent = `총 ${count ?? 0}건`;
    }

    function columns() {
        const periodStatus = {
            CURRENT: { label: '현재 적용', className: 'text-bg-success' },
            ENDED: { label: '종료', className: 'text-bg-secondary' },
            SCHEDULED: { label: '적용 예정', className: 'text-bg-info' },
        };
        return [
            { title: '<i class="bi bi-arrows-move"></i>', settingsKey: '__reorder', __dtColumnKind: 'virtual', className: 'reorder-handle no-sort no-colvis text-center', orderable: false, searchable: false, render: () => '<i class="bi bi-list"></i>' },
            { data: 'sort_no', title: metaByKey(standardMeta, 'sort_no').label, className: 'text-center' },
            { data: 'standard_type_name', name: 'standard_type_code', title: metaByKey(standardMeta, 'standard_type_code').label, render: data => escapeHtml(data || '') },
            { data: 'effective_from', title: metaByKey(standardMeta, 'effective_from').label, className: 'text-center' },
            { data: 'effective_to', title: metaByKey(standardMeta, 'effective_to').label, className: 'text-center', defaultContent: '' },
            { data: 'value_summary', settingsKey: 'value_data', name: 'value_data', title: '대표값', orderable: false, defaultContent: '-', render: data => escapeHtml(data || '-') },
            { data: 'source_count', title: '관련근거', className: 'text-center', render: data => `${Number(data || 0)}건` },
            { data: 'note', title: metaByKey(standardMeta, 'note').label, defaultContent: '' },
            actorColumn('created_by', '생성자', { visible: false, defaultContent: '' }),
            { data: 'created_at', title: metaByKey(standardMeta, 'created_at').label, className: 'text-center', visible: false },
            actorColumn('updated_by', '수정자', { defaultContent: '' }),
            { data: 'updated_at', title: metaByKey(standardMeta, 'updated_at').label, className: 'text-center' },
            { data: 'period_status', settingsKey: '__period_status', __dtColumnKind: 'virtual', title: '적용상태', className: 'text-center no-colvis', orderable: false, searchable: false, render: data => {
                const status = periodStatus[String(data || '')] || { label: '-', className: 'text-bg-light' };
                return `<span class="badge ${status.className}">${status.label}</span>`;
            } },
            { data: null, settingsKey: '__actions', title: '관리', orderable: false, searchable: false, className: 'text-center no-colvis', render: () => '<button type="button" class="btn btn-outline-primary btn-sm" data-action="detail">상세</button>' },
        ];
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-action="detail"]');
        if (!button || !table) return;
        const row = table.row(button.closest('tr')).data();
        if (row?.id) openDetail(row.id);
    });

    function openCreate() {
        document.getElementById('standardModalLabel').textContent = '법정기준 신규등록';
        form.reset();
        AdminPicker.clearSelect2(typeSelect, false);
        currentDetail = {};
        form.elements.id.value = '';
        values = {}; sources = [];
        renderDynamic(); renderSources(); renderSystemInfo(); resetSystemCard();
        document.getElementById('standardRenewalButton')?.classList.add('d-none');
        document.getElementById('standardDeleteButton')?.classList.add('d-none');
        modal.show();
    }

    async function openDetail(id) {
        let json;
        try { json = await request(`${API.DETAIL}?id=${encodeURIComponent(id)}`); }
        catch (error) { window.alert(error.message); return; }
        const data = json.data;
        document.getElementById('standardModalLabel').textContent = '법정기준 상세';
        currentDetail = data;
        ['id', 'standard_type_code', 'effective_from', 'effective_to', 'note'].forEach(key => {
            form.elements[key].value = data[key] ?? '';
        });
        AdminPicker.setSelect2Value(typeSelect, data.standard_type_code || '', false);
        values = data.value_data || {}; sources = data.sources || [];
        renderDynamic(); renderSources(); renderSystemInfo(data); resetSystemCard();
        document.getElementById('standardRenewalButton')?.classList.toggle('d-none', !capabilities.save);
        document.getElementById('standardDeleteButton')?.classList.toggle('d-none', !capabilities.delete);
        modal.show();
    }

    function collectCurrentInputs(validateStructured = true) {
        const collectedValues = {};
        collectSources();
        structuredEditors.forEach((editor, key) => {
            if (validateStructured) {
                const messages = editor.validate();
                if (messages.length) throw new Error(messages[0]);
            }
            collectedValues[key] = editor.getValue();
        });
        form.querySelectorAll('[data-value-key]').forEach(input => {
            if (input.dataset.valueType === 'json') {
                try { collectedValues[input.dataset.valueKey] = JSON.parse(input.value); }
                catch (error) { throw new Error(`${input.closest('div')?.querySelector('label')?.textContent || 'JSON'} 형식이 올바르지 않습니다.`); }
            } else if (input.dataset.valueNumber === 'true') {
                const number = input.value.trim() === '' ? '' : parseNumber(input.value);
                collectedValues[input.dataset.valueKey] = input.dataset.valueRate === 'true' && number !== ''
                    ? percentToRate(number)
                    : number;
            } else {
                collectedValues[input.dataset.valueKey] = input.value;
            }
        });
        const calculationPolicy = {};
        form.querySelectorAll('[data-policy-key]').forEach(input => {
            if (input.dataset.policyNumber === 'true') {
                const number = input.value.trim() === '' ? '' : parseNumber(input.value);
                calculationPolicy[input.dataset.policyKey] = input.dataset.policyRate === 'true' && number !== ''
                    ? percentToRate(number)
                    : number;
            } else if (input.dataset.policyBoolean === 'true') {
                calculationPolicy[input.dataset.policyKey] = input.value === '' ? '' : input.value === 'true';
            } else {
                calculationPolicy[input.dataset.policyKey] = input.value;
            }
        });
        if (Object.keys(calculationPolicy).length > 0) collectedValues.calculation_policy = calculationPolicy;
        values = collectedValues;
    }

    async function startPeriodRenewal() {
        if (!form.elements.id.value || !capabilities.save) return;
        if (!currentDetail.effective_to) {
            window.alert('기존 현행 기준의 적용종료일을 먼저 저장해 주세요.\n종료일 확정 후 개정 등록을 진행할 수 있습니다.');
            return;
        }
        const confirmed = await confirmDialog({
            title: '개정 등록',
            message: '현재 내용을 새로운 법정기준으로 등록하시겠습니까?\n\n기존 등록내용은 변경되지 않습니다.\n현재 입력된 기준값, 계산처리 기준 및 관련근거를 바탕으로\n새로운 법정기준을 등록할 수 있도록 신규등록 상태로 전환합니다.\n\n적용기간이 기존 기준과 중복되는 경우 저장할 수 없습니다.',
            confirmText: '진행',
            cancelText: '취소',
        });
        if (!confirmed) return;

        try {
            collectCurrentInputs(false);
            ({ values, sources } = preparePeriodRenewalDraft({ values, sources }));
            form.elements.id.value = '';
            form.elements.effective_from.value = '';
            form.elements.effective_to.value = '';
            form.querySelectorAll('[data-source-field="id"]').forEach(input => { input.value = ''; });
            form.querySelectorAll('.statutory-source-file-link').forEach(link => link.remove());
            currentDetail = {};
            document.getElementById('standardModalLabel').textContent = '법정기준 신규등록(개정)';
            document.getElementById('standardRenewalButton')?.classList.add('d-none');
            document.getElementById('standardDeleteButton')?.classList.add('d-none');
            renderSystemInfo();
            resetSystemCard();
        } catch (error) {
            window.alert(error.message);
        }
    }

    function renderDynamic() {
        structuredEditors.forEach(editor => editor.destroy());
        structuredEditors.clear();
        const selectedCode = String(typeSelect.value || '').trim();
        const current = template(selectedCode);
        const container = document.getElementById('standardValueFields');
        const policySection = document.getElementById('standardCalculationPolicySection');
        const policyContainer = document.getElementById('standardCalculationPolicyFields');
        policySection.classList.add('d-none');
        policyContainer.innerHTML = '';
        if (selectedCode === '') {
            container.innerHTML = '<div class="col-12 text-muted">법정기준 종류를 선택하세요.</div>';
            return;
        }
        if (!current) {
            container.innerHTML = '<div class="col-12 text-danger">선택한 법정기준 종류의 입력 정보를 불러오지 못했습니다.</div>';
            return;
        }
        const currentFields = Array.isArray(current.fields) ? current.fields : [];
        const snapshotFields = values?._schema?.fields;
        const fields = current.preserve_schema_in_value && Array.isArray(snapshotFields) && snapshotFields.length
            ? fieldsWithCurrentPresentation(snapshotFields, currentFields)
            : currentFields;
        container.innerHTML = fields.map(rawField => {
            const field = templateField(rawField);
            const value = values[field.code] ?? '';
            if (['matrix', 'bracket'].includes(field.type)) {
                const label = field.ui?.collapsible === true ? '' : `<label class="form-label">${fieldLabelHtml(field)}</label>`;
                return `<div class="col-12">${label}<div data-matrix-field="${escapeHtml(field.code)}"></div></div>`;
            }
            if (field.type === 'rounding') {
                const options = bootstrapData.options?.roundingMethods || [];
                return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label><select class="form-select admin-select2" data-hide-common-add="true" data-value-key="${escapeHtml(field.code)}"${requiredAttribute(field)}><option value="">선택</option>${options.map(item => `<option value="${escapeHtml(item.value)}"${item.value === value ? ' selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></div>`;
            }
            if (field.type === 'json') {
                const serialized = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
                return `<div class="col-12"><label class="form-label">${fieldLabelHtml(field)}</label><textarea class="form-control font-monospace" rows="8" data-value-key="${escapeHtml(field.code)}" data-value-type="json"${requiredAttribute(field)}>${escapeHtml(serialized)}</textarea></div>`;
            }
            const fieldType = String(field.type || '').toLowerCase();
            const isNumeric = ['amount', 'number', 'rate'].includes(fieldType);
            const isRate = fieldType === 'rate';
            const isAmount = fieldType === 'amount';
            const input = `<input class="form-control" type="text"${isNumeric ? ' inputmode="decimal" data-value-number="true"' : ''}${isRate ? ' data-value-rate="true"' : ''} data-value-key="${escapeHtml(field.code)}" value="${escapeHtml(isRate ? rateToPercent(value) : value)}"${requiredAttribute(field)}>`;
            const unit = String(field.unit_label || (isRate ? '%' : (isAmount ? '원' : '')));
            return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label>${unit ? `<div class="input-group">${input}<span class="input-group-text">${unit}</span></div>` : input}</div>`;
        }).join('') || '<div class="col-12 text-muted">이 종류는 코드관리의 입력 템플릿 설정이 필요합니다.</div>';
        fields.forEach(rawField => {
            const field = templateField(rawField);
            if (!['matrix', 'bracket'].includes(field.type)) return;
            const host = container.querySelector(`[data-matrix-field="${CSS.escape(field.code)}"]`);
            if (host) {
                const Editor = field.type === 'bracket' ? BracketEditor : MatrixEditor;
                structuredEditors.set(field.code, new Editor({ host, field, value: values[field.code] || [] }));
            }
        });
        const snapshotPolicyFields = values?._schema?.calculation_policy?.fields;
        const currentPolicyFields = Array.isArray(current.calculation_policy?.fields) ? current.calculation_policy.fields : [];
        const policyFields = current.preserve_schema_in_value && Array.isArray(snapshotPolicyFields) && snapshotPolicyFields.length
            ? fieldsWithCurrentPresentation(snapshotPolicyFields, currentPolicyFields)
            : currentPolicyFields;
        if (policyFields.length > 0) {
            policySection.classList.remove('d-none');
            policyContainer.innerHTML = policyFields.map(rawField => {
                const field = templateField(rawField);
                const value = values?.calculation_policy?.[field.code] ?? '';
                if (field.type === 'rounding') {
                    const options = bootstrapData.options?.roundingMethods || [];
                    return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label><select class="form-select admin-select2" data-hide-common-add="true" data-policy-key="${escapeHtml(field.code)}"${requiredAttribute(field)}><option value="">선택</option>${options.map(item => `<option value="${escapeHtml(item.value)}"${item.value === value ? ' selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></div>`;
                }
                if (field.type === 'select') {
                    const options = Array.isArray(field.options) ? field.options : [];
                    return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label><select class="form-select admin-select2" data-hide-common-add="true" data-policy-key="${escapeHtml(field.code)}"${requiredAttribute(field)}><option value="">선택</option>${options.map(item => `<option value="${escapeHtml(item.value)}"${String(item.value) === String(value) ? ' selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></div>`;
                }
                if (field.type === 'boolean') {
                    return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label><select class="form-select admin-select2" data-hide-common-add="true" data-policy-key="${escapeHtml(field.code)}" data-policy-boolean="true"${requiredAttribute(field)}><option value="">선택</option><option value="true"${value === true ? ' selected' : ''}>예</option><option value="false"${value === false ? ' selected' : ''}>아니오</option></select></div>`;
                }
                const fieldType = String(field.type || '').toLowerCase();
                const isNumeric = ['amount', 'number', 'rate'].includes(fieldType);
                const isRate = fieldType === 'rate';
                const isAmount = fieldType === 'amount';
                const input = `<input class="form-control" type="text"${isNumeric ? ' inputmode="decimal" data-policy-number="true"' : ''}${isRate ? ' data-policy-rate="true"' : ''} data-policy-key="${escapeHtml(field.code)}" value="${escapeHtml(isRate ? rateToPercent(value) : value)}"${requiredAttribute(field)}>`;
                const unit = String(field.unit_label || (isRate ? '%' : (isAmount ? '원' : '')));
                return `<div class="col-md-4"><label class="form-label">${fieldLabelHtml(field)}</label>${unit ? `<div class="input-group">${input}<span class="input-group-text">${escapeHtml(unit)}</span></div>` : input}</div>`;
            }).join('');
        }
        initModalSelect2Controls(container);
        initModalSelect2Controls(policyContainer);
        container.querySelectorAll('[data-value-number="true"]').forEach(input => bindNumberInput(input));
        policyContainer.querySelectorAll('[data-policy-number="true"]').forEach(input => bindNumberInput(input));
    }

    function renderSources() {
        const sourceName = sourceField('source_name');
        const organization = sourceField('organization_name');
        const law = sourceField('law_name');
        const notice = sourceField('notice_no');
        const published = sourceField('published_at');
        const url = sourceField('source_url');
        const file = sourceField('file_name');
        const note = sourceField('note');
        document.getElementById('standardSources').innerHTML = sources.map((source, index) => `
            <div class="statutory-source-row" data-source-index="${index}">
                <input type="hidden" data-source-field="id" value="${escapeHtml(source.id || '')}">
                <div><label class="form-label">${fieldLabelHtml(sourceName)}</label><input class="form-control" data-source-field="source_name" value="${escapeHtml(source.source_name || '')}"${requiredAttribute(sourceName)}></div>
                <div><label class="form-label">${fieldLabelHtml(organization)}</label><input class="form-control" data-source-field="organization_name" value="${escapeHtml(source.organization_name || '')}"${requiredAttribute(organization)}></div>
                <div><label class="form-label">${fieldLabelHtml(law)}</label><input class="form-control" data-source-field="law_name" value="${escapeHtml(source.law_name || '')}"${requiredAttribute(law)}></div>
                <div><label class="form-label">${fieldLabelHtml(notice)}</label><input class="form-control" data-source-field="notice_no" value="${escapeHtml(source.notice_no || '')}"${requiredAttribute(notice)}></div>
                <div class="statutory-source-field statutory-source-date"><label class="form-label">${fieldLabelHtml(published)}</label><div class="date-input"><input type="text" class="form-control admin-date" data-source-field="published_at" inputmode="numeric" maxlength="10" autocomplete="off" value="${escapeHtml(source.published_at || '')}"${requiredAttribute(published)}><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></div></div>
                <div class="statutory-source-url"><label class="form-label">${fieldLabelHtml(url)}</label><div class="statutory-source-url-line"><input type="url" class="form-control" data-source-field="source_url" value="${escapeHtml(source.source_url || '')}"${requiredAttribute(url)}><button type="button" class="btn btn-outline-secondary btn-sm statutory-source-url-open" data-source-open-url="${index}"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> URL 열기</button></div></div>
                <div><label class="form-label">${fieldLabelHtml(file)}</label><input type="file" class="form-control" name="source_files[]"${sourceFileAccept ? ` accept="${escapeHtml(sourceFileAccept)}"` : ''}${requiredAttribute(file)}>${source.file_path ? `<a class="statutory-source-file-link" target="_blank" rel="noopener" href="${API.SOURCE_FILE}?id=${encodeURIComponent(source.id)}" title="${escapeHtml(source.file_name || '첨부파일')}"><i class="bi bi-paperclip" aria-hidden="true"></i><span>${escapeHtml(source.file_name || '첨부파일')}</span></a>` : ''}</div>
                <div class="statutory-source-note"><label class="form-label">${fieldLabelHtml(note)}</label><div class="statutory-source-note-line"><input class="form-control" data-source-field="note" value="${escapeHtml(source.note || '')}"${requiredAttribute(note)}><button type="button" class="btn btn-outline-danger btn-sm statutory-source-remove" data-source-remove="${index}">제거</button></div></div>
            </div>`).join('') || '<p class="text-muted mb-0">등록된 관련근거가 없습니다.</p>';
        document.querySelectorAll('[data-source-remove]').forEach(button => button.addEventListener('click', () => {
            collectSources(); sources.splice(Number(button.dataset.sourceRemove), 1); renderSources();
        }));
        document.querySelectorAll('[data-source-open-url]').forEach(button => button.addEventListener('click', () => {
            const row = button.closest('.statutory-source-row');
            const value = row?.querySelector('[data-source-field="source_url"]')?.value.trim() || '';
            let target;
            try {
                target = new URL(value);
            } catch (_error) {
                window.alert('올바른 자료 URL을 입력하세요.');
                return;
            }
            if (!['http:', 'https:'].includes(target.protocol)) {
                window.alert('http 또는 https 자료 URL만 열 수 있습니다.');
                return;
            }
            window.open(target.href, '_blank', 'noopener,noreferrer');
        }));
    }

    function addSource() {
        collectSources();
        const pendingBlankRow = Array.from(document.querySelectorAll('.statutory-source-row')).find(row => {
            const source = sources[Number(row.dataset.sourceIndex)] || {};
            if (String(source.id || '').trim() !== '') return false;

            const hasValue = Array.from(row.querySelectorAll('[data-source-field]')).some(input => (
                input.dataset.sourceField !== 'id' && String(input.value || '').trim() !== ''
            ));
            const hasFile = Array.from(row.querySelectorAll('input[type="file"]')).some(input => input.files?.length > 0);
            return !hasValue && !hasFile;
        });
        if (pendingBlankRow) {
            pendingBlankRow.querySelector('[data-source-field]:not([data-source-field="id"])')?.focus();
            window.alert('먼저 추가된 관련근거를 입력하거나 제거하세요.');
            return;
        }

        sources.push({});
        renderSources();
        document.querySelector('.statutory-source-row:last-child [data-source-field]:not([data-source-field="id"])')?.focus();
    }

    function collectSources() {
        sources = Array.from(document.querySelectorAll('.statutory-source-row')).map(row => {
            const original = sources[Number(row.dataset.sourceIndex)] || {};
            const result = { ...original };
            row.querySelectorAll('[data-source-field]').forEach(input => { result[input.dataset.sourceField] = input.value.trim(); });
            return result;
        });
    }

    async function save(event) {
        event.preventDefault();
        try {
            collectCurrentInputs(true);
            const payload = new FormData(form);
            payload.set('value_data', JSON.stringify(values));
            payload.set('sources', JSON.stringify(sources));
            payload.set('column_requirement_policy', JSON.stringify(tableState().columnRequirementPolicy || {}));
            await request(API.SAVE, { method: 'POST', body: payload });
            modal.hide(); table.ajax.reload(null, false);
        } catch (error) {
            window.alert(error.message);
        }
    }

    async function remove() {
        const sourceCount = Array.isArray(currentDetail.sources) ? currentDetail.sources.length : sources.length;
        const confirmed = await confirmDialog({
            title: '법정기준 영구삭제',
            message: `${currentDetail.standard_type_name || typeSelect.value || '선택한 법정기준'} `
                + `(${form.elements.effective_from.value || '-'} ~ ${form.elements.effective_to.value || '현재'})를 삭제합니다. `
                + `연결된 근거자료 ${sourceCount}건과 파일도 함께 삭제되며, 해당 적용기간에 기준 공백이 생길 수 있습니다.`,
            confirmText: '영구삭제',
            confirmClass: 'btn-danger',
        });
        if (!confirmed) return;
        try {
            await request(API.DELETE, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: form.elements.id.value }) });
            modal.hide(); table.ajax.reload(null, false);
        } catch (error) {
            window.alert(error.message);
        }
    }

    async function request(url, options = {}) {
        const response = await fetch(url, options);
        const json = await response.json();
        if (!response.ok || !json.success) throw new Error(json.message || '처리 중 오류가 발생했습니다.');
        return json;
    }

    init();
})();
