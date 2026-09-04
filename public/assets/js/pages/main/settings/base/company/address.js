import { bindLazyAddress } from '/public/assets/js/common/address-lazy.js';

export function bindLazyCompanyAddress(notify) {
    bindLazyAddress({
        root: document,
        detail: "[name='addr_detail']",
        notify,
    });
}
