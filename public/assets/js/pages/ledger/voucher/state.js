export function createVoucherState() {
    return {
        accountPickerItems: null,
        accountPickerById: new Map(),
        accountPickerByCode: new Map(),
        pickerOptionCache: {},
        accountPolicyCache: {},
        evidenceRows: [],
        journalTable: null,
        lineGrid: null,
        lineGridBridge: null,
        lineGridColumnState: null,
        summaryAutocompleteTimer: null,
        summaryAutocompleteItems: [],
        summaryAutocompleteActiveIndex: -1,
        summaryAutocompleteAbort: null,
        importTypeRows: [],
        evidenceSearchTimer: null,
        voucherEventsBound: false,
        voucherTrashEventsBound: false,
    };
}
