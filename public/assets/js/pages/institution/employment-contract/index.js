import { getCodeOptions } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { createEmploymentContractTable } from '/public/assets/js/pages/institution/employment-contract/table.js';
import { API, badge, escapeHtml, requestKey } from '/public/assets/js/pages/institution/employment-contract/shared.js';

const trashElement = document.getElementById('employmentContractTrashModal');
const trashModal = trashElement && window.bootstrap ? new window.bootstrap.Modal(trashElement) : null;
let table = null;
let modalRuntimePromise = null;
let trashRuntimePromise = null;

function loadModalRuntime() {
    if (!modalRuntimePromise) {
        modalRuntimePromise = import('/public/assets/js/pages/institution/employment-contract/modal-runtime.js')
            .catch(error => {
                modalRuntimePromise = null;
                throw error;
            });
    }
    return modalRuntimePromise;
}

function loadTrashRuntime() {
    if (!trashRuntimePromise) {
        trashRuntimePromise = import('/public/assets/js/components/trash-manager.js').catch(error => {
            trashRuntimePromise = null;
            throw error;
        });
    }
    return trashRuntimePromise;
}

window.TrashColumns = window.TrashColumns || {};
window.TrashColumns['employment-contract'] = (row = {}) => `
    <td>${escapeHtml(row.contract_no)}</td>
    <td>${escapeHtml(row.employee_name)}</td>
    <td>${badge(row.contract_status, row.contract_status_name)}</td>
    <td>${escapeHtml(row.deleted_by_name || row.deleted_by || '-')}</td>
    <td>${escapeHtml(row.deleted_at || '-')}</td>
    <td class="text-nowrap">
        <button type="button" class="btn btn-outline-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복구</button>
        <button type="button" class="btn btn-outline-danger btn-sm btn-purge" data-id="${escapeHtml(row.id)}">완전삭제</button>
    </td>`;

document.addEventListener('trash:changed', event => {
    if (event.detail?.type === 'employment-contract') table?.ajax.reload(null, false);
});
trashElement?.addEventListener('show.bs.modal', () => {
    void loadTrashRuntime().catch(error => window.alert(error.message));
});

async function boot() {
    await Promise.all([
        'EMPLOYMENT_CONTRACT_TYPE',
        'EMPLOYMENT_CONTRACT_PERIOD_TYPE',
        'EMPLOYMENT_CONTRACT_STATUS',
    ].map(group => getCodeOptions(group)));

    table = await createEmploymentContractTable({
        api: API,
        badge,
        escapeHtml,
        requestKey,
        trashModal,
        onOpen: id => loadModalRuntime()
            .then(runtime => runtime.openDetail(id, { table }))
            .catch(error => window.alert(error.message)),
        onNew: () => loadModalRuntime()
            .then(runtime => runtime.openCreate({ table }))
            .catch(error => window.alert(error.message)),
    });
}

void boot();
