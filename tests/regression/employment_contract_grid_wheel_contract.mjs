import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const css = fs.readFileSync(path.join(
    root,
    'public/assets/css/pages/institution/employment-contract/index.css',
), 'utf8');

assert.equal((css.match(/overscroll-behavior-x:\s*contain/g) || []).length, 2);
assert.equal((css.match(/overscroll-behavior-y:\s*auto/g) || []).length, 2);
assert.match(css, /\.employment-weekly-schedule-grid-host\s*\{[^}]*overscroll-behavior-y:\s*auto/s);
assert.match(css, /#employmentContractModal \.employment-component-grid-host\s*\{[^}]*overscroll-behavior-y:\s*auto/s);

console.log('employment contract grid wheel contract: OK');
