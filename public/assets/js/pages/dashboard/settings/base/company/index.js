import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatBizNumber, formatCorpNumber, formatPhone } from '/public/assets/js/common/format.js';
import { COMPANY_API } from './api.js';
import { createCompanyFormModule } from './form.js';
import { createCompanyModalModule } from './modal.js';
import { initCompanyTableModule } from './table.js';
import { initCompanyTrashModule } from './trash.js';
import { initCompanyExcelModule } from './excel.js';

window.AdminPicker = AdminPicker;

const wrapper = window.jQuery('#company-settings-wrapper');
const saveButton = window.jQuery('#btn-save-all');

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
}

const formModule = createCompanyFormModule({
    wrapper,
    saveButton,
    notify,
    api: COMPANY_API,
    formatBizNumber,
    formatCorpNumber,
    formatPhone,
});

const modalModule = createCompanyModalModule({ AdminPicker, notify });
initCompanyTableModule();
initCompanyTrashModule();
initCompanyExcelModule();

window.jQuery(document).ready(() => {
    modalModule.initAdminDatePicker();
    modalModule.bindAdminDateInputs();
    formModule.loadCompanyInfo();
    formModule.bindFormattingEvents();
    saveButton.on('click', formModule.saveCompanyInfo);

    if (window.KakaoAddress && typeof window.KakaoAddress.bind === 'function') {
        window.KakaoAddress.bind();
    }
});
