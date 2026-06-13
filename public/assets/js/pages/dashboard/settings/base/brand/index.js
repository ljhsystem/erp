import { BRAND_API, BRAND_ASSETS } from './api.js';
import { createBrandTableModule } from './table.js';
import { createBrandFormModule } from './form.js';
import { initBrandModalModule } from './modal.js';
import { initBrandTrashModule } from './trash.js';
import { initBrandExcelModule } from './excel.js';

const wrapper = window.jQuery('#brand-settings-wrapper');

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
}

const tableModule = createBrandTableModule({ api: BRAND_API, notify });
const formModule = createBrandFormModule({
    wrapper,
    api: BRAND_API,
    assets: BRAND_ASSETS,
    notify,
    tableModule,
});

initBrandModalModule();
initBrandTrashModule();
initBrandExcelModule();

window.jQuery(document).ready(() => {
    formModule.loadAll();
    tableModule.loadExistingFiles();
    formModule.bindEvents();
});
