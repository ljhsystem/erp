export function createCardTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    formatAmount,
    API,
    CARD_COLUMN_MAP,
    DATE_OPTIONS,
    formModule,
    modalModule,
    state,
}) {
    function initDataTable() {
        const columns = buildCardColumns();

        state.cardTable = createDataTable({
            tableSelector: '#card-table',
            api: API.LIST,
            deleteApi: API.DELETE,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: {
                widthResizable: true,
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
                {
                    text: '엑셀 관리',
                    className: 'btn btn-success btn-sm',
                    action: () => state.excelModal?.show(),
                },
                {
                    text: '새 카드',
                    className: 'btn btn-warning btn-sm',
                    action: modalModule.openCreateModal,
                },
            ],
        });

        window.cardTable = state.cardTable;

        if (state.cardTable) {
            state.cardTable.on('init.dt draw.dt', () => {
                updateCardCount(state.cardTable.page.info()?.recordsDisplay ?? 0);
            });

            SearchForm({
                table: state.cardTable,
                apiList: API.LIST,
                tableId: 'card',
                defaultSearchField: 'card_name',
                dateOptions: DATE_OPTIONS,
                normalizeFilters: normalizeCardFilters,
            });

            bindTableHighlight('#card-table', state.cardTable);
        }

        bindRowReorder(state.cardTable, {
            api: API.REORDER,
            onSuccess() {
                formModule.notify('success', '카드 순서가 저장되었습니다.');
                state.cardTable?.ajax.reload(null, false);
            },
            onError(json) {
                formModule.notify('error', json?.message || '카드 순서 저장에 실패했습니다.');
                state.cardTable?.ajax.reload(null, false);
            },
        });
    }

    function openTrashModal() {
        const trashModalEl = document.getElementById('cardTrashModal');
        if (!trashModalEl) return;

        trashModalEl.dataset.listUrl = API.TRASH;
        trashModalEl.dataset.restoreUrl = API.RESTORE;
        trashModalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
        trashModalEl.dataset.restoreAllUrl = API.RESTORE_ALL;
        trashModalEl.dataset.deleteUrl = API.PURGE;
        trashModalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

        new bootstrap.Modal(trashModalEl).show();
    }

    function updateCardCount(count) {
        const el = document.getElementById('cardCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
    }

    function normalizeCardFilters(filters) {
        return (filters || []).map((filter) => {
            if (filter?.field === 'is_active') {
                const value = normalizeActiveValue(filter.value);
                return value === '' ? null : { field: 'is_active', value };
            }

            return filter;
        }).filter(Boolean);
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    async function updateCardActive(cardId, active, toggleEl) {
        try {
            const data = await modalModule.fetchCardDetail(cardId);
            const formData = new FormData();

            Object.entries(data).forEach(([key, value]) => {
                if (key === 'card_file') return;
                formData.set(key, value ?? '');
            });

            formData.set('id', cardId);
            formData.set('is_active', active ? '1' : '0');
            formData.set('delete_card_file', '0');

            if (toggleEl) toggleEl.disabled = true;

            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: formData,
            });
            const json = await res.json();

            if (!json.success) {
                throw new Error(json.message || '상태 변경에 실패했습니다.');
            }

            state.cardTable?.ajax.reload(null, false);
            formModule.notify('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
        } catch (error) {
            console.error(error);
            if (toggleEl) toggleEl.checked = !active;
            formModule.notify('error', error.message || '상태 변경에 실패했습니다.');
        } finally {
            if (toggleEl) toggleEl.disabled = false;
        }
    }

    function bindTableEvents($) {
        $('#card-table tbody').on('dblclick', 'tr', async function () {
            const row = state.cardTable.row(this).data();
            if (!row) return;
            modalModule.openCardEditModal(row);
        });

        $('#card-table tbody').on('change', '.card-active-toggle', function (event) {
            event.stopPropagation();
            const cardId = this.dataset.id;
            if (!cardId) return;
            updateCardActive(cardId, this.checked, this);
        });

        $('#card-table tbody').on('click', '.card-edit-btn', function (event) {
            event.stopPropagation();
            const row = state.cardTable.row($(this).closest('tr')).data();
            if (!row) return;
            modalModule.openCardEditModal(row);
        });
    }

    function buildCardColumns() {
        const columns = [{
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            width: '40px',
            className: 'reorder-handle no-sort no-colvis text-center',
            headerClassName: 'no-sort text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>',
        }];

        Object.entries(CARD_COLUMN_MAP).forEach(([field, config]) => {
            if (field === 'is_active') return;

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                width: config.width,
                className: config.className || '',
                defaultContent: '',
                render(data, type) {
                    if (data == null) return '';
                    if (type !== 'display') return data;

                    if (field === 'card_file') {
                        if (!data) return '';
                        const path = encodeURIComponent(data);
                        return `<a href="/api/file/preview?path=${path}" target="_blank">보기</a>`;
                    }

                    if (field === 'limit_amount') {
                        return formatAmount(data);
                    }

                    if (field === 'note') {
                        const value = formModule.escapeHtml(data);
                        return `<span class="card-note-text" title="${value}">${value}</span>`;
                    }

                    return data;
                },
            });
        });

        columns.push({
            data: 'is_active',
            title: CARD_COLUMN_MAP.is_active.label,
            visible: true,
            width: CARD_COLUMN_MAP.is_active.width,
            className: 'text-center',
            headerClassName: 'text-center',
            defaultContent: '',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = String(data) === '1';
                return `
                    <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                        <input type="checkbox"
                               class="form-check-input card-active-toggle"
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
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm card-edit-btn"
                            data-id="${formModule.escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            },
        });

        return columns;
    }

    return {
        initDataTable,
        bindTableEvents,
        CARD_COLUMN_MAP,
    };
}
