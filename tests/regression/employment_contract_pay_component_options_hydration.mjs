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

const optionHydration = source.indexOf('payComponentOptions.splice(');
const masterHydration = source.indexOf('payComponentOptions.forEach(option => payComponentById.set(');
const gridDestroy = source.indexOf('componentGrid.destroy();', masterHydration);
const gridCreate = source.indexOf('ensureComponentGrid();', gridDestroy);

check(optionHydration >= 0, '급여항목 옵션 반영이 없습니다.');
check(masterHydration > optionHydration, '급여항목 Master Map 반영 순서가 잘못되었습니다.');
check(gridDestroy > masterHydration, '옵션 반영 후 기존 지급조건 Grid 제거가 없습니다.');
check(gridCreate > gridDestroy, '급여항목 옵션을 포함한 지급조건 Grid 재생성이 없습니다.');
check(source.indexOf('replaceComponentRows(basic.data.components || [])') > gridCreate, '상세 지급항목 복원이 Grid 재생성보다 먼저 실행됩니다.');

if (failures.length) {
    console.error(JSON.stringify({ success: false, failures }, null, 2));
    process.exit(1);
}
console.log(JSON.stringify({ success: true, checks: 5 }, null, 2));
