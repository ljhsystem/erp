// Path: /assets/js/components/search-form.cover.js
import { SearchForm as BaseSearchForm } from '/public/assets/js/components/search-form.js';

export function SearchForm(config) {
    BaseSearchForm(config);

    window.setPeriod = function (type) {
        const activeEl = document.activeElement;
        const btn = activeEl && activeEl.matches?.('[onclick*="setPeriod"]')
            ? activeEl
            : null;
        const form = btn?.closest('form') || document.querySelector('form[id$="SearchConditionsForm"]');
        if (!form) return;

        const now = new Date();
        const year = now.getFullYear();
        const currentMonth = String(now.getMonth() + 1).padStart(2, '0');

        let start = `${year}-01`;
        let end = `${year}-${currentMonth}`;

        switch (type) {
            case 'thisYear':
                break;
            case 'lastYear':
                start = `${year - 1}-01`;
                end = `${year - 1}-12`;
                break;
            case '3years':
                start = `${year - 2}-01`;
                break;
            case '5years':
                start = `${year - 4}-01`;
                break;
            case '10years':
                start = `${year - 9}-01`;
                break;
            default:
                return;
        }

        const $form = window.jQuery(form);
        $form.find('[name="dateStart"]').val(start);
        $form.find('[name="dateEnd"]').val(end);
        $form.trigger('submit');
    };
}
