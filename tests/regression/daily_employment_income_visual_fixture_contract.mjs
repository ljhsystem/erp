import fs from 'node:fs';

const source = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/visual-fixture.js', 'utf8');
const checks = [
    ['운영 저장과 분리된 Fixture다', source.includes('fixture_only: true')],
    ['문서 기준정보는 귀속연월이며 지급예정일은 없다', source.includes("income_year_month: '2025-08'") && !source.includes('payment_date')],
    ['Fixture에도 문서번호 Projection이 없다', !source.includes('document_number') && !source.includes('문서번호')],
    ['Group 4개를 제공한다', (source.match(/client_key: 'fixture-group-/g) || []).length === 4],
    ['작업자 7명을 제공한다', (source.match(/worker\('fixture-worker-/g) || []).length === 7],
    ['펼친 작업자 Workday 8건이다', source.includes('[3, 4, 5, 6, 11, 12, 13, 14]')],
    ['추가 Group 3개는 접힘 상태다', (source.match(/collapsed: true, items:/g) || []).length === 3],
];
let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
