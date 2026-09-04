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

export async function createDailyEmploymentIncomeExcelProvider(config) {
    const form = document.querySelector(config.formSelector);
    if (!form) return null;

    await createExcelManagerSettingsCore({
        domain: 'daily-employment-income',
        userSettingPageKey: 'institution.income-data.daily-employment',
        formSelector: config.formSelector,
        metaDomain: 'daily-employment-income',
    });

    const provider = {
        async downloadData({ prepared }) {
            const body = new FormData();
            body.set('groups', JSON.stringify(config.getGroups()));
            body.set('header', JSON.stringify(config.getHeader()));
            body.set('excel_download_columns', prepared.columns.join(','));
            const response = await fetch(config.downloadUrl, { method: 'POST', body });
            if (!response.ok) throw new Error('엑셀 다운로드 중 오류가 발생했습니다.');
            downloadBlob(await response.blob(), 'daily_employment_income.xlsx');
        },
        async prepareUpload({ formData }) {
            const incomeYearMonth = config.getIncomeYearMonth();
            if (!/^\d{4}-\d{2}$/.test(incomeYearMonth)) {
                throw new Error('귀속연월을 먼저 선택해 주세요.');
            }
            formData.set('income_year_month', incomeYearMonth);
        },
        async handleUploadResponse({ modal, response }) {
            const preview = response.data || {};
            if (!preview.valid) {
                const first = preview.errors?.[0];
                throw new Error(first ? `${first.row}행 ${first.message}` : '엑셀 검증 오류를 확인해 주세요.');
            }
            const summary = preview.summary || {};
            const confirmed = await config.confirmPreview(summary);
            if (!confirmed) return true;
            config.applyPreview(preview.groups || []);
            bootstrap.Modal.getInstance(modal)?.hide();
            return true;
        },
    };
    form.__excelProvider = provider;
    return provider;
}
