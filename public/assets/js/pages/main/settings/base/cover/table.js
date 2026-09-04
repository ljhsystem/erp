export function normalizeActiveValue(value) {
    const raw = String(value ?? '').trim().toLowerCase();
    if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
    if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
    return '';
}

export function extractYear(value) {
    const match = String(value || '').match(/^(\d{4})/);
    return match ? match[1] : '';
}

export function normalizeCoverFilters(filters) {
    const normalized = [];

    filters.forEach((filter) => {
        if (filter?.field === 'year' && filter.value && typeof filter.value === 'object') {
            const startYear = extractYear(filter.value.start);
            const endYear = extractYear(filter.value.end);

            if (startYear) {
                normalized.push({ field: 'year_start', value: startYear });
            }

            if (endYear) {
                normalized.push({ field: 'year_end', value: endYear });
            }

            return;
        }

        if (filter?.field === 'is_active') {
            const normalizedStatus = normalizeActiveValue(filter.value);
            if (normalizedStatus !== '') {
                normalized.push({ field: 'is_active', value: normalizedStatus });
            }
            return;
        }

        normalized.push(filter);
    });

    return normalized;
}
