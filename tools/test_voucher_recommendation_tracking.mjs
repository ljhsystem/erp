import assert from 'node:assert/strict';
import {
    createRecommendationSnapshot,
    recommendationOrigin,
    reconcileRecommendationTracking,
} from '../public/assets/js/pages/ledger/voucher/recommendation-tracking.js';

const recommended = {
    account_id: 'account-a',
    debit: 1100,
    credit: 0,
    refs: [{ ref_target: 'CLIENT', ref_id: 'client-a', is_primary: 1 }],
    journal_rule_id: 'rule-a',
};
const snapshot = createRecommendationSnapshot(recommended);
assert.deepEqual(recommendationOrigin(snapshot), {
    account_id: recommended.account_id,
    line_type: 'DEBIT',
    amount: 1100,
});
const reconcile = (patch = {}) => reconcileRecommendationTracking({
    ...recommended,
    recommendation_snapshot: snapshot,
    ...patch,
});

assert.equal(reconcile().is_user_modified, 0);
assert.equal(reconcile({ account_id: 'account-b' }).is_user_modified, 1);
assert.equal(reconcile({ debit: 0, credit: 1100 }).is_user_modified, 1);
assert.equal(reconcile({ debit: 1200 }).is_user_modified, 1);
assert.equal(reconcile({ refs: [{ ref_target: 'CLIENT', ref_id: 'client-b' }] }).is_user_modified, 1);
assert.equal(reconcile({ line_summary: '적요만 변경' }).is_user_modified, 0);
assert.equal(reconcile({ account_id: 'account-b' }).is_user_modified, 1);
assert.equal(reconcile({ account_id: 'account-a' }).is_user_modified, 0);
assert.deepEqual(
    reconcileRecommendationTracking({ account_id: 'manual', journal_rule_id: '', is_user_modified: 1 }),
    { journal_rule_id: '', is_user_modified: 0, recommendation_snapshot: '' },
);
assert.equal(reconcileRecommendationTracking({ journal_rule_id: 'rule-a', is_user_modified: 1 }).is_user_modified, 1);

console.log('voucher recommendation tracking tests passed');
