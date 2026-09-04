import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const buttonState = read('public/assets/js/common/trash/trash-button-state.js');
const deleteProgress = read('public/assets/js/common/delete-progress.js');
const dataTable = read('public/assets/js/common/table/data-table.js');
const trashManager = read('public/assets/js/common/trash/trash-manager.js');

const failures = [];
const check = (condition, message) => { if (!condition) failures.push(message); };

check(buttonState.includes("classList.toggle('btn-trash-has-data', hasTrash)"), '공용 휴지통 상태 클래스 반영이 없습니다.');
check(buttonState.includes("textContent || '').trim() === '휴지통'"), '텍스트 기반 공용 휴지통 버튼 탐색 fallback이 없습니다.');
check(deleteProgress.includes('trashChanged = false'), '소프트삭제 완료 capability가 명시적 옵션이 아닙니다.');
check(deleteProgress.includes('markTrashButtonsHasData('), '단건 소프트삭제 직후 버튼 갱신이 없습니다.');
check(dataTable.includes('if (deletedCount > 0) markTrashButtonsHasData(deletedCount);'), '공용 DataTable 선택삭제 직후 버튼 갱신이 없습니다.');
check(trashManager.includes("from './trash-button-state.js'"), 'TrashManager가 공용 버튼 상태 SSOT를 사용하지 않습니다.');

const pageRoot = path.join(root, 'public/assets/js/pages');
const pageFiles = [];
const visit = directory => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) visit(target);
        else if (entry.name.endsWith('.js')) pageFiles.push(target);
    }
};
visit(pageRoot);
for (const file of pageFiles) {
    const source = fs.readFileSync(file, 'utf8');
    for (const line of source.split('\n')) {
        if (line.includes("runDeleteProgress({ total: 1, title: '소프트삭제 처리 중'")
            && !line.includes('trashChanged: true')) {
            failures.push(`단건 소프트삭제 버튼 갱신 누락: ${path.relative(root, file)}`);
        }
    }
}

if (failures.length) {
    console.error(JSON.stringify({ success: false, failures }, null, 2));
    process.exit(1);
}

console.log(JSON.stringify({ success: true, checkedPageFiles: pageFiles.length }, null, 2));
