const page = document.getElementById('fundsOverviewPage');
const searchInput = document.getElementById('fundsOverviewSearch');
const emptyState = document.getElementById('fundsOverviewEmpty');

function normalize(value) {
    return String(value || '').trim().toLocaleLowerCase('ko-KR');
}

function filterAccounts() {
    if (!page || !searchInput) return;

    const keyword = normalize(searchInput.value);
    let visibleCount = 0;

    page.querySelectorAll('[data-funds-group]').forEach((group) => {
        let groupVisibleCount = 0;
        group.querySelectorAll('[data-funds-account]').forEach((row) => {
            const visible = keyword === '' || normalize(row.dataset.searchText).includes(keyword);
            row.classList.toggle('funds-search-hidden', !visible);
            if (visible) groupVisibleCount += 1;
        });
        group.classList.toggle('funds-search-active', keyword !== '');
        group.classList.toggle('d-none', groupVisibleCount === 0);
        visibleCount += groupVisibleCount;
    });

    emptyState?.classList.toggle('d-none', visibleCount !== 0);
}

function toggleGroup(button) {
    const group = button.closest('[data-funds-group]');
    if (!group) return;

    const expanded = group.dataset.expanded !== 'true';
    group.dataset.expanded = String(expanded);
    button.setAttribute('aria-expanded', String(expanded));

    const label = button.querySelector('[data-funds-toggle-label]');
    const extraCount = group.querySelectorAll('.funds-overview-account-extra').length;
    if (label) {
        label.textContent = expanded ? '접어보기' : `계좌 ${extraCount}개 더보기`;
    }
}

searchInput?.addEventListener('input', filterAccounts);
page?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-funds-toggle]');
    if (button) toggleGroup(button);
});
