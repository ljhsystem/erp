import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';

const API = {
    TEMPLATE_LIST: '/api/settings/organization/approval/template/list',
    TEMPLATE_SAVE: '/api/settings/organization/approval/template/save',
    TEMPLATE_DELETE: '/api/settings/organization/approval/template/delete',
    TEMPLATE_REORDER: '/api/settings/organization/approval/template/reorder',
    STEP_LIST: '/api/settings/organization/approval/step/list',
    STEP_SAVE: '/api/settings/organization/approval/step/save',
    STEP_DELETE: '/api/settings/organization/approval/step/delete',
    ROLE_LIST: '/api/settings/organization/role/list',
    EMPLOYEE_LIST: '/api/settings/organization/employee/list'
};

let selectedTemplateId = '';
let selectedStepId = '';
let templateTable = null;
let stepTable = null;
let templateModal = null;
let stepModal = null;
let isSorting = false;
let roleList = [];
let userList = [];
const ROLE_NONE_VALUE = '__NONE__';
const APPROVER_NONE_VALUE = '__NONE__';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalize(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ');
}

function normalizeApproverId(value) {
    const normalized = normalize(value);
    return normalized === APPROVER_NONE_VALUE ? '' : normalized;
}

function normalizeRoleId(value) {
    const normalized = normalize(value);
    return normalized === ROLE_NONE_VALUE ? '' : normalized;
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

function statusBadge(value) {
    return String(value) === '1'
        ? '<span class="badge bg-success">활성</span>'
        : '<span class="badge bg-secondary">비활성</span>';
}

function getRows(json) {
    return Array.isArray(json?.data) ? json.data : [];
}

function init() {
    initModals();
    initTemplateTable();
    initStepTable();
    bindTemplateEvents();
    bindStepEvents();
    bindLayoutEvents();
    preloadSelectLists();
}

function initModals() {
    const templateModalEl = document.getElementById('modal-template-edit');
    const stepModalEl = document.getElementById('modal-step-edit');

    if (templateModalEl) {
        templateModal = new bootstrap.Modal(templateModalEl, { focus: false });
        templateModalEl.addEventListener('hidden.bs.modal', resetTemplateModal);
    }

    if (stepModalEl) {
        stepModal = new bootstrap.Modal(stepModalEl, { focus: false });
        stepModalEl.addEventListener('hidden.bs.modal', resetStepModal);
    }
}

function initTemplateTable() {
    templateTable = createDataTable({
        tableSelector: '#template-list-table',
        api: API.TEMPLATE_LIST,
        defaultOrder: [[1, 'asc']],
        pageLength: 10,
        cellSearchFill: false,
        columns: [
            {
                data: null,
                className: 'text-center reorder-handle no-colvis',
                orderable: false,
                searchable: false,
                render: () => '<i class="bi bi-list"></i>'
            },
            { data: 'sort_no', className: 'text-center', render: (value) => escapeHtml(value) },
            { data: 'template_name', render: (value) => escapeHtml(value) },
            { data: 'document_type', render: (value) => escapeHtml(value) },
            { data: 'description', defaultContent: '', render: (value) => escapeHtml(value) },
            {
                data: 'is_active',
                className: 'text-center',
                render: (value, type) => (type === 'display' ? statusBadge(value) : escapeHtml(value))
            },
            { data: 'template_key', visible: false, render: (value) => escapeHtml(value) },
            { data: 'created_at', visible: false, render: (value) => escapeHtml(value) },
            {
                data: 'created_by_name',
                visible: false,
                render: (value, type, row) => escapeHtml(value || row?.created_by || '')
            },
            { data: 'updated_at', visible: false, render: (value) => escapeHtml(value) },
            {
                data: 'updated_by_name',
                visible: false,
                render: (value, type, row) => escapeHtml(value || row?.updated_by || '')
            }
        ],
        dataSrc(json) {
            return getRows(json);
        }
    });

    bindTableHighlight('#template-list-table', templateTable);
    bindRowReorder(templateTable, {
        api: API.TEMPLATE_REORDER,
        onSuccess() {
            reloadTemplateTable();
            notify('success', '템플릿 순번이 저장되었습니다.');
        },
        onError() {
            notify('error', '템플릿 순번 저장에 실패했습니다.');
            reloadTemplateTable();
        }
    });

    templateTable.on('draw.dt xhr.dt', () => {
        updateTemplateCount();
        markSelectedTemplateRow();
    });
}

function initStepTable() {
    stepTable = createDataTable({
        tableSelector: '#template-steps-table',
        api: API.STEP_LIST,
        defaultOrder: [[1, 'asc']],
        pageLength: 50,
        cellSearchFill: false,
        ajaxData() {
            return { template_id: selectedTemplateId || '' };
        },
        columns: [
            {
                data: null,
                className: 'text-center drag-handle no-colvis',
                orderable: false,
                searchable: false,
                render: () => '<i class="bi bi-list"></i>'
            },
            { data: 'sort_no', className: 'text-center step-sequence', render: (value) => escapeHtml(value) },
            { data: 'step_name', render: (value) => escapeHtml(value) },
            { data: 'role_name', render: (value) => escapeHtml(value || '-') },
            {
                data: null,
                render(data, type, row) {
                    const name = row?.specific_employee_name || row?.specific_username || '';
                    return escapeHtml(name || '-');
                }
            },
            {
                data: 'is_active',
                className: 'text-center',
                render: (value, type) => (type === 'display' ? statusBadge(value) : escapeHtml(value))
            },
            { data: 'created_at', visible: false, render: (value) => escapeHtml(value) },
            {
                data: 'created_by_name',
                visible: false,
                render: (value, type, row) => escapeHtml(value || row?.created_by || '')
            },
            { data: 'updated_at', visible: false, render: (value) => escapeHtml(value) },
            {
                data: 'updated_by_name',
                visible: false,
                render: (value, type, row) => escapeHtml(value || row?.updated_by || '')
            }
        ],
        createdRow(row, data) {
            setStepRowData(row, data);
        },
        dataSrc(json) {
            return getRows(json);
        }
    });

    bindTableHighlight('#template-steps-table', stepTable);

    stepTable.on('draw.dt xhr.dt', () => {
        updateStepCount();
        markSelectedStepRow();
        initSortable();
    });
}

function setStepRowData(row, data) {
    $(row)
        .attr('data-id', data?.id || '')
        .attr('data-sort_no', data?.sort_no || '')
        .attr('data-step_name', data?.step_name || '')
        .attr('data-role_id', data?.role_id || '')
        .attr('data-user_id', data?.approver_id || '')
        .attr('data-active', data?.is_active ?? 1);
}

function bindTemplateEvents() {
    $('#template-list-table tbody').on('click', 'tr', function () {
        const row = templateTable.row(this).data();
        if (!row) return;

        selectedTemplateId = String(row.id || '');
        selectedStepId = '';

        $('#template-list-table tbody tr').removeClass('table-active');
        $(this).addClass('table-active');

        $('#btn-add-step').prop('disabled', false);
        $('#ap-selected-template-name').text(`[${row.template_name}]`);

        reloadStepTable();
    });

    $('#template-list-table tbody').on('dblclick', 'tr', function () {
        const row = templateTable.row(this).data();
        if (row) {
            openTemplateModal('edit', row);
        }
    });

    $('#btn-create-template').on('click', () => openTemplateModal('create'));
    $('#btn-save-template-edit').on('click', saveTemplate);
    $('#btn-delete-template-edit').on('click', deleteTemplate);
}

function bindStepEvents() {
    $('#btn-add-step').on('click', async () => {
        if (!selectedTemplateId) {
            notify('warning', '먼저 템플릿을 선택해 주세요.');
            return;
        }

        await preloadSelectLists();
        openStepModal('create');
    });

    $('#template-steps-table tbody').on('click', 'tr', function () {
        const row = stepTable.row(this).data();
        if (!row) return;

        selectedStepId = String(row.id || '');
        $('#template-steps-table tbody tr').removeClass('table-active');
        $(this).addClass('table-active');
    });

    $('#template-steps-table tbody').on('dblclick', 'tr', async function () {
        const row = stepTable.row(this).data();
        if (!row) return;

        await preloadSelectLists();
        openStepModal('edit', row);
    });

    $('#btn-save-step-edit').on('click', saveStep);
    $('#btn-delete-step-edit').on('click', deleteStep);
}

function openTemplateModal(mode, row = null) {
    resetTemplateModal();

    const isCreate = mode === 'create';
    $('#modal-template-edit .modal-title').text(isCreate ? '템플릿 등록' : '템플릿 수정');
    $('#btn-delete-template-edit').toggle(!isCreate);

    if (!isCreate && row) {
        $('#tpl-edit-id').val(row.id || '');
        $('#tpl-edit-name').val(normalize(row.template_name));
        $('#tpl-edit-doc-type').val(normalize(row.document_type));
        $('#tpl-edit-desc').val(normalize(row.description || ''));
        $('#tpl-edit-active').prop('checked', String(row.is_active) === '1');
    } else {
        $('#tpl-edit-active').prop('checked', true);
    }

    templateModal?.show();
}

function resetTemplateModal() {
    $('#tpl-edit-id').val('');
    $('#tpl-edit-name').val('');
    $('#tpl-edit-doc-type').val('');
    $('#tpl-edit-desc').val('');
    $('#tpl-edit-active').prop('checked', true);
    $('#btn-delete-template-edit').hide();
}

async function saveTemplate() {
    const id = $('#tpl-edit-id').val();
    const payload = {
        id,
        name: normalize($('#tpl-edit-name').val()),
        document_type: normalize($('#tpl-edit-doc-type').val()),
        description: normalize($('#tpl-edit-desc').val()),
        is_active: $('#tpl-edit-active').is(':checked') ? 1 : 0
    };

    if (!payload.name || !payload.document_type) {
        notify('warning', '템플릿명과 문서유형을 입력해 주세요.');
        return;
    }

    try {
        const res = await $.post(API.TEMPLATE_SAVE, payload);
        if (!res?.success) {
            notify('error', res?.message || '저장에 실패했습니다.');
            return;
        }

        templateModal?.hide();
        reloadTemplateTable(id || res?.id || '');
        notify('success', '저장되었습니다.');
    } catch (err) {
        console.error('[approval] save template failed:', err);
        notify('error', '저장 중 오류가 발생했습니다.');
    }
}

async function deleteTemplate() {
    const id = $('#tpl-edit-id').val();
    if (!id) return;
    if (!confirm('템플릿을 영구삭제하시겠습니까?')) return;

    try {
        const res = await $.post(API.TEMPLATE_DELETE, { id });
        if (!res?.success) {
            notify('error', res?.message || '삭제에 실패했습니다.');
            return;
        }

        if (String(selectedTemplateId) === String(id)) {
            selectedTemplateId = '';
            selectedStepId = '';
            $('#ap-selected-template-name').text('');
            $('#approvalStepCount').text('');
            $('#btn-add-step').prop('disabled', true);
            reloadStepTable();
        }

        templateModal?.hide();
        reloadTemplateTable();
        notify('success', '삭제되었습니다.');
    } catch (err) {
        console.error('[approval] delete template failed:', err);
        notify('error', '삭제 중 오류가 발생했습니다.');
    }
}

function openStepModal(mode, step = null) {
    resetStepModal();

    const isCreate = mode === 'create';
    $('#modal-step-edit .modal-title').text(isCreate ? '단계 등록' : '단계 수정');
    $('#btn-delete-step-edit').toggle(!isCreate);

    fillRoleSelect('#step-edit-role', step?.role_id || '');
    fillUserSelect('#step-edit-user', step?.approver_id || '');

    if (!isCreate && step) {
        $('#step-edit-id').val(step.id || '');
        $('#step-edit-name').val(normalize(step.step_name || ''));
        $('#step-edit-active').prop('checked', String(step.is_active) === '1');
    } else {
        $('#step-edit-active').prop('checked', true);
    }

    stepModal?.show();
}

function resetStepModal() {
    $('#step-edit-id').val('');
    $('#step-edit-name').val('');
    resetSelect('#step-edit-role');
    resetSelect('#step-edit-user');
    $('#step-edit-active').prop('checked', true);
    $('#btn-delete-step-edit').hide();
}

async function saveStep() {
    if (!selectedTemplateId) {
        notify('warning', '먼저 템플릿을 선택해 주세요.');
        return;
    }

    const payload = {
        id: $('#step-edit-id').val(),
        template_id: selectedTemplateId,
        step_name: normalize($('#step-edit-name').val()),
        role_id: normalizeRoleId($('#step-edit-role').val()),
        approver_id: normalizeApproverId($('#step-edit-user').val()),
        is_active: $('#step-edit-active').is(':checked') ? 1 : 0
    };

    if (!payload.step_name) {
        notify('warning', '단계명을 입력해 주세요.');
        return;
    }

    if (!payload.role_id && !payload.approver_id) {
        notify('warning', '결재자 역할 또는 특정 결재자를 선택해 주세요.');
        return;
    }

    try {
        const res = await $.post(API.STEP_SAVE, payload);
        if (!res?.success) {
            notify('error', res?.message || '저장에 실패했습니다.');
            return;
        }

        stepModal?.hide();
        reloadStepTable();
        notify('success', '저장되었습니다.');
    } catch (err) {
        console.error('[approval] save step failed:', err);
        notify('error', '저장 중 오류가 발생했습니다.');
    }
}

async function deleteStep() {
    const stepId = $('#step-edit-id').val();
    if (!stepId) return;
    if (!confirm('단계를 영구삭제하시겠습니까?')) return;

    try {
        const res = await $.post(API.STEP_DELETE, { step_id: stepId });
        if (!res?.success) {
            notify('error', res?.message || '삭제에 실패했습니다.');
            return;
        }

        selectedStepId = '';
        stepModal?.hide();
        reloadStepTable();
        notify('success', '삭제되었습니다.');
    } catch (err) {
        console.error('[approval] delete step failed:', err);
        notify('error', '삭제 중 오류가 발생했습니다.');
    }
}

async function preloadSelectLists() {
    try {
        const [roleRes, userRes] = await Promise.all([
            $.get(API.ROLE_LIST),
            $.get(API.EMPLOYEE_LIST)
        ]);

        roleList = getRows(roleRes);
        userList = getRows(userRes);
    } catch (err) {
        console.error('[approval] preload select lists failed:', err);
    }
}

function resetSelect(selector) {
    const $el = $(selector);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }
    $el.empty();
}

function initSelect2(selector, dropdownParent, options = {}) {
    if (!$.fn.select2) return;

    const $el = $(selector);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        width: '100%',
        dropdownParent: $(dropdownParent),
        placeholder: options.placeholder || '선택',
        allowClear: Boolean(options.allowClear)
    });
}

function fillRoleSelect(selector, selected = '') {
    const $el = $(selector);
    const selectedValue = selected ? String(selected) : ROLE_NONE_VALUE;

    resetSelect(selector);
    $el.append(new Option('선택(없음)', ROLE_NONE_VALUE, false, selectedValue === ROLE_NONE_VALUE));

    roleList.forEach((role) => {
        $el.append(new Option(role.role_name || role.role_key || role.id, role.id, false, selectedValue === String(role.id)));
    });

    initSelect2(selector, '#modal-step-edit', { placeholder: '선택' });
    $el.val(selectedValue).trigger('change');
}

function fillUserSelect(selector, selected = '') {
    const $el = $(selector);
    const selectedValue = selected ? String(selected) : APPROVER_NONE_VALUE;

    resetSelect(selector);
    $el.append(new Option('선택(없음)', APPROVER_NONE_VALUE, false, selectedValue === APPROVER_NONE_VALUE));

    userList.forEach((user) => {
        const userId = user.user_id || user.id || '';
        const label = user.employee_name
            ? `${user.employee_name} (${user.username || ''})`
            : (user.username || userId);

        $el.append(new Option(label, userId, false, selectedValue === String(userId)));
    });

    initSelect2(selector, '#modal-step-edit', { placeholder: '선택' });
    $el.val(selectedValue).trigger('change');
}

function updateTemplateCount() {
    if (!templateTable?.page) return;
    const info = templateTable.page.info();
    $('#approvalTemplateCount').text(`총 ${info?.recordsDisplay ?? 0}건`);
}

function updateStepCount() {
    if (!stepTable?.page) return;
    const info = stepTable.page.info();
    $('#approvalStepCount').text(info.recordsDisplay ? `총 ${info.recordsDisplay}단계` : '');
}

function reloadTemplateTable(preferredId = '') {
    templateTable?.ajax.reload(() => {
        if (preferredId) {
            selectedTemplateId = String(preferredId);
        }

        markSelectedTemplateRow();
        updateTemplateCount();
    }, false);
}

function reloadStepTable() {
    stepTable?.ajax.reload(() => {
        updateStepCount();
        initSortable();
    }, false);
}

function markSelectedTemplateRow() {
    if (!templateTable || !selectedTemplateId) return;

    $('#template-list-table tbody tr').removeClass('table-active');
    templateTable.rows().every(function () {
        const row = this.data();
        if (String(row?.id || '') === String(selectedTemplateId)) {
            $(this.node()).addClass('table-active');
            $('#ap-selected-template-name').text(`[${row.template_name}]`);
            $('#btn-add-step').prop('disabled', false);
        }
    });
}

function markSelectedStepRow() {
    if (!stepTable || !selectedStepId) return;

    $('#template-steps-table tbody tr').removeClass('table-active');
    stepTable.rows().every(function () {
        const row = this.data();
        if (String(row?.id || '') === String(selectedStepId)) {
            $(this.node()).addClass('table-active');
        }
    });
}

function initSortable() {
    const $sortable = $('#template-steps-table tbody');
    if (!$sortable.length || isSorting) return;

    if (typeof $sortable.sortable !== 'function') {
        console.error('[approval] jQuery UI sortable is not available.');
        return;
    }

    if ($sortable.data('ui-sortable')) {
        $sortable.sortable('destroy');
    }

    $sortable.sortable({
        handle: '.drag-handle',
        items: '> tr',
        axis: 'y',
        containment: 'parent',
        tolerance: 'pointer',
        placeholder: 'approval-step-placeholder',
        helper(_, tr) {
            const $originals = tr.children();
            const $helper = tr.clone();

            $helper.children().each(function (index) {
                $(this).width($originals.eq(index).outerWidth());
            });

            return $helper;
        },
        stop() {
            const updateList = [];

            $('#template-steps-table tbody tr').each(function (index) {
                const rowData = stepTable.row(this).data();
                const id = rowData?.id || $(this).data('id');
                if (!id) return;

                $(this).attr('data-sort_no', index + 1);
                $(this).find('.step-sequence').text(index + 1);
                updateList.push({ id, sort_no: index + 1 });
            });

            if (!updateList.length) return;

            isSorting = true;
            $.post(API.STEP_SAVE, {
                reorder: 1,
                template_id: selectedTemplateId,
                steps: JSON.stringify(updateList)
            })
                .done((res) => {
                    if (!res?.success) {
                        notify('error', res?.message || '단계 순서 저장에 실패했습니다.');
                        return;
                    }

                    notify('success', '단계 순번이 저장되었습니다.');
                })
                .fail(() => notify('error', '단계 순서 저장 중 오류가 발생했습니다.'))
                .always(() => {
                    setTimeout(() => {
                        isSorting = false;
                        reloadStepTable();
                    }, 120);
                });
        }
    }).disableSelection();
}

function bindLayoutEvents() {
    window.addEventListener('resize', () => {
        templateTable?.columns.adjust();
        stepTable?.columns.adjust();
    });
}

$(function () {
    init();
});
