// 경로: /public/assets/js/components/search-form.cover.js

import {
    updateTableHeight,
    forceTableHeightSync,
    animateSearchFormRelayout
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

    const formId          = `#${tableId}SearchConditionsForm`;
    const conditionsId    = `#${tableId}SearchConditions`;
    const addBtnId        = `#${tableId}AddSearchCondition`;
    const resetBtnId      = `#${tableId}ResetButton`;
    const dateTypeId      = `#${tableId}DateType`;

    const containerEl = document.getElementById('searchFormContainer');
    const bodyEl      = document.getElementById('searchFormBody');
    const toggleBtnEl = document.getElementById('toggleSearchForm');
    const searchTooltipTrigger = document.getElementById(`${tableId}TooltipTrigger`);
    const searchTooltipBox     = document.getElementById(`${tableId}TooltipContainer`);
    const periodTooltipTrigger = document.getElementById(`${tableId}PeriodTooltipTrigger`);
    const periodTooltipBox     = document.getElementById(`${tableId}PeriodTooltipContainer`);



    

    /* =========================================================
       기존 공통 로직 그대로 유지
    ========================================================= */

    bindToggle();
    bindTooltips(); 
    bindSearchEvents();
    populateFirstSearchFields();
    populateDateOptions(dateOptions);
    bindPeriodButtons(); // 🔥 여기만 다름

    /* ========================================================= */

    function bindToggle(){
        if(!containerEl || !bodyEl || !toggleBtnEl) return;

        toggleBtnEl.addEventListener('click', () => {
            bodyEl.classList.toggle('hidden');
            containerEl.classList.toggle('collapsed');

            const hidden = bodyEl.classList.contains('hidden');
            toggleBtnEl.textContent = hidden ? '열기' : '접기';

            if(table){
                table.page.len(hidden ? 100 : 10).draw(false);

                updateTableHeight(table, `#${tableId}-table`);
                animateSearchFormRelayout(table, `#${tableId}-table`, 320);

                setTimeout(() => {
                    forceTableHeightSync(table, `#${tableId}-table`);
                }, 340);
            }
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
                setTimeout(() => {
                    forceTableHeightSync(table, `#${tableId}-table`);
                }, 30);
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

            setTimeout(() => {
                forceTableHeightSync(table, `#${tableId}-table`);
            }, 30);
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

            setTimeout(() => {
                forceTableHeightSync(table, `#${tableId}-table`);
            }, 30);
        });
    }

    function collectFilters(){

        const filters = [];
    
        /* =========================
           날짜 필터
        ========================= */
        const dateType = $(dateTypeId).val();
        const start = $(formId).find('[name="dateStart"]').val();
        const end   = $(formId).find('[name="dateEnd"]').val();
    
        if(dateType && start && end){
            filters.push({ field: dateType + '_start', value: start });
            filters.push({ field: dateType + '_end', value: end });
        }
    
        /* =========================
           🔥 검색어 필터 (핵심)
        ========================= */
        $(`${conditionsId} .search-condition`).each(function(){
    
            const field = $(this).find('[name="searchField[]"]').val();
            const value = $(this).find('[name="searchValue[]"]').val();
    
            if(field && value){
                filters.push({
                    field: field,
                    value: value
                });
            }
        });
    
        console.log('🔥 최종 filters:', filters);
    
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


    /* =========================================================
       🔥 핵심: 커버 전용 setPeriod
    ========================================================= */

    function bindPeriodButtons(){

        // 🔥 공통꺼 덮어쓰기
        window.setPeriod = function(type){

            console.log('🔥 cover setPeriod 실행:', type);

            const now = new Date();
            const year = now.getFullYear();
            const currentMonth = String(now.getMonth() + 1).padStart(2, '0');

            let start = `${year}-01`;
            let end   = `${year}-${currentMonth}`;

            switch(type){

                case 'thisYear':
                    break;

                case 'lastYear':
                    start = `${year - 1}-01`;
                    end   = `${year - 1}-12`;
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

            const $form = $(formId);

            $form.find('[name="dateStart"]').val(start);
            $form.find('[name="dateEnd"]').val(end);

            // 🔥 커버는 이게 핵심 (기존 방식 유지)
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
