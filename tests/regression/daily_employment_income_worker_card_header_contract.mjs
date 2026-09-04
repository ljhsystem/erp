import fs from 'node:fs';

const workerCards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const style = fs.readFileSync('public/assets/css/pages/institution/daily-employment-income/index.css', 'utf8');

const orderTokens = [
    'header.append(order, handle, worker, clientType, workType, description, workdayCount, averageRate, paymentTotal)',
    'header.append(copy, remove)',
    'header.append(fold)',
];

const checks = [
    ['Header 요소 순서가 순번→핸들→작업자→거래처유형→공종→작업내용→일수→평균단가→지급액(세전)→복사→삭제→접기 순서다', orderTokens.every((token, index) => workerCards.indexOf(token) >= 0 && (index === 0 || workerCards.indexOf(orderTokens[index - 1]) < workerCards.indexOf(token)))],
    ['근무일·단가·지급액 변경 시 Header 요약을 즉시 갱신한다', workerCards.includes('updateHeaderSummary(card, item)') && workerCards.includes('평균단가') && workerCards.includes('지급액(세전)')],
    ['공종과 작업내용은 항목명과 미선택·미입력 상태를 함께 표시한다', workerCards.includes("`공종: ${typeName || '미선택'}`") && workerCards.includes("`작업내용: ${String(item.work_description || '').trim() || '미입력'}`")],
    ['날짜별 입력값은 편집 종료 즉시 Workday와 Header·합계에 반영한다', workerCards.includes('commitEditorsOnChange: true') && workerCards.includes("grid.on('cell:changed'")],
    ['공용 Font Awesome 드래그핸들을 사용한다', workerCards.includes('fa-solid fa-grip-vertical')],
    ['복사·삭제·접기 Action은 아이콘과 접근성 라벨을 사용한다', workerCards.includes("copy.setAttribute('aria-label', copy.title)") && workerCards.includes("remove.setAttribute('aria-label', remove.title)") && workerCards.includes("fold.setAttribute('aria-label', fold.title)")],
    ['드래그 정렬 후 Group 배열 순서와 sort_no를 즉시 재계산한다', workerCards.includes("group.items.splice(targetIndex, 0, moved); this.normalizeSortNumbers(group)")],
    ['복사와 삭제 후 sort_no를 재계산한다', (workerCards.match(/this\.normalizeSortNumbers\(group\)/g) || []).length >= 4],
    ['저장 Payload의 sort_no는 화면 배열 순서와 일치한다', page.includes('sort_no: workerIndex + 1')],
    ['Header는 한 줄 Grid이며 작업내용만 줄임표시한다', style.includes('.daily-income-worker-card__header { display: grid;') && style.includes('white-space: nowrap;') && style.includes('.daily-income-worker-description { min-width: 0; overflow: hidden; text-overflow: ellipsis; }') && !style.includes('.daily-income-worker-work-type { min-width: 0; overflow: hidden; text-overflow: ellipsis; }')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
