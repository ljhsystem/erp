export function registerCalculation(ctx) {
    function blankLine() {
        return {
            __item_id: '',
            __line_key: `line-${Date.now()}-${++ctx.settlementLineKeySeed}`,
            item_date: document.getElementById('transaction_date')?.value || ctx.today(),
            item_name: '',
            specification: '',
            unit_name: '',
            quantity: '',
            unit_price: 0,
            foreign_unit_price: '',
            foreign_amount: '',
            amount: '',
            supply_amount: 0,
            description: '',
        };
    }

    function blankSettlement() {
        return {
            settlement_type: ctx.settlementTypeLabelFromCode('VAT'),
            amount_sign: ctx.amountSignLabelFromCode('PLUS'),
            amount: '',
            description: '',
        };
    }

    function normalizeLine(item = {}) {
        const lineKey = String(item.__line_key || item.id || `line-${Date.now()}-${++ctx.settlementLineKeySeed}`);

        return {
            evidence_identity: item.evidence_identity || null,
            __item_id: String(item.id || ''),
            __line_key: lineKey,
            item_date: item.item_date || document.getElementById('transaction_date')?.value || ctx.today(),
            item_name: item.item_name || '',
            specification: item.specification || item.item_specification || '',
            unit_name: item.unit_name || item.item_unit_name || '',
            quantity: item.quantity === undefined || item.quantity === null || item.quantity === ''
                ? (item.item_quantity === undefined || item.item_quantity === null || item.item_quantity === '' ? '' : ctx.numberValue(item.item_quantity))
                : ctx.numberValue(item.quantity),
            unit_price: ctx.numberValue(item.unit_price ?? item.item_unit_price ?? 0),
            foreign_unit_price: item.foreign_unit_price === undefined || item.foreign_unit_price === null || item.foreign_unit_price === ''
                ? (item.item_foreign_unit_price === undefined || item.item_foreign_unit_price === null || item.item_foreign_unit_price === '' ? '' : ctx.numberValue(item.item_foreign_unit_price))
                : ctx.numberValue(item.foreign_unit_price),
            foreign_amount: item.foreign_amount === undefined || item.foreign_amount === null || item.foreign_amount === ''
                ? (item.item_foreign_amount === undefined || item.item_foreign_amount === null || item.item_foreign_amount === '' ? '' : ctx.numberValue(item.item_foreign_amount))
                : ctx.numberValue(item.foreign_amount),
            amount: ctx.numberValue(item.amount ?? item.supply_amount ?? item.item_supply_amount ?? 0),
            supply_amount: ctx.numberValue(item.supply_amount ?? item.item_supply_amount ?? 0),
            description: item.description || item.item_description || '',
        };
    }

    function normalizeSettlement(row = {}) {
        return {
            evidence_identity: row.evidence_identity || null,
            settlement_type: ctx.settlementTypeLabelFromCode(row.settlement_type || 'VAT'),
            amount_sign: ctx.amountSignLabelFromCode(row.amount_sign || 'PLUS'),
            amount: row.amount === undefined || row.amount === null || row.amount === ''
                ? ''
                : ctx.numberValue(row.amount),
            description: row.description || row.settlement_description || '',
        };
    }

    function getExchangeRateValue() {
        const value = ctx.parseNumber(ctx.exchangeRateEl?.value || '');
        return value > 0 ? value : 0;
    }

    function calculateLine(row) {
        const quantity = ctx.numberValue(row.quantity);
        const foreignMode = ctx.usesForeignCurrency();
        const foreignUnitPrice = ctx.numberValue(row.foreign_unit_price);
        const foreignAmount = ctx.numberValue(row.foreign_amount || (quantity * foreignUnitPrice));
        const exchangeRate = getExchangeRateValue();
        const unitPrice = foreignMode && quantity > 0 && exchangeRate > 0
            ? (foreignAmount * exchangeRate) / quantity
            : ctx.numberValue(row.unit_price);
        const supply = foreignMode
            ? Math.round(foreignAmount * exchangeRate)
            : Math.round(quantity * unitPrice);
        const enteredAmount = ctx.numberValue(row.amount);
        const amount = quantity > 0 && (unitPrice !== 0 || foreignAmount !== 0)
            ? supply
            : enteredAmount;

        row.quantity = quantity;
        row.unit_price = unitPrice;
        row.foreign_unit_price = foreignMode ? foreignUnitPrice : '';
        row.foreign_amount = foreignMode ? foreignAmount : '';
        row.amount = amount;
        row.supply_amount = row.amount;
        return row;
    }

    function setLineCellValue(row, prop, value, source = 'program') {
        if (!ctx.lineGrid || row < 0) return;
        const currentValue = ctx.lineGrid.getDataAtRowProp?.(row, prop);
        if (String(currentValue ?? '') === String(value ?? '')) {
            return;
        }

        if (typeof ctx.lineGrid.setSourceDataAtCell === 'function') {
            ctx.lineGrid.setSourceDataAtCell(row, prop, value, source);
            return;
        }

        ctx.lineGrid.setDataAtRowProp(row, prop, value, source);
    }

    function setSettlementCellValue(row, prop, value, source = 'program') {
        if (!ctx.settlementGrid || row < 0) return;
        const currentValue = ctx.settlementGrid.getDataAtRowProp?.(row, prop);
        if (String(currentValue ?? '') === String(value ?? '')) {
            return;
        }

        if (typeof ctx.settlementGrid.setSourceDataAtCell === 'function') {
            ctx.settlementGrid.setSourceDataAtCell(row, prop, value, source);
            return;
        }

        ctx.settlementGrid.setDataAtRowProp(row, prop, value, source);
    }

    function calculateSettlementRow(row) {
        return normalizeSettlement(row);
    }

    function getLineKey(row = {}) {
        const raw = String(row?.__line_key || row?.__item_id || '').trim();
        if (raw !== '') return raw;
        const nextKey = `line-${Date.now()}-${++ctx.settlementLineKeySeed}`;
        row.__line_key = nextKey;
        return nextKey;
    }

    function ensureLineRowIdentity(row = {}) {
        if (!row || typeof row !== 'object') return row;
        if (!String(row.__line_key || '').trim()) {
            row.__line_key = `line-${Date.now()}-${++ctx.settlementLineKeySeed}`;
        }
        row.__item_id = String(row.__item_id || row.id || '');
        return row;
    }

    function getLineRows() {
        return (ctx.lineGrid?.getSourceData() || []).map((row) => ensureLineRowIdentity(row));
    }

    function getLineRowByKey(lineKey) {
        return getLineRows().find((row) => getLineKey(row) === lineKey) || null;
    }

    function getLineDisplayName(row = {}) {
        const itemName = String(row?.item_name || '').trim();
        return itemName || '항목';
    }

    function updateSettlementSectionHeading() {
        if (ctx.settlementTitleEl) {
            ctx.settlementTitleEl.textContent = '거래 정산';
        }
        if (ctx.settlementSubtitleEl) {
            ctx.settlementSubtitleEl.textContent = '거래 전체 기준 정산을 관리합니다.';
        }
    }

    function saveCurrentSettlementRows() {
        if (!ctx.settlementGrid) {
            ctx.settlementRows = [];
            return;
        }

        ctx.settlementRows = (ctx.settlementGrid.getSourceData() || [])
            .map((row) => calculateSettlementRow(row))
            .filter((row) => (
                String(row.settlement_type || '').trim() !== ''
                || ctx.numberValue(row.amount) !== 0
                || String(row.description || '').trim() !== ''
            ));
    }

    function loadSettlementRows() {
        ctx.initSettlementGrid();
        const rows = Array.isArray(ctx.settlementRows) ? ctx.settlementRows.map((row) => normalizeSettlement(row)) : [];
        ctx.settlementGrid?.loadData(rows);
        updateSettlementSectionHeading();
    }

    function calculateTotals() {
        saveCurrentSettlementRows();
        const rows = ctx.lineGrid ? ctx.lineGrid.getSourceData() : [];
        let base = 0;
        let foreign = 0;
        let settlement = 0;

        rows.forEach((row, index) => {
            if (!row || (!row.item_name && !row.specification && !ctx.numberValue(row.amount) && !ctx.numberValue(row.unit_price) && !ctx.numberValue(row.foreign_amount) && !ctx.numberValue(row.foreign_unit_price))) return;
            const calculated = calculateLine(row);
            const lineAmount = ctx.numberValue(calculated.amount ?? calculated.supply_amount);
            base += lineAmount;
            foreign += ctx.numberValue(calculated.foreign_amount);

            if (ctx.lineGrid) {
                setLineCellValue(index, 'unit_price', calculated.unit_price, 'calc');
                setLineCellValue(index, 'foreign_amount', calculated.foreign_amount, 'calc');
                setLineCellValue(index, 'amount', calculated.amount, 'calc');
                setLineCellValue(index, 'supply_amount', calculated.supply_amount, 'calc');
            }
        });

        (ctx.settlementRows || []).forEach((row) => {
            if (!row || (!String(row.settlement_type || '').trim() && !ctx.numberValue(row.amount))) return;
            const calculated = calculateSettlementRow(row);
            const amount = ctx.numberValue(calculated.amount);
            const signed = (ctx.amountSignCodeFromCell(calculated.amount_sign) === 'MINUS' ? -1 : 1) * amount;
            settlement += signed;
        });

        const total = base + settlement;

        document.getElementById('transaction_foreign_total').value = ctx.formatAmount(foreign);
        document.getElementById('transaction_supply_total').value = ctx.formatAmount(base);
        const supplyTotalViewEl = document.getElementById('transaction_supply_total_view');
        if (supplyTotalViewEl) {
            supplyTotalViewEl.value = ctx.formatAmount(base);
        }
        document.getElementById('transaction_settlement_total').value = ctx.formatAmount(settlement);
        const settlementTotalViewEl = document.getElementById('transaction_settlement_total_view');
        if (settlementTotalViewEl) {
            settlementTotalViewEl.value = ctx.formatAmount(settlement);
        }
        const finalTotalEl = document.getElementById('transaction_final_total');
        if (finalTotalEl) {
            finalTotalEl.value = ctx.formatAmount(total);
        }
        ctx.setHeaderAmountValues({
            transaction_foreign_amount: foreign,
            base_amount: base,
            adjustment_amount: settlement,
            supply_amount: base,
            settlement_amount: settlement,
            final_amount: total,
        });
    }

    Object.assign(ctx, {
        blankLine,
        blankSettlement,
        normalizeLine,
        normalizeSettlement,
        getExchangeRateValue,
        calculateLine,
        setLineCellValue,
        setSettlementCellValue,
        calculateSettlementRow,
        getLineKey,
        ensureLineRowIdentity,
        getLineRows,
        getLineRowByKey,
        getLineDisplayName,
        updateSettlementSectionHeading,
        saveCurrentSettlementRows,
        loadSettlementRows,
        calculateTotals,
    });
    return ctx;
}
