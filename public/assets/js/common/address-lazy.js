let addressAssetsPromise = null;

function loadPostcodeScript() {
    if (window.daum?.Postcode) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
        script.async = true;
        script.onload = resolve;
        script.onerror = () => reject(new Error('주소검색 기능을 불러오지 못했습니다.'));
        document.head.appendChild(script);
    });
}

export function ensureAddressAssets() {
    if (!addressAssetsPromise) {
        addressAssetsPromise = loadPostcodeScript()
            .then(() => import('/public/assets/js/common/address.js'))
            .catch((error) => {
                addressAssetsPromise = null;
                throw error;
            });
    }

    return addressAssetsPromise;
}

export function bindLazyAddress({ root = document, detail = null, notify = null } = {}) {
    const eventRoot = root || document;
    if (eventRoot.dataset?.lazyAddressBound === 'true') return;
    if (eventRoot.dataset) eventRoot.dataset.lazyAddressBound = 'true';

    eventRoot.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-addr-picker]');
        if (!button || !eventRoot.contains(button)) return;

        event.preventDefault();
        try {
            await ensureAddressAssets();
            window.KakaoAddress.open({
                address: button.dataset.address || button.dataset.target,
                sido: button.dataset.sido || null,
                sigungu: button.dataset.sigungu || null,
                detail: button.dataset.detail || detail,
            });
        } catch (error) {
            const message = error.message || '주소검색 기능을 불러오지 못했습니다.';
            if (typeof notify === 'function') notify('error', message);
        }
    });
}
