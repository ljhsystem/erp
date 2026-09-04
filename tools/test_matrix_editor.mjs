import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
    buildDynamicMatrixColumns,
    buildMatrixObjectValue,
    createMatrixGridRows,
    detectMatrixPasteFormat,
    normalizeMatrixDimensions,
    normalizeMatrixRowsForStorage,
    parseMatrixPaste,
    parseMatrixPasteInChunks,
    parseMatrixTsv,
    validateMatrixRows,
} from '../public/assets/js/common/structured-field/matrix-editor.js';
import { formatNumber, formatOption, formatPercent } from '../public/assets/js/common/html-grid/formatter.js';
import { createInitialGridState } from '../public/assets/js/common/html-grid/state.js';
import { percentToRate, rateToPercent } from '../public/assets/js/common/format.js';

const matrixSource = fs.readFileSync(new URL('../public/assets/js/common/structured-field/matrix-editor.js', import.meta.url), 'utf8');
const bracketSource = fs.readFileSync(new URL('../public/assets/js/common/structured-field/bracket-editor.js', import.meta.url), 'utf8');
const gridCss = fs.readFileSync(new URL('../public/assets/css/components/html-grid.css', import.meta.url), 'utf8');
const matrixCss = fs.readFileSync(new URL('../public/assets/css/components/structured-field-editor.css', import.meta.url), 'utf8');
const gridBodySource = fs.readFileSync(new URL('../public/assets/js/common/html-grid/body-renderer.js', import.meta.url), 'utf8');
const gridCoreSource = fs.readFileSync(new URL('../public/assets/js/common/html-grid/index.js', import.meta.url), 'utf8');
const gridHeaderSource = fs.readFileSync(new URL('../public/assets/js/common/html-grid/header-renderer.js', import.meta.url), 'utf8');
const statutoryPageSource = fs.readFileSync(new URL('../public/assets/js/pages/main/settings/statutory-standards/index.js', import.meta.url), 'utf8');
assert.match(matrixSource, /structured-matrix-grid html-grid-host/);
assert.match(bracketSource, /class BracketEditor extends MatrixEditor/);
assert.equal(formatOption('TABLE', { options: [{ value: 'TABLE', label: '공식표 기준세액' }] }), '공식표 기준세액');
assert.match(matrixSource, /\? 'option'/);
assert.match(gridCoreSource, /option: formatOption/);
assert.match(statutoryPageSource, /fieldType === 'bracket'/);
assert.match(statutoryPageSource, /standardCalculationPolicySection/);
assert.match(statutoryPageSource, /data-policy-key/);
assert.match(statutoryPageSource, /collectedValues\.calculation_policy = calculationPolicy/);
assert.match(statutoryPageSource, /세율 \$\{rateRange\}/);
assert.match(matrixSource, /this\.ui\.allow_paste !== false/);
assert.match(matrixSource, /data-matrix-paste-cancel/);
assert.match(matrixSource, /this\.rebuild\(result\.rows\)/);
assert.doesNotMatch(matrixSource, /this\.rebuild\(\[\.\.\.this\.getNormalizedRows\(\)/);
assert.match(gridCss, /position: sticky; top: 0/);
assert.match(gridCss, /html-grid-cell-type-number/);
assert.match(matrixCss, /max-height: min\(48vh, 520px\)/);
assert.match(gridBodySource, /classList\.add\('is-selected'\)/);
assert.match(gridCoreSource, /eventBus\.on\('selection:changed'/);
assert.match(gridCoreSource, /String\(state\.rows\[rowIndex\]\?\.values\?\.\[columnKey\] \?\? ''\) === String\(value \?\? ''\)/);
assert.match(gridCoreSource, /eventBus\.on\('selection:changed', \(\) => \{\s*syncSelectionDom\(\);/);
assert.match(gridCoreSource, /classList\.add\('is-pinned-left'\)/);
assert.match(gridCoreSource, /--html-grid-pinned-left/);
assert.match(gridHeaderSource, /classList\.add\('has-resize-handle'\)/);
assert.match(matrixSource, /pinned_column_count \?\? \(this\.dimensionConfig \? 2 : 0\)/);
assert.match(gridCss, /\.html-grid-header-cell\.is-pinned-left/);
assert.match(gridCss, /\.html-grid-header-cell\.has-resize-handle/);
assert.match(gridCss, /html-grid-header-resize-handle::after[\s\S]*opacity: 0/);
assert.match(gridCss, /html-grid-header-resize-handle:hover::after/);
assert.match(statutoryPageSource, /document\.querySelector\('\[data-matrix-field\]'\) !== null/);
assert.match(statutoryPageSource, /structuredEditors\.size > 0/);
assert.doesNotMatch(statutoryPageSource, /formatScopeData|data: 'scope_data'|data-scope-key|scope_fields|적용조건/);

const columns = [
    { code: 'from', name: '이상', type: 'amount', required: true, range_role: 'from', key_part: true },
    { code: 'to', name: '미만', type: 'amount', required: true, range_role: 'to', key_part: true },
    { code: 'family', name: '가족수', type: 'number', required: true, key_part: true, group_key: true },
    { code: 'tax', name: '세액', type: 'amount', required: true },
];
const excessColumns = [
    { code: 'salary_from', name: '시작금액', type: 'amount', required: true, range_role: 'from', key_part: true },
    { code: 'salary_to', name: '종료금액', type: 'amount', required: false, nullable: true, range_role: 'to' },
    { code: 'base_salary', name: '기준급여', type: 'amount', required: true },
    { code: 'excess_base_rate', name: '초과금액 반영률', type: 'rate', required: true, min: 0, max: 1 },
    { code: 'tax_rate', name: '세율', type: 'rate', required: true, min: 0, max: 1 },
    { code: 'fixed_addition', name: '가산액', type: 'amount', required: true },
];
const excessStoredRows = normalizeMatrixRowsForStorage([
    { salary_from: 10000000, salary_to: 28000000, base_salary: 10000000, excess_base_rate: 95, tax_rate: 35, fixed_addition: 0 },
    { salary_from: 28000000, salary_to: null, base_salary: 10000000, excess_base_rate: 95, tax_rate: 38, fixed_addition: 5985000 },
], excessColumns);
const excessDisplayRows = [
    { salary_from: 10000000, salary_to: 28000000, base_salary: 10000000, excess_base_rate: 95, tax_rate: 35, fixed_addition: 0 },
    { salary_from: 28000000, salary_to: null, base_salary: 10000000, excess_base_rate: 95, tax_rate: 38, fixed_addition: 5985000 },
];
if (validateMatrixRows(excessDisplayRows, excessColumns, false).length !== 0
    || validateMatrixRows([{ ...excessDisplayRows[0], excess_base_rate: 100, tax_rate: 100 }], excessColumns, false).length !== 0
    || !validateMatrixRows([{ ...excessDisplayRows[0], excess_base_rate: 101 }], excessColumns, false)
        .some(message => message.includes('초과금액 반영률 최댓값'))
    || excessStoredRows[0].excess_base_rate !== 0.95 || excessStoredRows[0].tax_rate !== 0.35
    || excessStoredRows[1].tax_rate !== 0.38 || excessStoredRows[1].salary_to !== null
    || validateMatrixRows(excessStoredRows, excessColumns, false).length !== 0
    || rateToPercent(excessStoredRows[0].excess_base_rate) !== 95
    || rateToPercent(excessStoredRows[0].tax_rate) !== 35
    || rateToPercent(excessStoredRows[1].tax_rate) !== 38
    || percentToRate(rateToPercent(excessStoredRows[0].excess_base_rate)) !== 0.95
    || rateToPercent(0.02945) !== 2.945 || percentToRate(2.945) !== 0.02945
    || formatPercent(95, { inputScale: 'percent' }) !== '95%') {
    throw new Error('초과계산기준 rate 단위 또는 마지막 무제한 구간 검증에 실패했습니다.');
}
if (validateMatrixRows([
    { salary_from: 0, salary_to: 200000000, excess_base_rate: 10, tax_rate: 10, fixed_addition: 0 },
    { salary_from: 300000000, salary_to: null, excess_base_rate: 20, tax_rate: 20, fixed_addition: 0 },
], excessColumns, false, { strictContiguous: true }).length === 0) {
    throw new Error('Bracket 구간 공백 검증에 실패했습니다.');
}
const rows = parseMatrixTsv('이상\t미만\t가족수\t세액\n1,000\t1,100\t1\t20\n1,100\t1,200\t1\t30', columns);
if (rows.length !== 2 || rows[1].tax !== 30) throw new Error('TSV 파싱 검증에 실패했습니다.');
if (validateMatrixRows(rows, columns, true).length !== 0) throw new Error('정상 Matrix Validation에 실패했습니다.');
if (!validateMatrixRows([{ from: 2, to: 1, family: 1, tax: 0 }], columns, true).some(message => message.includes('시작값'))) {
    throw new Error('구간 역전 검증에 실패했습니다.');
}
const zeroColumns = [{ code: 'tax', name: '세액', type: 'amount', required: true, blank_as_zero: true }];
const zeroRows = parseMatrixTsv('-\n \n', zeroColumns);
if (zeroRows.length !== 1 || zeroRows[0].tax !== 0) throw new Error('세액 없음값 0원 정규화에 실패했습니다.');
const taxColumns = [
    { code: 'salary_from', name: '월급여액 이상', type: 'amount', required: true },
    { code: 'salary_to', name: '월급여액 미만', type: 'amount', nullable: true },
    ...range(1, 11).map(count => ({ code: `dependent_tax_${count}`, name: `가족수 ${count}명`, type: 'amount', dash_as_zero: true })),
];
const markdown = [
    `| ${taxColumns.map(column => column.name).join(' | ')} |`,
    `| ${taxColumns.map(() => '---').join(' | ')} |`,
    `| 0 | 775,000 | ${range(1, 11).map(() => '-').join(' | ')} |`,
    `| 775,000 | 780,000 | ${range(1, 11).map(() => '\u00a0-\u00a0').join(' | ')} |`,
    `| 1,340,000 | 1,345,000 | \u00a0\u00a04,440 | \u00a0\u00a01,060 | ${range(3, 11).map(() => '\u00a0-').join(' | ')} |`,
    `| 10,000,000 | \u3000 | \u00a0\u00a01,429,140 | \u00a0\u00a01,385,390 | ${range(3, 11).map(() => '\u00a0-').join(' | ')} |`,
].join('\n');
const markdownResult = parseMatrixPaste(markdown, taxColumns);
if (detectMatrixPasteFormat(markdown) !== 'markdown' || markdownResult.errors.length !== 0 || markdownResult.rows.length !== 4) {
    throw new Error('Markdown 표 자동판별 또는 구분선 제거 검증에 실패했습니다.');
}
if (markdownResult.rows[0].salary_from !== 0 || markdownResult.rows[0].salary_to !== 775000
    || markdownResult.rows[1].dependent_tax_1 !== 0
    || markdownResult.rows[2].dependent_tax_1 !== 4440 || markdownResult.rows[2].dependent_tax_2 !== 1060
    || markdownResult.rows[3].salary_to !== null || markdownResult.rows[3].dependent_tax_1 !== 1429140
    || markdownResult.rows[3].dependent_tax_2 !== 1385390) {
    throw new Error('Markdown 금액·NBSP·대시·nullable 정규화 검증에 실패했습니다.');
}
const gridRows = createMatrixGridRows(markdownResult.rows, taxColumns);
const gridState = createInitialGridState({ rows: gridRows });
if (gridRows.length !== markdownResult.rows.length
    || gridState.rows.length !== markdownResult.rows.length
    || gridRows[0].values.salary_from !== 0 || gridRows[0].values.dependent_tax_11 !== 0
    || gridRows[2].values.salary_from !== 1340000 || gridRows[2].values.dependent_tax_1 !== 4440
    || gridRows[3].values.salary_to !== null || gridRows[3].values.dependent_tax_2 !== 1385390
    || formatNumber(gridState.rows[2].values.salary_from) !== '1,340,000'
    || formatNumber(gridState.rows[2].values.dependent_tax_1) !== '4,440'
    || createMatrixGridRows([{}, { salary_from: null, salary_to: '' }], taxColumns).length !== 0) {
    throw new Error('Matrix Grid row.values Adapter 또는 빈 행 차단 검증에 실패했습니다.');
}
const matrixValue = buildMatrixObjectValue(
    markdownResult.rows,
    range(1, 11),
    taxColumns.slice(0, 2),
    { rows_key: 'rows', defaults: { salary_unit: 'KRW' } },
    { key: 'dependent_counts', row_map_key: 'tax_by_dependents', column: { code_prefix: 'dependent_tax_' } }
);
if (matrixValue.rows.length !== markdownResult.rows.length
    || matrixValue.rows[2].salary_from !== 1340000
    || matrixValue.rows[2].tax_by_dependents['1'] !== 4440
    || matrixValue.rows[2].tax_by_dependents['3'] !== 0
    || matrixValue.rows[3].salary_to !== null
    || matrixValue.rows[3].tax_by_dependents['2'] !== 1385390) {
    throw new Error('flat row에서 tax_by_dependents로 변환하는 계약 검증에 실패했습니다.');
}
const tsvEquivalent = markdownResult.rows.map(row => taxColumns.map(column => row[column.code] ?? '').join('\t')).join('\n');
const tsvResult = parseMatrixPaste(tsvEquivalent, taxColumns);
if (tsvResult.errors.length !== 0 || JSON.stringify(tsvResult.rows) !== JSON.stringify(markdownResult.rows)) {
    throw new Error('TSV와 Markdown 동일 결과 검증에 실패했습니다.');
}
const invalidMarkdown = parseMatrixPaste('| 1 | 2 | 3 |', taxColumns);
if (!invalidMarkdown.errors[0]?.includes('13개가 아닙니다. 실제 3개')) {
    throw new Error('Markdown 열 수 오류 검증에 실패했습니다.');
}
const futureDimensions = normalizeMatrixDimensions([12, 1, 2, 12, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
const futureColumns = buildDynamicMatrixColumns(columns.slice(0, 2), {
    column: { code_prefix: 'dependent_tax_', name_pattern: '가족수 {value}명', type: 'amount', required: true },
}, futureDimensions);
if (futureColumns.length !== 14 || futureColumns.at(-1).code !== 'dependent_tax_12') {
    throw new Error('가상 미래형 가족수 12명 동적 열 검증에 실패했습니다.');
}
const bulkMarkdown = range(0, 437).map(index => {
    const salaryFrom = index * 5000;
    return `| ${salaryFrom.toLocaleString('en-US')} | ${(salaryFrom + 5000).toLocaleString('en-US')} | ${range(1, 11).map(count => (index + count).toLocaleString('en-US')).join(' | ')} |`;
}).join('\n');
const bulkProgress = [];
const bulkStartedAt = performance.now();
const bulkResult = await parseMatrixPasteInChunks(bulkMarkdown, taxColumns, {
    chunkSize: 75,
    onProgress: progress => bulkProgress.push(progress),
});
const bulkElapsedMs = Math.round(performance.now() - bulkStartedAt);
if (bulkResult.errors.length !== 0 || bulkResult.rows.length !== 438
    || bulkProgress.length < 2 || bulkProgress.at(-1).completed !== 438) {
    throw new Error('438행 Chunk Parser 진행률 검증에 실패했습니다.');
}
console.log(JSON.stringify({
    tsv: true, markdown: true, unicode_space: true, dash_as_zero: true, nullable_blank: true,
    separator_removed: true, column_count_error: true, grid_row_values: true, tax_by_dependents: true,
    replace_policy: true, parsed_row_count: markdownResult.rows.length, validation: true, dynamic_12_columns: true,
    bulk_rows: bulkResult.rows.length, bulk_elapsed_ms: bulkElapsedMs, progress_updates: bulkProgress.length,
    excess_rate_round_trip: true, excess_last_open_ended: true,
}));

function range(from, to) {
    return Array.from({ length: to - from + 1 }, (_, index) => from + index);
}
