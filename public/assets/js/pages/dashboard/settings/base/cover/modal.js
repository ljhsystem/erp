export function setCoverModalMode(deps, mode = 'create') {
    const { DOM, jQuery } = deps;
    const $title = jQuery(DOM.modalLabel);

    if (mode === 'edit') {
        $title.text('커버사진 수정');
        jQuery(DOM.modalSaveBtn).text('수정');
        jQuery(DOM.modalDeleteBtn).show();
        jQuery(DOM.modalImageFile).prop('required', false);
        return;
    }

    $title.text('새 커버사진 등록');
    jQuery(DOM.modalSaveBtn).text('등록');
    jQuery(DOM.modalDeleteBtn).hide();
    jQuery(DOM.modalImageFile).prop('required', true);
    jQuery(DOM.modalImagePreview).attr('src', '').hide();
    jQuery(DOM.modalId).val('');
}

export function populateCoverYearOptions(deps, selectedYear = '') {
    const { DOM, jQuery } = deps;
    const $select = jQuery(DOM.modalYear);
    if (!$select.length) return;

    const currentYear = new Date().getFullYear();
    const normalizedYear = String(selectedYear ?? '').trim();

    let startYear = currentYear - 50;
    let endYear = currentYear + 5;

    if (/^\d{4}$/.test(normalizedYear)) {
        const selectedNum = parseInt(normalizedYear, 10);
        if (selectedNum < startYear) startYear = selectedNum;
        if (selectedNum > endYear) endYear = selectedNum;
    }

    $select.empty();
    $select.append('<option value="">선택하세요</option>');

    for (let year = endYear; year >= startYear; year--) {
        const selected = String(year) === normalizedYear ? 'selected' : '';
        $select.append(`<option value="${year}" ${selected}>${year}년</option>`);
    }

    $select.val(normalizedYear);
}
