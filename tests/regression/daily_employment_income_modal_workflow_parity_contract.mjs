import fs from 'node:fs';

const view = fs.readFileSync('app/views/institution/daily-employment-income/index.php', 'utf8');
const source = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const model = fs.readFileSync('app/Models/Institution/DailyEmploymentIncomeModel.php', 'utf8');
const style = fs.readFileSync('public/assets/css/pages/institution/daily-employment-income/index.css', 'utf8');
const footerView = view.slice(view.indexOf('<div class="modal-footer">'));
const footerOrder = ['dailyIncomeDelete', 'dailyIncomeWithdraw', 'dailyIncomeSubmit', 'type="submit"', 'data-bs-dismiss="modal"']
    .map(token => footerView.indexOf(token));

const checks = [
    ['회사는 내부 SSOT로만 사용하고 모달 입력필드로 노출하지 않는다', !view.includes('name="company_name"') && model.includes("company_name_ko AS name") && !source.includes('form.elements.company_name')],
    ['상용과 같은 삭제·회수·결재요청·저장·닫기 순서를 사용한다', footerOrder.every((position, index) => position >= 0 && (index === 0 || position > footerOrder[index - 1]))],
    ['삭제를 포함한 Footer 버튼을 상용과 같이 우측 정렬한다', style.includes('#dailyIncomeModal .modal-footer { justify-content: flex-end; }') && style.includes('#dailyIncomeModal .modal-footer #dailyIncomeDelete { margin-right: 0 !important; }')],
    ['결재요청과 회수 버튼을 영구 숨김 처리하지 않는다', !view.match(/id="dailyIncome(?:Submit|Withdraw)"[^>]*d-none/)],
    ['문서 상태와 저장 여부로 Workflow 버튼을 동기화한다', source.includes('const syncWorkflowActions') && source.includes('submit.disabled = !editable || !hasDocument')],
    ['결재요청은 공식 Preflight를 먼저 실행한다', source.includes("PREFLIGHT: '/api/institution/income-data/daily-employment/preflight'") && source.includes('preflight.blocking_errors')],
    ['미구현 Lifecycle을 실제 상신 성공으로 표시하지 않는다', source.includes('결재 Lifecycle은 아직 연결되지 않았습니다.')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
