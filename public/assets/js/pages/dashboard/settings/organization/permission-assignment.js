import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindSortableRowReorder } from '/public/assets/js/common/row-reorder.js';

const API_ROLE_LIST = '/api/settings/organization/role/list';
const API_ROLE_PERMISSIONS = '/api/settings/organization/role-permission/list';
const API_ASSIGN = '/api/settings/organization/role-permission/assign';
const API_REMOVE = '/api/settings/organization/role-permission/remove';
const API_REORDER = '/api/settings/organization/role-permission/reorder';
const API_PERMISSION_DELETE = '/api/settings/organization/permission/delete';

const PAGE_TABLE_SELECTOR = '#permission-assignment-table';
const ROLE_TABLE_SELECTOR = '#role-list-table';

let roleTable = null;
let permissionTable = null;
let selectedRoleId = '';
let currentTree = [];
let currentDisplayRows = [];
let pendingChanges = {};
let originalPermissionStates = new Map();
let lastClientReorderPlan = null;
let lastReorderWarningMessage = '';

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
        ? '<span class="badge bg-success">\uC0AC\uC6A9</span>'
        : '<span class="badge bg-secondary">\uBBF8\uC0AC\uC6A9</span>';
}

function getResponseRows(json) {
    return Array.isArray(json?.data) ? json.data : [];
}

function getDom() {
    return {
        selectedRoleName: document.getElementById('rp-selected-role-name'),
        permissionHeader: document.getElementById('permission-header'),
        permissionCount: document.getElementById('permission-count'),
        saveButton: document.getElementById('permission-save-btn'),
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

    saveButton.classList.toggle('btn-primary', isDirty);
    saveButton.classList.toggle('btn-secondary', !isDirty);
}

function initRoleTable() {
    roleTable = createDataTable({
        tableSelector: ROLE_TABLE_SELECTOR,
        api: API_ROLE_LIST,
        defaultOrder: [[0, 'asc']],
        pageLength: 100,
        cellSearchFill: false,
        selectable: false,
        deleteButton: false,
        columns: [
            {
                data: 'sort_no',
                title: '\uC21C\uBC88',
                className: 'text-center',
                render: (value) => escapeHtml(value),
            },
            {
                data: 'role_name',
                title: '\uC5ED\uD560\uBA85',
                render: (value) => escapeHtml(value),
            },
            {
                data: 'is_active',
                title: '\uC0C1\uD0DC',
                className: 'text-center',
                render: (value) => buildStatusBadge(value),
            },
        ],
        dataSrc(json) {
            const rows = getResponseRows(json).sort((a, b) => Number(a.sort_no || 0) - Number(b.sort_no || 0));
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

function initPermissionTable() {
    if (permissionTable) {
        return permissionTable;
    }

    permissionTable = createDataTable({
        tableSelector: PAGE_TABLE_SELECTOR,
        initialData: [],
        columns: buildPermissionColumns(),
        defaultOrder: [[1, 'asc']],
        pageLength: 100,
        scrollX: false,
        autoWidth: false,
        fixedLayout: true,
        paging: true,
        searching: true,
        info: true,
        selectable: true,
        selectionColumnIndex: 0,
        rowIdField: (row) => (row?.row_type === 'permission' ? String(row.permission_id || '') : ''),
        isRowSelectable: (row) => row?.row_type === 'permission',
        deleteButton: true,
        showCopyButton: false,
        showColumnVisibility: false,
        widthScopeSelector: '#permission-assignment-table-wrap',
        searchTableId: 'rolePermissionMatrix',
        tableSettings: {
            enabled: true,
            pageKey: 'settings.organization.role_permissions',
            tableKey: 'permission-matrix',
            storageKey: 'settings.organization.role_permissions.permission-matrix.flat.v2',
            tableLabel: 'Role Permission List',
            columns: buildPermissionColumns(),
            requiredColumns: [],
            defaultVisibleColumns: [
                'handle',
                'sort_no',
                'page',
                'category',
                'permission_name',
                'permission_description',
                'grant',
            ],
        },
    });

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
            settingsKey: 'handle',
            className: 'text-center no-colvis',
            headerClassName: 'text-center',
            width: '56px',
            orderable: false,
            searchable: false,
            widthResizable: false,
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
            title: '\uC21C\uBC88',
            data: 'sort_no',
            settingsKey: 'sort_no',
            className: 'text-center',
            headerClassName: 'text-center',
            width: '72px',
            orderSequence: ['asc'],
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return row.tree_sort || '';
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
            orderSequence: ['asc'],
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return row.tree_sort || '';
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return row.row_type === 'page' ? escapeHtml(value || '') : '';
            },
        },
        {
            title: '\uCE74\uD14C\uACE0\uB9AC',
            data: 'category',
            settingsKey: 'category',
            widthResizable: true,
            orderSequence: ['asc'],
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return row.tree_sort || '';
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return row.row_type === 'page' ? escapeHtml(value || '') : '';
            },
        },
        {
            title: '\uAD8C\uD55C\uBA85',
            data: 'permission_name',
            settingsKey: 'permission_name',
            widthResizable: true,
            orderSequence: ['asc'],
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return row.tree_sort || '';
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return renderPermissionHierarchyCell(row, value);
            },
        },
        {
            title: '\uAD8C\uD55C\uC124\uBA85',
            data: 'permission_description',
            settingsKey: 'permission_description',
            widthResizable: true,
            orderSequence: ['asc'],
            render: (value, type, row) => {
                if (type === 'sort' || type === 'type') {
                    return row.tree_sort || '';
                }

                if (type === 'filter') {
                    return row.search_text || String(value || '');
                }

                return escapeHtml(value || '');
            },
        },
        {
            title: '\uAD8C\uD55C\uBD80\uC5EC',
            data: null,
            settingsKey: 'grant',
            className: 'text-center',
            headerClassName: 'text-center',
            width: '96px',
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
    const { selectedRoleName, permissionHeader } = getDom();

    selectedRoleId = String(row?.id ?? '');
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
}

function reloadPermissions() {
    if (!selectedRoleId) {
        return;
    }

    $.post(API_ROLE_PERMISSIONS, { role_id: selectedRoleId })
        .done((res) => {
            if (!res || res.success === false) {
                notify('error', '\uC5ED\uD560 \uAD8C\uD55C \uC870\uD68C\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                return;
            }

            currentTree = getResponseRows(res);
            recomputeTreeState();
            rebuildOriginalPermissionStates(currentTree);
            pendingChanges = {};
            setSaveDirty(false);
            renderPermissionTable();
        })
        .fail(() => notify('error', '\uC5ED\uD560 \uAD8C\uD55C \uC870\uD68C\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.'));
}

function buildSearchText(pageNode, childNode = null) {
    const values = childNode
        ? [
            pageNode.page,
            pageNode.category,
            pageNode.permission_name,
            pageNode.permission_description,
            childNode.permission_name,
            childNode.permission_description,
            childNode.permission_source,
        ]
        : [
            pageNode.page,
            pageNode.category,
            pageNode.permission_name,
            pageNode.permission_description,
            ...(pageNode.children || []).flatMap((node) => [
                node.permission_name,
                node.permission_description,
                node.permission_source,
            ]),
        ];

    return values
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .join(' ');
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
            page_key: pageNode.page_key,
            parent_page_key: '',
            permission_id: '',
            permission_key: '',
            permission_source: '',
            page: pageNode.page,
            category: pageNode.category,
            permission_name: pageNode.permission_name,
            permission_description: pageNode.permission_description,
            checked: !!pageNode.checked,
            indeterminate: !!pageNode.indeterminate,
            depth_padding: 0,
            search_text: buildSearchText(pageNode),
        });
        sequence += 1;

        (pageNode.children || []).forEach((childNode) => {
            rows.push({
                row_id: `permission:${childNode.permission_id}`,
                row_type: 'permission',
                sort_no: sequence,
                tree_sort: String(sequence).padStart(6, '0'),
                page_key: pageNode.page_key,
                parent_page_key: pageNode.page_key,
                permission_id: childNode.permission_id,
                permission_key: childNode.permission_key,
                permission_source: childNode.permission_source,
                page: pageNode.page,
                category: pageNode.category,
                permission_name: childNode.permission_name,
                permission_description: childNode.permission_description,
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

    const pageCount = currentTree.length;
    const permissionCountValue = currentTree.reduce((sum, pageNode) => sum + (pageNode.children || []).length, 0);
    permissionCount.textContent = `\uD398\uC774\uC9C0 ${pageCount}\uAC1C / \uAD8C\uD55C ${permissionCountValue}\uAC1C`;
}

function renderPermissionTable() {
    const table = initPermissionTable();
    currentDisplayRows = selectedRoleId ? buildDisplayRows() : [];

    table.clear();
    table.rows.add(currentDisplayRows);
    table.draw(false);
    updatePermissionCount();
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
    $(document).off('datatable:delete-selected.rolePermissionFlat', PAGE_TABLE_SELECTOR);

    $(document).on('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-page-checkbox`, function onPageCheckboxChange() {
        setPageNodeChecked(String(this.dataset.pageKey || ''), !!this.checked);
        setSaveDirty(Object.keys(pendingChanges).length > 0);
        renderPermissionTable();
    });

    $(document).on('change.rolePermissionFlat', `${PAGE_TABLE_SELECTOR} .rp-permission-checkbox`, function onPermissionCheckboxChange() {
        setChildNodeChecked(
            String(this.dataset.pageKey || ''),
            String(this.dataset.permissionId || ''),
            !!this.checked
        );
        setSaveDirty(Object.keys(pendingChanges).length > 0);
        renderPermissionTable();
    });

    $(document).on('datatable:delete-selected.rolePermissionFlat', PAGE_TABLE_SELECTOR, async function onDeleteSelected(event) {
        const ids = Array.isArray(event.originalEvent?.detail?.ids) ? event.originalEvent.detail.ids : [];
        if (!ids.length) {
            notify('warning', '삭제할 권한을 선택해주세요.');
            return;
        }

        const confirmed = window.confirm('선택한 권한을 영구 삭제하시겠습니까?\n\n삭제된 권한은 복구되지 않습니다.');
        if (!confirmed) {
            return;
        }

        try {
            for (const id of ids) {
                const response = await $.post(API_PERMISSION_DELETE, { id });
                if (!response?.success) {
                    throw new Error(response?.message || '권한 삭제에 실패했습니다.');
                }
            }

            notify('success', '권한이 삭제되었습니다.');
            reloadPermissions();
        } catch (error) {
            notify('error', error?.message || '권한 삭제에 실패했습니다.');
        }
    });
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

    saveButton.addEventListener('click', () => {
        if (!selectedRoleId) {
            notify('warning', '\uC5ED\uD560\uC744 \uBA3C\uC800 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.');
            return;
        }

        const changes = Object.entries(pendingChanges);
        if (!changes.length) {
            notify('warning', '\uBCC0\uACBD\uB41C \uAD8C\uD55C\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.');
            return;
        }

        saveButton.disabled = true;

        const tasks = changes.map(([permissionId, checked]) => {
            const url = checked ? API_ASSIGN : API_REMOVE;
            return $.post(url, {
                role_id: selectedRoleId,
                permission_id: permissionId,
            });
        });

        Promise.all(tasks)
            .then(() => {
                notify('success', '\uAD8C\uD55C\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                reloadPermissions();
            })
            .catch(() => notify('error', '\uAD8C\uD55C \uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.'))
            .finally(() => {
                saveButton.disabled = false;
            });
    });
}

$(function onReady() {
    initRoleTable();
    initPermissionTable();
    bindSaveButton();
    renderPermissionTable();
});
