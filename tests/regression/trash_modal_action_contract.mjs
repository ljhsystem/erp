import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..', '..');
const manager = fs.readFileSync(path.join(root, 'public/assets/js/common/trash/trash-manager.js'), 'utf8');
const modal = fs.readFileSync(path.join(root, 'app/views/components/ui-modal-trash.php'), 'utf8');
const failures = [];
const check = (condition, message) => { if (!condition) failures.push(message); };

for (const selector of [
    '.btn-restore', '.btn-restore-selected', '.btn-restore-all',
    '.btn-purge', '.btn-delete-selected', '.btn-delete-all',
]) {
    check(manager.includes(`'${selector}'`), `공용 휴지통 액션 누락: ${selector}`);
}
check((manager.match(/await runTrashAction\(/g) || []).length === 6, '여섯 가지 성공 액션의 공용 모달 닫기 경로가 일치하지 않습니다.');
check(manager.includes('closeTrashModal(modal);'), '성공 액션 후 모달 닫기가 없습니다.');
check((manager.match(/void triggerChange\(modal\);/g) || []).length === 6, '성공 액션 후 목록 갱신이 모달 닫기를 지연합니다.');
check(!manager.includes('await triggerChange(modal);'), '모달 닫기 전에 목록 재조회를 기다리는 레거시 경로가 남아 있습니다.');
check(manager.includes('if (!await hasActionableTrash(modal)) return;'), '빈 휴지통의 선택·전체 액션 차단이 없습니다.');
check(manager.includes("notify('info', '휴지통에 처리할 항목이 없습니다.');"), '빈 휴지통 안내 문구가 없습니다.');
check(modal.includes('data-bs-dismiss="modal"'), '우측 상단 닫기 버튼 계약이 없습니다.');
check(modal.includes('data-trash-close'), '공용 휴지통의 명시적 닫기 제어가 없습니다.');
check(manager.includes("event.target.closest('[data-trash-close]')"), '우측 상단 닫기 공용 이벤트 경로가 없습니다.');
check(modal.includes('data-bs-backdrop="true"'), '모달 외부 클릭 닫기 계약이 없습니다.');
check(modal.includes('data-bs-keyboard="true"'), 'ESC 닫기 계약이 없습니다.');

if (failures.length) {
    console.error(JSON.stringify({ success: false, failures }, null, 2));
    process.exit(1);
}
console.log(JSON.stringify({ success: true, actions: 6, closeMethods: 4 }, null, 2));
