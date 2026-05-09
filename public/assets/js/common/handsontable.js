export function createSpreadsheet(container, options = {}) {
    if (!window.Handsontable) {
        throw new Error('Handsontable 라이브러리를 불러오지 못했습니다.');
    }
    if (!container) {
        throw new Error('Spreadsheet 컨테이너가 없습니다.');
    }

    const defaults = {
        width: '100%',
        height: 560,
        readOnly: true,
        stretchH: 'none',
        columnHeaderHeight: 48,
        autoColumnSize: false,
        manualColumnResize: true,
        manualRowResize: true,
        renderAllRows: false,
        licenseKey: 'non-commercial-and-evaluation',
    };

    return new window.Handsontable(container, {
        ...defaults,
        ...options,
    });
}

export function setSpreadsheetDataState(wrapper, hasData) {
    wrapper?.classList.toggle('has-data', Boolean(hasData));
}
