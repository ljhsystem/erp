export function createEvidenceSearchModule({
    state,
    API,
    DISPLAY_CODE_FIELDS,
    SERVER_TYPE_POLICIES,
    SERVER_TYPE_PAGE_MAP,
    normalizeEvidenceType,
    evidenceTypePolicy,
    defaultEvidenceTypeCode,
    escapeHtml,
    initCodeSelectControls,
}) {
    const DEFAULT_READY_TYPE_PRIORITY = [
        'BANK_TRANSACTION',
        'TAX_INVOICE',
        'TAX_INVOICE_MANUAL',
        'CASH_RECEIPT',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
    ];

    function evidenceSummaryBucket(type = '') {
        const normalizedType = normalizeEvidenceType(type || '');
        return evidenceTypePolicy(normalizedType)?.summaryBucket === 'bank' ? 'bank' : 'evidence';
    }

    function evidenceTypeRoute(value = '') {
        const type = normalizeEvidenceType(value);
        if (type === '') {
            return '/ledger/data';
        }
        return SERVER_TYPE_PAGE_MAP[type] || `/ledger/data/list?import_type=${encodeURIComponent(type)}`;
    }

    function selectedTypeLabel() {
        const selected = state.refs.typeSelect?.selectedOptions?.[0];
        const text = String(selected?.textContent || '').trim();
        if (text && !text.startsWith('+') && !text.includes('기타추가')) {
            return text;
        }

        const found = (state.codeOptions.IMPORT_TYPE || []).find((row) => normalizeEvidenceType(row.code) === state.currentType);
        return found?.code_name || evidenceTypePolicy(state.currentType)?.label || '';
    }

    function normalizeCodeRows(rows = []) {
        return rows
            .map((row) => ({
                code: String(row.code ?? row.value ?? '').trim(),
                code_name: String(row.code_name ?? row.label ?? row.code ?? row.value ?? '').trim(),
                is_active: Number(row.is_active ?? 1),
            }))
            .filter((row) => row.code !== '' && row.is_active === 1);
    }

    async function loadDisplayCodeOptions() {
        await Promise.all(Object.values(DISPLAY_CODE_FIELDS).map(async (group) => {
            if ((state.codeOptions[group] || []).length > 0) return;
            const response = await fetch(`${API.codeList}?code_group=${encodeURIComponent(group)}&filters=[]`, { cache: 'no-store' });
            const json = await response.json().catch(() => ({}));
            const rows = Array.isArray(json) ? json : (json.data || []);
            state.codeOptions[group] = normalizeCodeRows(rows);
        }));
    }

    function currentConfig() {
        const policy = evidenceTypePolicy(state.currentType);
        const label = selectedTypeLabel() || policy?.label || state.currentType || '자료유형';

        return {
            label,
            api: `${API.seedRows}?import_type=${encodeURIComponent(state.currentType)}`,
            excelTemplate: policy?.excelTemplate || (state.currentType ? state.currentType.toLowerCase() : ''),
            dateOptions: [],
            evidenceColumns: [],
        };
    }

    function isEvidenceUploadType(value) {
        const type = normalizeEvidenceType(value);
        return Boolean(type && !type.startsWith('__') && evidenceTypePolicy(type));
    }

    function isEvidencePageType(value) {
        const type = normalizeEvidenceType(value);
        return Boolean(type && !type.startsWith('__') && (evidenceTypePolicy(type) || SERVER_TYPE_PAGE_MAP[type]));
    }

    function evidenceTypeOptions() {
        if (state.fixedType) {
            const fixedPolicy = SERVER_TYPE_POLICIES.find((policy) => policy.code === state.fixedType);
            if (!fixedPolicy) {
                if (SERVER_TYPE_PAGE_MAP[state.fixedType]) {
                    return [{
                        value: state.fixedType,
                        label: evidenceTypePolicy(state.fixedType)?.label || state.fixedType,
                        disabled: false,
                    }];
                }
                return [];
            }
            return [{
                value: fixedPolicy.code,
                label: fixedPolicy.label,
                disabled: false,
            }];
        }

        const optionMap = new Map(
            Array.from(state.refs.typeSelect?.options || []).map((option) => [
                normalizeEvidenceType(option.value || ''),
                {
                    value: normalizeEvidenceType(option.value || ''),
                    label: String(option.textContent || '').trim(),
                    disabled: option.disabled,
                },
            ])
        );

        return SERVER_TYPE_POLICIES
            .map((policy) => optionMap.get(policy.code) || {
                value: policy.code,
                label: policy.label,
                disabled: false,
            })
            .filter((option) => option.value && !option.disabled && !option.value.startsWith('__') && isEvidenceUploadType(option.value));
    }

    function fallbackEvidenceTypeOptions() {
        const values = Array.from(new Set([
            ...Object.keys(SERVER_TYPE_PAGE_MAP || {}),
            state.currentType,
            state.initialType,
            state.fixedType,
            defaultEvidenceTypeCode(),
        ].map((value) => normalizeEvidenceType(value || '')).filter(Boolean)));

        return values
            .filter((value) => isEvidencePageType(value))
            .map((value) => ({
                value,
                label: evidenceTypePolicy(value)?.label || value,
                disabled: false,
            }));
    }

    function evidenceTypeLabel(value) {
        const type = normalizeEvidenceType(value);
        const found = (state.codeOptions.IMPORT_TYPE || []).find((row) => normalizeEvidenceType(row.code) === type);
        return found?.code_name || evidenceTypePolicy(type)?.label || type;
    }

    function evidenceTypeDisplayName(row = {}) {
        const type = normalizeEvidenceType(row.import_type || row.source_type || state.currentType);
        const found = (state.codeOptions.IMPORT_TYPE || []).find((option) => normalizeEvidenceType(option.code) === type);
        return found?.code_name || selectedTypeLabel() || evidenceTypePolicy(type)?.label || type || '자료유형';
    }

    function renderEvidenceTypeTabs() {
        if (!state.refs.typeTabs) return;

        let options = evidenceTypeOptions();
        if (options.length === 0) {
            options = fallbackEvidenceTypeOptions();
        }
        if (state.refs.typeSelectCount) {
            const total = Number(state.evidenceTotalSummary.total || 0);
            const bank = Number(state.evidenceTotalSummary.bank || 0);
            const evidence = Number(state.evidenceTotalSummary.evidence || 0);
            state.refs.typeSelectCount.textContent = `전체 ${total.toLocaleString('ko-KR')}건`;
            state.refs.typeSelectCount.title = [
                `입출금(은행) ${bank.toLocaleString('ko-KR')}건`,
                `통합증빙 ${evidence.toLocaleString('ko-KR')}건`,
                `합계 ${total.toLocaleString('ko-KR')}건`,
            ].join(' / ');
        }

        state.refs.typeTabs.innerHTML = options.map((option) => {
            const count = Number(state.evidenceTypeCounts[option.value] || 0);
            const active = option.value === state.currentType ? ' active' : '';
            const label = option.label || evidenceTypeLabel(option.value);
            const pageReady = evidenceTypePolicy(option.value)?.pageReady !== false;
            return `
                <button type="button"
                    class="evidence-type-tab${active}"
                    data-evidence-type="${escapeHtml(option.value)}"
                    aria-pressed="${option.value === state.currentType ? 'true' : 'false'}">
                    <span>${escapeHtml(label)}</span>
                    <span class="evidence-type-tab-count">${pageReady ? count.toLocaleString('ko-KR') : '준비중'}</span>
                </button>
            `;
        }).join('');

        if (state.refs.typeTabs.childElementCount === 0) {
            const fallbackOptions = fallbackEvidenceTypeOptions();
            if (fallbackOptions.length > 0) {
                state.refs.typeTabs.innerHTML = fallbackOptions.map((option) => `
                    <button type="button"
                        class="evidence-type-tab${option.value === state.currentType ? ' active' : ''}"
                        data-evidence-type="${escapeHtml(option.value)}"
                        aria-pressed="${option.value === state.currentType ? 'true' : 'false'}">
                        <span>${escapeHtml(option.label || option.value)}</span>
                        <span class="evidence-type-tab-count">0</span>
                    </button>
                `).join('');
            }
        }

        state.refs.typeTabs.querySelectorAll('.evidence-type-tab').forEach((button) => {
            const type = button.dataset.evidenceType || '';
            const pageReady = evidenceTypePolicy(type)?.pageReady !== false;
            if (pageReady) {
                return;
            }

            const countEl = button.querySelector('.evidence-type-tab-count');
            if (!countEl) {
                return;
            }

            countEl.classList.add('evidence-type-tab-count-planned');
            countEl.textContent = '준비중';
        });
    }

    async function refreshEvidenceTypeCounts() {
        if (state.fixedType) {
            const response = await fetch(`${API.seedRows}?type_counts=1`, { cache: 'no-store' });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || json.success === false) {
                throw new Error(json.message || '?먮즺?좏삎蹂?嫄댁닔瑜?遺덈윭?ㅼ? 紐삵뻽?듬땲??');
            }

            let fixedCount = 0;
            (Array.isArray(json.data) ? json.data : []).forEach((row) => {
                const type = normalizeEvidenceType(row.import_type || row.source_type || '');
                if (type === state.fixedType) {
                    fixedCount = Number(row.row_count || row.count || 0);
                }
            });
            state.evidenceTypeCounts = { [state.fixedType]: fixedCount };
            const bucket = evidenceSummaryBucket(state.fixedType);
            state.evidenceTotalSummary = {
                total: fixedCount,
                bank: bucket === 'bank' ? fixedCount : 0,
                evidence: bucket === 'bank' ? 0 : fixedCount,
            };
            renderEvidenceTypeTabs();
            return;
        }

        const response = await fetch(`${API.seedRows}?type_counts=1`, { cache: 'no-store' });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '자료유형별 건수를 불러오지 못했습니다.');
        }

        const nextCounts = {};
        (Array.isArray(json.data) ? json.data : []).forEach((row) => {
            const type = normalizeEvidenceType(row.import_type || row.source_type || '');
            if (!type) return;
            nextCounts[type] = Number(row.row_count || row.count || 0);
        });

        const total = Object.values(nextCounts).reduce((sum, count) => sum + Number(count || 0), 0);
        const bank = Object.entries(nextCounts).reduce((sum, [type, count]) => (
            evidenceSummaryBucket(type) === 'bank' ? sum + Number(count || 0) : sum
        ), 0);
        state.evidenceTypeCounts = nextCounts;
        state.evidenceTotalSummary = {
            total,
            bank,
            evidence: total - bank,
        };
        renderEvidenceTypeTabs();
    }

    function filterEvidenceTypeSelect() {
        if (!state.refs.typeSelect) return;

        const existingOptions = new Map(
            Array.from(state.refs.typeSelect.options || [])
                .map((option) => [normalizeEvidenceType(option.value || ''), option])
                .filter(([value]) => value && !value.startsWith('__'))
        );

        state.refs.typeSelect.innerHTML = '';
        const policies = state.fixedType
            ? SERVER_TYPE_POLICIES.filter((policy) => policy.code === state.fixedType)
            : SERVER_TYPE_POLICIES;
        policies.forEach((policy) => {
            const option = existingOptions.get(policy.code) || new Option(policy.label, policy.code, false, false);
            option.value = policy.code;
            option.textContent = String(option.textContent || '').trim() || policy.label || policy.code;
            option.disabled = false;
            state.refs.typeSelect.appendChild(option);
        });

        if (window.jQuery?.fn?.select2 && window.jQuery(state.refs.typeSelect).hasClass('select2-hidden-accessible')) {
            window.jQuery(state.refs.typeSelect).trigger('change.select2');
        }
        renderEvidenceTypeTabs();
    }

    function firstAvailableType() {
        if (state.fixedType) {
            return state.fixedType;
        }
        if (state.initialType && isEvidencePageType(state.initialType)) {
            return state.initialType;
        }
        for (const type of DEFAULT_READY_TYPE_PRIORITY) {
            if (isEvidencePageType(type)) {
                return type;
            }
        }
        return SERVER_TYPE_POLICIES[0]?.code || defaultEvidenceTypeCode();
    }

    function syncTypeControls() {
        const config = currentConfig();
        const policy = evidenceTypePolicy(state.currentType);
        if (state.refs.typeSelect && state.refs.typeSelect.value !== state.currentType) {
            state.refs.typeSelect.value = state.currentType;
        }
        if (window.jQuery?.fn?.select2 && window.jQuery(state.refs.typeSelect).data('select2')) {
            window.jQuery(state.refs.typeSelect).val(state.currentType).trigger('change.select2');
        }
        if (state.refs.excelLabel) {
            state.refs.excelLabel.textContent = `${config.label} / 템플릿 ${config.excelTemplate}`;
        }
        renderEvidenceTypeTabs();
        if (state.refs.typeSelectCount && policy?.pageReady === false) {
            const currentTitle = String(state.refs.typeSelectCount.title || '').trim();
            const noticeTitle = policy.pageNotice || `${config.label} 페이지는 개발예정입니다.`;
            state.refs.typeSelectCount.title = currentTitle !== '' ? `${currentTitle} / ${noticeTitle}` : noticeTitle;
        }
    }

    function changeType(nextType) {
        nextType = normalizeEvidenceType(nextType);
        if (state.fixedType) {
            nextType = state.fixedType;
        }
        if (!nextType || String(nextType).startsWith('__') || !isEvidencePageType(nextType)) {
            return false;
        }
        if (nextType === state.currentType) {
            return false;
        }
        state.currentType = nextType;
        syncTypeControls();
        return true;
    }

    function handleTypeSelectChanged() {
        const value = String(state.refs.typeSelect?.value || '').trim();
        return changeType(value);
    }

    async function initTypeSelect() {
        if (state.fixedType) {
            state.currentType = state.fixedType;
            if (state.refs.typeSelect) {
                await initCodeSelectControls(document.getElementById('ledgerDataStatusPage') || document);
                filterEvidenceTypeSelect();
                state.refs.typeSelect.value = state.fixedType;
                if (window.jQuery?.fn?.select2 && window.jQuery(state.refs.typeSelect).hasClass('select2-hidden-accessible')) {
                    window.jQuery(state.refs.typeSelect).val(state.fixedType).trigger('change.select2');
                }
            }
            return;
        }

        if (!state.refs.typeSelect) {
            state.currentType = defaultEvidenceTypeCode();
            return;
        }

        await initCodeSelectControls(document.getElementById('ledgerDataStatusPage') || document);
        filterEvidenceTypeSelect();
        state.currentType = firstAvailableType();
        if (state.refs.typeSelect.value !== state.currentType) {
            state.refs.typeSelect.value = state.currentType;
            if (window.jQuery?.fn?.select2 && window.jQuery(state.refs.typeSelect).hasClass('select2-hidden-accessible')) {
                window.jQuery(state.refs.typeSelect).val(state.currentType).trigger('change.select2');
            }
        }
    }

    return {
        selectedTypeLabel,
        loadDisplayCodeOptions,
        currentConfig,
        isEvidenceUploadType,
        evidenceTypeOptions,
        evidenceTypeLabel,
        evidenceTypeDisplayName,
        renderEvidenceTypeTabs,
        refreshEvidenceTypeCounts,
        filterEvidenceTypeSelect,
        firstAvailableType,
        syncTypeControls,
        changeType,
        handleTypeSelectChanged,
        initTypeSelect,
        evidenceTypeRoute,
    };
}
