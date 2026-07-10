export function registerSelects(ctx) {
    const { AdminPicker, openClientQuickCreate } = ctx;

    function getDropdownParent() {
        return window.jQuery(ctx.modalEl);
    }

    function mapSelect2Results(rows = [], mapper, emptyLabel = '선택(없음)') {
        return [
            { id: '', text: emptyLabel },
            ...rows.map((row) => mapper(row)).filter((item) => item.id !== '' && item.text !== ''),
        ];
    }

    function setCodeSelectValue(selectId, value) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const nextValue = value || '';
        select.value = nextValue;

        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).val(nextValue).trigger('change.select2');
        }
    }

    function initClientSelect() {
        if (!ctx.clientSelectEl || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(ctx.clientSelectEl, {
            url: ctx.API.clientSearch,
            placeholder: '거래처 검색',
            allowClear: true,
            includeCommonAdd: true,
            minimumInputLength: 0,
            dropdownParent: getDropdownParent(),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                    is_active: 1,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];

                return {
                    results: mapSelect2Results(rows, (row) => ({
                        id: String(row.id ?? ''),
                        text: row.text || row.client_name || row.company_name || '',
                    })),
                };
            },
        });

        window.jQuery(ctx.clientSelectEl).off('select2:select.transactionClient');
        ctx.clientSelectEl.removeEventListener?.('picker:add', ctx.clientSelectEl.__transactionClientPickerAdd);
        ctx.clientSelectEl.__transactionClientPickerAdd = () => {
            clearClientSelect();
            window.jQuery(ctx.clientSelectEl).select2('close');
            openTransactionClientQuickCreate('');
        };
        ctx.clientSelectEl.addEventListener('picker:add', ctx.clientSelectEl.__transactionClientPickerAdd);
    }

    function clearClientSelect() {
        if (!ctx.clientSelectEl) return;

        ctx.clientSelectEl.value = '';
        if (window.jQuery?.fn?.select2) {
            window.jQuery(ctx.clientSelectEl).val(null).trigger('change');
        }
    }

    function setClientSelectValue(value = '', text = '') {
        if (!ctx.clientSelectEl) return;

        const clientId = String(value || '').trim();
        if (clientId === '') {
            clearClientSelect();
            return;
        }

        const label = String(text || '-').trim();
        if (window.jQuery?.fn?.select2) {
            const option = new Option(label || '-', clientId, true, true);
            window.jQuery(ctx.clientSelectEl)
                .find('option')
                .filter((index, item) => item.value === clientId)
                .remove();
            window.jQuery(ctx.clientSelectEl).append(option).val(clientId).trigger('change');
            return;
        }

        ctx.clientSelectEl.value = clientId;
    }

    function openTransactionClientQuickCreate(defaultName = '') {
        openClientQuickCreate({
            select: ctx.clientSelectEl,

            title: '신규 거래처 추가',

            initialValues: {
                client_name: defaultName,
            },

            getOptionText(values) {
                return values.client_name || values.company_name || '';
            },

            onSuccess() {
                ctx.notify('success', '거래처가 등록되었습니다.');
            },
        });
    }

    function initProjectSelect() {
        if (!ctx.projectSelectEl || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(ctx.projectSelectEl, {
            url: ctx.API.projectSearch,
            placeholder: '프로젝트 검색',
            allowClear: true,
            minimumInputLength: 0,
            dropdownParent: getDropdownParent(),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];

                return {
                    results: mapSelect2Results(rows, (row) => ({
                        id: String(row.id ?? ''),
                        text: row.text || row.project_name || row.construction_name || '',
                    })),
                };
            },
        });

        window.jQuery(ctx.projectSelectEl).off('select2:select.transactionProject');
    }

    function clearProjectSelect() {
        if (!ctx.projectSelectEl) return;

        ctx.projectSelectEl.value = '';
        if (window.jQuery?.fn?.select2) {
            window.jQuery(ctx.projectSelectEl).trigger('change.select2');
        }
    }

    function setProjectSelectValue(value = '', text = '') {
        if (!ctx.projectSelectEl) return;

        const projectId = String(value || '').trim();
        if (projectId === '') {
            clearProjectSelect();
            return;
        }

        const label = String(text || '-').trim();
        if (window.jQuery?.fn?.select2) {
            const option = new Option(label || '-', projectId, true, true);
            window.jQuery(ctx.projectSelectEl)
                .find('option')
                .filter((index, item) => item.value === projectId)
                .remove();
            window.jQuery(ctx.projectSelectEl).append(option).val(projectId).trigger('change');
            return;
        }

        ctx.projectSelectEl.value = projectId;
    }

    function initReferenceAjaxSelect(select, options = {}) {
        if (!select || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(select, {
            url: options.url,
            allowClear: true,
            minimumInputLength: 0,
            dropdownParent: getDropdownParent(),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                    is_active: 1,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];
                return {
                    results: mapSelect2Results(rows, (row) => ({
                        id: String(row.id ?? ''),
                        text: options.labelForRow ? options.labelForRow(row) : String(row.text || row.name || ''),
                    })),
                };
            },
        });
    }

    function initTeamSelect() {
        if (!ctx.teamSelectEl || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(ctx.teamSelectEl, {
            url: ctx.API.workTeamList,
            placeholder: ctx.teamSelectEl.dataset.placeholder || '팀 검색',
            allowClear: true,
            minimumInputLength: 0,
            dropdownParent: getDropdownParent(),
            width: '100%',
            dataBuilder(params) {
                const keyword = String(params.term || '').trim();
                return keyword === ''
                    ? {}
                    : {
                        filters: JSON.stringify([
                            { field: 'team_name', value: keyword },
                        ]),
                    };
            },
            processResults(data) {
                const rows = Array.isArray(data?.data) ? data.data : [];
                return {
                    results: mapSelect2Results(rows, (row) => ({
                        id: String(row.id ?? ''),
                        text: String(row.team_name || row.text || '').trim(),
                    })),
                };
            },
        });
    }

    function initBankAccountSelect() {
        initReferenceAjaxSelect(ctx.bankAccountSelectEl, {
            url: ctx.API.bankAccountSearch,
            placeholder: ctx.bankAccountSelectEl?.dataset?.placeholder || '계좌 검색',
            labelForRow: (row) => row.account_name || row.bank_account_name || row.name || '',
        });
    }

    function initCardSelect() {
        initReferenceAjaxSelect(ctx.cardSelectEl, {
            url: ctx.API.cardSearch,
            placeholder: ctx.cardSelectEl?.dataset?.placeholder || '카드 검색',
            labelForRow: (row) => row.text || row.card_name || row.card_number || row.card_company_name || '',
        });
    }

    function initEmployeeSelect() {
        initReferenceAjaxSelect(ctx.employeeSelectEl, {
            url: ctx.API.employeeSearch,
            placeholder: ctx.employeeSelectEl?.dataset?.placeholder || '직원 검색',
            labelForRow: (row) => row.text || row.employee_name || row.name || '',
        });
    }

    function setStaticSelectValue(select, value = '', text = '', emptyLabel = '') {
        if (!select) return;

        const itemId = String(value || '').trim();
        const isSelect2Ready = Boolean(window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible'));
        const $select = isSelect2Ready ? window.jQuery(select) : null;

        select.innerHTML = '';
        select.appendChild(new Option(emptyLabel, '', itemId === '', itemId === ''));

        if (itemId !== '') {
            const label = String(text || itemId).trim() || itemId;
            const option = new Option(label, itemId, true, true);
            select.appendChild(option);
            select.value = itemId;
            if ($select) {
                $select.append(option).val(itemId).trigger('change');
            }
            return;
        }

        select.value = '';
        if ($select) {
            $select.val('').trigger('change');
        }
    }

    function setBankAccountValue(value = '', text = '') {
        setStaticSelectValue(ctx.bankAccountSelectEl, value, text, '계좌선택');
    }

    function setCardValue(value = '', text = '') {
        setStaticSelectValue(ctx.cardSelectEl, value, text, '카드선택');
    }

    function setTeamValue(value = '', text = '') {
        setStaticSelectValue(ctx.teamSelectEl, value, text, '팀선택');
    }

    function setEmployeeValue(value = '', text = '') {
        setStaticSelectValue(ctx.employeeSelectEl, value, text, '직원선택');
    }

    Object.assign(ctx, { setCodeSelectValue, initClientSelect, clearClientSelect, setClientSelectValue, openTransactionClientQuickCreate, initProjectSelect, clearProjectSelect, setProjectSelectValue, initReferenceAjaxSelect, initTeamSelect, initBankAccountSelect, initCardSelect, initEmployeeSelect, setStaticSelectValue, setBankAccountValue, setCardValue, setTeamValue, setEmployeeValue });
    return ctx;
}
