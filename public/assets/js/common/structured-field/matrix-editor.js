import { createHtmlGrid } from '../html-grid/index.js?v=20260812-option-1';
import { percentToRate, rateToPercent } from '../format.js?v=20260811-rate-5';

const blank = value => value === null || value === undefined || String(value).trim() === '';
const numericTypes = new Set(['amount', 'number', 'rate']);
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
})[character]);

export function normalizeMatrixDimensions(values = []) {
    const normalized = values.map(value => String(value).trim()).filter(Boolean);
    return [...new Set(normalized)].sort((left, right) => Number(left) - Number(right));
}

export function buildDynamicMatrixColumns(columns = [], dimension = null, values = []) {
    const result = [...columns];
    if (!dimension) return result;
    const template = dimension.column || {};
    const prefix = String(template.code_prefix || 'dimension_');
    normalizeMatrixDimensions(values).forEach(value => result.push({
        ...template,
        code: `${prefix}${value}`,
        name: String(template.name_pattern || '{value}').replace('{value}', value),
    }));
    return result;
}

function normalizeWhitespace(value) {
    return String(value ?? '').replace(/[\u00a0\u1680\u2000-\u200b\u202f\u205f\u3000\ufeff]/g, ' ').trim();
}

export function normalizeMatrixCell(value, column = {}) {
    const text = normalizeWhitespace(value);
    if ((column.dash_as_zero === true || column.blank_as_zero === true) && text === '-') return 0;
    if (column.blank_as_zero === true && text === '') return 0;
    if (column.nullable === true && text === '') return null;
    if (!numericTypes.has(String(column.type || '').toLowerCase()) || text === '') return text;
    const number = Number(text.replaceAll(',', '').replace(/%$/, '').trim());
    return Number.isFinite(number) ? number : text;
}

export function normalizeMatrixRowsForStorage(rows = [], columns = []) {
    return rows.map(row => Object.fromEntries(columns.map(column => {
        const value = normalizeMatrixCell(row?.[column.code] ?? column.default_value ?? '', column);
        return [column.code, String(column.type || '').toLowerCase() === 'rate' && value !== '' && value !== null
            ? percentToRate(value)
            : value];
    })));
}

export function detectMatrixPasteFormat(text) {
    const firstLine = String(text || '').replace(/\r/g, '').split('\n').find(line => normalizeWhitespace(line) !== '') || '';
    return firstLine.includes('|') && !firstLine.includes('\t') ? 'markdown' : 'tsv';
}

function parsePasteLines(text, format) {
    return String(text || '').replace(/\r/g, '').split('\n')
        .map((line, index) => ({ line, lineNumber: index + 1 }))
        .filter(item => normalizeWhitespace(item.line) !== '')
        .map(item => {
            if (format === 'markdown') {
                let line = normalizeWhitespace(item.line);
                if (line.startsWith('|')) line = line.slice(1);
                if (line.endsWith('|')) line = line.slice(0, -1);
                return { ...item, cells: line.split('|').map(normalizeWhitespace) };
            }
            return { ...item, cells: item.line.split('\t').map(normalizeWhitespace) };
        });
}

function isMarkdownSeparator(cells) {
    return cells.length > 0 && cells.every(cell => /^:?-{3,}:?$/.test(normalizeWhitespace(cell)));
}

function isHeaderRow(cells, columns) {
    return cells.length === columns.length && columns.every((column, index) => (
        [normalizeWhitespace(column.code), normalizeWhitespace(column.name)].includes(normalizeWhitespace(cells[index]))
    ));
}

export function parseMatrixPaste(text, columns = []) {
    const format = detectMatrixPasteFormat(text);
    const parsedLines = parsePasteLines(text, format).filter(item => format !== 'markdown' || !isMarkdownSeparator(item.cells));
    if (parsedLines.length && isHeaderRow(parsedLines[0].cells, columns)) parsedLines.shift();
    const errors = [];
    const rows = [];
    parsedLines.forEach(item => {
        if (item.cells.length !== columns.length) {
            errors.push(`붙여넣기 ${item.lineNumber}행의 열 수가 ${columns.length}개가 아닙니다. 실제 ${item.cells.length}개입니다.`);
            return;
        }
        if (item.cells.every(cell => normalizeWhitespace(cell) === '')) return;
        const row = Object.fromEntries(columns.map((column, index) => [
            column.code, normalizeMatrixCell(item.cells[index], column),
        ]));
        if (Object.values(row).every(value => value === '' || value === null)) {
            errors.push(`붙여넣기 ${item.lineNumber}행에서 유효한 값을 인식하지 못했습니다.`);
            return;
        }
        rows.push(row);
    });
    if (!errors.length && rows.length === 0) errors.push('붙여넣기 데이터에서 유효한 값을 인식하지 못했습니다.');
    return { format, rows, errors, preview: rows.slice(0, 3) };
}

export function parseMatrixTsv(text, columns = []) {
    return parseMatrixPaste(text, columns).rows;
}

export function createMatrixGridRows(rows = [], columns = []) {
    const columnCodes = columns.map(column => String(column.code || '')).filter(Boolean);
    return rows.filter(row => row && typeof row === 'object' && columnCodes.some(code => (
        row[code] !== '' && row[code] !== null && row[code] !== undefined
    ))).map((values, index) => ({
        rowId: `row-${index + 1}`,
        values: { ...values },
    }));
}

export function buildMatrixObjectValue(rows, dimensions, storageColumns, objectConfig, dimensionConfig, rawValue = null) {
    const rowsKey = String(objectConfig?.rows_key || 'rows');
    const dimensionKey = String(dimensionConfig?.key || 'dimensions');
    const mapKey = String(dimensionConfig?.row_map_key || 'values');
    const prefix = String(dimensionConfig?.column?.code_prefix || 'dimension_');
    const baseColumnCodes = new Set(storageColumns.map(column => column.code));
    const nestedRows = rows.map(row => {
        const nested = Object.fromEntries([...baseColumnCodes].map(code => [code, row[code]]));
        nested[mapKey] = Object.fromEntries(dimensions.map(dimension => [
            String(dimension), row[`${prefix}${dimension}`],
        ]));
        return nested;
    });
    const preserved = rawValue && !Array.isArray(rawValue) ? { ...rawValue } : {};
    delete preserved[rowsKey];
    delete preserved[dimensionKey];
    return { ...objectConfig?.defaults, ...preserved, [dimensionKey]: dimensions, [rowsKey]: nestedRows };
}

function yieldMatrixPasteFrame() {
    return new Promise(resolve => {
        if (typeof requestAnimationFrame === 'function') requestAnimationFrame(() => resolve());
        else setTimeout(resolve, 0);
    });
}

export async function parseMatrixPasteInChunks(text, columns = [], options = {}) {
    const format = detectMatrixPasteFormat(text);
    const parsedLines = parsePasteLines(text, format).filter(item => format !== 'markdown' || !isMarkdownSeparator(item.cells));
    if (parsedLines.length && isHeaderRow(parsedLines[0].cells, columns)) parsedLines.shift();
    const chunkSize = Math.max(25, Number(options.chunkSize) || 75);
    const total = parsedLines.length;
    const errors = [];
    const rows = [];
    options.onProgress?.({ stage: '입력 분석 중...', completed: 0, total, percent: total ? 5 : 100 });
    await yieldMatrixPasteFrame();
    for (let offset = 0; offset < total; offset += chunkSize) {
        parsedLines.slice(offset, offset + chunkSize).forEach(item => {
            if (item.cells.length !== columns.length) {
                errors.push(`붙여넣기 ${item.lineNumber}행의 열 수가 ${columns.length}개가 아닙니다. 실제 ${item.cells.length}개입니다.`);
                return;
            }
            if (item.cells.every(cell => normalizeWhitespace(cell) === '')) return;
            const row = Object.fromEntries(columns.map((column, index) => [
                column.code, normalizeMatrixCell(item.cells[index], column),
            ]));
            if (Object.values(row).every(value => value === '' || value === null)) {
                errors.push(`붙여넣기 ${item.lineNumber}행에서 유효한 값을 인식하지 못했습니다.`);
                return;
            }
            rows.push(row);
        });
        const completed = Math.min(offset + chunkSize, total);
        options.onProgress?.({
            stage: '행 파싱 및 값 정규화 중...', completed, total,
            percent: total ? Math.round(5 + (completed / total) * 75) : 80,
        });
        await yieldMatrixPasteFrame();
    }
    if (!errors.length && rows.length === 0) errors.push('붙여넣기 데이터에서 유효한 값을 인식하지 못했습니다.');
    return { format, rows, errors, preview: rows.slice(0, 3) };
}

export function validateMatrixRows(rows = [], columns = [], required = false, options = {}) {
    const messages = [];
    const validationLimit = (limit, column) => String(column.type || '').trim().toLowerCase() === 'rate'
        ? rateToPercent(limit)
        : Number(limit);
    if (required && rows.length === 0) messages.push('최소 1행 이상 입력해야 합니다.');
    rows.forEach((row, rowIndex) => columns.forEach(column => {
        const value = row[column.code];
        if (column.required && blank(value)) messages.push(`${rowIndex + 1}행 ${column.name}은(는) 필수입니다.`);
        if (!blank(value) && numericTypes.has(String(column.type || '').toLowerCase()) && !Number.isFinite(Number(value))) {
            messages.push(`${rowIndex + 1}행 ${column.name}은(는) 숫자여야 합니다.`);
        }
        if (!blank(value) && numericTypes.has(String(column.type || '').toLowerCase())
            && column.allow_negative !== true && Number(value) < 0) messages.push(`${rowIndex + 1}행 ${column.name}은(는) 음수일 수 없습니다.`);
        if (!blank(value) && column.min != null && Number(value) < validationLimit(column.min, column)) messages.push(`${rowIndex + 1}행 ${column.name} 최솟값이 올바르지 않습니다.`);
        if (!blank(value) && column.max != null && Number(value) > validationLimit(column.max, column)) messages.push(`${rowIndex + 1}행 ${column.name} 최댓값이 올바르지 않습니다.`);
    }));
    const fromColumn = columns.find(column => column.range_role === 'from');
    const toColumn = columns.find(column => column.range_role === 'to');
    if (fromColumn && toColumn) rows.forEach((row, rowIndex) => {
        if (!blank(row[fromColumn.code]) && !blank(row[toColumn.code])
            && (fromColumn.allow_equal_to === true
                ? Number(row[fromColumn.code]) > Number(row[toColumn.code])
                : Number(row[fromColumn.code]) >= Number(row[toColumn.code]))) {
            messages.push(`${rowIndex + 1}행 구간 시작값은 종료값보다 작아야 합니다.`);
        }
    });
    if (fromColumn && toColumn) {
        const groupColumns = columns.filter(column => column.group_key === true);
        const lastEnds = new Map();
        rows.forEach((row, rowIndex) => {
            const group = JSON.stringify(groupColumns.map(column => row[column.code] ?? ''));
            const from = Number(row[fromColumn.code]);
            const to = Number(row[toColumn.code]);
            if (Number.isFinite(from) && lastEnds.has(group) && from < lastEnds.get(group)) {
                messages.push(`${rowIndex + 1}행 급여 구간이 앞 행과 겹칩니다.`);
            }
            if (blank(row[toColumn.code]) && rowIndex !== rows.length - 1) messages.push(`${rowIndex + 1}행의 종료값 없음은 마지막 구간에서만 허용됩니다.`);
            if (options.strictContiguous === true && Number.isFinite(from) && lastEnds.has(group)
                && from !== lastEnds.get(group)) {
                messages.push(`${rowIndex + 1}행의 구간 시작금액은 이전 구간 종료금액과 같아야 합니다.`);
            }
            if (Number.isFinite(to) && !blank(row[toColumn.code])) lastEnds.set(group, to);
        });
    }
    const keyColumns = columns.filter(column => column.key_part === true);
    if (keyColumns.length) {
        const seen = new Set();
        rows.forEach((row, rowIndex) => {
            const key = JSON.stringify(keyColumns.map(column => row[column.code] ?? ''));
            if (seen.has(key)) messages.push(`${rowIndex + 1}행의 기준 구간이 중복되었습니다.`);
            seen.add(key);
        });
    }
    return [...new Set(messages)];
}

export class MatrixEditor {
    constructor({ host, field, value = [] }) {
        this.host = host;
        this.field = field || {};
        this.objectConfig = this.field.object_storage || null;
        this.dimensionConfig = this.field.dynamic_dimension || null;
        this.ui = this.field.ui || {};
        this.storageColumns = Array.isArray(this.field.columns) ? this.field.columns : [];
        this.rawValue = value;
        this.dimensions = this.readDimensions(value);
        this.columns = this.buildColumns();
        this.rows = this.readRows(value);
        this.grid = null;
        this.pasteBusy = false;
        this.renderShell();
        this.rebuild(this.rows);
    }

    renderShell() {
        this.host.classList.add('structured-matrix-editor');
        const dimensionControl = this.dimensionConfig ? `
            <div class="structured-matrix-dimension${this.ui.show_dimension_control === true ? '' : ' d-none'}" data-matrix-dimension-panel>
                <label class="form-label">${String(this.dimensionConfig.name || '동적 열')}</label>
                <input type="text" class="form-control form-control-sm" data-matrix-dimensions value="${this.dimensions.join(', ')}" placeholder="예: 1, 2, 3">
                <small class="text-muted">쉼표로 구분합니다. 변경하면 열이 동적으로 다시 구성됩니다.</small>
            </div>` : '';
        const expanded = this.ui.default_expanded !== false;
        const collapsibleHeader = this.ui.collapsible === true ? `
            <button type="button" class="structured-matrix-section-toggle${expanded ? '' : ' collapsed'}" data-matrix-section-toggle aria-expanded="${expanded}">
                <span><strong>${escapeHtml(this.ui.title || this.field.name || '표 입력')}</strong>${this.ui.description ? `<small>${escapeHtml(this.ui.description)}</small>` : ''}</span>
                <span class="structured-matrix-section-summary" data-matrix-section-summary></span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>` : '';
        const advancedButton = this.dimensionConfig && this.ui.allow_dimension_change === true ? `
            <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-dimension-toggle>가족수 범위 변경</button>` : '';
        const pasteButton = this.ui.allow_paste !== false ? `
            <button type="button" class="btn btn-outline-secondary" data-matrix-paste-toggle>엑셀/표 붙여넣기</button>` : '';
        const pastePanel = this.ui.allow_paste !== false ? `
            <div class="structured-matrix-paste d-none" data-matrix-paste-panel>
                <div><small class="text-muted d-block mb-1">엑셀 표 또는 Markdown 표를 그대로 붙여넣을 수 있습니다.</small><textarea class="form-control form-control-sm" rows="5" placeholder="표를 여기에 붙여넣으세요."></textarea></div>
                <div class="structured-matrix-paste-actions">
                    <button type="button" class="btn btn-primary btn-sm" data-matrix-paste-apply>붙여넣기 적용</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-matrix-paste-cancel>취소</button>
                </div>
                <div class="structured-matrix-paste-progress d-none" data-matrix-paste-progress aria-live="polite">
                    <div class="d-flex justify-content-between gap-2"><span data-matrix-paste-stage></span><span data-matrix-paste-numbers></span></div>
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-bar" data-matrix-paste-progress-bar style="width:0%"></div>
                    </div>
                </div>
            </div>` : '';
        this.host.innerHTML = `${collapsibleHeader}<div data-matrix-section-body${expanded ? '' : ' class="d-none"'}>${dimensionControl}
            <div class="structured-matrix-toolbar">
                <div class="btn-group btn-group-sm">
                    ${pasteButton}
                    <button type="button" class="btn btn-outline-primary" data-matrix-add>행 추가</button>
                    <button type="button" class="btn btn-outline-danger" data-matrix-delete>선택행 삭제</button>
                    <button type="button" class="btn btn-outline-danger" data-matrix-clear>전체삭제</button>
                    ${advancedButton}
                </div>
                <span class="structured-matrix-count" data-matrix-count></span>
            </div>
            ${pastePanel}
            <div class="structured-matrix-grid html-grid-host" data-matrix-grid></div>
            <div class="invalid-feedback d-block" data-matrix-error></div></div>`;
        this.gridHost = this.host.querySelector('[data-matrix-grid]');
        this.host.querySelector('[data-matrix-section-toggle]')?.addEventListener('click', event => {
            const button = event.currentTarget;
            const body = this.host.querySelector('[data-matrix-section-body]');
            const nextExpanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(nextExpanded));
            button.classList.toggle('collapsed', !nextExpanded);
            body?.classList.toggle('d-none', !nextExpanded);
        });
        this.host.querySelector('[data-matrix-dimension-toggle]')?.addEventListener('click', () => {
            this.host.querySelector('[data-matrix-dimension-panel]')?.classList.toggle('d-none');
        });
        this.host.querySelector('[data-matrix-dimensions]')?.addEventListener('change', event => {
            const rows = this.getFlatRows();
            this.dimensions = this.normalizeDimensions(String(event.target.value || '').split(','));
            event.target.value = this.dimensions.join(', ');
            this.columns = this.buildColumns();
            this.rebuild(rows);
        });
        this.host.querySelector('[data-matrix-add]').addEventListener('click', () => {
            const result = this.grid.addRow({ values: this.blankRow() });
            const firstEditableColumn = this.columns.find(column => column.hidden !== true && column.editable !== false);
            if (!result?.executed || !firstEditableColumn) return;
            requestAnimationFrame(() => {
                this.grid.focusCell(result.rowIndex, firstEditableColumn.code);
                this.grid.beginEdit(result.rowIndex, firstEditableColumn.code);
            });
        });
        this.host.querySelector('[data-matrix-delete]').addEventListener('click', () => {
            const index = this.grid.getState().selection?.activeCell?.rowIndex ?? -1;
            if (index >= 0) this.grid.deleteRow(index);
        });
        this.host.querySelector('[data-matrix-clear]').addEventListener('click', () => this.rebuild([]));
        this.host.querySelector('[data-matrix-paste-toggle]')?.addEventListener('click', () => {
            if (this.pasteBusy) return;
            this.host.querySelector('[data-matrix-paste-panel]').classList.remove('d-none');
            this.host.querySelector('[data-matrix-paste-panel] textarea')?.focus();
        });
        this.host.querySelector('[data-matrix-paste-cancel]')?.addEventListener('click', () => this.closePastePanel());
        this.host.querySelector('[data-matrix-paste-apply]')?.addEventListener('click', async () => {
            if (this.pasteBusy) return;
            const textarea = this.host.querySelector('[data-matrix-paste-panel] textarea');
            const currentCount = this.getNormalizedRows().length;
            if (currentCount > 0 && !window.confirm(`현재 등록된 간이세액표 ${currentCount}건을 새 데이터로 교체합니다.\n계속하시겠습니까?`)) return;
            const pasteColumns = this.columns.filter(column => column.hidden !== true);
            this.setPasteBusy(true);
            this.updatePasteProgress({ stage: '입력 분석 중...', completed: 0, total: 0, percent: 0 });
            try {
                const result = await parseMatrixPasteInChunks(textarea.value, pasteColumns, {
                    onProgress: progress => this.updatePasteProgress(progress),
                });
                if (result.errors.length) {
                    this.host.querySelector('[data-matrix-error]').textContent = result.errors[0];
                    return;
                }
                this.updatePasteProgress({ stage: '전체 데이터 검증 중...', completed: result.rows.length, total: result.rows.length, percent: 85 });
                await yieldMatrixPasteFrame();
                const validationErrors = validateMatrixRows(result.rows, this.columns, this.field.required === true, {
                    strictContiguous: this.field.ui?.strict_contiguous === true,
                });
                if (validationErrors.length) {
                    this.host.querySelector('[data-matrix-error]').textContent = validationErrors[0];
                    return;
                }
                this.updatePasteProgress({ stage: 'Grid 반영 중...', completed: result.rows.length, total: result.rows.length, percent: 95 });
                await yieldMatrixPasteFrame();
                this.rebuild(result.rows);
                this.host.querySelector('[data-matrix-error]').textContent = '';
                textarea.value = '';
                this.updatePasteProgress({ stage: '완료', completed: result.rows.length, total: result.rows.length, percent: 100 });
                await yieldMatrixPasteFrame();
                this.setPasteBusy(false);
                this.closePastePanel();
                window.AppCore?.notify?.('success', `간이세액표 ${result.rows.length}건을 적용했습니다.`);
            } finally {
                this.setPasteBusy(false);
            }
        });
    }

    setPasteBusy(busy) {
        this.pasteBusy = busy;
        this.host.setAttribute('aria-busy', String(busy));
        this.host.querySelectorAll('[data-matrix-paste-apply], [data-matrix-paste-cancel], [data-matrix-add], [data-matrix-delete], [data-matrix-clear], [data-matrix-paste-toggle]')
            .forEach(control => { control.disabled = busy; });
        this.host.closest('.modal')?.querySelectorAll('button[type="submit"]')
            .forEach(control => { control.disabled = busy; });
    }

    updatePasteProgress({ stage, completed, total, percent }) {
        const progress = this.host.querySelector('[data-matrix-paste-progress]');
        const normalizedPercent = Math.max(0, Math.min(100, Number(percent) || 0));
        progress?.classList.remove('d-none');
        if (progress) progress.querySelector('[role="progressbar"]')?.setAttribute('aria-valuenow', String(normalizedPercent));
        const bar = progress?.querySelector('[data-matrix-paste-progress-bar]');
        if (bar) bar.style.width = `${normalizedPercent}%`;
        const stageNode = progress?.querySelector('[data-matrix-paste-stage]');
        if (stageNode) stageNode.textContent = String(stage || '');
        const numbers = progress?.querySelector('[data-matrix-paste-numbers]');
        if (numbers) numbers.textContent = total > 0 ? `${completed} / ${total}행 · ${normalizedPercent}%` : `${normalizedPercent}%`;
    }

    closePastePanel() {
        if (this.pasteBusy) return;
        const panel = this.host.querySelector('[data-matrix-paste-panel]');
        const textarea = panel?.querySelector('textarea');
        if (textarea) textarea.value = '';
        panel?.querySelector('[data-matrix-paste-progress]')?.classList.add('d-none');
        panel?.classList.add('d-none');
        this.host.querySelector('[data-matrix-error]').textContent = '';
    }

    blankRow() {
        return Object.fromEntries(this.columns.map(column => [
            column.code,
            column.type === 'rate' && column.default_value !== undefined
                ? rateToPercent(column.default_value)
                : (column.default_value ?? ''),
        ]));
    }

    readDimensions(value) {
        if (!this.dimensionConfig) return [];
        const key = String(this.dimensionConfig.key || 'dimensions');
        const configured = value && !Array.isArray(value) ? value[key] : null;
        return this.normalizeDimensions(Array.isArray(configured) ? configured : (this.dimensionConfig.default || []));
    }

    normalizeDimensions(values) {
        return normalizeMatrixDimensions(values);
    }

    buildColumns() {
        return buildDynamicMatrixColumns(
            this.storageColumns,
            this.dimensionConfig,
            this.dimensions
        );
    }

    readRows(value) {
        const toDisplayRow = row => Object.fromEntries(this.columns.map(column => {
            const cell = row?.[column.code] ?? column.default_value ?? '';
            return [column.code, column.type === 'rate' && cell !== '' && cell !== null ? rateToPercent(cell) : cell];
        }));
        if (!this.objectConfig) return (Array.isArray(value) ? value : []).map(toDisplayRow);
        const rowsKey = String(this.objectConfig.rows_key || 'rows');
        const mapKey = String(this.dimensionConfig?.row_map_key || 'values');
        const prefix = String(this.dimensionConfig?.column?.code_prefix || 'dimension_');
        return (Array.isArray(value?.[rowsKey]) ? value[rowsKey] : []).map(row => {
            const flat = { ...row };
            const map = row?.[mapKey] || {};
            this.dimensions.forEach(dimension => { flat[`${prefix}${dimension}`] = map[String(dimension)] ?? ''; });
            delete flat[mapKey];
            return toDisplayRow(flat);
        });
    }

    getFlatRows() {
        return (this.grid?.serialize?.().rows || []).map(row => ({ ...row }));
    }

    rebuild(rows) {
        this.grid?.destroy?.();
        this.gridHost.innerHTML = '';
        const gridRows = createMatrixGridRows(rows, this.columns);
        const pinnedColumnCount = Math.max(0, Number(this.field.ui?.pinned_column_count ?? (this.dimensionConfig ? 2 : 0)) || 0);
        this.grid = createHtmlGrid({
            host: this.gridHost,
            gridId: `matrix-${String(this.field.code || 'field')}`,
            rows: gridRows,
            commitEditorsOnChange: true,
            commitEditorsBeforeRead: true,
            columns: this.columns.map((column, columnIndex) => ({
                key: column.code,
                label: column.name,
                type: String(column.type || '').toLowerCase() === 'rate'
                    ? 'rate'
                    : (String(column.type || '').toLowerCase() === 'amount'
                        ? 'amount'
                        : (numericTypes.has(String(column.type || '').toLowerCase()) ? 'number' : 'text')),
                editor: String(column.type || '').toLowerCase() === 'select' ? 'select' : (numericTypes.has(String(column.type || '').toLowerCase()) ? 'number' : 'text'),
                formatter: String(column.type || '').toLowerCase() === 'select'
                    ? 'option'
                    : (String(column.type || '').toLowerCase() === 'rate'
                    ? 'percent'
                    : (numericTypes.has(String(column.type || '').toLowerCase()) ? 'number' : 'text')),
                required: column.required === true,
                visible: column.hidden !== true,
                pinned: column.pinned || (columnIndex < pinnedColumnCount ? 'left' : null),
                width: Number(column.width || 160),
                meta: {
                    formatterOptions: String(column.type || '').toLowerCase() === 'rate'
                        ? { inputScale: 'percent' }
                        : (String(column.type || '').toLowerCase() === 'select' ? { options: column.options || [] } : {}),
                    editorOptions: { allowNegative: column.allow_negative === true, options: column.options || [] },
                },
            })),
            hooks: { serializer: { serializeRow: ({ row }) => row.rowState === 'deleted' ? null : row.values } },
            capabilities: { addRow: true, deleteRow: true, keyboard: true, selection: true, columnResize: true, clipboard: false, footer: false },
        });
        ['row:added', 'row:deleted', 'cell:changed'].forEach(event => this.grid.on(event, () => this.updateCount()));
        this.grid.render({ noDataMessage: '등록된 표 데이터가 없습니다.' });
        this.updateCount();
    }

    getDisplayRows() {
        return (this.grid?.serialize?.().rows || []).map(row => Object.fromEntries(this.columns.map(column => [
            column.code,
            normalizeMatrixCell(row?.[column.code] ?? column.default_value ?? '', column),
        ])));
    }

    getNormalizedRows() {
        const rows = normalizeMatrixRowsForStorage(this.getDisplayRows(), this.columns);
        const sortColumns = this.columns.filter(column => column.sort_order != null)
            .sort((left, right) => Number(left.sort_order) - Number(right.sort_order));
        if (sortColumns.length) rows.sort((left, right) => {
            for (const column of sortColumns) {
                const result = left[column.code] < right[column.code] ? -1 : (left[column.code] > right[column.code] ? 1 : 0);
                if (result !== 0) return result;
            }
            return 0;
        });
        return rows;
    }

    getValue() {
        const rows = this.getNormalizedRows();
        if (!this.objectConfig) return rows;
        return buildMatrixObjectValue(
            rows,
            this.dimensions,
            this.storageColumns,
            this.objectConfig,
            this.dimensionConfig,
            this.rawValue
        );
    }

    validate() {
        const messages = validateMatrixRows(this.getDisplayRows(), this.columns, this.field.required === true, {
            strictContiguous: this.field.ui?.strict_contiguous === true,
        });
        if (this.dimensionConfig && this.dimensions.length === 0) messages.unshift(`${this.dimensionConfig.name || '동적 열'}이 필요합니다.`);
        this.host.querySelector('[data-matrix-error]').textContent = messages[0] || '';
        if (messages.length) this.grid?.focusFirstError?.();
        return messages;
    }

    updateCount() {
        const count = this.getNormalizedRows().length;
        this.host.querySelector('[data-matrix-count]').textContent = `등록 행 수: ${count}건`;
        const summary = this.host.querySelector('[data-matrix-section-summary]');
        if (summary) {
            const dimensionSummary = this.dimensionConfig && this.dimensions.length
                ? ` · 가족수 ${this.dimensions[0]}~${this.dimensions.at(-1)}명`
                : '';
            summary.textContent = `${count}건${dimensionSummary}`;
        }
    }
    destroy() { this.grid?.destroy?.(); this.host.innerHTML = ''; }
}
