import { getCodeName } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';

export const API = Object.freeze({
    list: '/api/institution/human-resources/employment-contract/list',
    detail: '/api/institution/human-resources/employment-contract/detail',
    save: '/api/institution/human-resources/employment-contract/save',
    reorder: '/api/institution/human-resources/employment-contract/reorder',
    submit: '/api/institution/human-resources/employment-contract/submit',
    withdraw: '/api/institution/human-resources/employment-contract/withdraw',
    revise: '/api/institution/human-resources/employment-contract/revise',
    terminate: '/api/institution/human-resources/employment-contract/terminate',
    delete: '/api/institution/human-resources/employment-contract/delete',
    trash: '/api/institution/human-resources/employment-contract/trash',
    restore: '/api/institution/human-resources/employment-contract/restore',
    purge: '/api/institution/human-resources/employment-contract/purge',
    options: '/api/institution/human-resources/employment-contract/options',
    employeeSearch: '/api/settings/organization/employee/search-picker',
    projectSearch: '/api/settings/base-info/project/search-picker',
});

export const EDITABLE = new Set(['DRAFT']);
export const CONTRACT_TABLE_SETTINGS_KEY = 'datatable.settings.institution.employment-contract.main.v1';
export const CONTRACT_TABLE_SETTINGS_OPTIONS = Object.freeze({
    pageKey: 'institution.human_resources.employment_contracts',
    metaDomain: 'employment-contract',
});
export const FIXED_TERM_DETAIL_REQUIRED = new Set([
    'PROJECT_COMPLETION', 'TASK_COMPLETION', 'REPLACEMENT',
    'STATUTORY_EXCEPTION', 'OTHER', 'REVIEW_REQUIRED',
]);

export function recommendedProjectReason(project) {
    const projectName = project?.selectedOptions?.[0]?.textContent?.trim() || '';
    return projectName && project?.value ? `${projectName} 관련 업무 완료 시까지` : '';
}

export function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

export function badge(status, statusName = '') {
    const label = statusName || getCodeName('contract_status', status) || status || '-';
    return `<span class="badge text-bg-secondary employment-contract-status">${escapeHtml(label)}</span>`;
}

export function requestKey() {
    return window.crypto?.randomUUID?.() || `employment-contract-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function formatPickerDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
