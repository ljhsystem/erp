import assert from 'node:assert/strict';
import {
    createKeyboardController,
    isHtmlGridNativeEditorTarget,
} from '../../public/assets/js/common/html-grid/keyboard.js';
import { createSelectionController } from '../../public/assets/js/common/html-grid/selection.js';

assert.equal(isHtmlGridNativeEditorTarget({ tagName: 'INPUT', type: 'text' }), true);
assert.equal(isHtmlGridNativeEditorTarget({ tagName: 'TEXTAREA' }), true);
assert.equal(isHtmlGridNativeEditorTarget({ tagName: 'SELECT' }), true);
assert.equal(isHtmlGridNativeEditorTarget({ tagName: 'DIV', isContentEditable: true }), true);
assert.equal(isHtmlGridNativeEditorTarget({ tagName: 'INPUT', type: 'checkbox' }), false);

let focusedCell = null;
let prevented = false;
const controller = createKeyboardController({
    api: {
        getRows: () => [
            { rowId: 'row-1', values: {} },
            { rowId: 'row-2', values: {} },
        ],
        getCapabilities: () => ({}),
    },
    selection: {
        getActiveCell: () => ({ rowId: 'row-1', rowIndex: 0, columnKey: 'name' }),
        focusCell(rowIndex, columnKey) {
            focusedCell = { rowIndex, columnKey };
            return { executed: true };
        },
    },
    getVisibleColumns: () => [
        { key: 'name', editable: true, editor: 'text' },
        { key: 'description', editable: true, editor: 'text' },
    ],
});

const editorTarget = { tagName: 'INPUT', type: 'text' };
for (const key of ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Backspace', 'Delete', 'Home', 'End']) {
    prevented = false;
    focusedCell = null;
    const result = controller.handleKeyDown({
        key,
        target: editorTarget,
        preventDefault() {
            prevented = true;
        },
    });
    assert.equal(result.reason, 'native-editor-key');
    assert.equal(prevented, false);
    assert.equal(focusedCell, null);
}

prevented = false;
controller.handleKeyDown({
    key: 'ArrowRight',
    target: { tagName: 'DIV' },
    preventDefault() {
        prevented = true;
    },
});
assert.equal(prevented, true);
assert.deepEqual(focusedCell, { rowIndex: 0, columnKey: 'description' });

prevented = false;
focusedCell = null;
controller.handleKeyDown({
    key: 'Tab',
    target: editorTarget,
    preventDefault() {
        prevented = true;
    },
});
assert.equal(prevented, true);
assert.deepEqual(focusedCell, { rowIndex: 0, columnKey: 'description' });

const navigationOnlySelection = createSelectionController({
    api: {
        getRows: () => [{ rowId: 'row-1', values: {} }],
        getCapabilities: () => ({ selection: false }),
    },
    getVisibleColumns: () => [{ key: 'name', editable: true, editor: 'text' }],
    executeCommand: command => ({ executed: true, commandType: command.type }),
});
assert.deepEqual(
    navigationOnlySelection.focusCell(0, 'name'),
    { executed: true, commandType: 'move-cell' },
    '행 선택 기능이 꺼진 입력 Grid에서도 Tab 셀 포커스는 허용해야 합니다.'
);

console.log('공용 HTML Grid 편집 키보드 계약 검증 통과');
