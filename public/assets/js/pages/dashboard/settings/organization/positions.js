// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/positions.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    updateTableHeight,
    forceTableHeightSync,
    bindTableHighlight
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[positions.js] loaded');

    const API = {
        LIST: '/api/settings/organization/position/list',
        SAVE: '/api/settings/organization/position/save',
        REORDER: '/api/settings/organization/position/reorder'
    };

    const POSITION_COLUMN_MAP = {
        sort_no:          { label: '순번', visible: true },
        position_name: { label: '직책명', visible: true },
        level_rank:    { label: '레벨', visible: true },
        description:   { label: '설명', visible: true },
        is_active:     { label: '상태', visible: true, noVis: true },
        created_at:    { label: '등록일자', visible: false },
        updated_at:    { label: '수정일자', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    let positionTable = null;
    let positionModal = null;
    let todayPicker = null;
    let globalBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[positions.js] jQuery not loaded');
            return;
        }

        initPositionPage(window.jQuery);
    });

    function initPositionPage($) {
        initModal();
        initAdminDatePicker();
        bindAdminDateInputs();
        initDataTable($);
        bindRowReorder(positionTable, { api: API.REORDER });
        bindTableEvents($);
        bindModalEvents($);
        bindTableLayoutEvents(positionTable, '#position-table');
        bindGlobalEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('positionEditModal');
        if (!modalEl) return;

        positionModal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('shown.bs.modal', () => {
            document.getElementById('position_edit_name')?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetPositionForm();
        });
    }

    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;

        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({
            type: 'today',
            container
        });

        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;

            input.value = formatDate(date);
            normalizeStartEnd(input.name === 'dateStart' ? 'start' : 'end');
            todayPicker.close();
        });

        return todayPicker;
    }

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach(input => {
            if (input.__positionDateBound) return;
            input.__positionDateBound = true;

            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                openDatePicker(input);
            });
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            if (icon.__positionDateBound) return;
            icon.__positionDateBound = true;

            icon.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('input') : null;
                if (input) openDatePicker(input);
            });
        });
    }

    function openDatePicker(input) {
        const picker = initAdminDatePicker();
        if (!picker) return;

        picker.__target = input;

        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }

        const value = input.value;
        if (value) {
            const date = new Date(value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }

        picker.open({ anchor: input });
    }

    function initDataTable($) {
        const columns = buildPositionColumns();

        positionTable = createDataTable({
            tableSelector: '#position-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            buttons: [
                {
                    text: '새 직책',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        openCreateModal();
                    }
                }
            ]
        });

        window.PositionTable = positionTable;
        window.EmployeePositionsTable = {
            instance: positionTable,
            reload: () => positionTable?.ajax.reload(null, false)
        };

        if (positionTable) {
            SearchForm({
                table: positionTable,
                apiList: API.LIST,
                tableId: 'position',
                defaultSearchField: 'position_name',
                dateOptions: DATE_OPTIONS
            });

            updateTableHeight(positionTable, '#position-table');
            bindTableHighlight('#position-table', positionTable);

            positionTable.on('draw', updatePositionCountFromTable);
            updatePositionCountFromTable();
        }
    }

    function buildPositionColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            width: '40px',
            className: 'reorder-handle no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(POSITION_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: config.noVis ? 'noVis text-center' : (field === 'level_rank' ? 'text-center' : ''),
                defaultContent: '',
                render: function (data, type) {
                    if (data == null) return '';
                    if (type !== 'display') return data;

                    if (field === 'is_active') {
                        return String(data) === '1'
                            ? '<span class="badge bg-success">활성</span>'
                            : '<span class="badge bg-secondary">비활성</span>';
                    }

                    return escapeHtml(data);
                }
            });
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#position-table tbody')
            .off('dblclick.positionEdit', 'tr')
            .on('dblclick.positionEdit', 'tr', function () {
                const data = positionTable.row(this).data();
                if (data) openEditModal(data);
            });

        $('#position-table tbody')
            .off('click.positionCellSearch', 'td')
            .on('click.positionCellSearch', 'td', function () {
                const cell = positionTable.cell(this);
                const idx = cell.index();
                if (!idx) return;

                const field = positionTable.column(idx.column).dataSrc();
                if (!field || field === 'is_active') return;

                const value = cell.data();
                const $first = $('#positionSearchConditions .search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(stripHtml(String(value ?? '')).trim());
            });
    }

    function bindModalEvents($) {
        $(document)
            .off('submit.positionForm', '#position-edit-form')
            .on('submit.positionForm', '#position-edit-form', async function (e) {
                e.preventDefault();
                await savePosition();
            });

        $(document)
            .off('click.positionDelete', '#position_edit_delete_btn')
            .on('click.positionDelete', '#position_edit_delete_btn', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $('#position_edit_id').val();
                if (!id) return;
                if (!confirm('\uC9C1\uCC45\uC744 \uC601\uAD6C\uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

                await deletePosition(id);
            });
    }

    function openCreateModal() {
        resetPositionForm();
        setPositionModalMode('create');
        positionModal?.show();
    }

    function openEditModal(row) {
        resetPositionForm();
        setPositionModalMode('edit');

        $('#position_edit_id').val(row.id || '');
        $('#position_edit_name').val(row.position_name || '');
        $('#position_edit_rank').val(row.level_rank ?? 0);
        $('#position_edit_description').val(row.description || '');
        $('#position_edit_is_active').prop('checked', String(row.is_active) === '1');

        positionModal?.show();
    }

    function setPositionModalMode(mode) {
        const isCreate = mode === 'create';
        $('#positionEditModal .modal-title').text(isCreate ? '직책 등록' : '직책 수정');
        $('#position_edit_delete_btn')
            .text('\uC601\uAD6C\uC0AD\uC81C')
            .toggle(!isCreate);
    }

    function resetPositionForm() {
        const form = document.getElementById('position-edit-form');
        form?.reset();

        $('#position_edit_id').val('');
        $('#position_edit_rank').val('0');
        $('#position_edit_is_active').prop('checked', true);
        setPositionModalMode('create');
    }

    async function savePosition() {
        const id = $('#position_edit_id').val();
        const name = String($('#position_edit_name').val() || '').trim();

        if (!name) {
            notify('warning', '직책명을 입력하세요.');
            return;
        }

        const fd = new FormData(document.getElementById('position-edit-form'));
        fd.set('action', id ? 'update' : 'create');
        fd.set('is_active', $('#position_edit_is_active').is(':checked') ? '1' : '0');

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', resolveSaveMessage(json?.message));
                return;
            }

            notify('success', '저장되었습니다.');
            positionModal?.hide();
            reloadPositionTable();
        } catch (err) {
            console.error('[positions.js] save failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    async function deletePosition(id) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', json?.message || '삭제 실패');
                return;
            }

            notify('success', '삭제되었습니다.');
            positionModal?.hide();
            reloadPositionTable();
        } catch (err) {
            console.error('[positions.js] delete failed:', err);
            notify('error', '삭제 중 오류가 발생했습니다.');
        }
    }

    function resolveSaveMessage(message) {
        if (message === 'duplicate') return '이미 등록된 직책명입니다.';
        if (message === 'empty') return '직책명을 입력하세요.';
        return message || '저장 실패';
    }

    function reloadPositionTable() {
        positionTable?.ajax.reload(() => {
            updatePositionCountFromTable();
            forceTableHeightSync(positionTable, '#position-table');
        }, false);
    }

    function updatePositionCountFromTable() {
        if (!positionTable?.page) return;

        const info = positionTable.page.info();
        const el = document.getElementById('positionCount');
        if (el) {
            el.textContent = `총 ${info?.recordsDisplay ?? 0}건`;
        }
    }

    function bindTableLayoutEvents(table, tableSelector) {
        if (!table) return;

        window.addEventListener('resize', () => {
            updateTableHeight(table, tableSelector);
        });

        document.addEventListener('sidebar:toggled', () => {
            updateTableHeight(table, tableSelector);

            setTimeout(() => {
                forceTableHeightSync(table, tableSelector);
            }, 340);
        });
    }

    function bindGlobalEvents() {
        if (globalBound) return;
        globalBound = true;

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            if (todayPicker && typeof todayPicker.close === 'function') {
                const pickerEl = document.getElementById('today-picker');
                if (pickerEl && !pickerEl.classList.contains('is-hidden')) {
                    e.preventDefault();
                    e.stopPropagation();
                    todayPicker.close();
                }
            }
        });
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('#positionSearchConditionsForm input[name="dateStart"]');
        const end = document.querySelector('#positionSearchConditionsForm input[name="dateEnd"]');

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }

        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
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

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stripHtml(value) {
        const div = document.createElement('div');
        div.innerHTML = value;
        return div.textContent || '';
    }
})();
