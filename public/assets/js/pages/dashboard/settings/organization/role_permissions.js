import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';

const API_ROLE_LIST = '/api/settings/organization/role/list';
const API_PERM_LIST = '/api/settings/organization/permission/list';
const API_ROLE_PERMISSIONS = '/api/settings/organization/role-permission/list';
const API_ASSIGN = '/api/settings/organization/role-permission/assign';
const API_REMOVE = '/api/settings/organization/role-permission/remove';

let roleTable = null;
let permissionTable = null;
let selectedRoleId = '';
let selectedRoleKey = '';
let assignedPermissionIds = new Set();
let assignedPermissionKeys = new Set();
let pendingChanges = {};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }

    if (type === 'error' || type === 'warning') {
        alert(message);
        return;
    }

    console.log(message);
}

function buildStatusBadge(value) {
    return String(value) === '1'
        ? '<span class="badge bg-success">사용</span>'
        : '<span class="badge bg-secondary">미사용</span>';
}

function getResponseRows(json) {
    return Array.isArray(json?.data) ? json.data : [];
}

function mergeAssignedState(rows) {
    const isSuperAdmin = selectedRoleKey === 'super_admin';

    return rows.map((row) => {
        const id = String(row.id ?? '');
        const key = String(row.permission_key ?? '').toLowerCase();

        return {
            ...row,
            assigned: isSuperAdmin || assignedPermissionIds.has(id) || assignedPermissionKeys.has(key)
        };
    });
}

function calculatePermissionCounts(rows) {
    const total = rows.length;
    const apiCount = rows.filter((row) => String(row.permission_key || '').toLowerCase().startsWith('api.')).length;

    return {
        total,
        apiCount,
        webCount: total - apiCount
    };
}

function updatePermissionCount() {
    if (!permissionTable) return;

    const rows = permissionTable.rows({ filter: 'applied' }).data().toArray();
    const stats = calculatePermissionCounts(rows);

    $('#permission-count').text(`총 ${stats.total}건 (api ${stats.apiCount} / web ${stats.webCount})`);
}

function syncCheckAll() {
    if (!permissionTable) return;

    const rows = permissionTable.rows({ filter: 'applied' }).nodes();
    const total = $(rows).find('.rp-toggle').length;
    const checked = $(rows).find('.rp-toggle:checked').length;
    const $all = $('#permission-check-all');

    if (!total || checked === 0) {
        $all.prop('checked', false).prop('indeterminate', false);
        return;
    }

    if (checked === total) {
        $all.prop('checked', true).prop('indeterminate', false);
        return;
    }

    $all.prop('checked', false).prop('indeterminate', true);
}

function setSaveDirty(isDirty) {
    $('#permission-save-btn')
        .toggleClass('btn-primary', isDirty)
        .toggleClass('btn-secondary', !isDirty);
}

function setSelectedRole(row) {
    selectedRoleId = String(row?.id ?? '');
    selectedRoleKey = String(row?.role_key ?? '').toLowerCase();
    pendingChanges = {};
    setSaveDirty(false);

    $('#rp-selected-role-name').text(row?.role_name ? `[${row.role_name}]` : '');
    $('#permission-header').show();

    $('#role-list-table tbody tr').removeClass('table-active');
    if (roleTable) {
        roleTable.rows().every(function () {
            const current = this.data();
            if (String(current?.id ?? '') === selectedRoleId) {
                $(this.node()).addClass('table-active');
            }
        });
    }

    reloadPermissions();
}

function initRoleTable() {
    roleTable = createDataTable({
        tableSelector: '#role-list-table',
        api: API_ROLE_LIST,
        defaultOrder: [[0, 'asc']],
        pageLength: 10,
        cellSearchFill: false,
        columns: [
            {
                data: 'sort_no',
                className: 'text-center',
                render: (value) => escapeHtml(value)
            },
            {
                data: 'role_name',
                render: (value) => escapeHtml(value)
            },
            {
                data: 'is_active',
                className: 'text-center',
                render: (value) => buildStatusBadge(value)
            }
        ],
        dataSrc(json) {
            const rows = getResponseRows(json).sort((a, b) => Number(a.sort_no || 0) - Number(b.sort_no || 0));
            $('#roleListCount').text(`총 ${rows.length}건`);
            return rows;
        }
    });

    bindTableHighlight('#role-list-table', roleTable);
    hideRoleTableControls();

    $('#role-list-table tbody').on('click', 'tr', function () {
        const row = roleTable.row(this).data();
        if (row) {
            setSelectedRole(row);
        }
    });

    roleTable.on('draw.dt', () => {
        if (!selectedRoleId) return;

        roleTable.rows().every(function () {
            const row = this.data();
            if (String(row?.id ?? '') === selectedRoleId) {
                $(this.node()).addClass('table-active');
            }
        });
    });

    roleTable.on('draw.dt xhr.dt', syncRoleTableStickyRange);
}

function hideRoleTableControls() {
    const wrapper = document.getElementById('role-list-table_wrapper');
    if (!wrapper) return;

    [
        '.dt-buttons',
        '.dataTables_length',
        '.dt-bottom',
        '.dataTables_info',
        '.dataTables_paginate'
    ].forEach((selector) => {
        wrapper.querySelectorAll(selector).forEach((node) => {
            node.style.display = 'none';
        });
    });
}

function initPermissionTable() {
    permissionTable = createDataTable({
        tableSelector: '#role-permissions-table',
        api: API_PERM_LIST,
        defaultOrder: [[0, 'asc']],
        pageLength: 50,
        cellSearchFill: false,
        columns: [
            {
                data: 'sort_no',
                className: 'text-center',
                render: (value) => escapeHtml(value)
            },
            {
                data: 'category',
                render: (value) => escapeHtml(value)
            },
            {
                data: 'permission_name',
                render: (value) => escapeHtml(value)
            },
            {
                data: 'permission_key',
                render: (value) => `<code>${escapeHtml(value)}</code>`
            },
            {
                data: 'description',
                visible: true,
                render: (value) => escapeHtml(value)
            },
            {
                data: 'is_active',
                className: 'text-center',
                visible: true,
                render: (value, type) => (type === 'display' ? buildStatusBadge(value) : escapeHtml(value))
            },
            {
                data: 'created_at',
                visible: false,
                render: (value) => escapeHtml(value)
            },
            {
                data: 'created_by',
                visible: false,
                render: (value) => escapeHtml(value)
            },
            {
                data: 'updated_at',
                visible: false,
                render: (value) => escapeHtml(value)
            },
            {
                data: 'updated_by',
                visible: false,
                render: (value) => escapeHtml(value)
            },
            {
                data: 'id',
                className: 'text-center no-colvis',
                orderable: false,
                searchable: false,
                render(id, type, row) {
                    const permissionId = String(id ?? '');
                    const assigned = Object.prototype.hasOwnProperty.call(pendingChanges, permissionId)
                        ? pendingChanges[permissionId]
                        : row.assigned;
                    const checked = assigned ? 'checked' : '';
                    const disabled = selectedRoleId ? '' : 'disabled';

                    return `
                        <input type="checkbox"
                               class="form-check-input rp-toggle"
                               data-permission="${escapeHtml(permissionId)}"
                               ${checked}
                               ${disabled}>
                    `;
                }
            }
        ],
        dataSrc(json) {
            return mergeAssignedState(getResponseRows(json));
        }
    });

    bindTableHighlight('#role-permissions-table', permissionTable);

    permissionTable.on('draw.dt xhr.dt', () => {
        updatePermissionCount();
        syncCheckAll();
        syncRoleTableStickyRange();
    });

    $('#role-permissions-table tbody')
        .on('change', '.rp-toggle', function () {
            const permissionId = String($(this).data('permission') || '');
            if (!permissionId) return;

            pendingChanges[permissionId] = $(this).prop('checked');
            setSaveDirty(Object.keys(pendingChanges).length > 0);
            syncCheckAll();
        });
}

function reloadPermissions() {
    if (!selectedRoleId || !permissionTable) return;

    $.post(API_ROLE_PERMISSIONS, { role_id: selectedRoleId })
        .done((res) => {
            if (!res || res.success === false) {
                notify('error', '역할 권한 조회에 실패했습니다.');
                return;
            }

            const rows = getResponseRows(res);
            assignedPermissionIds = new Set(
                rows.map((row) => row.permission_id ?? row.id ?? '')
                    .filter((value) => value !== '')
                    .map((value) => String(value))
            );
            assignedPermissionKeys = new Set(
                rows.map((row) => row.permission_key ?? '')
                    .filter((value) => value !== '')
                    .map((value) => String(value).toLowerCase())
            );

            permissionTable.ajax.reload(() => {
                updatePermissionCount();
                syncCheckAll();
            }, false);
        })
        .fail(() => notify('error', '역할 권한 조회에 실패했습니다.'));
}

function bindCheckAll() {
    $('#permission-check-all').on('change', function () {
        if (!selectedRoleId || !permissionTable) return;

        const checked = $(this).prop('checked');
        const rows = permissionTable.rows({ filter: 'applied' }).nodes();

        $(rows).find('.rp-toggle').each(function () {
            $(this).prop('checked', checked).trigger('change');
        });
    });
}

function bindSaveButton() {
    $('#permission-save-btn').on('click', () => {
        if (!selectedRoleId) {
            notify('warning', '역할을 먼저 선택해 주세요.');
            return;
        }

        const changes = Object.entries(pendingChanges);
        if (!changes.length) {
            notify('warning', '변경된 권한이 없습니다.');
            return;
        }

        const tasks = changes.map(([permissionId, checked]) => {
            const url = checked ? API_ASSIGN : API_REMOVE;

            return $.post(url, {
                role_id: selectedRoleId,
                permission_id: permissionId
            });
        });

        $('#permission-save-btn').prop('disabled', true);

        Promise.all(tasks)
            .then(() => {
                pendingChanges = {};
                setSaveDirty(false);
                notify('success', '권한이 저장되었습니다.');
                reloadPermissions();
            })
            .catch(() => notify('error', '권한 저장에 실패했습니다.'))
            .finally(() => $('#permission-save-btn').prop('disabled', false));
    });
}

function syncRoleTableStickyRange() {
    const roleTableNode = document.getElementById('role-list-table');
    const permissionTableNode = document.getElementById('role-permissions-table');

    if (!roleTableNode || !permissionTableNode) return;

    const tbody = roleTableNode.querySelector('tbody');
    if (!tbody) return;

    tbody.querySelector('tr.rp-role-spacer')?.remove();

    const spacerHeight = Math.max(0, permissionTableNode.offsetHeight - roleTableNode.offsetHeight);
    if (spacerHeight <= 0) {
        return;
    }

    const spacer = document.createElement('tr');
    spacer.className = 'rp-role-spacer';
    spacer.innerHTML = '<td colspan="3"></td>';
    spacer.style.setProperty('--rp-role-spacer-height', `${spacerHeight}px`);
    tbody.appendChild(spacer);
}

$(function () {
    initRoleTable();
    initPermissionTable();
    bindCheckAll();
    bindSaveButton();
    window.addEventListener('resize', syncRoleTableStickyRange);
});
