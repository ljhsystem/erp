<?php
// Path: PROJECT_ROOT . '/core/PageKeyResolver.php'

namespace Core;

use PDO;

class PageKeyResolver
{
    private PDO $pdo;
    private bool $loaded = false;

    /** @var array<string,string> */
    private array $pageKeyByRouteKey = [];

    /** @var array<string,string> */
    private array $pageKeyByBreadcrumb = [];

    /** @var array<string,string> */
    private array $pageKeyByBreadcrumbAlias = [];

    /** @var array<string,string> */
    private array $legacyPrefixMap = [
        'api.settings.base-info.company.' => 'settings.base_info.company',
        'api.settings.base-info.brand.' => 'settings.base_info.brand',
        'api.settings.base-info.cover.' => 'settings.base_info.cover',
        'api.settings.base-info.client.' => 'settings.base_info.clients',
        'api.settings.base-info.project.' => 'settings.base_info.projects',
        'api.settings.base-info.bank-account.' => 'settings.base_info.bank_accounts',
        'api.settings.base-info.card.' => 'settings.base_info.cards',
        'api.settings.base-info.work-team.' => 'settings.base_info.work_teams',
        'api.settings.base-info.code.' => 'settings.system.codes',
        'api.settings.employee.' => 'settings.organization.employees',
        'api.settings.department.' => 'settings.organization.departments',
        'api.settings.position.' => 'settings.organization.positions',
        'api.settings.rolepermission.' => 'settings.organization.role_permissions',
        'api.settings.role.' => 'settings.organization.roles',
        'api.settings.permission.' => 'settings.organization.permissions',
        'api.settings.approval.template.' => 'settings.organization.approval',
        'api.settings.system.site.' => 'settings.system.site',
        'api.settings.system.session.' => 'settings.system.session',
        'api.settings.system.security.' => 'settings.system.security',
        'api.settings.system.api.' => 'settings.system.api',
        'api.settings.system.external.' => 'settings.system.external_services',
        'api.settings.system.storage.policy.' => 'settings.system.storage',
        'api.settings.system.storage.' => 'settings.system.storage',
        'api.settings.system.database.' => 'settings.system.database_backup',
        'api.settings.system.logs.' => 'settings.system.logs',
        'api.ledger.transaction.' => 'ledger.transactions',
        'api.ledger.voucher.' => 'ledger.vouchers',
        'api.funds.bank_transactions.' => 'ledger.funds.bank_transactions',
        'api.funds.payment_info.' => 'ledger.funds.payment_info',
        'api.approval.request.' => 'approval.dashboard',
        'api.approval.step.' => 'approval.dashboard',
        'api.import.format.' => 'ledger.data.formats',
        'api.import.formats.' => 'ledger.data.formats',
        'api.import.seed_row.' => 'ledger.data.upload',
        'api.import.seed_rows.' => 'ledger.data.upload',
        'api.import.processing_items.' => 'ledger.data.create_center',
        'api.user.external_accounts.' => 'profile.view',
    ];

    /** @var array<string,string> */
    private array $legacyExactMap = [
        'code.view' => 'settings.system.codes',
        'code.save' => 'settings.system.codes',
        'code.delete' => 'settings.system.codes',
        'work_team.view' => 'settings.base_info.work_teams',
        'work_team.save' => 'settings.base_info.work_teams',
        'work_team.delete' => 'settings.base_info.work_teams',
        'api.auth.account_lock.unlock' => 'auth.account_lock',
        'api.funds.bank_transactions.accounts' => 'ledger.funds.bank_transactions',
        'api.funds.bank_transactions.reconcile' => 'ledger.funds.reconciliation',
        'api.import.batch.delete' => 'ledger.data.upload',
        'api.import.create_bundled_voucher' => 'ledger.data.create_center',
        'api.import.data_types' => 'ledger.data.formats',
        'api.import.recommend_transactions' => 'ledger.data.create_center',
        'api.import.recommend_voucher_lines' => 'ledger.data.create_center',
        'api.ledger.voucher.number' => 'ledger.vouchers',
        'web.ledger.data.raw' => 'ledger.data.list',
        'web.ledger.funds.reconciliation' => 'ledger.funds.reconciliation',
        'web.ledger.sub_accounts' => 'ledger.settings.sub_accounts',
        'web.ledger.transaction.create' => 'ledger.vouchers.index',
        'web.ledger.vouchers.index' => 'ledger.vouchers.index',
    ];

    /** @var array<string,string> */
    private array $legacyBreadcrumbMap = [
        '설정 > 기준정보관리 > 거래처관리' => 'settings.base_info.clients',
        '설정 > 기준정보관리 > 코드관리' => 'settings.system.codes',
        '설정 > 기준정보관리 > 팀관리' => 'settings.base_info.work_teams',
        '설정 > 조직관리 > 권한관리' => 'settings.organization.permissions',
        '회계관리 > 자료관리 > 원본자료' => 'ledger.data.list',
        '회계관리 > 자금관리 > 계좌별거래내역' => 'ledger.funds.bank_transactions',
        '회계관리 > 자금관리 > 계좌대사' => 'ledger.funds.reconciliation',
        '회계관리 > 전표관리 > 전표검토/승인' => 'ledger.vouchers.index',
        '회계관리 > 전표관리 > 전표조회' => 'ledger.vouchers.index',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function resolve(string $permissionKey, ?string $description = null, ?string $category = null): ?string
    {
        $permissionKey = trim($permissionKey);
        $description = $this->normalizeMetaText($description);
        $category = $this->normalizeMetaText($category);

        if ($permissionKey === '') {
            return null;
        }

        $normalizedKey = strtolower($permissionKey);
        $this->loadRegistryMaps();

        if (isset($this->pageKeyByRouteKey[$permissionKey])) {
            return $this->pageKeyByRouteKey[$permissionKey];
        }

        if (isset($this->legacyExactMap[$normalizedKey])) {
            return $this->legacyExactMap[$normalizedKey];
        }

        $breadcrumb = $this->extractPageBreadcrumb($description);
        if ($breadcrumb !== '') {
            if (isset($this->pageKeyByBreadcrumb[$breadcrumb])) {
                return $this->pageKeyByBreadcrumb[$breadcrumb];
            }

            if (isset($this->pageKeyByBreadcrumbAlias[$breadcrumb])) {
                return $this->pageKeyByBreadcrumbAlias[$breadcrumb];
            }

            if (isset($this->legacyBreadcrumbMap[$breadcrumb])) {
                return $this->legacyBreadcrumbMap[$breadcrumb];
            }
        }

        return $this->resolveLegacyAlias($normalizedKey, $description, $category);
    }

    private function loadRegistryMaps(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        try {
            $stmt = $this->pdo->query("
                SELECT page_key, breadcrumb, default_route_key
                FROM system_page_registry
                WHERE is_active = 1
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $pageKey = trim((string)($row['page_key'] ?? ''));
            $breadcrumb = trim((string)($row['breadcrumb'] ?? ''));
            $defaultRouteKey = trim((string)($row['default_route_key'] ?? ''));

            if ($pageKey === '') {
                continue;
            }

            if ($defaultRouteKey !== '') {
                $this->pageKeyByRouteKey[$defaultRouteKey] = $pageKey;
            }

            if ($breadcrumb === '') {
                continue;
            }

            $this->pageKeyByBreadcrumb[$breadcrumb] = $pageKey;

            $parts = array_values(array_filter(array_map('trim', explode('>', $breadcrumb)), static fn ($value) => $value !== ''));
            if (count($parts) < 3) {
                continue;
            }

            $pageLabel = $parts[2];
            if (!str_ends_with($pageLabel, '관리')) {
                $aliasParts = $parts;
                $aliasParts[2] = $pageLabel . '관리';
                $this->pageKeyByBreadcrumbAlias[implode(' > ', $aliasParts)] = $pageKey;
            }
        }
    }

    private function normalizeMetaText(?string $value): string
    {
        $text = trim((string)($value ?? ''));
        $lower = strtolower($text);

        if ($text === '' || $lower === 'route' || $lower === 'system') {
            return '';
        }

        return $text;
    }

    private function extractPageBreadcrumb(string $description): string
    {
        if ($description === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode('>', $description)), static fn ($value) => $value !== ''));
        if (count($parts) < 3) {
            return '';
        }

        return implode(' > ', array_slice($parts, 0, 3));
    }

    private function resolveLegacyAlias(string $normalizedKey, string $description, string $category): ?string
    {
        if ($normalizedKey === '') {
            return null;
        }

        foreach ($this->legacyPrefixMap as $prefix => $pageKey) {
            if (str_starts_with($normalizedKey, $prefix)) {
                return $pageKey;
            }
        }

        if (str_starts_with($normalizedKey, 'api.import.')) {
            return $this->resolveImportPageKey($normalizedKey);
        }

        if ($category === '기초정보' || $category === '기준정보관리') {
            if (str_contains($normalizedKey, 'work-team')) {
                return 'settings.base_info.work_teams';
            }
            if (str_contains($normalizedKey, 'code')) {
                return 'settings.system.codes';
            }
        }

        if ($description === '자금관리 입출금 대사완료 처리') {
            return 'ledger.funds.reconciliation';
        }

        if ($description === '자금관리 계좌 필터 목록') {
            return 'ledger.funds.bank_transactions';
        }

        return null;
    }

    private function resolveImportPageKey(string $normalizedKey): ?string
    {
        if (
            str_contains($normalizedKey, '.seed_row.') ||
            str_contains($normalizedKey, '.seed_rows.') ||
            str_contains($normalizedKey, '.batch.delete')
        ) {
            return 'ledger.data.upload';
        }

        if (
            str_contains($normalizedKey, '.processing_items.') ||
            str_contains($normalizedKey, '.create_bundled_voucher') ||
            str_contains($normalizedKey, '.recommend_transactions') ||
            str_contains($normalizedKey, '.recommend_voucher_lines')
        ) {
            return 'ledger.data.create_center';
        }

        if (
            str_contains($normalizedKey, '.format.') ||
            str_contains($normalizedKey, '.formats.') ||
            str_contains($normalizedKey, '.data_types')
        ) {
            return 'ledger.data.formats';
        }

        return null;
    }
}
