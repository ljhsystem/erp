import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = path.resolve(import.meta.dirname, '../..');
const compensation = await import(pathToFileURL(path.join(
    root,
    'public/assets/js/pages/institution/employment-contract/compensation.js',
)).href);
const modalSource = fs.readFileSync(path.join(
    root,
    'public/assets/js/pages/institution/employment-contract/modal-runtime.js',
), 'utf8');

const row = (amount, rowState = 'clean') => ({ rowState, values: { amount } });
const original = [row(653011), row(304634), row(31245), row(100000)];

assert.equal(compensation.compensationAmount(original), 1088890);
assert.equal(compensation.compensationSummary(1088890, 'MONTHLY').convertedAmount, 13066680);
assert.equal(compensation.compensationAmount(original.slice(0, 3)), 988890);
assert.equal(compensation.compensationAmount([...original.slice(0, 3), row(100000)]), 1088890);
assert.equal(compensation.compensationAmount([...original, row(50000)]), 1138890);
assert.equal(compensation.compensationAmount([row(653011), row(304634), row(31245), row(120000)]), 1108890);
assert.equal(compensation.compensationAmount([...original, row(50000, 'deleted')]), 1088890);

const replaceFunction = modalSource.match(/function replaceComponentRows\(rows = \[\]\) \{[\s\S]*?\n\}/)?.[0] || '';
assert.ok(replaceFunction.includes('updateCompensationSummary();'));
assert.ok(!replaceFunction.includes('recalculateFormulaComponents();'));
assert.ok(modalSource.includes("if (columnKey === 'pay_component_id') recalculateFormulaComponents();"));
assert.ok(!modalSource.includes("['pay_component_id', 'amount'].includes(columnKey)"));
assert.match(modalSource, /recalculateFormulaComponents\(\);\s*renderAllowanceDetails\(\);\s*updateCompensationSummary\(\);/);

console.log('employment contract compensation SSOT regression: OK');
