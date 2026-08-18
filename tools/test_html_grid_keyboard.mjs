import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createKeyboardController } from '../public/assets/js/common/html-grid/keyboard.js';

const rows = [{ rowId: 'r1' }, { rowId: 'r2' }];
const columns = [
    { key: 'readonly', editable: false, editor: 'text' },
    { key: 'from', editable: true, editor: 'number' },
    { key: 'to', editable: true, editor: 'number' },
    { key: 'display', editable: true, editor: null },
];
let activeCell = { rowId: 'r1', rowIndex: 0, columnKey: 'from' };
const focused = [];
let committed = 0;
const selection = {
    getActiveCell: () => activeCell,
    focusCell(rowIndex, columnKey) {
        activeCell = { rowId: rows[rowIndex].rowId, rowIndex, columnKey };
        return { executed: true, activeCell };
    },
};
const controller = createKeyboardController({
    api: { getRows: () => rows, getCapabilities: () => ({}) },
    selection,
    getVisibleColumns: () => columns,
    onBeforeMove: () => { committed += 1; },
    onFocusCell: cell => focused.push(cell),
});
const tab = shiftKey => controller.handleKeyDown({ key: 'Tab', shiftKey, preventDefault() {} });

tab(false);
assert.deepEqual(activeCell, { rowId: 'r1', rowIndex: 0, columnKey: 'to' });
tab(false);
assert.deepEqual(activeCell, { rowId: 'r2', rowIndex: 1, columnKey: 'from' });
tab(true);
assert.deepEqual(activeCell, { rowId: 'r1', rowIndex: 0, columnKey: 'to' });
assert.equal(committed, 3);
assert.equal(focused.length, 3);

const gridSource = fs.readFileSync(new URL('../public/assets/js/common/html-grid/index.js', import.meta.url), 'utf8');
const gridCss = fs.readFileSync(new URL('../public/assets/css/components/html-grid.css', import.meta.url), 'utf8');
assert.match(gridSource, /addEventListener\('focusin'/);
assert.match(gridSource, /api\.focusCell\(rowIndex, columnKey\)/);
assert.match(gridSource, /focusEditorCell\(rowIndex, columnKey\)/);
assert.doesNotMatch(gridSource, /scrollIntoView/);
assert.match(gridCss, /\.html-grid-cell\.is-pinned-left:focus-within\s*\{[\s\S]*?position: sticky/);
assert.doesNotMatch(gridCss, /\.html-grid-cell:focus-within\s*\{\s*position: relative/);

console.log(JSON.stringify({ focus_sync: true, tab_forward: true, tab_row_wrap: true, shift_tab: true, commit_before_move: true, pinned_focus_stable: true }));
