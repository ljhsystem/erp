import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';

export function createVoucherExcelManager(config = {}) {
    const {
        modalSelector = '#voucherExcelModal',
        formSelector = '#voucher-excel-upload-form',
        templateUrl = '',
        downloadUrl = '',
        uploadUrl = '',
        description = '',
        onShown = null,
        onHidden = null,
    } = config;

    const modalEl = document.querySelector(modalSelector);
    const formEl = document.querySelector(formSelector);
    if (!modalEl || !formEl || !window.bootstrap) {
        return null;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    formEl.dataset.templateUrl = String(templateUrl || '').trim();
    formEl.dataset.downloadUrl = String(downloadUrl || '').trim();
    formEl.dataset.uploadUrl = String(uploadUrl || '').trim();

    let core = createExcelManagerSettingsCore({
        domain: 'voucher-header',
        userSettingPageKey: 'ledger.voucher',
        formSelector,
        metaDomain: 'voucher-header',
        description: String(description || '').trim(),
    });

    const handleShown = () => {
        if (typeof onShown === 'function') {
            onShown(modalEl);
        }
    };
    const handleHidden = () => {
        if (typeof onHidden === 'function') {
            onHidden(modalEl);
        }
    };

    modalEl.addEventListener('shown.bs.modal', handleShown);
    modalEl.addEventListener('hidden.bs.modal', handleHidden);

    return {
        modal,
        modalEl,
        formEl,
        core,
        open() {
            core?.reload?.();
            modal.show();
        },
        reload() {
            core?.reload?.();
        },
        destroy() {
            modalEl.removeEventListener('shown.bs.modal', handleShown);
            modalEl.removeEventListener('hidden.bs.modal', handleHidden);
            core?.destroy?.();
            core = null;
        },
    };
}
