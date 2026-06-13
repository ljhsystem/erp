export function createEvidenceTransactionActions(deps = {}) {
    const {
        selectedTransactionReadyIds,
        setCreatingState,
        updateButtons,
        notify,
        requestCreateTransactions,
        reloadRows,
    } = deps;

    async function createSelectedTransactions(button) {
        const ids = selectedTransactionReadyIds();
        if (ids.length === 0) {
            notify('warning', '거래생성 가능한 READY 자료를 선택해주세요.');
            return;
        }

        setCreatingState(true);
        updateButtons();
        const originalText = button?.textContent || '거래생성';
        if (button) button.textContent = '생성 중';
        try {
            const json = await requestCreateTransactions(ids, { action: 'transaction' });
            notify('success', json.message || '선택한 자료의 거래생성 요청했습니다.');
        } finally {
            setCreatingState(false);
            if (button) button.textContent = originalText;
            reloadRows();
        }
    }

    return {
        createSelectedTransactions,
    };
}
