export function createEvidenceSelector() {
    const selectedIds = new Set();

    return {
        selectedIds,
        add(id) {
            if (!id) return;
            selectedIds.add(String(id));
        },
        remove(id) {
            if (!id) return;
            selectedIds.delete(String(id));
        },
        clear() {
            selectedIds.clear();
        },
        has(id) {
            if (!id) return false;
            return selectedIds.has(String(id));
        },
        toArray() {
            return Array.from(selectedIds);
        },
        size() {
            return selectedIds.size;
        },
    };
}

