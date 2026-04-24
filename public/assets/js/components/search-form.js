// 경로: /assets/js/components/search-form.js
import {
    updateTableHeight,
    forceTableHeightSync
} from '/public/assets/js/components/data-table.js';

export function SearchForm(config){

    const {
        table,
        apiList,
        tableId,
        defaultSearchField,
        dateOptions
    } = config;

    /* 🔥 테이블에서 자동 생성 */
    const fields = getTableColumns(table);

    const $ = window.jQuery;
    const MAX_CONDITION = 5;
    const DEFAULT_PAGE_LENGTH = 100;
    const OPEN_LABEL = '\uC5F4\uAE30';
    const CLOSE_LABEL = '\uC811\uAE30';

    const formId          = `#${tableId}SearchConditionsForm`;
    const conditionsId    = `#${tableId}SearchConditions`;
    const addBtnId        = `#${tableId}AddSearchCondition`;
    const resetBtnId      = `#${tableId}ResetButton`;
    const dateTypeId      = `#${tableId}DateType`;

    const containerEl     = document.getElementById(`${tableId}SearchFormContainer`);
    const bodyEl          = document.getElementById(`${tableId}SearchFormBody`);
    const toggleBtnEl     = document.getElementById(`${tableId}ToggleSearchForm`);

    const searchTooltipTrigger = document.getElementById(`${tableId}TooltipTrigger`);
    const searchTooltipBox     = document.getElementById(`${tableId}TooltipContainer`);
    const periodTooltipTrigger = document.getElementById(`${tableId}PeriodTooltipTrigger`);
    const periodTooltipBox     = document.getElementById(`${tableId}PeriodTooltipContainer`);

    bindToggle();
    bindTooltips();
    bindSearchEvents();
    populateFirstSearchFields();
    populateDateOptions(dateOptions);
    bindPeriodButtons();
    applyInitialState();

    function applyInitialState(){
        if(containerEl){
            containerEl.classList.add('collapsed');
        }

        if(bodyEl){
            bodyEl.classList.add('hidden');
        }

        if(toggleBtnEl){
            toggleBtnEl.textContent = OPEN_LABEL;
        }

        if(!table){
            return;
        }

        if(table.page.len() !== DEFAULT_PAGE_LENGTH){
            table.page.len(DEFAULT_PAGE_LENGTH).draw(false);
        }

        updateTableHeight(table, `#${tableId}-table`);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                forceTableHeightSync(table, `#${tableId}-table`);
            });
        });
    }

    function bindToggle(){
        if(!containerEl || !bodyEl || !toggleBtnEl) return;

        toggleBtnEl.addEventListener('click', () => {
            bodyEl.classList.toggle('hidden');
            containerEl.classList.toggle('collapsed');

            const hidden = bodyEl.classList.contains('hidden');
            toggleBtnEl.textContent = hidden ? '열기' : '접기';

            if(table){
                table.page.len(DEFAULT_PAGE_LENGTH).draw(false);
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        updateTableHeight(table, `#${tableId}-table`);
                        table.columns.adjust().draw(false);
                        forceTableHeightSync(table, `#${tableId}-table`);
                    });
                });
            }

            toggleBtnEl.textContent = hidden ? OPEN_LABEL : CLOSE_LABEL;
        });
    }

    function bindTooltips(){

        setupTooltip(searchTooltipTrigger, searchTooltipBox);
        setupTooltip(periodTooltipTrigger, periodTooltipBox);
    
        function setupTooltip(trigger, tooltip){
    
            if(!trigger || !tooltip) return;
    
            // ⭐ 중복 방지
            if(trigger.__tooltipBound) return;
            trigger.__tooltipBound = true;
    
            trigger.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
    
                const isOpen = tooltip.classList.contains('show');
    
                closeAllTooltips();
    
                if(!isOpen){
    
                    const rect = trigger.getBoundingClientRect();
    
                    // ⭐ 무조건 body로 이동 (핵심)
                    document.body.appendChild(tooltip);
    
                    tooltip.style.position = 'fixed';
                    tooltip.style.top  = rect.bottom + 6 + 'px';
                    tooltip.style.left = rect.left + 'px';
    
                    tooltip.style.display = 'block';
                    tooltip.classList.add('show');
                }
            });
    
            tooltip.addEventListener('click', function(e){
                e.stopPropagation();
            });
        }
    
        function closeAllTooltips(){
            document.querySelectorAll('.tooltip-container').forEach(t => {
                t.style.display = 'none';
                t.classList.remove('show');
            });
        }
    
        // ⭐ 전역 이벤트도 1번만
        if(!window.__tooltipGlobalBound){
    
            window.__tooltipGlobalBound = true;
    
            document.addEventListener('click', closeAllTooltips);
    
            document.addEventListener('keydown', function(e){
                if(e.key === 'Escape'){
                    closeAllTooltips();
                }
            });
        }
    }

    function bindSearchEvents(){

        $(formId).off('submit.searchForm').on('submit.searchForm', function(e){
            e.preventDefault();

            const filters = collectFilters();

            if(!filters.length){
                table.ajax.url(apiList).load();
                return;
            }

            table.ajax.url(
                apiList + '?filters=' + encodeURIComponent(JSON.stringify(filters))
            ).load();
        });

        $(document).off('click.searchFormRemove', '.remove-condition')
        .on('click.searchFormRemove', '.remove-condition', function(){
            const rows = $(`${conditionsId} .search-condition`);

            if(rows.length <= 1){
                alert('최소 1개의 검색조건은 유지해야 합니다.');
                return;
            }

            $(this).closest('.search-condition').remove();
            updateRemoveButtons();

            if(table){
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        forceTableHeightSync(table, `#${tableId}-table`);
                    });
                });
            }
        });

        $(resetBtnId).off('click.searchFormReset').on('click.searchFormReset', function(e){
            e.preventDefault();

            $(`${conditionsId} input[type="text"]`).val('');
            $(`${conditionsId}`).find('.search-condition:gt(0)').remove();

            $(formId).find('input[name="dateStart"]').val('');
            $(formId).find('input[name="dateEnd"]').val('');

            const dateTypeEl = document.getElementById(`${tableId}DateType`);
            if(dateTypeEl && dateOptions.length){
                dateTypeEl.value = dateOptions[0].value;
            }

            populateFirstSearchFields();
            updateRemoveButtons();

            table.ajax.url(apiList).load();

            if(typeof onAfterReset === 'function'){
                onAfterReset();
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    forceTableHeightSync(table, `#${tableId}-table`);
                });
            });
        });

        $(addBtnId).off('click.searchFormAdd').on('click.searchFormAdd', function(){
            const rows = $(`${conditionsId} .search-condition`);
            const count = rows.length;

            if(count >= MAX_CONDITION){
                alert('검색조건은 최대 5개까지 가능합니다.');
                return;
            }

            const firstField = rows.first().find('select').val();
            const fields = getTableColumns(table);
            const baseIndex = fields.findIndex(f => f.value === firstField);

            let nextIndex = baseIndex + count;
            if(nextIndex >= fields.length){
                nextIndex = fields.length - 1;
            }

            const html = `
                <div class="search-condition">
                    ${renderSearchSelect(nextIndex)}
                    <input type="text"
                           name="searchValue[]"
                           class="form-control search-input"
                           placeholder="검색어 입력">
                    <button type="button" class="btn btn-danger remove-condition">-</button>
                </div>
            `;

            $(`${conditionsId} .search-condition:last`).after(html);
            updateRemoveButtons();

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    forceTableHeightSync(table, `#${tableId}-table`);
                });
            });
        });
    }

    function collectFilters(){
        const filters = [];

        $(`${conditionsId} .search-condition`).each(function(){
            const field = $(this).find('select').val();
            const value = $(this).find('input').val();

            if(field && value){
                filters.push({ field, value });
            }
        });

        const dateType = $(dateTypeId).val();
        let start = $(formId).find('input[name="dateStart"]').val();
        let end   = $(formId).find('input[name="dateEnd"]').val();
        
        if(dateType && start && end){
        
            // DATETIME 필드는 하루 범위 전체를 잡도록 보정
            if(dateType === 'created_at' || dateType === 'updated_at'){
                start = `${start} 00:00:00`;
                end   = `${end} 23:59:59`;
            }
        
            filters.push({
                field: dateType,
                value: { start, end }
            });
        }
        return filters;
    }

    function getTableColumns(table){

        if(!table || typeof table.settings !== 'function'){
            console.error('❌ DataTable instance 아님', table);
            return [];
        }
    
        const settings = table.settings()[0];
    
        if(!settings){
            console.error('❌ settings 없음');
            return [];
        }
    
        return settings.aoColumns
        .filter(col => col.data && col.sTitle)
        .map(col => {
    
            const label = stripHtml(col.sTitle).trim();
    
            if(!label) return null;
    
            return {
                value: col.data,
                label
            };
    
        })
        .filter(Boolean);
    }

    function renderSearchSelect(selectedIndex = 0){
        const fields = getTableColumns(table);
        if(!fields.length) return '';

        let html = `<select name="searchField[]" class="form-select form-select-sm search-field">`;

        fields.forEach((f, i) => {
            const sel = (i === selectedIndex) ? 'selected' : '';
            html += `<option value="${f.value}" ${sel}>${f.label}</option>`;
        });

        html += `</select>`;
        return html;
    }

    function updateRemoveButtons(){
        const rows = $(`${conditionsId} .search-condition`);

        rows.each(function(index){
            const btn = $(this).find('.remove-condition');
            if(index === 0){
                btn.hide();
            }else{
                btn.show();
            }
        });
    }

    function populateFirstSearchFields(){
        const fields = getTableColumns(table);
        const firstSelect = document.querySelector(`${conditionsId} .search-condition select`);

        if(!firstSelect || !fields.length) return;

        firstSelect.innerHTML = '';

        fields.forEach(field => {
            const opt = document.createElement('option');
            opt.value = field.value;
            opt.textContent = field.label;

            if(defaultSearchField && field.value === defaultSearchField){
                opt.selected = true;
            }

            firstSelect.appendChild(opt);
        });
    }

    function bindPeriodButtons(){

        if(window.__searchFormPeriodBound) return;
        window.__searchFormPeriodBound = true;
    
        window.setPeriod = function(type){
    
            const activeEl = document.activeElement;
            const btn = activeEl && activeEl.matches?.('[onclick*="setPeriod"]')
                ? activeEl
                : null;
    
            let form = null;
    
            if(btn){
                form = btn.closest('form');
            }
    
            if(!form){
                form = document.querySelector('form[id$="SearchConditionsForm"]');
            }
    
            if(!form) return;
    
            const today = new Date();
            let start = new Date(today);
            let end   = new Date(today);
    
            switch(type){
    
                case 'today':
                    break;
    
                case 'yesterday':
                    start.setDate(today.getDate() - 1);
                    end = new Date(start);
                    break;
    
                case '3days':
                    start.setDate(today.getDate() - 3);
                    break;
    
                case '7days':
                    start.setDate(today.getDate() - 7);
                    break;
    
                case '15days':
                    start.setDate(today.getDate() - 15);
                    break;
    
                case '1month':
                    start.setMonth(today.getMonth() - 1);
                    break;
    
                case '3months':
                    start.setMonth(today.getMonth() - 3);
                    break;
    
                case '6months':
                    start.setMonth(today.getMonth() - 6);
                    break;
    
                default:
                    return;
            }
    
            const format = (d) => {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${dd}`;
            };
    
            const $form = window.jQuery(form);
    
            $form.find('[name="dateStart"]').val(format(start));
            $form.find('[name="dateEnd"]').val(format(end));
    
            $form.trigger('submit');
        };
    }
    
    function stripHtml(html){
        if(!html) return '';
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent.trim();
    }
    function populateDateOptions(options){

        const el = document.getElementById(`${tableId}DateType`);
        if(!el || !options?.length) return;
    
        el.innerHTML = options.map(o =>
            `<option value="${o.value}">${o.label}</option>`
        ).join('');
    }


}
