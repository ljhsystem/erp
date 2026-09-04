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

check(source.includes('async function createRevisionDraft(revisionKind, reason, contractDate)'), '개정·정정 DRAFT 공용 생성 흐름이 없습니다.');
check(source.includes('await openContract(result.data.id);'), '생성된 DRAFT를 모달에 자동으로 여는 동작이 없습니다.');
check(source.includes("await createRevisionDraft('CHANGE', reason, contractDate);"), '계약개정이 계약일을 포함한 새 DRAFT 자동 열기를 사용하지 않습니다.');
check(source.includes("await createRevisionDraft('CORRECTION', reason, contractDate);"), '입력누락정정이 원 계약일을 포함한 새 DRAFT 자동 열기를 사용하지 않습니다.');

if (failures.length) {
    console.error(JSON.stringify({ success: false, failures }, null, 2));
    process.exit(1);
}
console.log(JSON.stringify({ success: true, revisionKinds: ['CHANGE', 'CORRECTION'] }, null, 2));
