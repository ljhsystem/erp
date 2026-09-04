import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
import '/public/assets/js/components/excel-manager.js';

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export async function createBusinessIncomeExcelProvider(config) {
    const form = document.querySelector(config.formSelector);
    if (!form) return null;
    await createExcelManagerSettingsCore({
        domain: 'business-income',
        userSettingPageKey: 'institution.income-data.business-income',
        formSelector: config.formSelector,
        metaDomain: 'business-income',
    });
    form.__excelProvider = {
        async downloadData() {
            const body = new FormData();
            body.set('groups', JSON.stringify(config.getGroups()));
            body.set('header', JSON.stringify(config.getHeader()));
            const response = await fetch(config.downloadUrl, { method: 'POST', body });
            if (!response.ok) throw new Error('엑셀 다운로드 중 오류가 발생했습니다.');
            downloadBlob(await response.blob(), 'business_income.xlsx');
        },
        async prepareUpload({ formData }) {
            const incomeYearMonth = config.getIncomeYearMonth();
            if (!/^\d{4}-\d{2}$/.test(incomeYearMonth)) throw new Error('귀속연월을 먼저 선택해 주세요.');
            formData.set('income_year_month', incomeYearMonth);
        },
        async handleUploadResponse({ modal, response }) {
            const preview = response.data || {};
            if (!preview.valid) {
                const first = preview.errors?.[0];
                throw new Error(first ? `${first.row ? `${first.row}행 ` : ''}${first.message}` : '엑셀 검증 오류를 확인해 주세요.');
            }
            if (!await config.confirmPreview(preview.summary || {})) return true;
            config.applyPreview(preview.groups || [], preview.totals || {});
            bootstrap.Modal.getInstance(modal)?.hide();
            return true;
        },
    };
    return form.__excelProvider;
}
