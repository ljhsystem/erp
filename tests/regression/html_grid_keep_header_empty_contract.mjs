import fs from 'node:fs';

const renderer = fs.readFileSync('public/assets/js/common/html-grid/renderer.js', 'utf8');
const runtime = fs.readFileSync('public/assets/js/common/html-grid/index.js', 'utf8');
const daily = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const checks = [
    ['기본값 false로 기존 사용처를 보존한다', renderer.includes("config.keepHeaderWhenEmpty === true")],
    ['true일 때 빈 Grid Header를 유지한다', renderer.includes("!keepHeaderWhenEmpty && !hasRows")],
    ['부분 렌더링에서도 계약을 유지한다', runtime.includes("dataset.keepHeaderWhenEmpty === 'true'")],
    ['일용근로소득만 옵션을 명시한다', daily.includes('keepHeaderWhenEmpty: true')],
    ['공용 Empty State 문구를 전달한다', renderer.includes('options.emptyMessage || config.emptyMessage')],
];
let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
