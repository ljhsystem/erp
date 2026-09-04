import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..', '..');
const source = fs.readFileSync(
    path.join(root, 'public/assets/js/pages/institution/employment-contract/modal-runtime.js'),
    'utf8',
);
const failures = [];
const check = (condition, message) => { if (!condition) failures.push(message); };

check(source.includes('if (input.disabled) return context.value ?? null;'), '읽기전용 시간 Editor가 저장값을 보존하지 않습니다.');
check(source.includes('input.disabled ? context.value ?? null : normalizeHtmlGridNumberValue'), '읽기전용 휴게시간 Editor가 저장값을 보존하지 않습니다.');
check(source.includes('weeklyScheduleGrid.destroy();') && source.includes('weeklyScheduleGrid = null;'), '근무구분 옵션 로드 후 Grid 재생성이 없습니다.');
check(source.indexOf('weeklyScheduleDayTypes.splice(') < source.indexOf('weeklyScheduleGrid.destroy();'), '근무구분 옵션 적용 전에 Grid를 재생성합니다.');

if (failures.length) {
    console.error(JSON.stringify({ success: false, failures }, null, 2));
    process.exit(1);
}
console.log(JSON.stringify({ success: true, checks: 4 }, null, 2));
