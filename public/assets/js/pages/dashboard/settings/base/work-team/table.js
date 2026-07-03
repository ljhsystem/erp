import { actorDisplay } from '/public/assets/js/common/actor.js';

export function createWorkTeamTableModule({
    api,
    columnMap,
    dateOptions,
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    notify,
    escapeHtml,
    onOpenCreateModal,
    onOpenEditRow,
    onToggleActive,
    onOpenTrashModal,
    onOpenExcelModal,
}) {
    let table = null;

    function initDataTable() {
        table = createDataTable({
            tableSelector: '#work-team-table',
            api: api.LIST,
            deleteApi: api.DELETE,
            columns: buildColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: {
                widthResizable: true,
            },
            tableSettings: {
                pageKey: 'dashboard.settings.base-info.work-team',
                tableKey: 'work-team-table',
                storageKey: 'datatable.settings.dashboard.settings.base-info.work-team.work-team-table.v1',
                metaDomain: 'work-team',
                tableLabel: '근무팀',
                title: '근무팀 테이블 설정',
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: onOpenTrashModal,
                },
                {
                    text: '엑셀관리',
                    className: 'btn btn-success btn-sm',
                    action: onOpenExcelModal,
                },
                {
                    text: '신규등록',
                    className: 'btn btn-warning btn-sm',
                    action: onOpenCreateModal,
                }
            ]
        });

        window.workTeamTable = table;

        if (table) {
            table.on('init.dt draw.dt xhr.dt', () => {
                updateCount(table.page.info()?.recordsDisplay ?? 0);
            });

            SearchForm({
                table,
                apiList: api.LIST,
                tableId: 'workTeam',
                defaultSearchField: 'team_name',
                dateOptions,
            });

            bindTableHighlight('#work-team-table', table);

            bindRowReorder(table, {
                api: api.REORDER,
                onSuccess() {
                    notify('success', '순서가 저장되었습니다.');
                    reloadTable();
                },
                onError(json) {
                    notify('error', json?.message || '순서 저장에 실패했습니다.');
                    reloadTable();
                }
            });
        }

        return table;
    }

    function buildColumns() {
        const columns = [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            className: 'col-reorder reorder-handle no-sort no-colvis text-center',
            headerClassName: 'col-reorder no-colvis text-center',
            orderable: false,
            searchable: false,
            widthResizable: true,
            defaultContent: '<i class="bi bi-list"></i>'
        }];

        Object.entries(columnMap).forEach(([field, config]) => {
            if (field === 'is_active') return;
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: config.className || '',
                headerClassName: config.className || '',
                defaultContent: '',
                render(data, type, row) {
                    if (data === null || data === undefined) return '';
                    if (type !== 'display') return data;
                    if (field === 'team_leader_client_id') return row?.team_leader_client_name || data;
                    if (config.type === 'actor') return actorDisplay(row, field);
                    return data;
                }
            });
        });

        columns.push({
            data: 'is_active',
            title: columnMap.is_active.label,
            visible: true,
            className: 'text-center',
            headerClassName: 'text-center',
            widthResizable: true,
            defaultContent: '',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = Number(data) === 1;
                return `
                    <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                        <input type="checkbox"
                               class="form-check-input work-team-active-toggle"
                               data-id="${escapeHtml(row.id || '')}"
                               ${active ? 'checked' : ''}
                               aria-label="상태 변경">
                    </div>
                `;
            }
        });

        columns.push({
            data: null,
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            widthResizable: true,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm work-team-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            }
        });

        return columns;
    }

    function bindTableEvents() {
        window.jQuery('#work-team-table tbody').on('dblclick', 'tr', function () {
            const row = table.row(this).data();
            if (!row?.id) return;
            onOpenEditRow(row);
        });

        window.jQuery('#work-team-table tbody').on('change', '.work-team-active-toggle', function (event) {
            event.stopPropagation();
            const teamId = this.dataset.id;
            if (!teamId) return;
            onToggleActive(teamId, this.checked, this);
        });

        window.jQuery('#work-team-table tbody').on('click', '.work-team-edit-btn', function (event) {
            event.stopPropagation();
            const row = table.row(window.jQuery(this).closest('tr')).data();
            if (!row) return;
            onOpenEditRow(row);
        });
    }

    function updateCount(count) {
        const el = document.getElementById('workTeamCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function reloadTable() {
        table?.ajax.reload(null, false);
    }

    function getTable() {
        return table;
    }

    return {
        initDataTable,
        bindTableEvents,
        reloadTable,
        getTable,
    };
}
