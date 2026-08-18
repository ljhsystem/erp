import { notify } from '/public/assets/js/common/notification.js';
import { API, getJson, postJson } from './api.js';
import { createPaymentScheduleTable, escapeHtml, money } from './table.js';
import '/public/assets/js/components/trash-manager.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

const filter = document.getElementById('paymentScheduleFilter');
const form = document.getElementById('paymentScheduleForm');
const allocationForm = document.getElementById('paymentAllocationForm');
const formModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentScheduleFormModal'));
const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentScheduleDetailModal'));
let current = null;

const query = () => new URLSearchParams(new FormData(filter));
let table = await createPaymentScheduleTable(`${API.list}?${query()}`, openDetail);

window.TrashColumns = window.TrashColumns || {};
window.TrashColumns.paymentSchedule = (row = {}) => `<td>${escapeHtml(row.payment_due_date)}</td><td>${escapeHtml(row.source_name)}</td><td>${escapeHtml(row.client_name || '-')}</td><td class="text-end">${money(row.scheduled_amount)}</td><td>${escapeHtml(row.deleted_at || '-')}</td><td><button class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복구</button></td>`;

function setTableUrl() {
    table.ajax.url(`${API.list}?${query()}`).load();
}

window.addEventListener('payment-schedule-loaded', ({ detail }) => {
    const summary = detail.summary || {};
    document.querySelectorAll('[data-summary]').forEach((element) => { element.textContent = money(summary[element.dataset.summary]); });
    document.getElementById('paymentScheduleCount').textContent = `${Number(detail.recordsFiltered || 0).toLocaleString('ko-KR')}건`;
});

async function openDetail(id) {
    try {
        const data = await getJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        current = data.schedule;
        document.getElementById('paymentScheduleDetail').innerHTML = [
            ['지급예정일', current.payment_due_date_label], ['지급상태', current.payment_status_label],
            ['원천', current.source_name], ['거래처', current.client_name || '-'],
            ['프로젝트', current.project_name || '-'], ['담당자', current.assignee_name || '-'],
            ['지급계좌', current.payment_bank_account_name || '-'], ['지급예정액', `${money(current.scheduled_amount)}원`],
            ['기지급액', `${money(current.paid_amount)}원`], ['잔여액', `${money(current.remaining_amount)}원`],
            ['보류사유', current.hold_reason || '-'], ['메모', current.memo || '-'],
        ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
        allocationForm.schedule_id.value = current.id;
        allocationForm.amount.value = Number(current.remaining_amount || 0).toFixed(2);
        document.getElementById('paymentAllocationRows').innerHTML = (data.allocations || []).map((row) => `<tr><td>${escapeHtml(row.transaction_datetime)}</td><td>${escapeHtml([row.bank_name,row.account_name].filter(Boolean).join(' ') || '-')}</td><td>${escapeHtml(row.description || '-')}</td><td class="text-end">${money(row.withdraw_amount)}</td><td class="text-end">${money(row.amount)}</td><td>${escapeHtml(row.created_at)}</td><td><button class="btn btn-outline-danger btn-sm allocation-release" data-link-id="${escapeHtml(row.id)}">해제</button></td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">연결된 실제 지급이 없습니다.</td></tr>';
        document.getElementById('paymentScheduleHistory').innerHTML = (data.histories || []).map((row) => `<article><div><strong>${escapeHtml(row.change_summary)}</strong><span>${escapeHtml(row.acted_at)} · ${escapeHtml(row.processed_by_name || '-')}</span></div>${row.reason ? `<p>${escapeHtml(row.reason)}</p>` : ''}<details><summary>변경값 보기</summary><pre>${escapeHtml(JSON.stringify({ before: row.before, after: row.after }, null, 2))}</pre></details></article>`).join('') || '<p class="text-muted">업무이력이 없습니다.</p>';
        document.getElementById('paymentScheduleHold').textContent = Number(current.is_on_hold) === 1 ? '보류 해제' : '지급 보류';
        detailModal.show();
        await loadWithdrawals();
    } catch (error) { notify('error', error.message); }
}

async function loadWithdrawals(term = '') {
    if (!current) return;
    const rows = await getJson(`${API.withdrawals}?q=${encodeURIComponent(term)}&limit=50`);
    allocationForm.evidence_id.innerHTML = '<option value="">출금 원본을 선택하세요.</option>' + rows.map((row) => `<option value="${escapeHtml(row.id)}" data-available="${escapeHtml(row.available_amount)}">${escapeHtml(row.transaction_datetime)} · ${escapeHtml(row.account_name || '-')} · ${escapeHtml(row.description || '-')} · 가용 ${money(row.available_amount)}원</option>`).join('');
}

function openForm(row = null) {
    if (!row) return;
    form.reset();
    form.id.value = row?.id || '';
    ['source_type','source_id','source_line_key','payment_due_date','scheduled_amount','client_id','project_id','assignee_id','payment_bank_account_id','memo'].forEach((key) => { if (row && form.elements[key]) form.elements[key].value = row[key] ?? ''; });
    document.getElementById('paymentSourcePicker').value = row.source_name || '';
    form.querySelector('.modal-title').textContent = '지급예정 수정';
    formModal.show();
}

filter.addEventListener('submit', (event) => { event.preventDefault(); setTableUrl(); });
filter.addEventListener('reset', () => setTimeout(setTableUrl, 0));
form.addEventListener('submit', async (event) => { event.preventDefault(); try { await postJson(API.save, Object.fromEntries(new FormData(form))); formModal.hide(); table.ajax.reload(); notify('success', '저장되었습니다.'); } catch (error) { notify('error', error.message); } });
allocationForm.addEventListener('submit', async (event) => { event.preventDefault(); try { await postJson(API.allocate, Object.fromEntries(new FormData(allocationForm))); await openDetail(current.id); table.ajax.reload(null, false); notify('success', '실제 지급을 연결했습니다.'); } catch (error) { notify('error', error.message); } });
document.getElementById('paymentAllocationRows').addEventListener('click', async (event) => { const button = event.target.closest('.allocation-release'); if (!button || !confirm('이 지급 연결을 해제하시겠습니까?')) return; try { await postJson(API.releaseAllocation, { schedule_id: current.id, link_id: button.dataset.linkId, reason: '화면에서 연결 해제' }); await openDetail(current.id); table.ajax.reload(null, false); } catch (error) { notify('error', error.message); } });
document.getElementById('paymentScheduleRefresh').addEventListener('click', () => table.ajax.reload());
document.getElementById('paymentScheduleExcel').addEventListener('click', () => { location.href = `${API.excel}?${query()}`; });
document.getElementById('paymentSchedulePrint').addEventListener('click', () => print());
document.getElementById('paymentScheduleEdit').addEventListener('click', () => { detailModal.hide(); openForm(current); });
document.getElementById('paymentScheduleHold').addEventListener('click', async () => { if (!current) return; const held = Number(current.is_on_hold) === 1; const reason = prompt(held ? '보류 해제 사유를 입력하세요.' : '보류 사유를 입력하세요.', ''); if (reason === null || (!held && reason.trim() === '')) return; try { await postJson(held ? API.releaseHold : API.hold, { id: current.id, reason }); await openDetail(current.id); table.ajax.reload(null, false); } catch (error) { notify('error', error.message); } });
document.getElementById('paymentScheduleDelete').addEventListener('click', async () => { if (!current || !confirm('이 지급예정을 삭제하시겠습니까?')) return; try { await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '지급예정을 휴지통으로 이동 중' }, async () => { await postJson(API.delete, { id: current.id }); detailModal.hide(); await new Promise(resolve => table.ajax.reload(() => resolve())); notify('success', '삭제되었습니다.'); }); } catch (error) { notify('error', error.message); } });
allocationForm.evidence_id.addEventListener('change', () => { const option = allocationForm.evidence_id.selectedOptions[0]; if (option?.dataset.available) allocationForm.amount.value = Math.min(Number(option.dataset.available), Number(current?.remaining_amount || 0)).toFixed(2); });
