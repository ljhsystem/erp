import fs from 'node:fs';
import assert from 'node:assert/strict';

const source = fs.readFileSync(new URL('../../public/assets/js/common/datatable/dataTableSettings.js', import.meta.url), 'utf8');

assert.match(source, /column\?\.__dtColumnKind === 'physical'/, '물리컬럼에만 DB Comment 동기화 예외를 적용해야 합니다.');
assert.match(source, /savedDisplayName === normalizedKey/, '원본 key와 같은 과거 기본 표시명을 감지해야 합니다.');
assert.match(source, /savedDisplayName === sourceColumn/, '원본 컬럼명과 같은 과거 기본 표시명을 감지해야 합니다.');
assert.match(source, /columnDisplayName\[normalizedKey\] = normalizedDisplayName\(value,/, '사용자 지정 표시명 병합 계약을 유지해야 합니다.');

console.log(JSON.stringify({ success: true, checks: ['영문 기본명 재동기화', '사용자 지정명 보존'] }, null, 2));
