import '/public/assets/js/common/core/AppAjax.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindSortableRowReorder } from '/public/assets/js/common/row-reorder.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { bindTableSettingsPolicy } from './permission-assignment/table-settings-policy.js';
import { mergePermissionSelection, revealDataTable } from './permission-assignment/permission-cache.js';
import { loadPermissionMaster, loadPermissionSelection, postPermissionJson } from './permission-assignment/api.js';
import { bindPermissionAssignmentStickyLayout } from './permission-assignment/sticky-layout.js?v=20260814-1';
import { buildStatusBadge, escapeHtml, notify, responseRows } from './permission-assignment/ui-helpers.js';

const API_ROLE_LIST = '/api/settings/organization/role/list';
const API_ROLE_PERMISSIONS = '/api/settings/organization/role-permission/list';
const API_SAVE = '/api/settings/organization/role-permission/save';
const API_REORDER = '/api/settings/organization/role-permission/reorder';
const CORE_MANAGEMENT_PERMISSION_KEYS = new Set([
    'web.settings.organization.role_permissions',
    'api.settings.rolepermission.list',
    'api.settings.rolepermission.assign',
]);

const PAGE_TABLE_SELECTOR = '#permission-assignment-table';
const ROLE_TABLE_SELECTOR = '#role-list-table';
const ROLE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.organization.permission-assignment.role-list-table.v2';
const PERMISSION_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.settings.organization.role_permissions.permission-matrix.flat.v5';
const ROLE_TABLE_USER_SETTING_PAGE_KEY = 'permission-assignment-role';
const PERMISSION_TABLE_USER_SETTING_PAGE_KEY = 'permission-assignment-permission';

let roleTable = null;
let roleDataLoaded = false;
let permissionTable = null;
let selectedRoleId = '';
let selectedRoleActive = false;
let currentTree = [];
let permissionMasterPromise = null;
let currentDisplayRows = [];
let pendingChanges = {};
let originalPermissionStates = new Map();
let lastClientReorderPlan = null;
let lastReorderWarningMessage = '';
let permissionRequestSequence = 0;
let isSavingPermissions = false;

function bindRoleTableSettingsPolicy() {
    bindTableSettingsPolicy({ storageKey: ROLE_TABLE_SETTINGS_STORAGE_KEY, userSettingPageKey: ROLE_TABLE_USER_SETTING_PAGE_KEY,
        deprecatedKeys: ['__legacy_role_status'], handlerKey: '__roleTableSettingsPolicyHandler' });
}

function bindPermissionTableSettingsPolicy() {
    bindTableSettingsPolicy({ storageKey: PERMISSION_TABLE_SETTINGS_STORAGE_KEY, userSettingPageKey: PERMISSION_TABLE_USER_SETTING_PAGE_KEY,
        deprecatedKeys: ['grant', '__select', 'handle'], handlerKey: '__permissionTableSettingsPolicyHandler' });
}

function getDom() {
    return {
        selectedRoleName: document.getElementById('rp-selected-role-name'),
        permissionHeader: document.getElementById('permission-header'),
        permissionCount: document.getElementById('permission-count'),
        saveButton: document.getElementById('permission-save-btn'),
        checkAll: document.getElementById('permission-check-all'),
    };
}

function hideRoleTableControls() {
    const wrapper = document.getElementById('role-list-table_wrapper');
    if (!wrapper) {
        return;
    }

    [
        '.dt-buttons',
        '.dataTables_length',
        '.dt-bottom',
        '.dataTables_info',
        '.dataTables_paginate',
    ].forEach((selector) => {
        wrapper.querySelectorAll(selector).forEach((node) => {
            node.style.display = 'none';
        });
    });
}

function setSaveDirty(isDirty) {
    const { saveButton } = getDom();
    if (!saveButton) {
        return;
    }

    const canSave = Boolean(isDirty && selectedRoleActive && !isSavingPermissions);
    saveButton.disabled = !canSave;
    saveButton.classList.toggle('btn-primary', canSave);
    saveButton.classList.toggle('btn-secondary', !canSave);
}

function normalizeRoleTableHeader() {
    const headerRow = document.querySelector(`${ROLE_TABLE_SELECTOR} thead tr`);
    if (!headerRow) {
        return;
    }

    headerRow.innerHTML = `
        <th class="text-center" style="width:80px">\uC21C\uBC88</th>
        <th style="width:160px">\uC5ED\uD560 \uD0A4</th>
        <th style="width:180px">\uC5ED\uD560\uBA85</th>
        <th style="width:220px">\uC124\uBA85</th>
        <th class="text-center" style="width:90px">\uC0C1\uD0DC</th>
    `;
}

async function initRoleTable() {
    normalizeRoleTableHeader();

    roleTable = await createDataTable({
        tableSelector: ROLE_TABLE_SELECTOR,
        api: API_ROLE_LIST,
        tableSettings: {
            pageKey: 'dashboard.settings.organization.permission-assignment.role-list',
            userSettingPageKey: ROLE_TABLE_USER_SETTING_PAGE_KEY,
            tableKey: 'role-list-table',
            storageKey: ROLE_TABLE_SETTINGS_STORAGE_KEY,
            metaDomain: 'role',
            tableLabel: '\uC5ED\uD560\uBAA9\uB85D',
            title: '\uC5ED\uD560\uBAA9\uB85D \uD14C\uC774\uBE14 \uC124\uC815',
            defaultVisibleColumns: ['sort_no', 'role_key', 'role_name', 'description', 'is_active'],
        },
        defaultOrder: [[0, 'asc']],
        pageLength: 100,
        autoWidth: false,
        fixedLayout: false,
        cellSearchFill: false,
        selectable: false,
        deleteButton: false,
        columns: [
            {
                data: 'sort_no',
                settingsKey: 'sort_no',
                title: '\uC21C\uBC88',
                className: 'text-center',
                width: '80px',
                widthResizable: true,
                render: (value) => escapeHtml(value),
            },
            {
                data: 'role_key',
                settingsKey: 'role_key',
            title: '\uC5ED\uD560 \uD0A4',
                width: '160px',
                widthResizable: true,
                render: (value) => escapeHtml(value),
            },
            {
                data: 'role_name',
                settingsKey: 'role_name',
                title: '\uC5ED\uD560\uBA85',
                width: '180px',
                widthResizable: true,
                render: (value) => escapeHtml(value),
            },
            {
                data: 'description',
                settingsKey: 'description',
                title: '\uC124\uBA85',
                width: '220px',
                widthResizable: true,
                render: (value) => escapeHtml(value),
            },
            {
                data: 'is_active',
                settingsKey: 'is_active',
                title: '\uC0C1\uD0DC',
                className: 'text-center',
                width: '90px',
                widthResizable: true,
                render: (value) => buildStatusBadge(value),
            },
        ],
        dataSrc(json) {
            roleDataLoaded = true;
            const rows = responseRows(json).sort((a, b) => Number(a.sort_no || 0) - Number(b.sort_no || 0));
            document.getElementById('roleListCount').textContent = `\uCD1D ${rows.length}\uAC74`;
            return rows;
        },
        widthScopeSelector: '#roleListCard',
    });

    bindTableHighlight(ROLE_TABLE_SELECTOR, roleTable);
    hideRoleTableControls();

    $(`${ROLE_TABLE_SELECTOR} tbody`).on('click', 'tr', function onClick() {
        const row = roleTable.row(this).data();
        if (row) {
            setSelectedRole(row);
        }
    });

    roleTable.on('draw.dt', () => {
        if (roleDataLoaded && document.getElementById('roleListCard')?.classList.contains('rp-table-pending')) {
            revealDataTable(roleTable, 'roleListCard', 'roleListCardBody');
        }
        if (!selectedRoleId) {
            return;
        }

        roleTable.rows().every(function eachRow() {
            const row = this.data();
            if (String(row?.id ?? '') === selectedRoleId) {
                $(this.node()).addClass('table-active');
            }
        });
    });
}

async function initPermissionTable() {
    if (permissionTable) {
        return permissionTable;
    }

    permissionTable = await createDataTable({
        tableSelector: PAGE_TABLE_SELECTOR,
        initialData: [],
        columns: buildPermissionColumns(),
        defaultOrder: [[1, 'asc']],
        pageLength: 100,
        scrollX: false,
        autoWidth: false,
        fixedLayout: false,
        paging: true,
        searching: true,
        info: true,
        selectable: true,
        selectionColumnIndex: 0,
        selectionColumn: { widthResizable: true },
        rowIdField: (row) => (row?.row_type === 'permission' ? String(row.permission_id || '') : ''),
        isRowSelectable: (row) => row?.row_type === 'permission',
        deleteButton: false,
        showCopyButton: true,
        showColumnVisibility: false,
        widthScopeSelector: '#permission-assignment-table-wrap',
        searchTableId: 'rolePermissionMatrix',
        tableSettings: {
            enabled: true,
            pageKey: 'settings.organization.role_permissions',
            userSettingPageKey: PERMISSION_TABLE_USER_SETTING_PAGE_KEY,
            tableKey: 'permission-matrix',
            storageKey: PERMISSION_TABLE_SETTINGS_STORAGE_KEY,
            metaDomain: 'permission-assignment',
            tableLabel: '\uC5ED\uD560\uBCC4 \uAD8C\uD55C\uBAA9\uB85D',
            columns: buildPermissionColumns(),
            resetOnColumnSchemaChange: true,
            defaultVisibleColumns: [
                '__select',
                '__reorder',
                'sort_no',
                'page',
                'permission_source',
                'category',
                'permission_name',
                'description',
                '__actions',
            ],
        },
    });

    revealDataTable(permissionTable, 'permissionListCard', 'permissionListCardBody');

    permissionTable.on('draw.dt', () => {
        applyPermissionRowStyles();
        applyParentIndeterminateStates();
        refreshPermissionGroupReorder();
    });

    bindPermissionTableEvents();
    return permissionTable;
}

function buildPermissionColumns() {
    return [
        {
            title: '<i class="bi bi-arrows-move"></i>',
            data: null,
            settingsKey: '__reorder',
            className: 'text-center no-colvis',
            headerClassName: 'text-center',
            width: '56px',
            orderable: false,
            searchable: false,
            widthResizable: true,
            render: (_, type, row) => {
                if (type === 'sort' || type === 'type' || type === 'filter') {
                    return '';
                }

                const disabledClass = isPermissionGroupReorderAvailable() ? '' : ' is-disabled';
                return `
                    <span
                        class="rp-reorder-handle reorder-handle${disabledClass}"
                        data-row-id="${escapeHtml(row.row_id || '')}"
                        data-row-type="${escapeHtml(row.row_type || '')}"
                        data-page-key="${escapeHtml(row.page_key || '')}"
                        role="button"
                        tabindex="0"
                        aria-label="drag"
                        title="\uC21C\uC11C \uBCC0\uACBD"
                    >
                        &#9776;
                    </span>
                `;
            },
        },
        {
            title: 'ID',
            data: 'id',
            settingsKey: 'id',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uC21C\uBC88',
            data: 'sort_no',
            settingsKey: 'sort_no',
            className: 'text-center',
            headerClassName: 'text-center',
            width: '72px',
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'sort_no');
                }

                if (type === 'filter') {
                    return String(value || '');
                }

                return escapeHtml(value || '');
            },
        },
        {
            title: '\uD398\uC774\uC9C0',
            data: 'page',
            settingsKey: 'page',
            widthResizable: true,
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'page');
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return row.row_type === 'page' ? escapeHtml(value || '') : '';
            },
        },
        {
            title: '\uAD6C\uBD84',
            data: 'permission_source',
            settingsKey: 'permission_source',
            className: 'text-center',
            headerClassName: 'text-center',
            width: '80px',
            widthResizable: true,
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'permission_source');
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                if (row.row_type === 'page') {
                    return 'PAGE';
                }

                return escapeHtml(String(value || '').toUpperCase());
            },
        },
        {
            title: '\uCE74\uD14C\uACE0\uB9AC',
            data: 'category',
            settingsKey: 'category',
            widthResizable: true,
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'category');
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return row.row_type === 'page' ? escapeHtml(value || '') : '';
            },
        },
        {
            title: '\uAD8C\uD55C\uD0A4',
            data: 'permission_key',
            settingsKey: 'permission_key',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uAD8C\uD55C\uBA85',
            data: 'permission_name',
            settingsKey: 'permission_name',
            widthResizable: true,
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'permission_name');
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return renderPermissionHierarchyCell(row, value);
            },
        },
        {
            title: '\uAD8C\uD55C\uC124\uBA85',
            data: 'description',
            settingsKey: 'description',
            widthResizable: true,
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return buildPermissionColumnSortValue(row, value, 'description');
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return escapeHtml(value || '');
            },
        },
        {
            title: '\uD398\uC774\uC9C0\uD0A4',
            data: 'page_key',
            settingsKey: 'page_key',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uC0C1\uD0DC',
            data: 'is_active',
            settingsKey: 'is_active',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uC0DD\uC131\uC77C\uC2DC',
            data: 'created_at',
            settingsKey: 'created_at',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uC0DD\uC131\uC790',
            data: 'created_by',
            settingsKey: 'created_by',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (_, __, row) => escapeHtml(actorDisplay(row, 'created_by')),
        },
        {
            title: '\uC218\uC815\uC77C\uC2DC',
            data: 'updated_at',
            settingsKey: 'updated_at',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (value) => escapeHtml(value || ''),
        },
        {
            title: '\uC218\uC815\uC790',
            data: 'updated_by',
            settingsKey: 'updated_by',
            visible: false,
            defaultContent: '',
            widthResizable: true,
            render: (_, __, row) => escapeHtml(actorDisplay(row, 'updated_by')),
        },
        {
            title: '\uAD8C\uD55C',
            data: null,
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            settingsVirtualType: 'system',
            settingsTitle: '\uAD00\uB9AC',
            className: 'text-center',
            headerClassName: 'text-center',
            width: '96px',
            widthResizable: true,
            orderable: false,
            searchable: false,
            render: (_, __, row) => {
                if (row.row_type === 'page') {
                    return `
                        <input
                            type="checkbox"
                            class="form-check-input rp-page-checkbox"
                            data-page-key="${escapeHtml(row.page_key || '')}"
                            ${row.checked ? 'checked' : ''}
                            ${selectedRoleActive ? '' : 'disabled'}
                        >
                    `;
                }

                return `
                    <input
                        type="checkbox"
                        class="form-check-input rp-permission-checkbox"
                        data-page-key="${escapeHtml(row.page_key || '')}"
                        data-permission-id="${escapeHtml(row.permission_id || '')}"
                        ${row.checked ? 'checked' : ''}
                        ${selectedRoleActive ? '' : 'disabled'}
                    >
                `;
            },
        },
    ];
}

function renderPermissionHierarchyCell(row = {}, value = '') {
    const safeValue = escapeHtml(value || '');

    if (row.row_type === 'page') {
        return `
            <div class="rp-hierarchy-cell rp-hierarchy-page" title="${safeValue}">
                <span class="rp-hierarchy-icon"><i class="bi bi-folder2-open"></i></span>
                <span class="rp-hierarchy-title">${safeValue}</span>
            </div>
        `;
    }

    return `
        <div class="rp-hierarchy-cell rp-hierarchy-child" style="padding-left:${Number(row.depth_padding || 0)}px" title="${safeValue}">
            <span class="rp-hierarchy-branch">\u3134</span>
            <span class="rp-hierarchy-icon"><i class="bi bi-shield-check"></i></span>
            <span class="rp-hierarchy-title">${safeValue}</span>
        </div>
    `;
}

function rebuildOriginalPermissionStates(tree) {
    originalPermissionStates = new Map();

    tree.forEach((pageNode) => {
        (pageNode.children || []).forEach((childNode) => {
            originalPermissionStates.set(String(childNode.permission_id), !!childNode.checked);
        });
    });
}

function recomputePageState(pageNode) {
    const children = Array.isArray(pageNode.children) ? pageNode.children : [];
    const childCount = children.length;
    const checkedCount = children.filter((childNode) => childNode.checked).length;

    pageNode.checked = childCount > 0 && checkedCount === childCount;
    pageNode.indeterminate = checkedCount > 0 && checkedCount < childCount;
}

function recomputeTreeState() {
    currentTree.forEach((pageNode) => {
        recomputePageState(pageNode);
    });
}

function updatePendingChange(permissionId, checked) {
    const original = originalPermissionStates.get(String(permissionId));
    if (original === checked) {
        delete pendingChanges[String(permissionId)];
    } else {
        pendingChanges[String(permissionId)] = checked;
    }
}

function setSelectedRole(row) {
    if (isSavingPermissions) {
        notify('warning', '권한 저장이 끝난 후 다른 역할을 선택해 주세요.');
        return;
    }
    const { selectedRoleName, permissionHeader } = getDom();

    selectedRoleId = String(row?.id ?? '');
    selectedRoleActive = Number(row?.is_active ?? 0) === 1;
    pendingChanges = {};
    setSaveDirty(false);

    if (selectedRoleName) {
        selectedRoleName.textContent = row?.role_name ? `[${row.role_name}]` : '';
    }
    if (permissionHeader) {
        permissionHeader.style.display = 'flex';
    }

    $(`${ROLE_TABLE_SELECTOR} tbody tr`).removeClass('table-active');
    if (roleTable) {
        roleTable.rows().every(function eachRow() {
            const current = this.data();
            if (String(current?.id ?? '') === selectedRoleId) {
                $(this.node()).addClass('table-active');
            }
        });
    }

    reloadPermissions();
    if (!selectedRoleActive) {
        notify('warning', '비활성 역할의 기존 권한은 조회만 가능하며 저장할 수 없습니다.');
    }
}

async function reloadPermissions() {
    if (!selectedRoleId) {
        return;
    }
    const requestedRoleId = selectedRoleId;
    const requestSequence = ++permissionRequestSequence;
    try {
        const [masterTree, response] = await Promise.all([
            permissionMasterPromise,
            loadPermissionSelection(API_ROLE_PERMISSIONS, requestedRoleId),
        ]);
        if (requestSequence !== permissionRequestSequence || requestedRoleId !== selectedRoleId) return;
        currentTree = mergePermissionSelection(masterTree, response?.data || {});
        recomputeTreeState();
        rebuildOriginalPermissionStates(currentTree);
        pendingChanges = {};
        setSaveDirty(false);
        await renderPermissionTable();
    } catch (error) {
        if (requestSequence === permissionRequestSequence && requestedRoleId === selectedRoleId) {
            notify('error', error?.message || '역할 권한 조회에 실패했습니다.');
        }
    }
}

function buildSearchText(pageNode, childNode = null) {
    const values = childNode
        ? [
            pageNode.page,
            pageNode.category,
            pageNode.permission_name,
            pageNode.description,
            String(childNode.permission_source || '').toUpperCase(),
            childNode.permission_name,
            childNode.description,
            childNode.permission_source,
        ]
        : [
            pageNode.page,
            pageNode.category,
            pageNode.permission_name,
            pageNode.description,
            ...(pageNode.children || []).flatMap((node) => [
                node.permission_name,
                node.description,
                node.permission_source,
            ]),
        ];

    return values
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .join(' ');
}

function getPermissionSourcePriority(source = '') {
    const normalized = String(source || '').trim().toLowerCase();
    if (normalized === 'web') {
        return 0;
    }

    if (normalized === 'api') {
        return 1;
    }

    return 9;
}

function sortPermissionChildrenForDisplay(children = []) {
    return [...children].sort((left, right) => {
        const sourceCompare = getPermissionSourcePriority(left?.permission_source) - getPermissionSourcePriority(right?.permission_source);
        if (sourceCompare !== 0) {
            return sourceCompare;
        }

        const sortCompare = Number(left?.sort_no || 0) - Number(right?.sort_no || 0);
        if (sortCompare !== 0) {
            return sortCompare;
        }

        return String(left?.permission_name || '').localeCompare(String(right?.permission_name || ''), 'ko');
    });
}

function buildPermissionColumnSortValue(row = {}, value = '', columnKey = '') {
    const rowTypeWeight = row?.row_type === 'page' ? '0' : '1';
    const normalizedValue = String(value || '').trim().toLowerCase();

    if (columnKey === 'sort_no') {
        return Number(row?.sort_no || 0);
    }

    if (columnKey === 'permission_source') {
        return `${rowTypeWeight}|${getPermissionSourcePriority(value)}|${normalizedValue}`;
    }

    return `${rowTypeWeight}|${normalizedValue}`;
}

function buildDisplayRows() {
    const rows = [];
    let sequence = 1;

    currentTree.forEach((pageNode) => {
        rows.push({
            row_id: `page:${pageNode.page_key}`,
            row_type: 'page',
            sort_no: sequence,
            tree_sort: String(sequence).padStart(6, '0'),
            id: '',
            page_key: pageNode.page_key,
            role_permission_id: '',
            role_id: '',
            parent_page_key: '',
            permission_id: '',
            permission_key: '',
            permission_source: '',
            page: pageNode.page,
            category: pageNode.category,
            permission_name: pageNode.permission_name,
            description: '',
            is_active: '',
            created_at: '',
            created_by: '',
            updated_at: '',
            updated_by: '',
            role_permission_created_at: '',
            role_permission_created_by: '',
            checked: !!pageNode.checked,
            indeterminate: !!pageNode.indeterminate,
            depth_padding: 0,
            search_text: buildSearchText(pageNode),
        });
        sequence += 1;

        sortPermissionChildrenForDisplay(pageNode.children || []).forEach((childNode) => {
            rows.push({
                ...childNode,
                row_id: `permission:${childNode.permission_id}`,
                row_type: 'permission',
                sort_no: sequence,
                tree_sort: String(sequence).padStart(6, '0'),
                id: childNode.id || childNode.permission_id,
                role_permission_id: childNode.role_permission_id || '',
                role_id: childNode.role_id || selectedRoleId,
                page_key: pageNode.page_key,
                parent_page_key: pageNode.page_key,
                permission_id: childNode.permission_id,
                permission_key: childNode.permission_key,
                permission_source: childNode.permission_source,
                page: pageNode.page,
                category: pageNode.category,
                permission_name: childNode.permission_name,
                description: childNode.description,
                role_permission_created_at: childNode.role_permission_created_at ?? '',
                role_permission_created_by: childNode.role_permission_created_by ?? '',
                checked: !!childNode.checked,
                indeterminate: false,
                depth_padding: 18,
                search_text: buildSearchText(pageNode, childNode),
            });
            sequence += 1;
        });
    });

    return rows;
}

function updatePermissionCount() {
    const { permissionCount } = getDom();
    if (!permissionCount) {
        return;
    }

    if (!selectedRoleId) {
        permissionCount.textContent = '';
        return;
    }

    const totalPageCount = currentTree.length;
    const totalPermissionCount = currentTree.reduce((sum, pageNode) => sum + (pageNode.children || []).length, 0);
    const configuredPageCount = currentTree.filter((pageNode) =>
        (pageNode.children || []).some((permissionNode) => !!permissionNode.checked)).length;
    const configuredPermissionCount = currentTree.reduce(
        (sum, pageNode) => sum + (pageNode.children || []).filter((permissionNode) => !!permissionNode.checked).length,
        0
    );
    permissionCount.textContent = `설정: 페이지 ${configuredPageCount}개, 권한 ${configuredPermissionCount}개 / 전체: 페이지 ${totalPageCount}개, 권한 ${totalPermissionCount}개`;
}

function getPermissionSortColumnIndex() {
    if (!permissionTable?.settings) {
        return 2;
    }

    const settings = permissionTable.settings()[0];
    const columns = Array.isArray(settings?.aoColumns) ? settings.aoColumns : [];
    const columnIndex = columns.findIndex((column) => {
        const dataField = typeof column?.mData === 'string' ? column.mData : '';
        const settingsKey = String(column?.settingsKey || column?.__dtSettingsKey || column?.sName || '').trim();
        return dataField === 'sort_no' || settingsKey === 'sort_no';
    });

    return columnIndex >= 0 ? columnIndex : 2;
}

async function renderPermissionTable() {
    const table = await initPermissionTable();
    currentDisplayRows = selectedRoleId ? buildDisplayRows() : [];

    table.clear();
    table.rows.add(currentDisplayRows);
    table.draw(false);
    updatePermissionCount();
    syncPermissionHeaderCheckboxState();
}

function applyPermissionRowStyles() {
    if (!permissionTable) {
        return;
    }

    permissionTable.rows({ page: 'current' }).every(function eachRow() {
        const rowData = this.data();
        const node = this.node();
        if (!rowData || !node) {
            return;
        }

        node.classList.remove('rp-page-row', 'rp-permission-row');
        node.dataset.rowId = String(rowData.row_id || '');
        node.dataset.pageKey = String(rowData.page_key || '');
        node.dataset.rowType = String(rowData.row_type || '');

        if (rowData.row_type === 'page') {
            node.classList.add('rp-page-row');
            return;
        }

        node.classList.add('rp-permission-row');
        node.dataset.parentPageKey = String(rowData.parent_page_key || '');
    });
}

function applyParentIndeterminateStates() {
    const tableNode = document.querySelector(PAGE_TABLE_SELECTOR);
    if (!tableNode) {
        return;
    }

    currentTree.forEach((pageNode) => {
        const checkbox = tableNode.querySelector(`.rp-page-checkbox[data-page-key="${String(pageNode.page_key || '')}"]`);
        if (checkbox) {
            checkbox.indeterminate = !!pageNode.indeterminate;
        }
    });
}

function syncPermissionHeaderCheckboxState() {
    const { checkAll } = getDom();
    if (!checkAll) {
        return;
    }

    const permissionNodes = currentTree.flatMap((pageNode) => pageNode.children || []);
    const totalCount = permissionNodes.length;
    const checkedCount = permissionNodes.filter((node) => !!node.checked).length;

    checkAll.disabled = totalCount === 0 || !selectedRoleActive;
    checkAll.checked = totalCount > 0 && checkedCount === totalCount;
    checkAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
}

function syncVisiblePermissionCheckboxes() {
    const tableNode = document.querySelector(PAGE_TABLE_SELECTOR);
    if (!tableNode) {
        return;
    }

    currentTree.forEach((pageNode) => {
        const pageKey = String(pageNode.page_key || '');
        const pageCheckbox = tableNode.querySelector(`.rp-page-checkbox[data-page-key="${pageKey}"]`);
        if (pageCheckbox) {
            pageCheckbox.checked = !!pageNode.checked;
            pageCheckbox.indeterminate = !!pageNode.indeterminate;
        }

        (pageNode.children || []).forEach((childNode) => {
            const permissionId = String(childNode.permission_id || '');
            const childCheckbox = tableNode.querySelector(
                `.rp-permission-checkbox[data-page-key="${pageKey}"][data-permission-id="${permissionId}"]`
            );
            if (childCheckbox) {
                childCheckbox.checked = !!childNode.checked;
            }
        });
    });
}

function syncPermissionSelectionState() {
    syncVisiblePermissionCheckboxes();
    syncPermissionHeaderCheckboxState();
    updatePermissionCount();
}

function setPageNodeChecked(pageKey, checked) {
    const pageNode = currentTree.find((node) => String(node.page_key) === String(pageKey));
    if (!pageNode) {
        return;
    }

    (pageNode.children || []).forEach((childNode) => {
        childNode.checked = checked;
        updatePendingChange(childNode.permission_id, checked);
    });

    recomputePageState(pageNode);
}

function setChildNodeChecked(pageKey, permissionId, checked) {
    const pageNode = currentTree.find((node) => String(node.page_key) === String(pageKey));
    if (!pageNode) {
        return;
    }

    const childNode = (pageNode.children || []).find((node) => String(node.permission_id) === String(permissionId));
    if (!childNode) {
        return;
    }

    childNode.checked = checked;
    updatePendingChange(childNode.permission_id, checked);
    recomputePageState(pageNode);
}

function bindPermissionTableEvents() {
    $(document).off('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-page-checkbox`);
    $(document).off('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-permission-checkbox`);
    $(document).off('change.rolePermissionFlat', '#permission-check-all');

    $(document).on('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-page-checkbox`, function onPageCheckboxChange() {
        setPageNodeChecked(String(this.dataset.pageKey || ''), !!this.checked);
        setSaveDirty(Object.keys(pendingChanges).length > 0);
        syncPermissionSelectionState();
    });

    $(document).on('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-permission-checkbox`, function onPermissionCheckboxChange() {
        setChildNodeChecked(
            String(this.dataset.pageKey || ''),
            String(this.dataset.permissionId || ''),
            !!this.checked
        );
        setSaveDirty(Object.keys(pendingChanges).length > 0);
        syncPermissionSelectionState();
    });

    $(document).on('change.rolePermissionFlat', '#permission-check-all', function onCheckAllChange() {
        currentTree.forEach((pageNode) => {
            setPageNodeChecked(String(pageNode.page_key || ''), !!this.checked);
        });
        setSaveDirty(Object.keys(pendingChanges).length > 0);
        syncPermissionSelectionState();
    });

}

function moveSelectedPermissionRows(rows = [], selectedRowIds = new Set(), direction = 'up') {
    const nextRows = rows.map((row) => ({ ...row }));
    const isSelected = (row) => selectedRowIds.has(String(row?.row_id || ''));

    if (direction === 'down') {
        for (let index = nextRows.length - 2; index >= 0; index -= 1) {
            if (!isSelected(nextRows[index]) || isSelected(nextRows[index + 1])) {
                continue;
            }
            [nextRows[index], nextRows[index + 1]] = [nextRows[index + 1], nextRows[index]];
        }
        return nextRows;
    }

    for (let index = 1; index < nextRows.length; index += 1) {
        if (!isSelected(nextRows[index]) || isSelected(nextRows[index - 1])) {
            continue;
        }
        [nextRows[index - 1], nextRows[index]] = [nextRows[index], nextRows[index - 1]];
    }

    return nextRows;
}

function bindPermissionSelectedMove() {
    const tableNode = document.querySelector(PAGE_TABLE_SELECTOR);
    if (!tableNode) {
        return;
    }

    if (tableNode.__rpSelectedMoveHandler) {
        tableNode.removeEventListener('datatable:move-selected', tableNode.__rpSelectedMoveHandler);
    }

    tableNode.__rpSelectedMoveHandler = async (event) => {
        event.preventDefault();

        if (!permissionTable || !selectedRoleId) {
            return;
        }

        const direction = event.detail?.direction === 'down' ? 'down' : 'up';
        const ids = Array.isArray(event.detail?.ids)
            ? event.detail.ids.map((id) => String(id || '').trim()).filter(Boolean)
            : [];

        if (!ids.length) {
            notify('warning', '이동할 권한을 선택해 주세요.');
            return;
        }

        const selectedRowIds = new Set(ids.map((id) => `permission:${id}`));
        const beforeRows = currentDisplayRows.map((row) => ({ ...row }));
        const movedRows = moveSelectedPermissionRows(beforeRows, selectedRowIds, direction);
        const changed = beforeRows.some((row, index) => String(row.row_id || '') !== String(movedRows[index]?.row_id || ''));

        if (!changed) {
            notify('warning', direction === 'up'
                ? '선택한 권한이 이미 가장 위에 있습니다.'
                : '선택한 권한이 이미 가장 아래에 있습니다.');
            return;
        }

        const candidateRows = movedRows.map((row, index) => ({
            ...row,
            sort_no: index + 1,
            tree_sort: String(index + 1).padStart(6, '0'),
        }));
        const validation = validateDisplayRowOrder(candidateRows);
        if (!validation.valid) {
            notify('warning', validation.message || '허용되지 않는 위치로 이동할 수 없습니다.');
            return;
        }

        const plan = createReorderPlan(candidateRows.map((row) => ({ id: row.row_id })));
        if (!plan.valid) {
            notify('warning', plan.message || '허용되지 않는 위치로 이동할 수 없습니다.');
            return;
        }

        const previousTree = JSON.parse(JSON.stringify(currentTree));
        rebuildTreeFromDisplayRows(plan.rows);
        renderPermissionTable();
        permissionTable.setSelectedIds?.(ids);

        try {
            await postPermissionJson(API_REORDER, { changes: plan.changes });

            notify('success', '순서가 저장되었습니다.');
            reloadPermissions();
        } catch (error) {
            currentTree = previousTree;
            recomputeTreeState();
            renderPermissionTable();
            permissionTable.setSelectedIds?.(ids);
            notify('error', error?.message || '순서 저장에 실패했습니다.');
        }
    };

    tableNode.addEventListener('datatable:move-selected', tableNode.__rpSelectedMoveHandler);
}

function isPermissionGroupReorderAvailable() {
    if (!permissionTable || !selectedRoleId || currentDisplayRows.length <= 1) {
        return false;
    }

    const searchActive = String(permissionTable.search() || '').trim() !== '';
    return !searchActive;
}

function getDisplayRowMap() {
    return new Map(currentDisplayRows.map((row) => [String(row.row_id || ''), row]));
}

function buildDisplayRowsFromOrderedRowIds(rowIds = []) {
    const rowMap = getDisplayRowMap();
    const orderedRows = rowIds
        .map((rowId) => rowMap.get(String(rowId || '')))
        .filter(Boolean)
        .map((row) => ({ ...row }));

    return orderedRows.map((row, index) => ({
        ...row,
        sort_no: index + 1,
        tree_sort: String(index + 1).padStart(6, '0'),
    }));
}

function validateDisplayRowOrder(rows = []) {
    if (!rows.length) {
        return { valid: true, rows };
    }

    let currentPageKey = '';
    let seenPageKeys = new Set();

    for (const row of rows) {
        if (row.row_type === 'page') {
            currentPageKey = String(row.page_key || '');
            if (!currentPageKey) {
                return { valid: false, message: '\uD398\uC774\uC9C0 \uADF8\uB8F9 \uC815\uBCF4\uAC00 \uC62C\uBC14\uB974\uC9C0 \uC54A\uC2B5\uB2C8\uB2E4.' };
            }
            seenPageKeys.add(currentPageKey);
            continue;
        }

        if (row.row_type !== 'permission') {
            return { valid: false, message: '\uAD8C\uD55C \uD589 \uAD6C\uC870\uAC00 \uC62C\uBC14\uB974\uC9C0 \uC54A\uC2B5\uB2C8\uB2E4.' };
        }

        if (!currentPageKey) {
            return { valid: false, message: '\uC790\uC2DD \uAD8C\uD55C\uC740 \uBD80\uBAA8 \uD398\uC774\uC9C0 \uC704\uB85C \uC774\uB3D9\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.' };
        }

        if (String(row.page_key || '') !== currentPageKey) {
            return { valid: false, message: '\uC790\uC2DD \uAD8C\uD55C\uC740 \uB3D9\uC77C \uD398\uC774\uC9C0 \uADF8\uB8F9 \uB0B4\uC5D0\uC11C\uB9CC \uC774\uB3D9\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4.' };
        }
    }

    const originalPageKeys = new Set(currentDisplayRows.filter((row) => row.row_type === 'page').map((row) => String(row.page_key || '')));
    if (seenPageKeys.size !== originalPageKeys.size) {
        return { valid: false, message: '\uD398\uC774\uC9C0 \uADF8\uB8F9 \uC21C\uC11C\uAC00 \uC62C\uBC14\uB974\uC9C0 \uC54A\uC2B5\uB2C8\uB2E4.' };
    }

    return { valid: true, rows };
}

function rebuildTreeFromDisplayRows(rows = []) {
    const pageSourceMap = new Map(currentTree.map((pageNode) => [String(pageNode.page_key || ''), pageNode]));
    const childSourceMap = new Map();

    currentTree.forEach((pageNode) => {
        (pageNode.children || []).forEach((childNode) => {
            childSourceMap.set(String(childNode.permission_id || ''), childNode);
        });
    });

    const nextTree = [];
    let currentPageNode = null;

    rows.forEach((row) => {
        if (row.row_type === 'page') {
            const sourcePageNode = pageSourceMap.get(String(row.page_key || ''));
            if (!sourcePageNode) {
                return;
            }

            currentPageNode = {
                ...sourcePageNode,
                sort_no: row.sort_no,
                children: [],
            };
            nextTree.push(currentPageNode);
            return;
        }

        if (!currentPageNode || String(currentPageNode.page_key || '') !== String(row.page_key || '')) {
            return;
        }

        const sourceChildNode = childSourceMap.get(String(row.permission_id || ''));
        if (!sourceChildNode) {
            return;
        }

        currentPageNode.children.push({
            ...sourceChildNode,
        });
    });

    currentTree = nextTree;
    recomputeTreeState();
}

function createReorderPlan(mappedRows = []) {
    const orderedRowIds = mappedRows.map((row) => String(row.id || '')).filter(Boolean);
    const candidateRows = buildDisplayRowsFromOrderedRowIds(orderedRowIds);
    const validation = validateDisplayRowOrder(candidateRows);
    const rows = validation.rows || candidateRows;
    const changes = rows
        .filter((row) => row.row_type === 'permission' && String(row.permission_id || '') !== '')
        .map((row, index) => ({
            permission_id: String(row.permission_id || ''),
            permission_key: String(row.permission_key || ''),
            sort_no: index + 1,
        }));

    return {
        valid: validation.valid,
        message: validation.message || '',
        rows,
        changes,
    };
}

function finalizePermissionReorderSuccess() {
    if (!lastClientReorderPlan) {
        return;
    }

    if (!lastClientReorderPlan.valid) {
        const message = lastClientReorderPlan.message || '\uD5C8\uC6A9\uB418\uC9C0 \uC54A\uB294 \uC704\uCE58\uB85C \uC774\uB3D9\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        lastClientReorderPlan = null;
        renderPermissionTable();
        notify('warning', message);
        return;
    }

    rebuildTreeFromDisplayRows(lastClientReorderPlan.rows);
    lastClientReorderPlan = null;
    renderPermissionTable();
    notify('success', '\uC21C\uC11C\uAC00 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
}

function restoreRowOrderByIds(rowIds = []) {
    const $tbody = $(`${PAGE_TABLE_SELECTOR} tbody`);
    if (!$tbody.length || !Array.isArray(rowIds) || rowIds.length === 0) {
        return;
    }

    const rowMap = new Map();
    $tbody.children('tr').each(function eachRow() {
        const rowId = String(this.dataset.rowId || '');
        if (rowId) {
            rowMap.set(rowId, this);
        }
    });

    rowIds.forEach((rowId) => {
        const rowNode = rowMap.get(String(rowId || ''));
        if (rowNode) {
            $tbody.append(rowNode);
        }
    });
}

function attachPermissionGroupHooks() {
    const $tbody = $(`${PAGE_TABLE_SELECTOR} tbody`);
    if (!$tbody.length || !$tbody.data('ui-sortable')) {
        return;
    }

    const instance = $tbody.data('ui-sortable');
    if (instance.options.__rolePermissionGroupHooked) {
        return;
    }

    const originalStart = instance.options.start;
    const originalStop = instance.options.stop;

    $tbody.sortable('option', 'start', function onGroupStart(event, ui) {
        lastReorderWarningMessage = '';
        ui.item.data(
            'rp-before-row-ids',
            $tbody.children('tr').map((_, row) => String(row.dataset.rowId || '')).get()
        );

        if (typeof originalStart === 'function') {
            originalStart.call(this, event, ui);
        }

        const rowType = String(ui.item.data('rowType') || '');
        if (rowType !== 'page') {
            ui.item.data('rp-group-children', []);
            return;
        }

        const childRows = [];
        let nextNode = ui.item.next();
        while (nextNode.length && String(nextNode.data('rowType') || '') !== 'page') {
            const currentNode = nextNode;
            nextNode = nextNode.next();
            childRows.push(currentNode.detach().get(0));
        }

        ui.item.data('rp-group-children', childRows);
    });

    $tbody.sortable('option', 'stop', function onGroupStop(event, ui) {
        const childRows = ui.item.data('rp-group-children') || [];
        const beforeRowIds = ui.item.data('rp-before-row-ids') || [];
        let anchor = ui.item;
        childRows.forEach((childRow) => {
            anchor.after(childRow);
            anchor = window.jQuery(childRow);
        });
        ui.item.removeData('rp-group-children');
        ui.item.removeData('rp-before-row-ids');

        const afterRowIds = $tbody.children('tr').map((_, row) => String(row.dataset.rowId || '')).get();
        const validationPlan = createReorderPlan(afterRowIds.map((rowId) => ({ id: rowId })));
        if (!validationPlan.valid) {
            lastReorderWarningMessage = validationPlan.message || '\uD5C8\uC6A9\uB418\uC9C0 \uC54A\uB294 \uC704\uCE58\uB85C \uC774\uB3D9\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
            restoreRowOrderByIds(beforeRowIds);
        }

        if (typeof originalStop === 'function') {
            originalStop.call(this, event, ui);
        }
    });

    instance.options.__rolePermissionGroupHooked = true;
}

function refreshPermissionGroupReorder() {
    const $tbody = $(`${PAGE_TABLE_SELECTOR} tbody`);
    if (!$tbody.length) {
        return;
    }

    if ($tbody.data('ui-sortable')) {
        $tbody.sortable('destroy');
    }

    if (!isPermissionGroupReorderAvailable()) {
        return;
    }

    bindSortableRowReorder({
        table: permissionTable,
        tableSelector: PAGE_TABLE_SELECTOR,
        handle: '.reorder-handle',
        items: '> tr',
        api: API_REORDER,
        requestType: 'json',
        includeAppliedRows: true,
        changedRowsOnly: false,
        isReorderableRow: (rowData) => rowData?.row_type === 'page' || rowData?.row_type === 'permission',
        mapRow({ rowData, index }) {
            return rowData?.row_id
                ? {
                    id: rowData.row_id,
                    row_type: rowData.row_type,
                    page_key: rowData.page_key,
                    parent_page_key: rowData.parent_page_key,
                    permission_key: rowData.permission_key || '',
                    sort_no: index + 1,
                    newSortNo: index + 1,
                }
                : null;
        },
        updateRow() {
            // The UI recalculates full sequence numbers after renderPermissionTable().
        },
        buildPayload(rows) {
            if (lastReorderWarningMessage) {
                const message = lastReorderWarningMessage;
                lastReorderWarningMessage = '';
                lastClientReorderPlan = {
                    valid: false,
                    message,
                    rows: currentDisplayRows,
                    changes: [],
                };
                return { changes: [] };
            }

            lastClientReorderPlan = createReorderPlan(rows);
            return { changes: lastClientReorderPlan.changes };
        },
        onSuccess() {
            if (lastClientReorderPlan && !lastClientReorderPlan.valid) {
                const message = lastClientReorderPlan.message || '\uD5C8\uC6A9\uB418\uC9C0 \uC54A\uB294 \uC704\uCE58\uB85C \uC774\uB3D9\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
                lastClientReorderPlan = null;
                renderPermissionTable();
                notify('warning', message);
                return;
            }

            finalizePermissionReorderSuccess();
            reloadPermissions();
        },
        onError(response) {
            if (lastClientReorderPlan && !lastClientReorderPlan.valid) {
                const message = lastClientReorderPlan.message || '\uD5C8\uC6A9\uB418\uC9C0 \uC54A\uB294 \uC704\uCE58\uB85C \uC774\uB3D9\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
                lastClientReorderPlan = null;
                renderPermissionTable();
                notify('warning', message);
                return;
            }

            lastClientReorderPlan = null;
            renderPermissionTable();
            notify('error', response?.message || '\uC21C\uC11C \uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
        },
    });

    attachPermissionGroupHooks();
}

function bindSaveButton() {
    const { saveButton } = getDom();
    if (!saveButton) {
        return;
    }

    saveButton.addEventListener('click', async () => {
        if (!selectedRoleId) {
            notify('warning', '\uC5ED\uD560\uC744 \uBA3C\uC800 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.');
            return;
        }

        if (!selectedRoleActive) {
            notify('warning', '비활성 역할에는 권한을 저장할 수 없습니다.');
            return;
        }

        if (!Object.keys(pendingChanges).length) {
            notify('warning', '\uBCC0\uACBD\uB41C \uAD8C\uD55C\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.');
            return;
        }

        const requestedRoleId = selectedRoleId;
        isSavingPermissions = true;
        saveButton.disabled = true;

        const permissionIds = currentTree
            .flatMap((pageNode) => pageNode.children || [])
            .filter((permissionNode) => !!permissionNode.checked)
            .map((permissionNode) => String(permissionNode.permission_id || ''))
            .filter(Boolean);

        if (permissionIds.length === 0 && !await confirmDialog({
            title: '전체 권한 해제',
            message: '선택한 역할의 모든 권한을 해제하시겠습니까?',
            confirmText: '전체 해제',
            confirmClass: 'btn-danger',
        })) {
            isSavingPermissions = false;
            saveButton.disabled = false;
            return;
        }

        const removesCorePermission = permissionIds.length > 0 && currentTree
            .flatMap(pageNode => pageNode.children || [])
            .some(permission => CORE_MANAGEMENT_PERMISSION_KEYS.has(String(permission.permission_key || '')) && !permission.checked);
        if (removesCorePermission && !await confirmDialog({
            title: '핵심 관리 권한 해제',
            message: '권한부여 접근에 필요한 핵심 관리 권한이 해제됩니다. 계속하시겠습니까?',
            confirmText: '계속',
            confirmClass: 'btn-danger',
        })) {
            isSavingPermissions = false;
            saveButton.disabled = false;
            return;
        }

        try {
            await postPermissionJson(API_SAVE, { role_id: requestedRoleId, permission_ids: permissionIds });
            if (requestedRoleId !== selectedRoleId) return;
            notify('success', '권한이 저장되었습니다.');
            await reloadPermissions();
        } catch (error) {
            notify('error', error?.message || '권한 저장 중 오류가 발생했습니다.');
        } finally {
            isSavingPermissions = false;
            saveButton.disabled = false;
        }
    });
}

$(async function onReady() {
    bindRoleTableSettingsPolicy();
    bindPermissionTableSettingsPolicy();
    permissionMasterPromise = loadPermissionMaster(API_ROLE_PERMISSIONS).then(responseRows);
    await Promise.all([initRoleTable(), initPermissionTable(), permissionMasterPromise]);
    bindPermissionAssignmentStickyLayout();
    bindPermissionSelectedMove();
    bindSaveButton();
    setSaveDirty(false);
    syncPermissionHeaderCheckboxState();
    await renderPermissionTable();
});
