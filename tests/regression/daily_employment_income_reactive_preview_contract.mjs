import fs from 'node:fs';

const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const cards = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');

const checks = {
    debounce_350ms: page.includes('}, immediate ? 0 : 350);'),
    date_and_select_immediate: cards.includes('this.onChanged({ immediate: true })')
        && page.includes('scheduleAutoCalculation({ immediate: true })'),
    numeric_blur_and_enter_flush: page.includes("groupsHost.addEventListener('focusout'")
        && page.includes("event.key === 'Enter'"),
    scheduled_payment_date_removed: !page.includes('paymentDateInput') && !page.includes('payment_date'),
    aborts_stale_request: page.includes('new AbortController()')
        && page.includes('calculationAbortController?.abort()')
        && page.includes("error?.name === 'AbortError'"),
    ignores_stale_response: page.includes('requestVersion !== calculationRequestVersion'),
    deduplicates_payload: page.includes('lastPreviewPayloadKey')
        && page.includes('payloadKey === lastPreviewPayloadKey'),
    preview_is_read_only: page.includes('request(API.CALCULATE')
        && !page.slice(page.indexOf('const calculateDocument'), page.indexOf('function invalidWorkMinuteWorkdays')).includes('request(API.SAVE'),
    modal_close_cancels: page.includes("modalElement.addEventListener('hidden.bs.modal'")
        && page.includes("calculationAbortController = null; lastPreviewPayloadKey = '';"),
};

const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([name]) => name);
console.log(JSON.stringify({ success: failed.length === 0, checks, failed }, null, 2));
if (failed.length) process.exit(1);
