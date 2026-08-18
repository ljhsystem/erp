import assert from 'node:assert/strict';
import {
    formatHtmlGridNumberWhileTyping,
    normalizeHtmlGridNumberValue,
} from '../public/assets/js/common/html-grid/editors/number-editor.js';

assert.deepEqual(formatHtmlGridNumberWhileTyping('1', 1), { value: '1', caret: 1 });
assert.deepEqual(formatHtmlGridNumberWhileTyping('1000', 4), { value: '1,000', caret: 5 });
assert.deepEqual(formatHtmlGridNumberWhileTyping('1234567', 7), { value: '1,234,567', caret: 9 });
assert.deepEqual(formatHtmlGridNumberWhileTyping('19999', 2), { value: '19,999', caret: 2 });
assert.equal(normalizeHtmlGridNumberValue('1,234,567'), '1234567');
assert.equal(normalizeHtmlGridNumberValue('-1,000', false), '');

console.log(JSON.stringify({ live_grouping: true, caret_preserved: true, raw_storage: true, negative_policy: true }));
