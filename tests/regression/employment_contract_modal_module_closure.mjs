import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const runtime = read('public/assets/js/pages/institution/employment-contract/modal-runtime.js');
const statutory = read('public/assets/js/pages/institution/employment-contract/statutory-validation.js');
const controls = read('public/assets/js/pages/institution/employment-contract/modal-form-controls.js');
const performance = read('public/assets/js/pages/institution/employment-contract/modal-performance.js');

assert.ok(runtime.split(/\r?\n/).length - 1 < 1500, 'modal-runtime.js가 1,500라인 미만이 아닙니다.');
assert.ok(statutory.split(/\r?\n/).length - 1 < 1500, 'statutory-validation.js 라인수 위반');
assert.ok(controls.split(/\r?\n/).length - 1 < 1500, 'modal-form-controls.js 라인수 위반');
assert.ok(performance.split(/\r?\n/).length - 1 < 1500, 'modal-performance.js 라인수 위반');
assert.equal((runtime.match(/statutoryValidation\.load\(id\)/g) || []).length, 1, 'Projection load 호출이 중복되었습니다.');
assert.equal((statutory.match(/hidden\.bs\.modal/g) || []).length, 1, 'Modal close cleanup listener가 중복되었습니다.');
assert.ok(statutory.includes('activeContractId === normalizedId && pending'), '동일 계약 pending fetch 중복 방지가 없습니다.');
assert.ok(statutory.includes('generation !== requestGeneration'), 'stale Projection 응답 차단이 없습니다.');
assert.ok(!runtime.includes('function renderStatutoryProjection'), '원본 Runtime에 Projection renderer 중복이 남았습니다.');
assert.ok(!runtime.includes('async function request('), '원본 Runtime에 API client 중복이 남았습니다.');

console.log(JSON.stringify({ success: true, modalRuntimeLines: runtime.split(/\r?\n/).length - 1 }, null, 2));
