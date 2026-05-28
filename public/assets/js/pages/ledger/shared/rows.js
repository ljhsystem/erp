import { mapped } from './mapper.js';

export function rowClientName(row) {
    const payload = mapped(row);
    return row?.client_name
        || payload.client_name
        || payload.client_company_name
        || payload.merchant_company_name
        || payload.supplier_name
        || payload.supplier_company_name
        || payload.customer_name
        || payload.customer_company_name
        || payload.counterparty_name
        || '';
}

export function rowProjectName(row) {
    const payload = mapped(row);
    return row?.project_name
        || payload.project_name
        || payload.project_code
        || '';
}
