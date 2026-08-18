import { actorDisplay } from '/public/assets/js/common/actor.js';

export function createBankAccountTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    API,
    DATE_OPTIONS,
    ACCOUNT_COLUMN_MAP,
    getCodeName,
    formatAccountNumber,
    formModule,
    modalModule,
    state,
}) {
    function updateAccountCount(count) {
        const el = document.getElementById('accountCount');
        if (!el) return;
        el.textContent = `총 ${Number(count ?? 0).toLocaleString()}건`;
    }

    function normalizeAccountFilters(filters) {
        return (filters || []).map((filter) => {
            if (filter?.field !== 'is_active') return filter;
            const value = normalizeActiveValue(filter.value);
            return value === '' ? null : { field: 'is_active', value };
        }).filter(Boolean);
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function buildAccountColumns() {
        const columns = [{
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            width: '40px',
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>',
        }];

        Object.entries(ACCOUNT_COLUMN_MAP).forEach(([field, config]) => {
            if (field === 'is_active') return;

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                width: config.width || null,
                className: config.className || '',
                defaultContent: '',
                render(data, type, row) {
                    if (data == null) return '';
                    if (type !== 'display') return data;

                    if (config.type === 'actor') return actorDisplay(row, field);

                    if (field === 'bank_file') {
                        if (!data) return '';
                        const path = encodeURIComponent(data);
                        return `<a href="/api/file/preview?path=${path}" target="_blank">미리보기</a>`;
                    }

                    if (field === 'currency' || field === 'account_type') {
                        return getCodeName(field, data);
                    }

                    if (field === 'bank_name') {
                        return formModule.escapeHtml(getCodeName(field, data) || data);
                    }

                    if (field === 'account_number') {
                        return formModule.escapeHtml(formatAccountNumber(data, row?.bank_name || ''));
                    }

                    if (field === 'note') {
                        const value = formModule.escapeHtml(data);
                        return `<span class="account-note-text" title="${value}">${value}</span>`;
                    }

                    return data;
                },
            });
        });

        columns.push({
            data: 'is_active',
            title: ACCOUNT_COLUMN_MAP.is_active.label,
            visible: true,
            className: 'text-center',
            defaultContent: '',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = String(data) === '1';
                return `
                    <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                        <input type="checkbox"
                               class="form-check-input account-active-toggle"
                               data-id="${formModule.escapeHtml(row.id || '')}"
                               ${active ? 'checked' : ''}
                               aria-label="상태 변경">
                    </div>
                `;
            },
        });

        columns.push({
            data: null,
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            title: '관리',
            className: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm account-edit-btn"
                            data-id="${formModule.escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            },
        });

        return columns;
    }

    async function initDataTable() {
        state.accountTable = await createDataTable({
            tableSelector: '#account-table',
            api: API.LIST,
            deleteApi: API.DELETE,
            columns: buildAccountColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: {
                widthResizable: true,
            },
            tableSettings: {
                pageKey: 'dashboard.settings.base-info.bank-account',
                userSettingPageKey: 'bank-account',
                tableKey: 'account-table',
                storageKey: 'datatable.settings.dashboard.settings.base-info.bank-account.account-table.v1',
                metaDomain: 'bank-account',
                tableLabel: '계좌',
                title: '계좌 테이블 설정',
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action() {
                        const trashModalEl = document.getElementById('accountTrashModal');
                        if (!trashModalEl) return;

                        trashModalEl.dataset.listUrl = API.TRASH;
                        trashModalEl.dataset.restoreUrl = API.RESTORE;
                        trashModalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
                        trashModalEl.dataset.restoreAllUrl = API.RESTORE_ALL;
                        trashModalEl.dataset.deleteUrl = API.PURGE;
                        trashModalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

                        new bootstrap.Modal(trashModalEl).show();
                    },
                },
                {
                    text: '엑셀 관리',
                    className: 'btn btn-success btn-sm',
                    action() {
                        state.excelModal?.show();
                    },
                },
                {
                    text: '신규등록',
                    className: 'btn btn-warning btn-sm',
                    action() {
                        const form = document.getElementById('accountForm');
                        if (form) form.reset();

                        const currencyEl = document.getElementById('modal_account_currency');
                        if (currencyEl) {
                            currencyEl.value = 'KRW';
                        }

                        window.isNewAccount = true;

                        const titleEl = document.querySelector('#accountModal .modal-title');
                        if (titleEl) {
                            titleEl.textContent = '계좌 신규 등록';
                        }

                        const idEl = formModule.getIdEl();
                        if (idEl) idEl.value = '';

                        const deleteBtn = document.getElementById('btnDeleteAccount');
                        if (deleteBtn) deleteBtn.style.display = 'none';

                        const createdAtEl =
                            document.getElementById('account_created_at') ||
                            document.getElementById('modal_created_at');
                        if (createdAtEl && !createdAtEl.value) {
                            const d = new Date();
                            createdAtEl.value = d.toISOString().slice(0, 10);
                        }

                        formModule.resetBankBookUI();
                        state.accountModal?.show();

                        void formModule.preloadAccountModalControls().catch((error) => {
                            console.error('[bank-account] modal controls prepare failed', error);
                            formModule.notify('error', '계좌 입력 항목 준비 중 오류가 발생했습니다.');
                        });
                    },
                },
            ],
        });

        window.accountTable = state.accountTable;
        if (!state.accountTable) return;

        updateAccountCount(state.accountTable.page.info()?.recordsDisplay ?? 0);
        state.accountTable.on('draw.dt', () => {
            updateAccountCount(state.accountTable.page.info()?.recordsDisplay ?? 0);
        });

        SearchForm({
            table: state.accountTable,
            apiList: API.LIST,
            tableId: 'account',
            defaultSearchField: 'account_name',
            dateOptions: DATE_OPTIONS,
            normalizeFilters: normalizeAccountFilters,
        });
        bindTableHighlight('#account-table', state.accountTable);

        bindRowReorder(state.accountTable, {
            api: API.REORDER,
            onSuccess() {
                formModule.notify('success', '계좌 순서가 저장되었습니다.');
                state.accountTable?.ajax.reload(null, false);
            },
            onError(json) {
                formModule.notify('error', json?.message || '계좌 순서 저장에 실패했습니다.');
                state.accountTable?.ajax.reload(null, false);
            },
        });
    }

    async function updateAccountActive(accountId, active, toggleEl) {
        if (!accountId) return;
        if (toggleEl) toggleEl.disabled = true;

        try {
            const data = await modalModule.fetchAccountDetail(accountId);
            const formData = new FormData();
            Object.entries(data || {}).forEach(([key, value]) => {
                if (key === 'bank_file') return;
                formData.set(key, value ?? '');
            });

            formData.set('id', accountId);
            formData.set('is_active', active ? '1' : '0');
            formData.set('delete_bank_file', '0');

            await window.AppAjax.fetchJson(API.SAVE, { method: 'POST', body: formData });

            formModule.notify('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
            state.accountTable?.ajax.reload(null, false);
        } catch (error) {
            if (toggleEl) toggleEl.checked = !active;
            console.error(error);
            formModule.notify('error', error.message || '상태 변경 중 오류가 발생했습니다.');
        } finally {
            if (toggleEl) toggleEl.disabled = false;
        }
    }

    function bindTableEvents($) {
        $('#account-table tbody').on('change', '.account-active-toggle', function (event) {
            event.preventDefault();
            event.stopPropagation();
            updateAccountActive(this.dataset.id, this.checked, this);
        });

        $('#account-table tbody').on('click', '.account-edit-btn', function (event) {
            event.preventDefault();
            event.stopPropagation();
            modalModule.openAccountEditModal(this.dataset.id);
        });

        $('#account-table tbody').on('dblclick', 'tr', async function () {
            const row = state.accountTable.row(this).data();
            if (!row) return;
            await modalModule.openAccountEditModal(row.id);
        });
    }

    return {
        initDataTable,
        bindTableEvents,
        ACCOUNT_COLUMN_MAP,
    };
}
