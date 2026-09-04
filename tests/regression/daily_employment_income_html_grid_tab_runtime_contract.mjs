import fs from 'node:fs';

const cards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');

const changedHandler = cards.slice(cards.indexOf("grid.on('cell:changed'"), cards.indexOf("if (allDays.length > visibleCount)"));
const checks = [
    ['cell:changed에서 Grid 전체 render를 호출하지 않는다', !changedHandler.includes('render({ preserveScroll: true })')],
    ['변경된 지급액 Cell과 합계만 부분 갱신한다', changedHandler.includes('paymentCell.textContent = formatAmount(payment)') && changedHandler.includes('this.updateTotals(footer, item)')],
    ['자동 공식계산은 편집 Grid를 재생성하지 않는다', page.includes('calculateDocument({ render: false })') && page.includes('groupGridRegistry.refreshTotals(workGroups)')],
    ['공용 Grid Tab capability는 계속 활성화돼 있다', cards.includes('keyboard: true')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
