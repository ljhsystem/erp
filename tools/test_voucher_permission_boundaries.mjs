import assert from 'node:assert/strict';
import fs from 'node:fs';

const routes = fs.readFileSync('routes/api/ledger.php', 'utf8');
const controller = fs.readFileSync('app/Controllers/Ledger/VoucherController.php', 'utf8');

function routeBlock(url) {
    const start = routes.indexOf(`$router->post('${url}'`);
    assert.notEqual(start, -1, `missing route: ${url}`);
    const end = routes.indexOf(']);', start);
    assert.notEqual(end, -1, `unterminated route: ${url}`);
    return routes.slice(start, end + 3);
}

const boundaries = new Map([
    ['/api/ledger/voucher/complete-review', 'api.ledger.voucher.review'],
    ['/api/ledger/voucher/reject', 'api.ledger.voucher.review'],
    ['/api/ledger/voucher/cancel-complete-review', 'api.ledger.voucher.review_cancel'],
    ['/api/ledger/voucher/post', 'api.ledger.voucher.post'],
    ['/api/ledger/voucher/reverse', 'api.ledger.voucher.reverse'],
]);

for (const [url, permissionKey] of boundaries) {
    const block = routeBlock(url);
    assert.match(block, new RegExp(`'permission_key'\\s*=>\\s*'${permissionKey.replaceAll('.', '\\.')}['']`));
    assert.doesNotMatch(block, /'permission_key'\s*=>\s*'api\.ledger\.voucher\.save'/);
}

const genericStatusStart = controller.indexOf('public function apiUpdateStatus(): void');
const genericStatusEnd = controller.indexOf('public function apiRequestReview(): void', genericStatusStart);
assert.notEqual(genericStatusStart, -1, 'missing generic status controller');
assert.notEqual(genericStatusEnd, -1, 'missing request-review controller boundary');
const genericStatus = controller.slice(genericStatusStart, genericStatusEnd);
assert.match(genericStatus, /throw new \\RuntimeException/);
assert.doesNotMatch(genericStatus, /updateStatus|completeReview|post\(/);

console.log('voucher permission boundary fixture passed: save bypass blocked; review/review_cancel/post/reverse isolated');
