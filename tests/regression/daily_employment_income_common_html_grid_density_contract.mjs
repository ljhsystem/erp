import fs from 'node:fs';

const cards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const commonStyle = fs.readFileSync('public/assets/css/components/html-grid.css', 'utf8');
const bodyRenderer = fs.readFileSync('public/assets/js/common/html-grid/body-renderer.js', 'utf8');
const dictionary = fs.readFileSync('docs/architecture/CommonDictionary.md', 'utf8');

const checks = [
    ['날짜별 표는 공용 createHtmlGrid를 사용한다', cards.includes("createHtmlGrid } from '/public/assets/js/common/html-grid/index.js'")],
    ['페이지 전용 Editor 구현과 Editor 덮어쓰기가 없다', !cards.includes('function inputEditor') && !cards.includes('editors: { number:')],
    ['공용 compact 밀도 변형을 적용한다', cards.includes('html-grid-variant-compact daily-income-workday-grid') && commonStyle.includes('.html-grid-host.html-grid-variant-compact')],
    ['compact Header·Cell·Editor 글꼴과 패딩이 일치한다', commonStyle.includes('height: 34px;') && commonStyle.includes('height: 27px;') && commonStyle.includes('padding: 3px 7px 3px 6px;') && commonStyle.includes('padding: 2px 6px;')],
    ['공용 Renderer가 편집 셀을 명시적으로 식별한다', bodyRenderer.includes("classNames.push('has-editor')")],
    ['compact Editor와 열 경계의 좌우 여백이 정확히 대칭이다', commonStyle.includes('.html-grid-cell.has-editor') && commonStyle.includes('padding-inline: 0;') && commonStyle.includes('padding-inline: 6px;') && !commonStyle.includes('padding-right: 1px;')],
    ['마지막 지급액 열은 80px 고정 너비로 산정내역 입력폭을 보장한다', cards.includes("key: 'payment_amount', label: '지급액', type: 'number', formatter: 'number', editable: false, width: 80")],
    ['공용 compact 변형이 CommonDictionary에 등록됐다', dictionary.includes('`.html-grid-variant-compact`') && dictionary.includes('페이지별 Editor 재구현 없이')],
];

let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
