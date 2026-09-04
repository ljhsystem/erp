export function createEvidenceReorderModule({
    state,
    DATA_TYPE_SORT_RULES,
    normalizeEvidenceType,
    evidenceTypePolicy,
    defaultEvidenceTypeCode,
    rebuildTable,
}) {
    function sortTargetsForCurrentType() {
        const fallbackType = defaultEvidenceTypeCode();
        const normalizedType = normalizeEvidenceType(state.currentType || fallbackType) || fallbackType;
        return evidenceTypePolicy(normalizedType)?.sortTargetKeys || DATA_TYPE_SORT_RULES[normalizedType] || [];
    }

    function evidenceStatusTableSettingsStorageKey(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        const resolvedType = normalizeEvidenceType(type || fallbackType) || fallbackType;
        const typeKey = String(resolvedType).trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
        return `ledger.data.status.${typeKey}.evidence-status.db-physical.v4`;
    }

    function handleEvidenceStatusTableOrderChange() {
        void rebuildTable();
        return true;
    }

    return {
        sortTargetsForCurrentType,
        evidenceStatusTableSettingsStorageKey,
        handleEvidenceStatusTableOrderChange,
    };
}
