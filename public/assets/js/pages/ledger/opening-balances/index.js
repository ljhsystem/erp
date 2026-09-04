import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { actorColumn } from '/public/assets/js/common/actor.js';

const API = {
    LIST: '/api/ledger/opening-balance/list', DETAIL: '/api/ledger/opening-balance/detail', OPTIONS: '/api/ledger/opening-balance/options',
    ACCOUNTS: '/api/ledger/account/posting', POLICIES: '/api/account/sub-accounts', SAVE: '/api/ledger/opening-balance/save', DELETE: '/api/ledger/opening-balance/delete',
    REQUEST: '/api/ledger/opening-balance/request-review', CANCEL_REQUEST: '/api/ledger/opening-balance/cancel-review',
    REVIEW: '/api/ledger/opening-balance/review', CANCEL_REVIEW: '/api/ledger/opening-balance/cancel-reviewed', POST: '/api/ledger/opening-balance/post', REVERSE: '/api/ledger/opening-balance/reverse'
};
const REF_API = {
    CLIENT: '/api/settings/base-info/client/list', PROJECT: '/api/settings/base-info/project/list', EMPLOYEE: '/api/settings/organization/employee/list',
    ACCOUNT: '/api/settings/base-info/bank-account/list', BANK_ACCOUNT: '/api/settings/base-info/bank-account/list', CARD: '/api/settings/base-info/card/list'
};
const REF_LABEL = { CLIENT:'거래처',PROJECT:'프로젝트',EMPLOYEE:'직원',ACCOUNT:'계좌',BANK_ACCOUNT:'계좌',CARD:'카드' };
const STATUS = { DRAFT: '작성', REVIEW_REQUESTED: '검토요청', REVIEWED: '검토완료', POSTED: '전기완료', CLOSED: '마감' };
let table, modal, accounts = [], companies = [], currentStatus = 'DRAFT';
const refOptions = {};
const $ = (selector) => document.querySelector(selector);
const money = (value) => `${Math.round(Number(value) || 0).toLocaleString('ko-KR')}원`;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));

async function request(url, options = {}) {
    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) throw new Error(json.message || '처리 중 오류가 발생했습니다.');
    return json;
}
function notify(type, message) {
    if (window.showToast) window.showToast(type, message); else alert(message);
}
function statusBadge(value) { return `<span class="badge text-bg-${value === 'POSTED' ? 'success' : value === 'DRAFT' ? 'secondary' : 'primary'}">${STATUS[value] || value}</span>`; }

async function loadOptions() {
    const [optionJson, accountJson] = await Promise.all([request(API.OPTIONS), request(API.ACCOUNTS)]);
    companies = optionJson.data?.companies || [];
    accounts = accountJson.data || [];
    $('#openingCompany').innerHTML = '<option value="">선택</option>' + companies.map((row) => `<option value="${escapeHtml(row.id)}">${escapeHtml(row.name)}</option>`).join('');
}
function accountOptions(selected = '') {
    return '<option value="">선택</option>' + accounts.map((row) => `<option value="${escapeHtml(row.id)}" ${String(row.id) === String(selected) ? 'selected' : ''}>${escapeHtml(row.account_code)} ${escapeHtml(row.account_name)}</option>`).join('');
}
function rowsFrom(payload) { return Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []; }
function refText(row) { return row.text || row.client_name || row.company_name || row.project_name || row.construction_name || row.employee_name || row.account_name || row.bank_name || row.card_name || row.name || row.id; }
async function loadRefOptions(type) {
    if (refOptions[type]) return refOptions[type];
    if (!REF_API[type]) return [];
    const rows = rowsFrom(await request(REF_API[type]));
    refOptions[type] = rows.map((row) => ({id: String(row.id ?? row.value ?? ''), text: String(refText(row) || '')})).filter((row) => row.id);
    return refOptions[type];
}
async function renderRefs(tr, selectedRefs = []) {
    const accountId = tr.querySelector('.line-account').value, container = tr.querySelector('.line-refs');
    container.innerHTML = accountId ? '<span class="text-muted small">불러오는 중...</span>' : '<span class="text-muted small">계정 선택</span>';
    if (!accountId) return;
    const policies = rowsFrom(await request(`${API.POLICIES}?account_id=${encodeURIComponent(accountId)}`));
    container.innerHTML = '';
    if (!policies.length) { container.innerHTML = '<span class="text-muted small">해당 없음</span>'; return; }
    for (const policy of policies) {
        const type = String(policy.ref_target || policy.sub_account_type || '').toUpperCase();
        const selected = selectedRefs.find((ref) => String(ref.ref_target).toUpperCase() === type)?.ref_id || '';
        const options = await loadRefOptions(type); const wrap = document.createElement('div');
        wrap.innerHTML = `<label class="form-label">${escapeHtml(REF_LABEL[type] || type)}${Number(policy.is_required) ? ' *' : ''}</label><select class="form-select form-select-sm line-ref" data-ref-target="${escapeHtml(type)}"><option value="">선택</option>${options.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === String(selected) ? 'selected' : ''}>${escapeHtml(item.text)}</option>`).join('')}</select>`;
        container.append(wrap);
    }
}
function addLine(line = {}) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td class="text-center line-index"></td><td><select class="form-select form-select-sm line-account">${accountOptions(line.account_id)}</select></td><td class="line-refs"></td><td><input class="form-control form-control-sm line-summary" maxlength="255" value="${escapeHtml(line.line_summary || '')}"></td><td><input class="form-control form-control-sm amount-input line-debit" inputmode="numeric" value="${Number(line.debit || 0)}"></td><td><input class="form-control form-control-sm amount-input line-credit" inputmode="numeric" value="${Number(line.credit || 0)}"></td><td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger p-1 line-delete" title="행 삭제"><i class="bi bi-trash"></i></button></td>`;
    $('#openingLines').append(tr);
    tr.querySelectorAll('.amount-input').forEach((input) => input.addEventListener('input', calculate));
    tr.querySelector('.line-delete').addEventListener('click', () => { tr.remove(); renumber(); calculate(); });
    tr.querySelector('.line-account').addEventListener('change', () => { void renderRefs(tr); });
    void renderRefs(tr, line.refs || []);
    renumber(); calculate();
}
function renumber() { [...$('#openingLines').rows].forEach((row, index) => { row.querySelector('.line-index').textContent = index + 1; }); }
function numberValue(input) { return Number(String(input.value || '').replace(/,/g, '')) || 0; }
function calculate() {
    let debit = 0, credit = 0;
    [...$('#openingLines').rows].forEach((row) => { debit += numberValue(row.querySelector('.line-debit')); credit += numberValue(row.querySelector('.line-credit')); });
    $('#openingDebitTotal').textContent = money(debit); $('#openingCreditTotal').textContent = money(credit); $('#openingDifference').textContent = money(debit - credit);
    $('#openingDifference').classList.toggle('text-danger', debit !== credit);
}
function syncDate() { const year = Number($('#openingYear').value); $('#openingDate').value = year >= 1900 ? `${year - 1}-12-31` : ''; }
function resetForm() {
    $('#openingBalanceId').value = ''; $('#openingCompany').value = companies[0]?.id || ''; $('#openingYear').value = new Date().getFullYear(); $('#openingNote').value = '';
    $('#openingLines').innerHTML = ''; currentStatus = 'DRAFT'; syncDate(); addLine(); addLine(); syncActions();
}
function syncActions() {
    const editable = currentStatus === 'DRAFT';
    $('#openingBalanceStatus').innerHTML = `상태: ${statusBadge(currentStatus)}`;
    ['#openingCompany','#openingYear','#openingNote','#btnAddOpeningLine','#btnSaveOpening'].forEach((selector) => { $(selector).disabled = !editable; });
    $('#openingLines').querySelectorAll('input,select,button').forEach((element) => { element.disabled = !editable; });
    $('#btnDeleteOpening').disabled = !$('#openingBalanceId').value || !editable;
    const transitions = { DRAFT: ['검토요청', API.REQUEST], REVIEW_REQUESTED: ['검토완료', API.REVIEW], REVIEWED: ['전기', API.POST] };
    const config = transitions[currentStatus]; const button = $('#btnOpeningTransition');
    button.classList.toggle('d-none', !config); if (config) { button.textContent = config[0]; button.dataset.api = config[1]; }
    const backTransitions = { REVIEW_REQUESTED: ['검토요청 취소', API.CANCEL_REQUEST], REVIEWED: ['검토완료 취소', API.CANCEL_REVIEW], POSTED: ['취소전표 생성', API.REVERSE] };
    const backConfig = backTransitions[currentStatus], backButton = $('#btnOpeningBackTransition');
    backButton.classList.toggle('d-none', !backConfig); if (backConfig) { backButton.textContent = backConfig[0]; backButton.dataset.api = backConfig[1]; }
}
async function openDetail(id) {
    const json = await request(`${API.DETAIL}?id=${encodeURIComponent(id)}`), row = json.data;
    $('#openingBalanceId').value = row.id; $('#openingCompany').value = row.company_id; $('#openingYear').value = row.fiscal_year; $('#openingDate').value = row.opening_date; $('#openingNote').value = row.note || '';
    $('#openingLines').innerHTML = ''; (row.lines || []).forEach(addLine); currentStatus = row.status || 'DRAFT'; syncActions(); modal.show();
}
function payload() {
    return { id: $('#openingBalanceId').value, company_id: $('#openingCompany').value, fiscal_year: Number($('#openingYear').value), note: $('#openingNote').value,
        lines: [...$('#openingLines').rows].map((row, index) => ({ line_no: index + 1, account_id: row.querySelector('.line-account').value, line_summary: row.querySelector('.line-summary').value, debit: numberValue(row.querySelector('.line-debit')), credit: numberValue(row.querySelector('.line-credit')), refs: [...row.querySelectorAll('.line-ref')].filter((select) => select.value).map((select) => ({ref_target: select.dataset.refTarget, ref_id: select.value})) })) };
}
async function post(url, body) { return request(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) }); }
async function reload() { table?.ajax.reload(null, false); }

document.addEventListener('DOMContentLoaded', async () => {
    modal = bootstrap.Modal.getOrCreateInstance($('#openingBalanceModal'));
    await loadOptions();
    table = await createDataTable({ tableSelector: '#opening-balance-table', api: API.LIST, columns: [
        { data: 'fiscal_year', title: '회계연도' }, { data: 'company_name', title: '회사' }, { data: 'opening_date', title: '기준일' },
        { data: 'voucher_no', title: '기초전표' }, { data: 'debit_total', title: '차변합계', className: 'text-end', render: money },
        { data: 'credit_total', title: '대변합계', className: 'text-end', render: money }, { data: 'status', title: '상태', render: statusBadge },
        actorColumn('updated_by', '수정자')
    ], defaultOrder: [[0, 'desc']], pageLength: 50, autoWidth: false, searchTableId: 'ledgerOpeningBalance',
        tableSettings: { pageKey: 'ledger.settings.opening_balances', tableKey: 'opening-balance-table', metaDomain: 'opening-balance', tableLabel: '기초금액', title: '기초금액 테이블 설정', defaultVisibleColumns: ['fiscal_year','company_name','opening_date','voucher_no','debit_total','credit_total','status','updated_by_name'] },
        buttons: [{ text: '신규등록', className: 'btn btn-warning btn-sm', action: () => { resetForm(); modal.show(); } }]
    });
    $('#opening-balance-table tbody').addEventListener('dblclick', (event) => { const row = event.target.closest('tr'); const data = table.row(row).data(); if (data?.id) openDetail(data.id).catch((error) => notify('error', error.message)); });
    $('#btnAddOpeningLine').addEventListener('click', () => addLine()); $('#openingYear').addEventListener('input', syncDate);
    $('#btnSaveOpening').addEventListener('click', async () => { try { const json = await post(API.SAVE, payload()); notify('success', json.message); await reload(); await openDetail(json.data.id); } catch (error) { notify('error', error.message); } });
    $('#btnDeleteOpening').addEventListener('click', async () => { if (!confirm('기초금액을 삭제하시겠습니까?')) return; try { const json = await post(API.DELETE, {id: $('#openingBalanceId').value}); notify('success', json.message); modal.hide(); reload(); } catch (error) { notify('error', error.message); } });
    $('#btnOpeningTransition').addEventListener('click', async (event) => { const actionApi = event.currentTarget.dataset.api; try { const json = await post(actionApi, {id: $('#openingBalanceId').value}); notify('success', json.message); await reload(); await openDetail($('#openingBalanceId').value); } catch (error) { notify('error', error.message); } });
    $('#btnOpeningBackTransition').addEventListener('click', async (event) => { const actionApi = event.currentTarget.dataset.api; if (actionApi === API.REVERSE && !confirm('취소전표를 생성하시겠습니까?')) return; try { const json = await post(actionApi, {id: $('#openingBalanceId').value}); notify('success', json.message); await reload(); if (actionApi !== API.REVERSE) await openDetail($('#openingBalanceId').value); } catch (error) { notify('error', error.message); } });
});
