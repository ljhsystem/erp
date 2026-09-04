import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { notify } from '/public/assets/js/common/notification.js';

const root = document.querySelector('.employment-rules-page');
if (root) {
  const bootstrapData = JSON.parse(root.dataset.bootstrap || '{}');
  const options = bootstrapData.options || {};
  const capabilities = bootstrapData.capabilities || {};
  const API = {
    list: '/api/institution/human-resources/employment-rules/list',
    detail: '/api/institution/human-resources/employment-rules/detail',
    history: '/api/institution/human-resources/employment-rules/history',
    save: '/api/institution/human-resources/employment-rules/save',
    revise: '/api/institution/human-resources/employment-rules/revise',
    submit: '/api/institution/human-resources/employment-rules/submit',
    withdraw: '/api/institution/human-resources/employment-rules/withdraw',
    activate: '/api/institution/human-resources/employment-rules/activate',
    remove: '/api/institution/human-resources/employment-rules/delete',
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const optionHtml = (rows, empty = '선택') => `<option value="">${empty}</option>` + rows.map(row => `<option value="${esc(row.value)}">${esc(row.label)}</option>`).join('');
  const statusNames = Object.fromEntries((options.status || []).map(row => [row.value, row.label]));
  const form = document.getElementById('ruleForm');
  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleModal'));
  const historyModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleHistoryModal'));
  let mode = 'save';
  let currentRuleId = '';

  form.regulation_type_code.innerHTML = optionHtml(options.type || []);
  form.owner_department_id.innerHTML = optionHtml(options.departments || [], '소관부서 없음');
  const datePicker = AdminPicker.create({type:'today', container:document.getElementById('employmentRuleDatePicker')});
  let activeDateInput = null;
  datePicker.subscribe((_, value) => {
    if (activeDateInput) activeDateInput.value = typeof value === 'string' ? value : value.toISOString().slice(0, 10);
    datePicker.close();
  });
  document.querySelectorAll('#ruleModal .admin-date').forEach(input => input.addEventListener('click', () => {
    if (input.disabled) return;
    activeDateInput = input;
    datePicker.open({anchor:input});
  }));

  const columns = [
    {data:'regulation_type_name', title:'규정종류'},
    {data:'regulation_code', title:'규정코드'},
    {data:'regulation_title', title:'규정명'},
    {data:'revision_no', title:'Revision', className:'text-center'},
    {data:'revision_date', title:'제정·개정일'},
    {data:'effective_from', title:'시행일'},
    {data:'effective_to', title:'종료일', defaultContent:'-'},
    {data:'status_code', title:'상태', render:value => esc(statusNames[value] || value)},
    {data:'is_current', title:'현재 유효', className:'text-center', render:value => Number(value) === 1 ? '<span class="badge text-bg-success">현재</span>' : '-'},
    actorColumn('updated_by', '최종 변경자'),
    {data:'updated_at', title:'최종 변경일'},
    {data:null, title:'관리', orderable:false, searchable:false, render:row => actionHtml(row)},
  ];
  const table = await createDataTable({
    tableSelector:'#employmentRulesTable', api:API.list, serverSide:true, searching:false, pageLength:50, columns,
    // 공용 DataTable은 사용자 버튼을 복사·삭제 버튼 다음에 배치한다.
    buttons: capabilities.save ? [{text:'규정등록', className:'btn btn-primary btn-sm', action:openNew}] : [],
    tableSettings:{enabled:true, pageKey:'institution.human_resources.employment_rules', tableKey:'employment-rules', storageKey:'datatable.settings.institution.employment-rules.v2', tableLabel:'취업규칙·인사규정', metaDomain:'employment-rule', resetOnColumnSchemaChange:true},
  });
  SearchForm({table, apiList:API.list, tableId:'employmentRules', defaultSearchField:'title', dateOptions:[{value:'effective_from', label:'시행일'}, {value:'revision_date', label:'제정·개정일'}]});

  function actionHtml(row) {
    const buttons = [`<button class="btn btn-outline-primary btn-sm" data-detail="${esc(row.id)}">상세</button>`];
    if (capabilities.history) buttons.push(`<button class="btn btn-outline-secondary btn-sm" data-history="${esc(row.rule_id)}">이력</button>`);
    if (capabilities.submit && ['DRAFT','WITHDRAWN'].includes(row.status_code)) buttons.push(`<button class="btn btn-outline-success btn-sm" data-submit="${esc(row.id)}">결재요청</button>`);
    if (capabilities.withdraw && row.status_code === 'APPROVAL_PENDING' && row.approval_request_id) buttons.push(`<button class="btn btn-outline-secondary btn-sm" data-withdraw="${esc(row.approval_request_id)}">회수</button>`);
    if (capabilities.activate && row.status_code === 'APPROVED') buttons.push(`<button class="btn btn-outline-warning btn-sm" data-activate="${esc(row.id)}">시행</button>`);
    if (capabilities.revise && ['APPROVED','SCHEDULED','EFFECTIVE','RETIRED'].includes(row.status_code)) buttons.push(`<button class="btn btn-outline-info btn-sm" data-revise="${esc(row.id)}">개정</button>`);
    if (capabilities.activate && ['SCHEDULED','EFFECTIVE'].includes(row.status_code)) buttons.push(`<button class="btn btn-outline-dark btn-sm" data-retire="${esc(row.id)}">폐지</button>`);
    if (capabilities.delete && ['DRAFT','WITHDRAWN'].includes(row.status_code)) buttons.push(`<button class="btn btn-outline-danger btn-sm" data-delete="${esc(row.id)}">삭제</button>`);
    return `<div class="d-flex gap-1 flex-wrap">${buttons.join('')}</div>`;
  }

  function openNew() {
    mode = 'save'; currentRuleId = ''; form.reset(); form.id.value = '';
    form.company_id.value = options.companies?.[0]?.value || '';
    form.request_key.value = crypto.randomUUID();
    form.regulation_type_code.value = options.type?.[0]?.value || '';
    setEditable(true);
    document.querySelector('[data-rule-mode-label]').textContent = '신규 규정의 최초 초안을 작성합니다.';
    document.getElementById('ruleHistory').hidden = true;
    modal.show();
  }

  async function openDetail(id, revise = false) {
    const data = (await request(`${API.detail}?id=${encodeURIComponent(id)}`)).data;
    form.reset();
    Object.keys(data).forEach(key => { if (form.elements[key]) form.elements[key].value = data[key] ?? ''; });
    currentRuleId = data.rule_id;
    form.request_key.value = crypto.randomUUID();
    if (revise) {
      mode = 'revise'; form.id.value = data.id; form.revision_date.value = ''; form.effective_from.value = ''; form.effective_to.value = '';
      form.change_reason.value = ''; form.change_summary.value = '';
      setEditable(true);
      document.querySelector('[data-rule-mode-label]').textContent = `Revision ${data.revision_no}을 복사해 새 초안을 만듭니다.`;
    } else {
      mode = 'save';
      setEditable(['DRAFT','WITHDRAWN'].includes(data.status_code));
      document.querySelector('[data-rule-mode-label]').textContent = `Revision ${data.revision_no} · ${statusNames[data.status_code] || data.status_code}`;
    }
    document.querySelector('[data-file-metadata]').textContent = data.document_file_name || '등록된 원본파일 metadata가 없습니다.';
    document.getElementById('ruleHistory').hidden = !currentRuleId;
    modal.show();
  }

  function setEditable(editable) {
    [...form.elements].forEach(element => {
      if (['BUTTON','HIDDEN'].includes(element.type?.toUpperCase())) return;
      element.disabled = !editable;
    });
    form.querySelector('[data-save-rule]').hidden = !editable;
  }

  async function showHistory(ruleId) {
    const rows = (await request(`${API.history}?rule_id=${encodeURIComponent(ruleId)}`)).data || [];
    document.getElementById('ruleHistoryRows').innerHTML = rows.length ? rows.map(row => `<tr><td>${esc(row.revision_no)}</td><td>${esc(row.title)}</td><td>${esc(row.revision_date)}</td><td>${esc(row.effective_from)} ~ ${esc(row.effective_to || '')}</td><td>${esc(statusNames[row.status_code] || row.status_code)}</td><td>${esc(row.change_summary || '-')}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">개정이력이 없습니다.</td></tr>';
    historyModal.show();
  }

  document.querySelector('#employmentRulesTable tbody').addEventListener('click', async event => {
    const target = event.target.closest('button');
    if (!target) return;
    try {
      if (target.dataset.detail) await openDetail(target.dataset.detail);
      else if (target.dataset.history) await showHistory(target.dataset.history);
      else if (target.dataset.revise) await openDetail(target.dataset.revise, true);
      else if (target.dataset.submit) { await request(API.submit, {id:target.dataset.submit}); table.ajax.reload(null, false); }
      else if (target.dataset.withdraw) { await request(API.withdraw, {request_id:target.dataset.withdraw}); table.ajax.reload(null, false); }
      else if (target.dataset.activate) { await request(API.activate, {id:target.dataset.activate}); table.ajax.reload(null, false); }
      else if (target.dataset.retire) {
        const effectiveTo = window.prompt('폐지일을 YYYY-MM-DD 형식으로 입력해 주세요.');
        if (!effectiveTo) return;
        const reason = window.prompt('폐지 사유를 입력해 주세요.');
        if (!reason) return;
        await request(API.activate, {id:target.dataset.retire, action:'retire', effective_to:effectiveTo, reason});
        table.ajax.reload(null, false);
      } else if (target.dataset.delete && window.confirm('이 초안 개정본을 삭제하시겠습니까?')) {
        await request(API.remove, {id:target.dataset.delete}); table.ajax.reload(null, false);
      }
    } catch (error) { notify('error', error.message); }
  });
  document.getElementById('ruleHistory').addEventListener('click', () => currentRuleId && showHistory(currentRuleId));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    try {
      await request(mode === 'revise' ? API.revise : API.save, data);
      modal.hide(); table.ajax.reload(null, false); notify('success', mode === 'revise' ? '새 개정 초안을 생성했습니다.' : '저장했습니다.');
    } catch (error) { notify('error', error.message); }
  });

  async function request(url, data) {
    const response = await fetch(url, data ? {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)} : undefined);
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.');
    return payload;
  }
}
