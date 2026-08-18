import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync('routes/api/ledger.php', 'utf8');
const urls = [...source.matchAll(/\$router->(?:get|post|put|delete)\('([^']+)'/g)].map((match) => match[1]);
const keys = [...source.matchAll(/'key'\s*=>\s*'([^']+)'/g)].map((match) => match[1]);
const duplicates = (values) => values.filter((value, index) => values.indexOf(value) !== index);
assert.deepEqual(duplicates(urls), [], 'duplicate route URL');
assert.deepEqual(duplicates(keys), [], 'duplicate route key');
assert.ok(!source.includes('/api/ledger/voucher/excel'));
assert.ok(!source.includes('/api/ledger/voucher/template'));
console.log(`voucher route registry tests passed (${urls.length} URLs, ${keys.length} keys)`);
