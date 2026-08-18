import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const entry = path.join(root, 'public/assets/js/pages/institution/employment-contract/index.js');
const modalRuntime = path.join(root, 'public/assets/js/pages/institution/employment-contract/modal-runtime.js');
const related = [entry, modalRuntime,
    path.join(root, 'public/assets/js/pages/institution/employment-contract/table.js'),
    path.join(root, 'public/assets/js/pages/institution/employment-contract/shared.js'),
    path.join(root, 'public/assets/js/pages/institution/employment-contract/compensation.js')];

function source(file) {
    return fs.readFileSync(file, 'utf8');
}

function resolveImport(specifier, importer) {
    if (specifier.startsWith('/public/')) return path.join(root, specifier.slice(1));
    if (specifier.startsWith('.')) return path.resolve(path.dirname(importer), specifier);
    return null;
}

function staticGraph(file, visited = new Set()) {
    const normalized = path.normalize(file);
    if (visited.has(normalized) || !fs.existsSync(normalized)) return visited;
    visited.add(normalized);
    const imports = [...source(normalized).matchAll(/^import\s+(?:[^'";]+?\s+from\s+)?['"]([^'"]+)['"];?/gm)];
    imports.forEach(match => {
        const resolved = resolveImport(match[1], normalized);
        if (resolved) staticGraph(resolved, visited);
    });
    return visited;
}

const entrySource = source(entry);
const modalSource = source(modalRuntime);
const initialGraph = staticGraph(entry);
const forbiddenInitialImports = ['common/html-grid', 'common/picker/admin_picker', 'employment-contract/compensation.js', 'common/delete-progress.js'];
const results = {
    index_lines: entrySource.split(/\n/).length,
    modal_runtime_lines: modalSource.split(/\n/).length,
    related_files_under_1500: related.every(file => source(file).split(/\n/).length <= 1500),
    initial_graph_count: initialGraph.size,
    initial_baseline_dependency_count: Math.max(0, initialGraph.size - 2),
    initial_baseline_dependency_count_preserved: Math.max(0, initialGraph.size - 2) <= 13,
    initial_forbidden_imports_zero: forbiddenInitialImports.every(value => !entrySource.includes(value)),
    modal_dynamic_import_once: (entrySource.match(/import\('\/public\/assets\/js\/pages\/institution\/employment-contract\/modal-runtime\.js'\)/g) || []).length === 1,
    modal_runtime_owns_html_grid: modalSource.includes("import('/public/assets/js/common/html-grid/index.js')"),
    modal_runtime_owns_admin_picker: modalSource.includes("import('/public/assets/js/common/picker/admin_picker.js')"),
    modal_runtime_owns_compensation: modalSource.includes("import('/public/assets/js/pages/institution/employment-contract/compensation.js')"),
    modal_runtime_owns_options_cache: modalSource.includes('let modalOptionsPromise = null;'),
};

const failed = Object.entries(results).filter(([key, value]) => !key.endsWith('_count') && value !== true && !key.endsWith('_lines'));
console.log(JSON.stringify({ success: failed.length === 0, results, failed: failed.map(([key]) => key) }, null, 2));
if (failed.length) process.exit(1);
