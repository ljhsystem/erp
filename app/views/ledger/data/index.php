<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = 'Data List';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataList.js');
?>

<main class="ledger-data-list-page" id="ledgerDataListPage">
    <div class="container-fluid py-4">
        <div class="page-header mb-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-list-check me-2"></i>Data List
            </h5>
            <div class="d-flex gap-2">
                <a href="/ledger/data/upload" class="btn btn-outline-primary btn-sm">Upload Data</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="reloadBatchesBtn">Reload</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <section class="card h-100">
                    <div class="card-header fw-semibold">Upload Batches</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="uploadBatchTable">
                                <thead class="table-light">
                                <tr>
                                    <th>File</th>
                                    <th>Type</th>
                                    <th class="text-end">Rows</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Loading upload batches.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-8">
                <section class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Upload Rows</span>
                        <button type="button" class="btn btn-success btn-sm" id="createTransactionsBtn" disabled>
                            Create Transactions
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" id="uploadRowTable">
                                <thead class="table-light">
                                <tr>
                                    <th class="text-end">Row</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Description</th>
                                    <th class="text-end">Total</th>
                                    <th>Message</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Select a batch.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
