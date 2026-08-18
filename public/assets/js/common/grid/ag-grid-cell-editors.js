import { PickerSelect2 } from '../picker/picker.select2.js';

const valueOf = option => String(option?.id ?? option?.code ?? option?.value ?? '');
const textOf = option => String(option?.text ?? option?.name ?? option?.code_name ?? option?.label ?? valueOf(option));

export class AgGridSelect2CellEditor {
    init(params) {
        this.params = params;
        this.selectedData = null;
        this.editorWidth = Math.max(
            0,
            Number(params.column?.getActualWidth?.() || params.eGridCell?.getBoundingClientRect?.().width || 0)
        );
        this.select = document.createElement('select');
        this.select.className = 'form-select form-select-sm erp-ag-grid-select2-editor';
        if (this.editorWidth > 0) {
            this.select.style.width = `${this.editorWidth}px`;
            this.select.style.minWidth = `${this.editorWidth}px`;
        }
        const value = String(params.value ?? '');
        this.select.appendChild(new Option('선택(없음)', '', value === '', value === ''));
        if (value !== '') {
            this.select.appendChild(new Option(String(params.currentText?.(params) ?? params.valueFormatted ?? value), value, true, true));
        }
        for (const option of params.options || []) {
            const optionValue = valueOf(option);
            if (optionValue === '' || optionValue === value) continue;
            this.select.appendChild(new Option(textOf(option), optionValue));
        }
    }

    getGui() { return this.select; }

    afterGuiAttached() {
        const common = {
            dropdownParent: window.jQuery(this.params.popupParent || this.select.closest('.modal') || document.body),
            includeCommonAdd: false,
            minimumInputLength: Number(this.params.minimumInputLength ?? 0),
            tags: this.params.tags === true,
            createTag: this.params.tags === true
                ? params => {
                    const text = String(params.term || '').trim();
                    return text === '' ? null : { id: text, text, isNew: true };
                }
                : undefined,
        };
        this.$select = this.params.url
            ? PickerSelect2.createAjax(this.select, {
                ...common, url: this.params.url, delay: Number(this.params.delay ?? 250),
            })
            : PickerSelect2.create(this.select, {
                ...common, minimumResultsForSearch: Number(this.params.minimumResultsForSearch ?? 0),
            });
        if (this.editorWidth > 0) {
            this.$select?.next('.select2-container').css({
                width: `${this.editorWidth}px`,
                minWidth: `${this.editorWidth}px`,
            });
        }
        this.$select
            ?.off('.agGridCellEditor')
            .on('select2:open.agGridCellEditor', () => {
                document.querySelectorAll('.select2-container--open').forEach(container => {
                    container.classList.add('ag-custom-component-popup');
                    if (this.editorWidth > 0 && container.querySelector('.select2-dropdown')) {
                        container.style.width = `${this.editorWidth}px`;
                        container.style.minWidth = `${this.editorWidth}px`;
                    }
                });
            })
            .on('select2:select.agGridCellEditor', event => {
                const selected = event.params?.data || null;
                this.selectedData = String(selected?.id ?? '') === '__none__' ? null : selected;
                this.params.onSelected?.(this.selectedData, this.params);
                this.params.stopEditing();
            })
            .on('select2:clear.agGridCellEditor', () => {
                this.selectedData = null;
                this.params.onSelected?.(null, this.params);
                this.params.stopEditing();
            });
        this.$select?.select2('open');
    }

    getValue() {
        const value = String(this.select.value ?? '');
        const normalized = value === '__none__' ? '' : value;
        return typeof this.params.resolveValue === 'function'
            ? this.params.resolveValue(this.selectedData, normalized, this.params)
            : normalized;
    }

    isPopup() { return true; }

    destroy() {
        this.$select?.off('.agGridCellEditor');
        PickerSelect2.destroy(this.select);
        this.$select = null;
    }
}
