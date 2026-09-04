let dependencyPromise = null;

export function loadModalDependencies() {
    if (!dependencyPromise) {
        dependencyPromise = Promise.all([
            import('/public/assets/js/common/picker/admin_picker.js'),
            import('/public/assets/js/common/html-grid/index.js'),
            import('/public/assets/js/common/html-grid/editors/number-editor.js'),
            import('/public/assets/js/common/values.js'),
            import('/public/assets/js/pages/institution/employment-contract/compensation.js'),
            import('/public/assets/js/common/delete-progress.js'),
            import('/public/assets/js/pages/institution/employment-contract/payment-day.js'),
        ]).catch(error => {
            dependencyPromise = null;
            throw error;
        });
    }
    return dependencyPromise;
}
