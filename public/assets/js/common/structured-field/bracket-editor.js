import { MatrixEditor } from './matrix-editor.js?v=20260812-option-1';

export class BracketEditor extends MatrixEditor {
    constructor(options = {}) {
        const field = options.field || {};
        if (field.type !== 'bracket' || !Array.isArray(field.columns) || field.columns.length === 0) {
            throw new Error('Bracket 입력필드 계약이 올바르지 않습니다.');
        }
        super({ ...options, field });
    }
}
