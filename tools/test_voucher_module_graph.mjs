import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve('public/assets/js/pages/ledger/voucher');
const files = fs.readdirSync(root).filter((file) => file.endsWith('.js'));
const graph = new Map();
for (const file of files) {
    const source = fs.readFileSync(path.join(root, file), 'utf8');
    const imports = [...source.matchAll(/(?:import|export)\s+(?:[^'";]+\s+from\s+)?['"](\.\/[^'"]+)['"]/g)]
        .map((match) => path.basename(match[1].split('?')[0]));
    imports.forEach((target) => assert.ok(fs.existsSync(path.join(root, target)), `${file} -> ${target}`));
    graph.set(file, imports);
}

const visiting = new Set();
const visited = new Set();
function visit(file, stack = []) {
    assert.ok(!visiting.has(file), `circular import: ${[...stack, file].join(' -> ')}`);
    if (visited.has(file)) return;
    visiting.add(file);
    (graph.get(file) || []).forEach((target) => visit(target, [...stack, file]));
    visiting.delete(file);
    visited.add(file);
}
files.forEach((file) => visit(file));
console.log(`voucher module graph tests passed (${files.length} files)`);
