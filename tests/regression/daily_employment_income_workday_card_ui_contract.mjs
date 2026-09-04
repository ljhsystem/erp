import fs from 'node:fs';

const cards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const style = fs.readFileSync('public/assets/css/pages/institution/daily-employment-income/index.css', 'utf8');

const checks = [
    ['귀속연월과 근무일수를 한 줄 왼쪽 요약으로 표시한다', cards.includes('년 ${options.month.slice(5, 7)}월 귀속') && cards.includes('근무일수 ${item.workdays.size}일')],
    ['28·29·30·31일 날짜를 실제 말일 수만큼 전체 너비에 균등 분할한다', cards.includes("calendar.style.setProperty('--daily-income-days', String(options.dates.length))") && style.includes('repeat(var(--daily-income-days, 31), minmax(0, 1fr))')],
    ['날짜별 상세표는 5건부터 표시하고 5건 단위 더보기를 제공한다', cards.includes('const visibleCount = Math.max(5') && cards.includes('item.workdayVisibleCount = visibleCount + 5') && cards.includes('날짜별 상세 ${Math.min(5, allDays.length - visibleCount)}건 더보기')],
    ['날짜 선택만으로 5건 초과 행을 자동 노출하지 않는다', !cards.includes('Math.ceil(item.workdays.size / 5) * 5')],
    ['날짜별 상세표는 산정내역 215px와 지급액 80px 너비를 사용한다', ['근무일', '요일', '실제근로시간(휴게시간 제외)', '단가', '과세증감', '비과세증감', '비과세 적용사유', '산정내역', '지급액'].every(label => cards.includes(`label: '${label}'`)) && cards.includes("key: 'calculation_note', label: '산정내역', editor: 'text', width: 215") && cards.includes("key: 'payment_amount', label: '지급액', type: 'number', formatter: 'number', editable: false, width: 80")],
    ['근무일 Grid 위에서도 모달 세로 스크롤이 이어진다', style.includes('overscroll-behavior-x: contain; overscroll-behavior-y: auto;')],
    ['우측 계산결과는 모달 본문 상단 여백 없이 고정된다', style.includes('.daily-income-worker-result { position: sticky; top: -10px;')],
    ['과거 Workday의 NULL 실제근로시간은 미확인으로 표시한다', cards.includes("formatter: 'actual-work-minutes'") && cards.includes("'과거자료 미확인'")],
    ['실제근로시간은 넓은 분 입력과 인접한 시간 환산값을 한 줄로 표시한다', cards.includes('workMinutesText') && cards.includes('return `${hours}시간') && cards.includes('decorateWorkMinuteEditors(host)') && style.includes('grid-template-columns: minmax(90px, 0.65fr) minmax(55px, 0.35fr)') && style.includes('text-align: left')],
    ['문서 종합집계는 10개 항목을 간격을 줄여 한 줄로 표시한다', style.includes('grid-template-columns: repeat(10, minmax(96px, 1fr))') && style.includes('overflow-x: auto') && style.includes('white-space: nowrap')],
    ['셀 입력 즉시 지급액·합계만 부분 갱신하고 공식 Preview를 예약한다', cards.includes('paymentCell.textContent = formatAmount(payment)') && cards.includes('this.updateTotals(footer, item)') && page.includes('scheduleAutoCalculation()') && page.includes('calculateDocument({ render: false }).catch(() => {})')],
    ['이전 비동기 계산응답은 최신 입력을 덮어쓰지 않는다', page.includes('requestVersion !== calculationRequestVersion')],
    ['개별 근무일 선택 해제는 확인창 없이 즉시 반영한다', !page.includes('confirmRemoveWorkday') && !page.includes('해당 Workday를 삭제하시겠습니까?') && cards.includes("typeof options.confirmRemoveWorkday === 'function'")],
    ['신규 근무일은 기본 8시간을 실제근로시간으로 입력한다', page.includes('const DEFAULT_WORKDAY_MINUTES = 8 * 60;') && page.includes('defaultWorkMinutes: DEFAULT_WORKDAY_MINUTES') && cards.includes('actual_work_minutes: options.defaultWorkMinutes ?? null')],
    ['근로시간 누락 안내는 저장 전 자동 Preview 계약을 설명한다', page.includes('입력하면 세금·보험료와 실지급액이 자동으로 다시 계산됩니다.') && !page.includes('입력한 뒤 저장하면 공식 계산이 다시 실행됩니다.')],
    ['합계 라인에 근무일수·근무시간·증감·지급액·원천징수·실지급액을 표시한다', ['근무일수', '근무시간', '기본지급액', '과세증감', '비과세증감', '지급액(세전)', '원천징수', '실지급액(세후)'].every(label => cards.includes(label)) && cards.includes("remainingMinutes > 0 ? `${String(remainingMinutes).padStart(2, '0')}분` : ''") && !cards.includes('<span>조정금액</span>') && style.includes('.daily-income-worker-totals .is-emphasis')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
