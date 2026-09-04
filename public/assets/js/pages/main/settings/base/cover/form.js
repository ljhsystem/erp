export function clearCoverSearchConditions(deps) {
    const { DOM, jQuery, getDateStartInput, getDateEndInput, getSearchFieldSelector } = deps;

    jQuery(`${DOM.searchConditions} input[type='text']`).val('');
    jQuery(DOM.searchConditions).find('.search-condition:gt(0)').remove();

    const start = getDateStartInput();
    const end = getDateEndInput();

    if (start) start.value = '';
    if (end) end.value = '';

    const $firstSelect = jQuery(getSearchFieldSelector()).first();
    if ($firstSelect.length) {
        $firstSelect.val('title');
    }

    jQuery(`${DOM.searchConditions} .search-condition`).each(function (index) {
        const $btn = jQuery(this).find('.remove-condition');
        if (index === 0) {
            $btn.hide();
        } else {
            $btn.show();
        }
    });
}

export function resetCoverAfterAction(deps) {
    const {
        clearCoverSearchConditions: clearFilters,
        populateCoverYearOptions,
        getCoverModal,
        reloadCoverTable,
    } = deps;

    clearFilters();
    populateCoverYearOptions();

    const coverModal = getCoverModal();
    if (coverModal) {
        coverModal.hide();
    }

    reloadCoverTable(true);
}
