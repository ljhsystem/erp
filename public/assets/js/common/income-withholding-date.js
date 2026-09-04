const INCOME_WITHHOLDING_RULES = Object.freeze({
    REGULAR: 'NEXT_MONTH_DAY_11',
    MONTH_END: 'NEXT_MONTH_END',
});

const parseIncomeYearMonth = value => {
    const match = String(value || '').match(/^(\d{4})-(0[1-9]|1[0-2])$/);
    if (!match) return null;
    return { year: Number(match[1]), monthIndex: Number(match[2]) - 1 };
};

const previousFridayWhenWeekend = date => {
    const weekday = date.getDay();
    if (weekday === 6) date.setDate(date.getDate() - 1);
    if (weekday === 0) date.setDate(date.getDate() - 2);
    return date;
};

const formatLocalDate = date => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
].join('-');

export function isIncomeWithholdingDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return false;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return date.getFullYear() === Number(match[1])
        && date.getMonth() === Number(match[2]) - 1
        && date.getDate() === Number(match[3]);
}

export function incomeWithholdingDate(incomeYearMonth, rule) {
    const parsed = parseIncomeYearMonth(incomeYearMonth);
    if (!parsed) return '';
    const date = rule === INCOME_WITHHOLDING_RULES.REGULAR
        ? new Date(parsed.year, parsed.monthIndex + 1, 11)
        : new Date(parsed.year, parsed.monthIndex + 2, 0);
    return formatLocalDate(previousFridayWhenWeekend(date));
}

export { INCOME_WITHHOLDING_RULES };
