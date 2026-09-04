const SOURCE_IDENTITY_KEYS = new Set([
    'id', 'standard_id', 'sort_no',
    'file_path', 'file_name', 'file_size', 'mime_type',
    'created_at', 'created_by', 'created_by_name', 'updated_at', 'updated_by', 'updated_by_name',
]);

const clone = value => typeof structuredClone === 'function'
    ? structuredClone(value)
    : JSON.parse(JSON.stringify(value));

export function preparePeriodRenewalDraft({ values = {}, sources = [] } = {}) {
    const nextPeriodValues = clone(values);
    delete nextPeriodValues._schema;

    return {
        values: nextPeriodValues,
        sources: clone(sources).map(source => Object.fromEntries(
            Object.entries(source).filter(([key]) => !SOURCE_IDENTITY_KEYS.has(key) && key !== '_uploaded')
        )),
    };
}
