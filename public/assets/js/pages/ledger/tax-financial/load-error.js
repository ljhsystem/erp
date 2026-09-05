function showTaxFinancialLoadError(reason) {
    const page = document.querySelector('#taxFinancialPage');
    if (!page || page.querySelector('[data-tax-load-error]')) return;

    const message = String(reason?.message || reason || '페이지 정보를 불러오지 못했습니다.');
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger mb-3';
    alert.dataset.taxLoadError = 'true';
    alert.setAttribute('role', 'alert');
    alert.setAttribute('aria-live', 'assertive');

    const title = document.createElement('strong');
    title.textContent = '세무회계 자료를 불러오지 못했습니다.';
    alert.append(title);

    const detail = document.createElement('div');
    detail.className = 'small mt-1';
    detail.textContent = message === '접근 권한이 없습니다.'
        ? '현재 계정에 세무회계 조회 권한이 없습니다. 권한 설정을 확인해 주세요.'
        : message;
    alert.append(detail);
    page.prepend(alert);

    page.querySelectorAll('button, input, select').forEach((element) => {
        element.disabled = true;
    });
}

window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;
    if (!reason || !String(reason.message || reason).trim()) return;
    event.preventDefault();
    showTaxFinancialLoadError(reason);
});
