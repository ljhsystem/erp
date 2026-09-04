import fs from 'node:fs';

const source = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const view = fs.readFileSync('app/views/institution/daily-employment-income/index.php', 'utf8');
const required = [
    'function invalidWorkMinuteWorkdays()',
    'function renderCalculationReadiness()',
    '실제근로시간이 누락되었거나 유효하지 않은 Workday',
    '1~1,440분 정수',
    'submit.disabled = !editable || !hasDocument || workMinuteBlocked',
];
const missing = required.filter(fragment => !source.includes(fragment));
if (missing.length) throw new Error(`상세 자동재계산 입력 가드 누락: ${missing.join(', ')}`);
if (!view.includes('id="dailyIncomeCalculationReadiness"')) throw new Error('상세 계산 준비상태 UI가 누락되었습니다.');
const openDetailBody = source.slice(source.indexOf('const openDetail = async id =>'), source.indexOf("document.querySelector('#daily-income-table tbody')"));
if (openDetailBody.includes('calculateDocument(')) throw new Error('상세 열기에서 계산 Preview를 호출하면 안 됩니다.');
console.log('daily employment income detail auto-calculation guard: PASS');
