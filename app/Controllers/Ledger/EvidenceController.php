<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\EvidenceDualWriteService;
use App\Services\Ledger\EvidenceSummarySearchService;
use App\Services\Ledger\EvidenceTypePolicyService;
use Core\DbPdo;
use PDO;

class EvidenceController
{
    private const READY_PAGE_TYPES = [
        'BANK_TRANSACTION',
        'TAX_INVOICE',
        'TAX_INVOICE_MANUAL',
        'CASH_RECEIPT',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
    ];

    private const PLANNED_PAGE_NOTICES = [
        'CASH_RECEIPT_SALES' => '현금영수증매출(쇼핑몰) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'BUSINESS_DATA' => '현금매출(쇼핑몰) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'CARD_APPROVAL' => '카드매입(카드사) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'SHOPPING_ORDER' => '카드매출(쇼핑몰) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'EMPLOYEE_EXPENSE' => '직원경비(개인) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'PAYROLL' => '급여(신고) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'PAYROLL_WITHHOLDING' => '일용직(신고) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
        'BUSINESS_INCOME' => '사업소득(신고) 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.',
    ];

    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'TAX_INVOICE_HOMETAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CARD_SALES' => 'SHOPPING_ORDER',
        'CARD_SALES_SHOPPING' => 'SHOPPING_ORDER',
        'CASH_RECEIPT_PURCHASE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALES' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT',
        'CASH_SALES' => 'BUSINESS_DATA',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
        'DAILY_WORKER' => 'PAYROLL_WITHHOLDING',
        'DAILY_WORK_REPORT' => 'PAYROLL_WITHHOLDING',
        'PAYROLL_REPORT' => 'PAYROLL',
        'BUSINESS_INCOME_REPORT' => 'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE_PERSONAL' => 'EMPLOYEE_EXPENSE',
    ];

    private const TYPE_PAGE_PATHS = [
        'BANK_TRANSACTION' => '/ledger/data/bank-transactions',
        'TAX_INVOICE' => '/ledger/data/tax-invoices',
        'TAX_INVOICE_MANUAL' => '/ledger/data/manual-tax-invoices',
        'CARD_HOMETAX' => '/ledger/data/card-hometax',
        'CARD_APPROVAL' => '/ledger/data/card-approvals',
        'CARD_STATEMENT' => '/ledger/data/card-statements',
        'CASH_RECEIPT' => '/ledger/data/cash-receipts',
        'CASH_RECEIPT_PURCHASE' => '/ledger/data/cash-receipt-purchases',
        'CASH_RECEIPT_SALES' => '/ledger/data/cash-receipt-sales',
        'IMPORT_INVOICE' => '/ledger/data/import-invoices',
        'SHOPPING_ORDER' => '/ledger/data/shopping-orders',
        'PAYROLL_WITHHOLDING' => '/ledger/data/payroll-withholdings',
        'BUSINESS_DATA' => '/ledger/data/business-data',
        'PAYROLL' => '/ledger/data/payroll',
        'BUSINESS_INCOME' => '/ledger/data/business-income',
        'EMPLOYEE_EXPENSE' => '/ledger/data/employee-expenses',
        'CONSTRUCTION' => '/ledger/data/construction',
    ];

    private PDO $pdo;
    private LayoutController $layout;
    private ?EvidenceSummarySearchService $evidenceSummarySearchService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
    }

    public function webIndex(): void
    {
        $this->redirect($this->defaultTypePagePath());
    }

    public function webRaw(): void
    {
        $this->renderPage('/app/views/ledger/data/raw.php', [
            'pageTitle' => json_decode('"\uC6D0\uBCF8\uC790\uB8CC"'),
        ]);
    }

    public function webList(): void
    {
        $requestedType = self::normalizeDataType((string) ($_GET['import_type'] ?? ''));
        if ($requestedType === '') {
            $this->redirect($this->defaultTypePagePath());
            return;
        }

        $this->redirect($this->pagePathForType($requestedType));
    }

    public function webBankTransactionList(): void
    {
        $this->webTypePage();
    }

    public function webTaxInvoiceList(): void
    {
        $this->webTypePage();
    }

    public function webTypePage(): void
    {
        $type = $this->currentTypeFromRequestPath();
        if ($type === '') {
            $this->redirect($this->defaultTypePagePath());
            return;
        }

        $this->renderListPage(null, $type);
    }

    public function webUpload(): void
    {
        $this->renderPage('/app/views/ledger/data/upload.php', [
            'pageTitle' => json_decode('"\uC790\uB8CC\uC5C5\uB85C\uB4DC"'),
        ]);
    }

    public function apiEvidenceSummarySearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        $this->json([
            'success' => true,
            'items' => $this->evidenceSummarySearchService()->searchVoucherSummaryTexts($query, 10),
        ]);
    }

    public function apiEvidenceDualWriteSync(): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $evidenceId = trim((string) ($payload['evidence_id'] ?? $payload['id'] ?? ''));
        if ($evidenceId === '') {
            $this->json(['success' => false, 'message' => 'evidence_id is required.'], 400);
            return;
        }

        (new EvidenceDualWriteService($this->pdo))->syncByEvidenceId($evidenceId);
        $this->json(['success' => true, 'message' => 'Dual-write sync completed.', 'evidence_id' => $evidenceId]);
    }

    private function renderListPage(?string $pageTitle = null, string $initialEvidenceType = ''): void
    {
        $policies = $this->decoratePagePolicies($this->evidenceTypePolicyService()->statusViewImportTypePolicies());
        $normalizedInitialType = self::normalizeDataType($initialEvidenceType);
        $typePageMap = $this->activeTypePageMap($policies);
        $currentPolicy = $this->policyForType($policies, $normalizedInitialType);
        $pageReady = (bool) ($currentPolicy['page_ready'] ?? true);
        $pageNotice = (string) ($currentPolicy['page_notice'] ?? '');

        $this->renderPage('/app/views/ledger/data/list.php', [
            'pageTitle' => $pageTitle ?? json_decode('"\uC99D\uBE59\uC6D0\uBCF8"'),
            'evidenceTypePolicies' => $policies,
            'initialEvidenceType' => $normalizedInitialType,
            'evidenceTypePageMap' => $typePageMap,
            'currentEvidencePolicy' => $currentPolicy,
            'pageReady' => $pageReady,
            'pageNotice' => $pageNotice,
        ]);
    }

    private function activeTypePageMap(array $policies = []): array
    {
        if ($policies === []) {
            $policies = $this->evidenceTypePolicyService()->statusViewImportTypePolicies();
        }

        $map = [];
        foreach ($policies as $policy) {
            $type = self::normalizeDataType((string) ($policy['code'] ?? ''));
            if ($type === '' || !isset(self::TYPE_PAGE_PATHS[$type])) {
                continue;
            }

            $map[$type] = self::TYPE_PAGE_PATHS[$type];
        }

        return $map;
    }

    private function defaultTypePagePath(): string
    {
        $map = $this->activeTypePageMap();
        foreach (self::READY_PAGE_TYPES as $type) {
            if (isset($map[$type])) {
                return $map[$type];
            }
        }

        if ($map !== []) {
            return (string) reset($map);
        }

        return self::TYPE_PAGE_PATHS['TAX_INVOICE'] ?? '/ledger/data/list';
    }

    private function pagePathForType(string $type): string
    {
        $normalizedType = self::normalizeDataType($type);
        $map = $this->activeTypePageMap();

        if (isset($map[$normalizedType])) {
            return $map[$normalizedType];
        }

        return $this->defaultTypePagePath();
    }

    private function currentTypeFromRequestPath(): string
    {
        $currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $type = array_search($currentPath, self::TYPE_PAGE_PATHS, true);

        return $type !== false ? (string) $type : '';
    }

    private function decoratePagePolicies(array $policies): array
    {
        return array_map(function (array $policy): array {
            $type = self::normalizeDataType((string) ($policy['code'] ?? ''));
            $pageReady = $this->isReadyPageType($type);

            if ($type === 'CARD_HOMETAX') {
                $policy['excel_manager_mode'] = 'core';
                $policy['excel_manager_domain'] = 'evidence-card-hometax';
            }

            if ($type === 'CARD_STATEMENT') {
                $policy['excel_manager_mode'] = 'core';
                $policy['excel_manager_domain'] = 'evidence-card-statement';
            }

            $policy['page_ready'] = $pageReady;
            $policy['page_notice'] = $pageReady ? '' : $this->pageNoticeForType($type);
            $policy['page_status_label'] = $pageReady ? '사용중' : '개발예정';

            return $policy;
        }, $policies);
    }

    private function policyForType(array $policies, string $type): array
    {
        $normalizedType = self::normalizeDataType($type);
        foreach ($policies as $policy) {
            if (self::normalizeDataType((string) ($policy['code'] ?? '')) === $normalizedType) {
                return $policy;
            }
        }

        return [
            'code' => $normalizedType,
            'page_ready' => $this->isReadyPageType($normalizedType),
            'page_notice' => $this->pageNoticeForType($normalizedType),
            'page_status_label' => $this->isReadyPageType($normalizedType) ? '사용중' : '개발예정',
        ];
    }

    private function isReadyPageType(string $type): bool
    {
        return in_array(self::normalizeDataType($type), self::READY_PAGE_TYPES, true);
    }

    private function pageNoticeForType(string $type): string
    {
        $normalizedType = self::normalizeDataType($type);
        return self::PLANNED_PAGE_NOTICES[$normalizedType]
            ?? '이 자료유형 페이지는 개발예정입니다. 데이터 테이블과 검색 기능은 제공하지 않습니다.';
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function evidenceSummarySearchService(): EvidenceSummarySearchService
    {
        if ($this->evidenceSummarySearchService === null) {
            $this->evidenceSummarySearchService = new EvidenceSummarySearchService($this->pdo);
        }

        return $this->evidenceSummarySearchService;
    }

    private function evidenceTypePolicyService(): EvidenceTypePolicyService
    {
        if ($this->evidenceTypePolicyService === null) {
            $this->evidenceTypePolicyService = new EvidenceTypePolicyService(
                fn(string $type): string => self::normalizeDataType($type),
                self::LEGACY_DATA_TYPE_MAP,
                $this->pdo
            );
        }

        return $this->evidenceTypePolicyService;
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));

        return self::LEGACY_DATA_TYPE_MAP[$type] ?? $type;
    }
}
