import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { checkBusinessStatus } from '/public/assets/js/common/biz_api.js';
import * as NumberFormat from '/public/assets/js/common/format.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { initCodeSelectControls, getCodeName } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { CLIENT_QUICK_API, API, DATE_OPTIONS } from './api.js';
import { initExcelDataset, bindExcelEvents } from './excel.js';
import { bindTrashEvents } from './trash.js';
import { createClientFormModule } from './form.js';
import { createClientModalModule } from './modal.js';
import { createClientTableModule } from './table.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

const state = {
    clientTable: null,
    clientModal: null,
    excelModal: null,
};

const shortenFileName = (name, max = 20) => {
    if (!name) return '';
    const lastDot = name.lastIndexOf('.');
    if (lastDot <= 0) return name.length <= max ? name : `${name.substring(0, Math.max(1, max - 3))}...`;
    const ext = name.substring(lastDot);
    const base = name.substring(0, lastDot);
    if (name.length <= max) return name;
    return `${base.substring(0, Math.max(1, max - ext.length - 3))}...${ext}`;
};

const formModule = createClientFormModule({
    AdminPicker,
    NumberFormat,
    initCodeSelectControls,
    checkBusinessStatus,
    API,
});

function bindUIEvents() {
    document.getElementById('btnReplaceCert')?.addEventListener('click', () => {
        document.getElementById('modal_business_certificate')?.click();
    });
    document.getElementById('btnRemoveCert')?.addEventListener('click', () => {
        if (!confirm('사업자등록증 파일을 삭제하시겠습니까?')) return;
        document.getElementById('modal_business_certificate').value = '';
        document.getElementById('bizCertList') && (document.getElementById('bizCertList').dataset.original = '0', document.getElementById('bizCertList').innerHTML = '');
        document.getElementById('bizCertPreview') && (document.getElementById('bizCertPreview').innerHTML = '');
        document.getElementById('bizCertActions')?.style.setProperty('display', 'none');
        document.getElementById('certStatusIcon') && (document.getElementById('certStatusIcon').innerHTML = '');
        document.getElementById('delete_business_certificate').value = '1';
        document.getElementById('dropZoneTextBiz').innerHTML = '여기에 파일을 드래그하거나 클릭하여 선택하세요.<br>(PDF, JPG, PNG)';
    });
}

function initExternal() {
    window.KakaoAddress?.bind?.();
}

function bindGlobalEvents() {
    if (document.__clientGlobalBound === true) return;
    document.__clientGlobalBound = true;
    document.addEventListener('input', (event) => {
        const type = event.target?.dataset?.format;
        if (!type) return;
        if (type === 'biz') event.target.value = formModule.formatBizNumber(event.target.value);
        if (type === 'corp') event.target.value = formModule.formatCorpNumber(event.target.value);
        if (type === 'mobile') event.target.value = formModule.formatMobile(event.target.value);
        if (type === 'phone' || type === 'fax') event.target.value = formModule.formatPhone(event.target.value);
    });
}

function initBizCertUpload() {
    const drop = document.getElementById('dropZoneBiz');
    const input = document.getElementById('modal_business_certificate');
    const text = document.getElementById('dropZoneTextBiz');
    if (!drop || !input || !text) return;
    const render = (file) => {
        const ext = String(file.name || '').split('.').pop()?.toLowerCase() || '';
        if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) return formModule.notify('warning', '사업자등록증은 PDF, JPG, PNG 파일만 업로드할 수 있습니다.');
        if (Number(file.size || 0) > 10 * 1024 * 1024) return formModule.notify('warning', '사업자등록증 파일은 10MB 이하만 업로드할 수 있습니다.');
        const title = drop.dataset.original === '1' ? '교체 파일' : '선택 파일';
        const message = drop.dataset.original === '1' ? '저장 시 기존 사업자등록증이 교체됩니다.' : '저장 시 사업자등록증이 등록됩니다.';
        text.innerHTML = `파일 <strong>${title} (${shortenFileName(file.name)})</strong><br><span class="text-primary">${message}</span>`;
    };
    drop.addEventListener('click', () => input.click());
    input.addEventListener('change', (event) => event.target.files?.[0] && render(event.target.files[0]));
    drop.addEventListener('dragover', (event) => event.preventDefault());
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (!file) return;
        input.files = event.dataTransfer.files;
        render(file);
    });
}

function initRrnUpload() {
    const drop = document.getElementById('dropZoneRrn');
    const input = document.getElementById('modal_rrn_image');
    const text = document.getElementById('dropZoneTextRrn');
    if (!drop || !input || !text) return;
    const render = (file) => {
        const shortName = file.name.length > 20 ? `${file.name.substring(0, 17)}...` : file.name;
        text.innerHTML = `파일 <strong>${shortName}</strong><br><span class="text-primary">저장 시 신분증이 등록됩니다.</span>`;
    };
    drop.addEventListener('click', () => input.click());
    input.addEventListener('change', (event) => event.target.files?.[0] && render(event.target.files[0]));
    drop.addEventListener('dragover', (event) => event.preventDefault());
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (!file) return;
        input.files = event.dataTransfer.files;
        render(file);
    });
}

function initBankFileUpload() {
    const drop = document.getElementById('bankCopyUpload');
    const input = document.getElementById('modal_bank_file');
    const text = document.getElementById('bankCopyText');
    if (!drop || !input || !text) return;
    if (!drop.dataset.original) drop.dataset.original = '0';
    const render = (file) => {
        const message = drop.dataset.original === '1' ? '저장 시 기존 통장사본이 교체됩니다.' : '저장 시 통장사본이 등록됩니다.';
        text.innerHTML = `파일 <strong>통장사본</strong><br>(${shortenFileName(file.name, 20)})<br><span class="text-primary">${message}</span>`;
    };
    drop.addEventListener('click', () => input.click());
    input.addEventListener('change', (event) => event.target.files?.[0] && render(event.target.files[0]));
    drop.addEventListener('dragover', (event) => event.preventDefault());
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (!file) return;
        input.files = event.dataTransfer.files;
        render(file);
    });
}

function renderCompanyNameHistory(rows = []) {
    const card = document.getElementById('clientCompanyHistoryCard');
    const list = document.getElementById('clientCompanyHistoryList');
    if (!card || !list) return;
    const history = Array.isArray(rows) ? rows : [];
    if (!history.length) {
        card.classList.add('d-none');
        list.innerHTML = '';
        return;
    }
    card.classList.remove('d-none');
    list.innerHTML = history.map((row) => `
        <div class="d-flex justify-content-between align-items-center gap-2 border-bottom py-1" data-history-id="${formModule.escapeHtml(row.id || '')}">
            <span>${formModule.escapeHtml(row.old_company_name || '')} -> ${formModule.escapeHtml(row.new_company_name || '')}</span>
            <span class="d-inline-flex align-items-center gap-2 ms-auto text-end">
                <span class="text-muted">${formModule.escapeHtml(row.changed_at || '')}</span>
                <button type="button" class="btn btn-link p-0 text-danger text-decoration-none client-company-history-delete" style="font-size: 0.75rem;">-삭제</button>
            </span>
        </div>
    `).join('');
    list.querySelectorAll('.client-company-history-delete').forEach((button) => {
        button.addEventListener('click', deleteCompanyNameHistoryRow);
    });
}

async function deleteCompanyNameHistoryRow(event) {
    const row = event.currentTarget.closest('[data-history-id]');
    const historyId = row?.dataset.historyId || '';
    if (!historyId || !confirm('상호 변경 이력을 삭제하시겠습니까?')) return;
    event.currentTarget.disabled = true;
    try {
        const res = await fetch(API.COMPANY_NAME_HISTORY_DELETE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: historyId }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || '상호 변경 이력 삭제에 실패했습니다.');
        row.remove();
        const list = document.getElementById('clientCompanyHistoryList');
        if (list && !list.querySelector('[data-history-id]')) {
            list.innerHTML = '';
            document.getElementById('clientCompanyHistoryCard')?.classList.add('d-none');
        }
        formModule.notify('success', json.message || '상호 변경 이력이 삭제되었습니다.');
    } catch (error) {
        console.error(error);
        event.currentTarget.disabled = false;
        formModule.notify('error', error.message || '상호 변경 이력 삭제 중 오류가 발생했습니다.');
    }
}

function renderBusinessCertificate(pathValue) {
    const list = document.getElementById('bizCertList');
    const help = document.getElementById('bizCertHelp');
    if (!list) return;
    list.innerHTML = '';
    if (!pathValue) {
        list.dataset.original = '0';
        return;
    }
    if (help) help.style.display = 'none';
    const fileName = pathValue.split('/').pop();
    const path = encodeURIComponent(pathValue);
    list.dataset.original = '1';
    list.innerHTML = `<div class="file-item"><span>파일 <strong>사업자등록증</strong> (${fileName})</span><div class="file-actions"><a href="/api/file/preview?path=${path}" target="_blank" class="file-preview">미리보기</a><span class="file-divider">|</span><a href="javascript:void(0)" id="btnDeleteCert" class="file-delete">삭제</a></div></div>`;
    document.getElementById('btnDeleteCert')?.addEventListener('click', () => {
        if (!confirm('사업자등록증을 삭제하시겠습니까?')) return;
        document.getElementById('delete_business_certificate').value = '1';
        document.getElementById('modal_business_certificate').value = '';
        list.dataset.original = '0';
        list.innerHTML = `<div class="file-item"><span>파일 <strong>사업자등록증</strong> (${fileName})</span><div class="file-status text-danger">사업자등록증이 삭제됩니다. 저장 후 반영됩니다.</div></div>`;
    });
}

function renderRrnImage(pathValue) {
    const list = document.getElementById('rrnImageList');
    if (!list) return;
    list.innerHTML = '';
    if (!pathValue) return;
    const fileName = pathValue.split('/').pop();
    const path = encodeURIComponent(pathValue);
    list.innerHTML = `<div class="file-item"><span>파일 <strong>신분증</strong> (${fileName})</span><div class="file-actions"><a href="/api/file/preview?path=${path}" target="_blank">미리보기</a><span class="file-divider">|</span><a href="javascript:void(0)" id="btnDeleteRrn">삭제</a></div></div>`;
    document.getElementById('btnDeleteRrn')?.addEventListener('click', () => {
        if (!confirm('신분증을 삭제하시겠습니까?')) return;
        document.getElementById('modal_rrn_image').value = '';
        document.getElementById('delete_rrn_image').value = '1';
        list.innerHTML = `<div class="file-item"><span>파일 <strong>신분증</strong> (${fileName})</span><div class="file-status text-danger">신분증이 삭제됩니다. 저장 후 반영됩니다.</div></div>`;
    });
}

function renderBankFile(pathValue) {
    const text = document.getElementById('bankCopyText');
    const drop = document.getElementById('bankCopyUpload');
    if (!text) return;
    if (!pathValue) {
        if (drop) drop.dataset.original = '0';
        text.innerHTML = '여기에 파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)';
        return;
    }
    if (drop) drop.dataset.original = '1';
    const path = encodeURIComponent(pathValue);
    const fileName = pathValue.split('/').pop();
    text.innerHTML = `<div class="file-status"><div class="upload-guide">여기에 파일을 드래그하거나 클릭하여 업로드<br>(PDF, JPG, PNG)</div><div class="file-line">파일 <strong>통장사본 등록됨</strong></div><div class="file-links"><a href="/api/file/preview?path=${path}" id="btnOpenBankCopy" class="file-link-open" target="_blank">미리보기</a><span class="file-divider">|</span><a href="javascript:void(0)" id="btnDeleteBankCopy" class="file-link-delete">삭제</a></div></div>`;
    document.getElementById('btnDeleteBankCopy')?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!confirm('통장사본을 삭제하시겠습니까?')) return;
        document.getElementById('delete_bank_file').value = '1';
        document.getElementById('bankCopyUpload').dataset.original = '0';
        document.getElementById('modal_bank_file').value = '';
        text.innerHTML = `파일 <strong>통장사본</strong> (${fileName})<br><div class="file-status text-danger">통장사본이 삭제됩니다.<br>저장 후 반영됩니다.</div>`;
    });
}

const modalModule = createClientModalModule({
    API,
    CLIENT_QUICK_API,
    NumberFormat,
    formModule,
    state,
    renderers: {
        renderCompanyNameHistory,
        renderBusinessCertificate,
        renderRrnImage,
        renderBankFile,
    },
});

const tableModule = createClientTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    NumberFormat,
    getCodeName,
    API,
    DATE_OPTIONS,
    modalModule,
    formModule,
    state,
});

export const openClientQuickCreate = modalModule.openClientQuickCreate;
export const initClientQuickCreateButtons = modalModule.initClientQuickCreateButtons;

async function initClientPage($) {
    modalModule.initModal();
    formModule.bindAdminDateInputs();
    initBizCertUpload();
    initRrnUpload();
    initBankFileUpload();
    await tableModule.initDataTable();
    state.clientTable?.one('draw.dt', () => {
        void modalModule.preloadClientModalControls().then(() => {
            formModule.applyClientOptionalCodeSelects(document.getElementById('clientModal'));
            state.clientTable?.rows().invalidate('data').draw(false);
        });
    });
    state.clientTable?.one('draw.dt', () => { void initExcelDataset(API); });
    initExternal();
    tableModule.bindTableEvents($);
    modalModule.bindModalEvents($);
    formModule.bindBizStatusButton();
    formModule.bindRrnInputEvents($);
    bindUIEvents();
    bindExcelEvents(() => state.clientTable);
    bindTrashEvents({
        getClientTable: () => state.clientTable,
        clientColumnMap: tableModule.CLIENT_COLUMN_MAP,
    });
    bindGlobalEvents();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('client-table')) return;
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }
    void initClientPage(window.jQuery).catch(() => {
        window.AppCore?.notify?.('error', '거래처 목록을 불러오는 중 오류가 발생했습니다.');
    });
});
