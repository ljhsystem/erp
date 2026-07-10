import { actorNameField } from '/public/assets/js/common/actor.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';

const VOUCHER_META_DOMAIN = 'voucher-header';
const VOUCHER_TABLE_SETTINGS_KEY = 'datatable.settings.ledger.voucher.voucher-table.v1';
const VOUCHER_TABLE_PAGE_KEY = 'ledger.voucher';
const OVERVIEW_FIELDS = new Set(['voucher_no', 'voucher_date', 'summary']);

function isBlankValue(value) {
    const text = String(value ?? '').trim();
    return (
        text === ''
        || text === '0000-00-00'
        || text === '0000-00-00 00:00:00'
        || text === 'null'
        || text === 'undefined'
    );
}

function normalizeMetaField(meta = {}) {
    return String(meta?.column || meta?.key || '').trim();
}

function normalizeMetaColumn(meta = {}) {
    const field = normalizeMetaField(meta);
    return {
        ...meta,
        key: field,
        column: field,
        settingsKey: field,
        sourceField: String(meta?.source_title || field).trim() || field,
        __dtDefaultDisplayName: String(meta?.label || field).trim() || field,
    };
}

function readVoucherTableSettingsState() {
    return readDataTableSettingsState(VOUCHER_TABLE_SETTINGS_KEY, {
        userSettingPageKey: VOUCHER_TABLE_PAGE_KEY,
        metaDomain: VOUCHER_META_DOMAIN,
    });
}

function fieldLabel(meta = {}, state = null) {
    const column = normalizeMetaColumn(meta);
    return resolveDataTableColumnDisplayName(
        column,
        state,
        column.__dtDefaultDisplayName || column.key
    );
}

function fieldRequirementPolicy(meta = {}, state = null) {
    const column = normalizeMetaColumn(meta);
    const policy = resolveDataTableColumnRequirementPolicy(column, state);
    if (policy === 'required' || policy === 'optional') {
        return policy;
    }
    return meta?.required === true ? 'required' : 'none';
}

function fieldLabelHtml(ctx, label = '', policy = 'none') {
    const text = ctx.escapeHtml(label || '');
    if (policy === 'required') {
        return `${text}<span class="column-policy-star is-required" aria-hidden="true">*</span>`;
    }
    if (policy === 'optional') {
        return `${text}<span class="column-policy-star is-optional" aria-hidden="true">*</span>`;
    }
    return text;
}

function actorDisplayValue(ctx, row = {}, field = '') {
    const raw = String(row?.[field] ?? '').trim();
    if (raw === '') {
        return '-';
    }

    const enriched = String(row?.[actorNameField(field)] ?? '').trim();
    if (enriched !== '') {
        return enriched;
    }

    return String(ctx.actorDisplay?.(row, field) || '').trim() || raw;
}

function formatDateTime(ctx, value) {
    if (isBlankValue(value)) {
        return '-';
    }

    const raw = String(value).trim();
    const [datePart = '', timePart = ''] = raw.replace('T', ' ').split(' ');
    const formattedDate = ctx.formatDateInputValue?.(datePart) || datePart;
    if (timePart === '') {
        return formattedDate || raw;
    }

    return `${formattedDate || datePart} ${timePart}`;
}

function formatDisplayValue(ctx, field = '', data = {}) {
    const value = data?.[field];

    if (field === 'status') {
        return String(ctx.translateStatus?.(value || 'DRAFT') || value || '-').trim() || '-';
    }

    if (field === 'created_by' || field === 'updated_by' || field === 'deleted_by') {
        return actorDisplayValue(ctx, data, field);
    }

    if (/_at$/.test(field) || /_date$/.test(field)) {
        return formatDateTime(ctx, value);
    }

    if (/(^sort_no$|amount$|_amount$|_price$|quantity$|_count$|_rate$)/.test(field)) {
        return isBlankValue(value) ? '-' : String(ctx.formatNumber?.(value) || value).trim();
    }

    if (/_id$/.test(field)) {
        const nameField = field.replace(/_id$/, '_name');
        const nameValue = String(data?.[nameField] ?? '').trim();
        if (nameValue !== '') {
            return nameValue;
        }
    }

    return isBlankValue(value) ? '-' : String(value).trim();
}

function renderSystemInfoField(ctx, meta = {}, state = null, data = {}) {
    const field = normalizeMetaField(meta);
    const label = fieldLabel(meta, state);
    const policy = fieldRequirementPolicy(meta, state);
    const value = formatDisplayValue(ctx, field, data);

    return `
        <div class="journal-form-field journal-system-info-field">
            <label class="form-label">${fieldLabelHtml(ctx, label, policy)}</label>
            <input type="text" class="form-control form-control-sm" value="${ctx.escapeHtml(value)}" readonly>
        </div>
    `;
}

export function createVoucherDetailMetaManager(ctx) {
    async function fetchVoucherHeaderMeta() {
        if (Array.isArray(ctx.voucherHeaderMeta) && ctx.voucherHeaderMeta.length > 0) {
            return ctx.voucherHeaderMeta;
        }
        if (ctx.voucherHeaderMetaPromise) {
            return ctx.voucherHeaderMetaPromise;
        }

        ctx.voucherHeaderMetaPromise = ctx.fetchJson(`${ctx.API.systemTableColumns}?domain=${encodeURIComponent(VOUCHER_META_DOMAIN)}`)
            .then((json) => {
                ctx.voucherHeaderMeta = Array.isArray(json?.data) ? json.data : [];
                return ctx.voucherHeaderMeta;
            })
            .catch(() => {
                ctx.voucherHeaderMeta = [];
                return [];
            })
            .finally(() => {
                ctx.voucherHeaderMetaPromise = null;
            });

        return ctx.voucherHeaderMetaPromise;
    }

    function applyOverviewLabels(metaRows = []) {
        const state = readVoucherTableSettingsState();
        const metaMap = new Map(
            (Array.isArray(metaRows) ? metaRows : [])
                .map((meta) => [normalizeMetaField(meta), meta])
                .filter(([field]) => field !== '')
        );

        document.querySelectorAll('[data-voucher-label-for]').forEach((labelEl) => {
            const field = String(labelEl.getAttribute('data-voucher-label-for') || '').trim();
            const meta = metaMap.get(field) || { column: field, key: field, label: field };
            labelEl.innerHTML = fieldLabelHtml(
                ctx,
                fieldLabel(meta, state),
                fieldRequirementPolicy(meta, state)
            );
        });
    }

    function buildSystemInfoRows(metaRows = []) {
        return (Array.isArray(metaRows) ? metaRows : [])
            .filter((meta) => {
                const field = normalizeMetaField(meta);
                return field !== '' && !OVERVIEW_FIELDS.has(field);
            });
    }

    function renderSystemInfoFields(metaRows = [], data = {}) {
        const container = ctx.systemInfoFieldsEl;
        if (!container) {
            return;
        }

        const state = readVoucherTableSettingsState();
        container.innerHTML = buildSystemInfoRows(metaRows)
            .map((meta) => renderSystemInfoField(ctx, meta, state, data))
            .join('');
    }

    async function setVoucherDetailMeta(data = {}) {
        ctx.state.voucherDetailMetaData = data && typeof data === 'object' ? { ...data } : {};
        const metaRows = await fetchVoucherHeaderMeta();
        applyOverviewLabels(metaRows);
        renderSystemInfoFields(metaRows, ctx.state.voucherDetailMetaData);
    }

    document.addEventListener('datatable-settings:updated', (event) => {
        const storageKey = String(event?.detail?.storageKey || '').trim();
        const metaDomain = String(event?.detail?.metaDomain || '').trim();
        if (
            storageKey === VOUCHER_TABLE_SETTINGS_KEY
            || metaDomain === VOUCHER_META_DOMAIN
        ) {
            void setVoucherDetailMeta(ctx.state.voucherDetailMetaData || {});
        }
    });

    return setVoucherDetailMeta;
}
