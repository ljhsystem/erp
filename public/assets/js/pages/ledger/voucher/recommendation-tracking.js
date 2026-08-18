function normalizeAmount(value) {
    const number = Number(String(value ?? 0).replace(/,/g, ''));
    return Number.isFinite(number) ? number.toFixed(2) : '0.00';
}

export function normalizeVoucherRefs(refs = []) {
    return (Array.isArray(refs) ? refs : [])
        .map((ref) => ({
            ref_target: String(ref?.ref_target || ref?.line_ref_target || '').trim().toUpperCase(),
            ref_id: String(ref?.ref_id || '').trim(),
            is_primary: Number(ref?.is_primary) === 1 ? 1 : 0,
        }))
        .filter((ref) => ref.ref_target !== '' && ref.ref_id !== '')
        .sort((a, b) => `${a.ref_target}:${a.ref_id}`.localeCompare(`${b.ref_target}:${b.ref_id}`));
}

export function serializeVoucherRefs(refs = []) {
    return JSON.stringify(normalizeVoucherRefs(refs));
}

function snapshotValue(line = {}) {
    return JSON.stringify({
        account_id: String(line.account_id || '').trim(),
        debit: normalizeAmount(line.debit),
        credit: normalizeAmount(line.credit),
        journal_rule_id: String(line.journal_rule_id || '').trim(),
        refs: normalizeVoucherRefs(line.refs),
    });
}

export function createRecommendationSnapshot(line = {}) {
    return snapshotValue(line);
}

export function recommendationOrigin(snapshot = '') {
    let recommendation = null;
    try {
        recommendation = JSON.parse(String(snapshot || ''));
    } catch (_) {
        recommendation = null;
    }
    const debit = Number(String(recommendation?.debit ?? 0).replace(/,/g, '')) || 0;
    const credit = Number(String(recommendation?.credit ?? 0).replace(/,/g, '')) || 0;
    return {
        account_id: String(recommendation?.account_id || '').trim() || null,
        line_type: debit > 0 ? 'DEBIT' : (credit > 0 ? 'CREDIT' : null),
        amount: debit > 0 ? debit : (credit > 0 ? credit : null),
    };
}

export function reconcileRecommendationTracking(line = {}) {
    const journalRuleId = String(line.journal_rule_id || '').trim();
    const snapshot = String(line.recommendation_snapshot || '').trim();
    if (journalRuleId === '' || snapshot === '') {
        return {
            journal_rule_id: journalRuleId,
            is_user_modified: journalRuleId === '' ? 0 : (Number(line.is_user_modified) === 1 ? 1 : 0),
            recommendation_snapshot: '',
        };
    }

    return {
        journal_rule_id: journalRuleId,
        is_user_modified: snapshotValue(line) === snapshot ? 0 : 1,
        recommendation_snapshot: snapshot,
    };
}
