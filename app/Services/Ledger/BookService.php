<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\BookRepository;
use PDO;

final class BookService
{
    private BookRepository $repository;
    private VoucherLineRefService $refs;

    public function __construct(PDO $pdo)
    {
        $this->repository = new BookRepository($pdo);
        $this->refs = new VoucherLineRefService($pdo);
    }

    public function getJournalPage(array $request, array $filters): array
    {
        $page = $this->repository->getJournalPage($request, $filters);
        $rows = $this->refs->hydrateVoucherLines($page['rows']);
        foreach ($rows as &$row) {
            $row['debit'] = (float) $row['debit'];
            $row['credit'] = (float) $row['credit'];
            $row['ref_summary'] = implode(', ', array_values(array_filter(array_map(
                static fn(array $ref): string => trim((string) ($ref['ref_label'] ?? '')),
                $row['refs'] ?? []
            ))));
        }
        unset($row);
        $summary = $page['summary'];
        $summary['voucher_count'] = (int) ($summary['voucher_count'] ?? 0);
        $summary['line_count'] = (int) ($summary['line_count'] ?? 0);
        $summary['debit_total'] = (float) ($summary['debit_total'] ?? 0);
        $summary['credit_total'] = (float) ($summary['credit_total'] ?? 0);
        $summary['difference'] = $summary['debit_total'] - $summary['credit_total'];
        return ['records_total'=>$page['records_total'],'records_filtered'=>$page['records_filtered'],'rows'=>$rows,'summary'=>$summary];
    }

    public function getGeneralLedgerPage(array $request, array $filters): array
    {
        $normalized = $this->normalizeGeneralFilters($filters);
        return $this->repository->getGeneralLedgerPage($request, $normalized);
    }

    public function getGeneralLedgerDetailPage(array $request, array $filters): array
    {
        $accountId = trim((string) ($filters['account_id'] ?? ''));
        if ($accountId === '') {
            return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>['voucher_count'=>0,'line_count'=>0,'debit_total'=>0,'credit_total'=>0,'difference'=>0]];
        }
        return $this->getJournalPage($request, $filters);
    }

    public function getAccountLedgerPage(array $request, array $filters): array
    {
        $normalized = $this->normalizeAccountFilters($filters);
        $page = $this->repository->getAccountLedgerPage($request, $normalized);
        $rows = $this->refs->hydrateVoucherLines($page['rows']);
        foreach ($rows as &$row) {
            $row['debit'] = (float)$row['debit'];
            $row['credit'] = (float)$row['credit'];
            $row['running_balance'] = (float)$row['running_balance'];
            $row['ref_summary'] = implode(', ',array_values(array_filter(array_map(
                static fn(array $ref): string => trim((string)($ref['ref_label']??'')),
                $row['refs']??[]
            ))));
        }
        unset($row);
        $page['rows'] = $rows;
        return $page;
    }

    public function getClientLedgerPage(array $request,array $filters): array
    {
        $normalized=$this->normalizeClientFilters($filters);
        $page=$this->repository->getClientLedgerPage($request,$normalized);
        $rows=$this->refs->hydrateVoucherLines($page['rows']);
        foreach($rows as &$row){
            $row['debit']=(float)$row['debit'];$row['credit']=(float)$row['credit'];$row['running_balance']=(float)$row['running_balance'];
            $row['ref_summary']=implode(', ',array_values(array_filter(array_map(static fn(array $ref):string=>trim((string)($ref['ref_label']??'')),$row['refs']??[]))));
        }
        unset($row);$page['rows']=$rows;return $page;
    }

    public function getProjectLedgerPage(array $request,array $filters): array
    {
        $normalized=$this->normalizeClientFilters($filters);
        $page=$this->repository->getProjectLedgerPage($request,$normalized);
        $rows=$this->refs->hydrateVoucherLines($page['rows']);
        foreach($rows as &$row){$row['debit']=(float)$row['debit'];$row['credit']=(float)$row['credit'];$row['running_balance']=(float)$row['running_balance'];$row['ref_summary']=implode(', ',array_values(array_filter(array_map(static fn(array $ref):string=>trim((string)($ref['ref_label']??'')),$row['refs']??[]))));}
        unset($row);$page['rows']=$rows;return $page;
    }

    public function getDailyBookPage(array $request,array $filters): array
    {
        return $this->repository->getDailyBookPage($request,$this->normalizeClientFilters($filters));
    }

    public function getDailyBookDetailPage(array $request,array $filters): array
    {
        return $this->repository->getDailyBookDetailPage($request,$this->normalizeClientFilters($filters));
    }

    public function getPurchaseSalesPage(array $request,array $filters): array
    {
        $page=$this->repository->getPurchaseSalesPage($request,$filters);
        foreach($page['rows'] as &$row){$row['direction_label']=(string)$row['direction_code']==='INCOME'?'매출':((string)$row['direction_code']==='EXPENSE'?'매입':'미분류');}
        unset($row);return $page;
    }

    private function normalizeGeneralFilters(array $filters): array
    {
        $result = $filters;
        foreach ($filters as $filter) {
            if (!is_array($filter)) continue;
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? '';
            if (is_array($value) && $field === 'voucher_date') {
                $result['date_from'] = trim((string) ($value['start'] ?? ''));
                $result['date_to'] = trim((string) ($value['end'] ?? ''));
            } elseif (in_array($field, ['account_code','account_name'], true) && !is_array($value)) {
                $result['account_keyword'] = trim((string) $value);
            }
        }
        return $result;
    }

    private function normalizeAccountFilters(array $filters): array
    {
        $result = $filters;
        foreach ($filters as $filter) {
            if (!is_array($filter)) continue;
            $field = trim((string)($filter['field']??'')); $value = $filter['value']??'';
            if (is_array($value) && $field==='voucher_date') {
                $result['date_from'] = trim((string)($value['start']??''));
                $result['date_to'] = trim((string)($value['end']??''));
            } elseif (!is_array($value) && in_array($field,['voucher_no','line_summary','counterpart_summary'],true)) {
                $result[$field] = trim((string)$value);
            }
        }
        return $result;
    }

    private function normalizeClientFilters(array $filters): array
    {
        $result=$filters;
        foreach($filters as $filter){
            if(!is_array($filter))continue;
            $field=trim((string)($filter['field']??''));$value=$filter['value']??'';
            if(is_array($value)&&$field==='voucher_date'){$result['date_from']=trim((string)($value['start']??''));$result['date_to']=trim((string)($value['end']??''));}
            elseif(!is_array($value)&&in_array($field,['voucher_no','account_code','account_name','line_summary'],true))$result[$field]=trim((string)$value);
        }
        return $result;
    }
}
