import { BRAND_API, BRAND_ASSETS } from './api.js';
import { createBrandTableModule } from './table.js';
import { createBrandFormModule } from './form.js';

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

window.jQuery(document).ready(() => {
    formModule.bindEvents();
    tableModule.loadExistingFiles().then(formModule.renderActiveAssets);
});
