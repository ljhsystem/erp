import { formatBizNumber, formatCorpNumber, formatPhone } from '/public/assets/js/common/format.js';
import { COMPANY_API } from './api.js';
import { createCompanyFormModule } from './form.js';
import { bindLazyCompanyAddress } from './address.js';

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

window.jQuery(document).ready(() => {
    formModule.loadCompanyInfo();
    formModule.bindFormattingEvents();
    saveButton.on('click', formModule.saveCompanyInfo);
    bindLazyCompanyAddress(notify);
});
