export function bindExcelEvents(deps) {
    const { getCoverTable } = deps;

    document.addEventListener('excel:uploaded', () => {
        const coverTable = getCoverTable();
        if (coverTable) {
            coverTable.ajax.reload(null, false);
        }
    });
}
