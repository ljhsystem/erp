import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync('public/assets/js/common/html-grid/plugins/select2.js', 'utf8');

assert.ok(source.includes("event.key !== 'Tab'"), 'Select2 Tab 전달 Guard가 없습니다.');
assert.ok(source.includes("shiftKey: event.shiftKey === true"), 'Select2 Shift+Tab 전달이 없습니다.');
assert.ok(source.includes("select2:open.htmlGridTab"), '열린 Select2 검색창 Tab 연결이 없습니다.');
assert.ok(source.includes("editorElement.dispatchEvent(new KeyboardEvent('keydown'"), 'Select2 Tab을 Grid 키보드 흐름으로 전달하지 않습니다.');
assert.ok(source.includes('unbindTabTargets()'), 'Select2 Tab 이벤트 정리 계약이 없습니다.');

console.log('공용 HTML Grid Select2 Tab 이동 계약 검증 통과');
