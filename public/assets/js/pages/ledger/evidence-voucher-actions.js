export function createEvidenceVoucherActions(deps = {}) {
    const {
        selectedVoucherReadyIds,
        setCreatingState,
        updateButtons,
        notify,
        requestCreateTransactions,
        reloadRows,
    } = deps;

    async function createSelectedVouchers(button) {
        const ids = selectedVoucherReadyIds();
        if (ids.length === 0) {
            notify('warning', '전표발행 가능한 READY 자료를 선택해주세요. 일반 자료는 거래생성 후 전표발행할 수 있습니다.');
            return;
        }

        setCreatingState(true);
        updateButtons();
        const originalText = button?.textContent || '전표발행';
        if (button) button.textContent = '발행 중';
        try {
            let json = await requestCreateTransactions(ids, { action: 'voucher' });
            if (json.requires_confirmation && json.confirmation_code === 'EXISTING_VOUCHER') {
                const confirmed = window.confirm(json.message || '이미 같은 유형의 전표가 생성되어 있습니다. 기존 전표를 연결할까요?');
                if (!confirmed) {
                    notify('warning', '기존 전표 연결을 취소했습니다.');
                    return;
                }
                json = await requestCreateTransactions(ids, { action: 'voucher', confirm_existing_voucher: true });
            }
            notify('success', json.message || '선택한 자료의 전표발행 요청했습니다.');
        } finally {
            setCreatingState(false);
            if (button) button.textContent = originalText;
            reloadRows();
        }
    }

    return {
        createSelectedVouchers,
    };
}
