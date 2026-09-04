import fs from 'node:fs';

const views = [
    'app/views/institution/regular-employment-income/index.php',
    'app/views/institution/daily-employment-income/index.php',
    'app/views/institution/business-income/index.php',
].map(path => fs.readFileSync(path, 'utf8'));
const css = fs.readFileSync('public/assets/css/components/income-calculation-cards.css', 'utf8');
const businessPage = fs.readFileSync('public/assets/js/pages/institution/business-income/index.js', 'utf8');
const checks = {
    all_views_use_common_card: views.every(view => view.includes('ui-form-card income-document-card')),
    all_views_use_four_fields: views.every(view => ['--month', '--title', '--description', '--memo'].every(suffix => view.includes(`income-document-field${suffix}`))),
    all_views_keep_field_order: views.every(view => view.indexOf('--month') < view.indexOf('--title') && view.indexOf('--title') < view.indexOf('--description') && view.indexOf('--description') < view.indexOf('--memo')),
    common_width_contract: css.includes('.income-document-field--month { flex: 0 0 160px') && css.includes('.income-document-field--title { flex: 0 0 280px') && css.includes('.income-document-field--description { flex: 0 0 280px') && css.includes('.income-document-field--memo { flex: 1 1 240px'),
    business_memo_round_trip: businessPage.includes('memo:form.elements.memo.value') && businessPage.includes('form.elements.memo.value=detailState.memo||'),
};
const failed=Object.entries(checks).filter(([,passed])=>!passed).map(([key])=>key);
console.log(JSON.stringify({success:failed.length===0,checks,failed},null,2));
if(failed.length)process.exit(1);
