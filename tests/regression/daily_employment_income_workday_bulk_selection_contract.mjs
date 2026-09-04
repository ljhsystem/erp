import fs from 'node:fs';

const cards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const style = fs.readFileSync('public/assets/css/pages/institution/daily-employment-income/index.css', 'utf8');

const checks = [
    ['작업내용 입력필드 오른쪽에 단일 전체선택 토글 버튼을 배치한다', cards.includes("toggleAll.textContent = allDatesSelected ? '전체선택해제' : '전체선택'") && cards.includes('dateActions.append(toggleAll)') && !cards.includes('dateActions.append(selectAll, clearAll)') && style.includes('grid-template-columns: minmax(180px, 1fr) auto')],
    ['미선택·부분선택은 전체선택, 전체선택 완료 후에는 전체선택해제 상태다', cards.includes('options.dates.every(date => item.workdays.has(date))') && cards.includes("allDatesSelected ? 'btn-outline-secondary' : 'btn-outline-primary'")],
    ['전체선택은 귀속월 실제 날짜만 추가하고 기존 Workday를 보존한다', cards.includes('options.dates.forEach(date => { if (!item.workdays.has(date)) item.workdays.set(date')],
    ['전체선택해제는 입력자료가 있으면 공용 확인창을 거친다', cards.includes('some(hasWorkdayInput)') && cards.includes('confirmClearWorkdays') && page.includes("title: '전체 근무일 선택 해제'")],
    ['전체해제 후 더보기 상태를 5건으로 초기화한다', cards.includes('item.workdays.clear(); item.workdayVisibleCount = 5;')],
    ['전체선택과 해제 후 합계·공식 Preview 갱신 이벤트를 실행한다', (cards.match(/render\(\{ preserveScroll: true \}\); this\.onChanged\(\{ immediate: true \}\);/g) || []).length >= 2],
    ['읽기 전용 또는 귀속월 미선택 상태에서는 토글을 차단한다', cards.includes('toggleAll.disabled = options.readOnly || options.dates.length === 0')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
