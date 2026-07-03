export function registerValidation(ctx) {
    function getTransactionModalSnapshot() {
        const fieldNames = [
            'id',
            'status',
            'match_status',
            'transaction_date',
            'business_unit',
            'transaction_direction',
            'operation_type',
            'client_id',
            'project_id',
            'bank_account_id',
            'card_id',
            'team_id',
            'employee_id',
            'description',
            'currency',
            'exchange_rate',
            'supply_amount',
            'settlement_amount',
            'final_amount',
            'note',
            'memo',
        ];

        const fields = {};
        fieldNames.forEach((name) => {
            const field = ctx.form.elements[name];
            fields[name] = field ? String(field.value ?? '') : '';
        });

        const lines = (ctx.lineGrid?.getSourceData() || []).map((row) => ({            item_date: String(row?.item_date ?? ''),
            item_name: String(row?.item_name ?? ''),
            specification: String(row?.specification ?? ''),
            unit_name: String(row?.unit_name ?? ''),
            quantity: String(row?.quantity ?? ''),
            unit_price: String(row?.unit_price ?? ''),
            foreign_unit_price: String(row?.foreign_unit_price ?? ''),
            foreign_amount: String(row?.foreign_amount ?? ''),
            amount: String(row?.amount ?? ''),
            tax_type: String(row?.tax_type ?? ''),
            description: String(row?.description ?? ''),
        }));
        ctx.saveCurrentSettlementRows();
        const settlements = (ctx.settlementRows || []).map((row) => ({
            settlement_type: String(row?.settlement_type ?? ''),
            amount_sign: String(row?.amount_sign ?? ''),
            amount: String(row?.amount ?? ''),
            description: String(row?.description ?? ''),
        }));

        const deleteFileIds = Array.from(ctx.form.querySelectorAll('[data-generated-delete-file="true"]'))
            .map((input) => String(input.value || ''))
            .sort();

        return JSON.stringify({
            fields,
            toggles: {
                is_import: Boolean(ctx.importToggle?.checked),
                use_file_reference: Boolean(ctx.fileToggle?.checked),
            },
            lines,
            settlements,
            currentFiles: ctx.currentFiles.map((file) => String(file.id || file.file_name || '')).sort(),
            pendingFiles: ctx.pendingFiles.map((file) => ({
                name: String(file.name || ''),
                size: Number(file.size || 0),
                lastModified: Number(file.lastModified || 0),
            })),
            fileRowOrder: ctx.fileRowOrder.slice(),
            deleteFileIds,
            selectedVoucherId: String(ctx.selectedVoucherId || ''),
            selectedVoucherLabel: String(ctx.selectedVoucherLabel || ''),
        });
    }

    function markTransactionModalClean() {
        ctx.modalBaselineSnapshot = getTransactionModalSnapshot();
    }

    function hasTransactionModalChanges() {
        if (!ctx.modalBaselineSnapshot) return false;
        return ctx.modalBaselineSnapshot !== getTransactionModalSnapshot();
    }


    Object.assign(ctx, { getTransactionModalSnapshot, markTransactionModalClean, hasTransactionModalChanges });
    return ctx;
}
