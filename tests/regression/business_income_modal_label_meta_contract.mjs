import assert from 'node:assert/strict';
import fs from 'node:fs';

const pageScript = fs.readFileSync('public/assets/js/pages/institution/business-income/index.js', 'utf8');
const metaService = fs.readFileSync('app/Services/System/DataTableColumnMetaService.php', 'utf8');

for (const [domain, table] of [
    ['business-income-group', 'institution_business_income_groups'],
    ['business-income-item', 'institution_business_income_items'],
    ['business-income-work-line', 'institution_business_income_work_lines'],
]) {
    assert.match(metaService, new RegExp(`'${domain}'\\s*=>\\s*\\['table'\\s*=>\\s*'${table}'\\]`));
    assert.ok(pageScript.includes(domain), `${domain} 메타 도메인을 모달에서 사용해야 합니다.`);
}

assert.ok(pageScript.includes('fetchDataTableMetaColumns'), '모달 렌더링 전에 물리 컬럼 메타를 조회해야 합니다.');
assert.ok(pageScript.includes("column?.is_nullable==='NO'"), '필수 표시는 DB NULL 허용 여부를 기준으로 해야 합니다.');
assert.ok(pageScript.includes('column?.label||fallback'), '라벨은 DB 컬럼 코멘트 기반 메타를 우선해야 합니다.');

for (const field of ['business_unit', 'project_id', 'work_team_id', 'group_description']) {
    assert.ok(pageScript.includes(`physicalLabelHtml('group','${field}'`), `그룹 필드 ${field}가 물리 메타 라벨을 사용해야 합니다.`);
}

for (const field of ['transaction_date', 'client_id', 'service_type_code', 'service_description']) {
    assert.ok(pageScript.includes(`physicalLabelHtml('item','${field}'`), `소득자 필드 ${field}가 물리 메타 라벨을 사용해야 합니다.`);
}

for (const field of ['item_name', 'item_specification', 'item_unit_name', 'item_quantity', 'item_unit_price']) {
    assert.ok(pageScript.includes(`physicalLabel('workLine','${field}'`), `작업내역 ${field} 라벨이 물리 메타를 사용해야 합니다.`);
    assert.ok(pageScript.includes(`physicalRequired('workLine','${field}')`), `작업내역 ${field} 필수 여부가 물리 메타를 사용해야 합니다.`);
}

assert.ok(pageScript.includes("physicalLabelHtml('workLine','adjustment_amount'"));
assert.ok(pageScript.includes("physicalLabelHtml('workLine','adjustment_reason'"));
assert.ok(pageScript.includes('formSettings.apply()'), '현재 페이지 헤더 필드는 TableSettings를 계속 사용해야 합니다.');

console.log('business income modal label metadata contract: PASS');
