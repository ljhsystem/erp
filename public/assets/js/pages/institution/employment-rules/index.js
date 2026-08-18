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
    save: '/api/institution/human-resources/employment-rules/save',
    submit: '/api/institution/human-resources/employment-rules/submit',
    activate: '/api/institution/human-resources/employment-rules/activate',
    remove: '/api/institution/human-resources/employment-rules/delete',
  };
  let currentType = 'COMPANY';
  const form = document.getElementById('ruleForm');
  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleModal'));
  const policyRows = document.getElementById('policyRows');
  const scopeRows = document.getElementById('scopeRows');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const optionHtml = (rows, empty = '선택') => `<option value="">${empty}</option>` + rows.map(row => `<option value="${esc(row.value)}">${esc(row.label)}</option>`).join('');
  form.rule_type_code.innerHTML = optionHtml(options.type || []);

  const timePicker = AdminPicker.create({type:'time-list', container:document.getElementById('employmentRuleTimePicker'), options:{step:1, rows:8}});
  let timeTarget = null;
  timePicker.subscribe((_, value) => { if (timeTarget) timeTarget.value = value; timePicker.close(); });

  function itemValue(item) {
    return item.value_text ?? item.value_number ?? item.value_boolean ?? item.value_date ?? item.value_time ?? item.value_minutes ?? item.value_json ?? '';
  }
  function configureValueInput(row, valueType) {
    const input = row.querySelector('[name=value]');
    input.type = ['NUMBER','PERCENT','MINUTES'].includes(valueType) ? 'number' : (valueType === 'DATE' ? 'date' : 'text');
    input.classList.toggle('policy-time-input', valueType === 'TIME');
    if (valueType === 'BOOLEAN') input.placeholder = 'true 또는 false';
    else if (valueType === 'JSON') input.placeholder = '{"조건":"값"}';
    else input.placeholder = '정책값';
  }
  function addPolicy(item = {}) {
    const row = document.createElement('div');
    row.className = 'policy-row';
    row.innerHTML = `<select class="form-select" name="policy_code">${optionHtml(options.policy || [])}</select><select class="form-select" name="value_type_code">${optionHtml(options.value_type || [])}</select><input class="form-control" name="value"><select class="form-select" name="unit_code">${optionHtml(options.unit || [], '단위 없음')}</select><select class="form-select" name="operator_code">${optionHtml(options.operator || [])}</select><button class="btn btn-outline-danger" type="button">삭제</button>`;
    row.querySelector('[name=policy_code]').value = item.policy_code || '';
    row.querySelector('[name=value_type_code]').value = item.value_type_code || 'TEXT';
    row.querySelector('[name=value]').value = itemValue(item);
    row.querySelector('[name=unit_code]').value = item.unit_code || '';
    row.querySelector('[name=operator_code]').value = item.operator_code || 'EXACT';
    row.querySelector('[name=value_type_code]').addEventListener('change', event => configureValueInput(row, event.target.value));
    row.querySelector('[name=value]').addEventListener('click', event => { if (row.querySelector('[name=value_type_code]').value === 'TIME') { timeTarget = event.target; timePicker.open(event.target); } });
    row.querySelector('button').addEventListener('click', () => row.remove());
    configureValueInput(row, row.querySelector('[name=value_type_code]').value);
    policyRows.appendChild(row);
  }
  function addScope(scope = {}) {
    const row = document.createElement('div');
    row.className = 'scope-row';
    row.innerHTML = `<select class="form-select" name="scope_type_code">${optionHtml(options.scope_type || [])}</select><select class="form-select" name="scope_value"><option value="">대상 없음</option></select><button class="btn btn-outline-danger" type="button">삭제</button>`;
    const typeSelect = row.querySelector('[name=scope_type_code]');
    const valueSelect = row.querySelector('[name=scope_value]');
    const refresh = () => {
      const maps = {DEPARTMENT:options.departments || [], POSITION:options.positions || [], JOB:options.jobs || [], EMPLOYMENT_CATEGORY:options.employment_categories || []};
      valueSelect.innerHTML = optionHtml(maps[typeSelect.value] || [], '대상 없음');
      valueSelect.disabled = typeSelect.value === 'ALL' || !maps[typeSelect.value];
      valueSelect.value = scope.department_id || scope.position_id || scope.job_id || scope.employment_category_code || '';
    };
    typeSelect.value = scope.scope_type_code || 'ALL';
    typeSelect.addEventListener('change', refresh);
    row.querySelector('button').addEventListener('click', () => row.remove());
    refresh(); scopeRows.appendChild(row);
  }

  const columns = [
    {data:'rule_code', title:'규정코드'}, {data:'title', title:'규정명'}, {data:'revision_no', title:'개정차수'},
    {data:'revision_title', title:'개정본'}, {data:'effective_from', title:'시행일'}, {data:'effective_to', title:'종료일', defaultContent:'-'},
    {data:'status_code', title:'상태'}, actorColumn('updated_by','수정자'),
    {data:null, title:'관리', render:row => `<button class="btn btn-outline-primary btn-sm" data-edit="${row.id}">상세</button>` +
      (capabilities.submit && ['DRAFT','WITHDRAWN'].includes(row.status_code) ? `<button class="btn btn-outline-success btn-sm" data-submit="${row.id}">결재요청</button>` : '') +
      (capabilities.activate && row.status_code === 'APPROVED' ? `<button class="btn btn-outline-warning btn-sm" data-activate="${row.id}">시행</button>` : '') +
      (capabilities.delete && ['DRAFT','WITHDRAWN'].includes(row.status_code) ? `<button class="btn btn-outline-danger btn-sm" data-delete="${row.id}">삭제</button>` : '')}
  ];
  const table = await createDataTable({tableSelector:'#employmentRulesTable', api:API.list, serverSide:true, searching:false, pageLength:50, columns,
    ajaxData:data => { data.filters = JSON.stringify([{field:'rule_type_code', value:currentType}]); return data; },
    tableSettings:{enabled:true,pageKey:'institution.human_resources.employment_rules',tableKey:'employment-rules',storageKey:'datatable.settings.institution.employment-rules.v1',tableLabel:'취업규칙·인사규정'}});
  SearchForm({table, apiList:API.list, tableId:'employmentRules', defaultSearchField:'title', dateOptions:[{value:'effective_from',label:'시행일'}]});

  document.querySelectorAll('[data-type]').forEach(tab => tab.addEventListener('click', () => { document.querySelectorAll('[data-type]').forEach(item => item.classList.toggle('active', item === tab)); currentType = tab.dataset.type; table.ajax.reload(); }));
  document.getElementById('addPolicy')?.addEventListener('click', () => addPolicy());
  document.getElementById('addScope')?.addEventListener('click', () => addScope());
  document.getElementById('ruleAdd')?.addEventListener('click', () => {
    form.reset(); policyRows.innerHTML = ''; scopeRows.innerHTML = '';
    form.company_id.value = options.companies?.[0]?.value || '';
    form.rule_type_code.value = currentType;
    form.request_key.value = crypto.randomUUID();
    addPolicy(); addScope({scope_type_code:'ALL'}); modal.show();
  });
  document.querySelector('#employmentRulesTable tbody').addEventListener('click', async event => {
    const edit = event.target.closest('[data-edit]');
    const submit = event.target.closest('[data-submit]');
    const activate = event.target.closest('[data-activate]');
    const remove = event.target.closest('[data-delete]');
    try {
      if (edit) {
        const payload = await request(`${API.detail}?id=${encodeURIComponent(edit.dataset.edit)}`);
        const data = payload.data; form.reset(); policyRows.innerHTML = ''; scopeRows.innerHTML = '';
        Object.keys(data).forEach(key => { if (form.elements[key]) form.elements[key].value = data[key] ?? ''; });
        form.request_key.value = crypto.randomUUID(); (data.items || []).forEach(addPolicy); (data.scopes || []).forEach(addScope); modal.show();
      }
      if (submit) { await request(API.submit, {id:submit.dataset.submit}); table.ajax.reload(); }
      if (activate) { await request(API.activate, {id:activate.dataset.activate}); table.ajax.reload(); }
      if (remove && confirm('이 초안 개정본을 삭제하시겠습니까?')) { await request(API.remove, {id:remove.dataset.delete}); table.ajax.reload(); }
    } catch (error) { notify('error', error.message); }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    data.items = [...policyRows.children].map(row => ({policy_code:row.querySelector('[name=policy_code]').value,value_type_code:row.querySelector('[name=value_type_code]').value,value:row.querySelector('[name=value]').value,unit_code:row.querySelector('[name=unit_code]').value,operator_code:row.querySelector('[name=operator_code]').value}));
    data.scopes = [...scopeRows.children].map(row => { const type=row.querySelector('[name=scope_type_code]').value,value=row.querySelector('[name=scope_value]').value,result={scope_type_code:type}; if(type==='DEPARTMENT')result.department_id=value;if(type==='POSITION')result.position_id=value;if(type==='JOB')result.job_id=value;if(type==='EMPLOYMENT_CATEGORY')result.employment_category_code=value;return result; });
    try { await request(API.save, data); modal.hide(); table.ajax.reload(); notify('success', '저장했습니다.'); } catch (error) { notify('error', error.message); }
  });
  async function request(url, data) {
    const response = await fetch(url, data ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)} : undefined);
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.');
    return payload;
  }
}
