import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { openClientQuickCreate } from '/public/assets/js/pages/main/settings/base/client.js';
import { WORK_TEAM_API, WORK_TEAM_COLUMN_MAP, WORK_TEAM_DATE_OPTIONS } from './api.js';
import { createWorkTeamModalModule } from './modal.js';
import { createWorkTeamTableModule } from './table.js';
import { createWorkTeamTrashModule } from './trash.js';
import { createWorkTeamExcelModule } from './excel.js';
import { createWorkTeamQuickModal } from './quick-modal.js';
import { initCodeSelectControls } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { escapeHtml } from './form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';
import '/public/assets/js/common/core/AppAjax.js';

window.AdminPicker = AdminPicker;

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
}

let tableModule;
let modalModule;
let trashModule;
let excelModule;
let quickModalModule;

async function updateWorkTeamActive(teamId, active, toggleEl) {
    try {
        const data = await modalModule.fetchDetail(teamId);
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            formData.set(key, value ?? '');
        });
        formData.set('id', teamId);
        formData.set('is_active', active ? '1' : '0');

        if (toggleEl) toggleEl.disabled = true;

        await window.AppAjax.fetchJson(WORK_TEAM_API.SAVE, {
            method: 'POST',
            body: formData
        });

        tableModule.reloadTable();
        notify('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
    } catch (error) {
        console.error(error);
        if (toggleEl) toggleEl.checked = !active;
        notify('error', error.message || '상태 변경에 실패했습니다.');
    } finally {
        if (toggleEl) toggleEl.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }

    modalModule = createWorkTeamModalModule({
        AdminPicker,
        api: WORK_TEAM_API,
        notify,
        openClientQuickCreate: (defaultName = '') => {
            void openClientQuickCreate({
                select: document.getElementById('modal-work-team-team-leader-client-id'),
                initialValues: { client_name: defaultName },
                onSuccess() {
                    notify('success', '거래처가 등록되었습니다.');
                },
                getOptionText(values) {
                    return values.client_name || '';
                }
            });
        },
        reloadTable() {
            tableModule?.reloadTable();
        }
    });

    quickModalModule = createWorkTeamQuickModal({
        api: WORK_TEAM_API,
        notify,
        openDetail(initialValues) {
            modalModule.openCreateModal({ initialValues });
            void initCodeSelectControls(document.getElementById('workTeamModal'));
        },
        onSuccess() {
            tableModule?.reloadTable();
        },
    });

    tableModule = createWorkTeamTableModule({
        api: WORK_TEAM_API,
        columnMap: WORK_TEAM_COLUMN_MAP,
        dateOptions: WORK_TEAM_DATE_OPTIONS,
        createDataTable,
        bindTableHighlight,
        bindRowReorder,
        SearchForm,
        notify,
        escapeHtml,
        onOpenCreateModal() {
            quickModalModule.open();
        },
        onOpenEditRow(row) {
            modalModule.openEditModalByRow(row);
        },
        onToggleActive(teamId, active, toggleEl) {
            updateWorkTeamActive(teamId, active, toggleEl);
        },
        onOpenTrashModal() {
            trashModule.openTrashModal();
        },
        onOpenExcelModal() {
            modalModule.getExcelModal()?.show();
        }
    });

    trashModule = createWorkTeamTrashModule({
        api: WORK_TEAM_API,
        escapeHtml,
        reloadTable() {
            tableModule.reloadTable();
        }
    });

    excelModule = createWorkTeamExcelModule({
        reloadTable() {
            tableModule.reloadTable();
        }
    });

    modalModule.initModal();
    await initCodeSelectControls(document.getElementById('workTeamModal'));
    modalModule.initAdminDatePicker();
    modalModule.initExcelDataset();
    await tableModule.initDataTable();
    tableModule.getTable()?.one('draw.dt', () => { void excelModule.initExcelDataset(); });
    tableModule.bindTableEvents();
    modalModule.bindModalEvents();
    modalModule.preloadModalControls();
    modalModule.bindAdminDateInputs();
    modalModule.bindDateIconPicker();
    excelModule.bindExcelEvents();
    trashModule.bindTrashEvents();
});
