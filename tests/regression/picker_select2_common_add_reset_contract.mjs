import fs from 'node:fs';

const source = fs.readFileSync('public/assets/js/common/picker/picker.select2.js', 'utf8');
const addBranch = source.slice(source.indexOf('if (selectedId === COMMON_ADD_OPTION_ID)'), source.indexOf('function createSelect2'));
const checks = [
    ['+ 추가는 실제 선택값으로 남기지 않는다', addBranch.includes("window.jQuery(this).val('').trigger('change')")],
    ['빈 옵션이 없으면 선택(없음)을 생성한다', addBranch.includes("new Option('선택(없음)', '', true, true)")],
    ['값 복원 후 원본 퀵모달 이벤트를 발생시킨다', addBranch.indexOf("val('').trigger('change')") < addBranch.indexOf("new CustomEvent('picker:add'")],
];
let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
