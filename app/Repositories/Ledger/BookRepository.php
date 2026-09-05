<?php

namespace App\Repositories\Ledger;

use PDO;

final class BookRepository
{
    private const ORDER_COLUMNS = [
        'voucher_date' => 'v.voucher_date', 'voucher_no' => 'v.voucher_no',
        'voucher_status' => 'v.status', 'line_no' => 'l.line_no',
        'account_code' => 'a.account_code', 'account_name' => 'a.account_name',
        'line_summary' => 'l.line_summary', 'debit' => 'l.debit', 'credit' => 'l.credit',
        'evidence_count' => 'evidence_count',
    ];

    public function __construct(private readonly PDO $db) {}

    public function getJournalPage(array $request, array $filters): array
    {
        $start = max(0, (int) ($request['start'] ?? 0));
        $length = max(10, min(500, (int) ($request['length'] ?? 100)));
        [$scopeWhere, $scopeParams] = $this->scopeWhere();
        [$filterWhere, $filterParams] = $this->filterWhere($filters, $request);
        $where = $scopeWhere . $filterWhere;
        $params = $scopeParams + $filterParams;
        return [
            'records_total' => $this->countRows($scopeWhere, $scopeParams),
            'records_filtered' => $this->countRows($where, $params),
            'rows' => $this->fetchRows($where, $params, $this->orderBy($request), $start, $length),
            'summary' => $this->summary($where, $params),
        ];
    }

    public function getGeneralLedgerPage(array $request, array $filters): array
    {
        $rows = $this->generalLedgerRows($filters);
        $recordsTotal = count($rows);
        $keyword = trim((string) ($filters['account_keyword'] ?? ''));
        if ($keyword !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool =>
                str_contains((string) $row['account_code'], $keyword)
                || str_contains((string) $row['account_name'], $keyword)
            ));
        }
        $recordsFiltered = count($rows);
        $order = $request['order'][0] ?? [];
        $index = (int) ($order['column'] ?? -1);
        $field = trim((string) ($request['columns'][$index]['data'] ?? 'account_code'));
        $allowed = ['account_code','account_name','opening_balance','period_debit','period_credit','ending_balance','line_count'];
        $field = in_array($field, $allowed, true) ? $field : 'account_code';
        $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? -1 : 1;
        usort($rows, static function (array $left, array $right) use ($field, $direction): int {
            $result = in_array($field, ['opening_balance','period_debit','period_credit','ending_balance','line_count'], true)
                ? ((float) $left[$field] <=> (float) $right[$field])
                : strnatcasecmp((string) $left[$field], (string) $right[$field]);
            return $result * $direction;
        });
        $start = max(0, (int) ($request['start'] ?? 0));
        $length = max(10, min(500, (int) ($request['length'] ?? 100)));
        return [
            'records_total' => $recordsTotal,
            'records_filtered' => $recordsFiltered,
            'rows' => array_slice($rows, $start, $length),
            'summary' => $this->generalLedgerSummary($rows),
        ];
    }

    public function getAccountLedgerPage(array $request, array $filters): array
    {
        $accountId = trim((string) ($filters['account_id'] ?? ''));
        if ($accountId === '') {
            return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>$this->emptyAccountSummary()];
        }
        $accountStmt = $this->db->prepare("SELECT id,account_code,account_name,normal_balance FROM ledger_accounts WHERE id=:id AND deleted_at IS NULL AND COALESCE(is_posting,0)=1 LIMIT 1");
        $accountStmt->execute([':id'=>$accountId]);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$account) return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>$this->emptyAccountSummary()];

        [$scopeWhere,$scopeParams] = $this->scopeWhere();
        [$filterWhere,$filterParams] = $this->filterWhere($filters,$request);
        $where = $scopeWhere . $filterWhere;
        $params = $scopeParams + $filterParams;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM ledger_opening_balances obx WHERE obx.voucher_id=v.id AND obx.opening_date<=:account_opening_cutoff AND obx.period_end_date>=:account_opening_period)';
            $params[':account_opening_cutoff'] = $dateFrom;
            $params[':account_opening_period'] = $dateFrom;
        }
        $sql = "SELECT l.id,l.line_no,l.account_id,l.debit,l.credit,l.line_summary,v.id AS voucher_id,v.voucher_no,v.voucher_date,UPPER(v.status) AS voucher_status,v.summary AS voucher_summary,a.account_code,a.account_name,a.normal_balance,(SELECT GROUP_CONCAT(DISTINCT CONCAT(oa.account_code,' ',oa.account_name) ORDER BY oa.account_code SEPARATOR ', ') FROM ledger_voucher_lines ol JOIN ledger_accounts oa ON oa.id=ol.account_id WHERE ol.voucher_id=v.id AND ol.id<>l.id) AS counterpart_summary,(SELECT COUNT(*) FROM ledger_evidence_links el WHERE el.target_type='VOUCHER' AND el.target_id=v.id AND el.deleted_at IS NULL) AS evidence_count FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} ORDER BY v.voucher_date ASC,v.voucher_no ASC,l.line_no ASC,l.id ASC";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $recordsTotal = count($rows);
        $counterpartKeyword = trim((string) ($filters['counterpart_summary'] ?? ''));
        if ($counterpartKeyword !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => str_contains((string)($row['counterpart_summary']??''),$counterpartKeyword)));
        }
        $openingBalance = $this->accountOpeningBalance($accountId,$dateFrom,(string)$account['normal_balance']);
        $running = $openingBalance; $normal = strtoupper((string)$account['normal_balance']);
        foreach ($rows as &$row) {
            $running += $normal === 'CREDIT' ? (float)$row['credit']-(float)$row['debit'] : (float)$row['debit']-(float)$row['credit'];
            $row['running_balance'] = $running;
        }
        unset($row);
        $summary = $this->accountSummary($rows,$account,$openingBalance);
        $recordsFiltered = count($rows);
        $start = max(0,(int)($request['start']??0)); $length = max(10,min(500,(int)($request['length']??100)));
        return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsFiltered,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary,'account'=>$account];
    }

    public function getClientLedgerPage(array $request, array $filters): array
    {
        $clientId = trim((string)($filters['client_id']??''));
        if ($clientId==='') return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>$this->emptyClientSummary()];
        [$scopeWhere,$scopeParams]=$this->scopeWhere();
        [$filterWhere,$filterParams]=$this->filterWhere($filters,$request);
        $where=$scopeWhere.$filterWhere.' AND EXISTS (SELECT 1 FROM ledger_voucher_line_refs cr WHERE cr.voucher_line_id=l.id AND cr.ref_target IN (\'CLIENT\',\'CUSTOMER\',\'VENDOR\',\'COUNTERPARTY\') AND cr.ref_id=:client_id)';
        $params=$scopeParams+$filterParams+[':client_id'=>$clientId];
        $dateFrom=trim((string)($filters['date_from']??''));
        if($dateFrom!==''){
            $where.=' AND NOT EXISTS (SELECT 1 FROM ledger_opening_balances obx WHERE obx.voucher_id=v.id AND obx.opening_date<=:client_opening_cutoff AND obx.period_end_date>=:client_opening_period)';
            $params[':client_opening_cutoff']=$dateFrom; $params[':client_opening_period']=$dateFrom;
        }
        $sql="SELECT l.id,l.line_no,l.account_id,l.debit,l.credit,l.line_summary,v.id AS voucher_id,v.voucher_no,v.voucher_date,UPPER(v.status) AS voucher_status,v.summary AS voucher_summary,a.account_code,a.account_name,a.normal_balance,(SELECT GROUP_CONCAT(DISTINCT CONCAT(oa.account_code,' ',oa.account_name) ORDER BY oa.account_code SEPARATOR ', ') FROM ledger_voucher_lines ol JOIN ledger_accounts oa ON oa.id=ol.account_id WHERE ol.voucher_id=v.id AND ol.id<>l.id) AS counterpart_summary,(SELECT COUNT(*) FROM ledger_evidence_links el WHERE el.target_type='VOUCHER' AND el.target_id=v.id AND el.deleted_at IS NULL) AS evidence_count FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} ORDER BY a.account_code ASC,v.voucher_date ASC,v.voucher_no ASC,l.line_no ASC,l.id ASC";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        $recordsTotal=count($rows);$openings=$this->clientOpeningBalances($clientId,$dateFrom);$running=[];
        foreach($rows as &$row){
            $accountId=(string)$row['account_id'];$normal=strtoupper((string)$row['normal_balance']);
            $running[$accountId]??=(float)($openings[$accountId]??0);
            $running[$accountId]+=$normal==='CREDIT'?(float)$row['credit']-(float)$row['debit']:(float)$row['debit']-(float)$row['credit'];
            $row['running_balance']=$running[$accountId];
        }
        unset($row);
        $summary=$this->clientSummary($rows,$openings);$recordsFiltered=count($rows);
        $start=max(0,(int)($request['start']??0));$length=max(10,min(500,(int)($request['length']??100)));
        return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsFiltered,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary];
    }

    public function getProjectLedgerPage(array $request,array $filters): array
    {
        $projectId=trim((string)($filters['project_id']??''));
        if($projectId==='')return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>$this->emptyClientSummary()];
        [$scopeWhere,$scopeParams]=$this->scopeWhere();
        [$filterWhere,$filterParams]=$this->filterWhere($filters,$request);
        $where=$scopeWhere.$filterWhere." AND EXISTS (SELECT 1 FROM ledger_voucher_line_refs pr WHERE pr.voucher_line_id=l.id AND pr.ref_target='PROJECT' AND pr.ref_id=:project_id)";
        $params=$scopeParams+$filterParams+[':project_id'=>$projectId];$dateFrom=trim((string)($filters['date_from']??''));
        if($dateFrom!==''){$where.=' AND NOT EXISTS (SELECT 1 FROM ledger_opening_balances obx WHERE obx.voucher_id=v.id AND obx.opening_date<=:project_opening_cutoff AND obx.period_end_date>=:project_opening_period)';$params[':project_opening_cutoff']=$dateFrom;$params[':project_opening_period']=$dateFrom;}
        $sql="SELECT l.id,l.line_no,l.account_id,l.debit,l.credit,l.line_summary,v.id AS voucher_id,v.voucher_no,v.voucher_date,UPPER(v.status) AS voucher_status,v.summary AS voucher_summary,a.account_code,a.account_name,a.normal_balance,(SELECT GROUP_CONCAT(DISTINCT CONCAT(oa.account_code,' ',oa.account_name) ORDER BY oa.account_code SEPARATOR ', ') FROM ledger_voucher_lines ol JOIN ledger_accounts oa ON oa.id=ol.account_id WHERE ol.voucher_id=v.id AND ol.id<>l.id) AS counterpart_summary,(SELECT COUNT(*) FROM ledger_evidence_links el WHERE el.target_type='VOUCHER' AND el.target_id=v.id AND el.deleted_at IS NULL) AS evidence_count FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} ORDER BY a.account_code ASC,v.voucher_date ASC,v.voucher_no ASC,l.line_no ASC,l.id ASC";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$recordsTotal=count($rows);
        $openings=$this->projectOpeningBalances($projectId,$dateFrom);$running=[];
        foreach($rows as &$row){$accountId=(string)$row['account_id'];$normal=strtoupper((string)$row['normal_balance']);$running[$accountId]??=(float)($openings[$accountId]??0);$running[$accountId]+=$normal==='CREDIT'?(float)$row['credit']-(float)$row['debit']:(float)$row['debit']-(float)$row['credit'];$row['running_balance']=$running[$accountId];}
        unset($row);$summary=$this->clientSummary($rows,$openings);$recordsFiltered=count($rows);$start=max(0,(int)($request['start']??0));$length=max(10,min(500,(int)($request['length']??100)));
        return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsFiltered,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary];
    }

    public function getDailyBookPage(array $request,array $filters): array
    {
        [$scopeWhere,$scopeParams]=$this->scopeWhere();[$filterWhere,$filterParams]=$this->filterWhere($filters,$request);$where=$scopeWhere.$filterWhere;$params=$scopeParams+$filterParams;
        $sql="SELECT v.voucher_date,COUNT(DISTINCT v.id) AS voucher_count,COUNT(*) AS line_count,COALESCE(SUM(l.debit),0) AS debit_total,COALESCE(SUM(l.credit),0) AS credit_total FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} GROUP BY v.voucher_date ORDER BY v.voucher_date ASC";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$recordsTotal=count($rows);$runningDebit=0.0;$runningCredit=0.0;
        foreach($rows as &$row){$row['voucher_count']=(int)$row['voucher_count'];$row['line_count']=(int)$row['line_count'];$row['debit_total']=(float)$row['debit_total'];$row['credit_total']=(float)$row['credit_total'];$row['difference']=$row['debit_total']-$row['credit_total'];$runningDebit+=$row['debit_total'];$runningCredit+=$row['credit_total'];$row['running_debit']=$runningDebit;$row['running_credit']=$runningCredit;}
        unset($row);$summary=['day_count'=>count($rows),'voucher_count'=>array_sum(array_column($rows,'voucher_count')),'line_count'=>array_sum(array_column($rows,'line_count')),'debit_total'=>$runningDebit,'credit_total'=>$runningCredit,'difference'=>$runningDebit-$runningCredit];
        $start=max(0,(int)($request['start']??0));$length=max(10,min(500,(int)($request['length']??100)));
        return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsTotal,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary];
    }

    public function getDailyBookDetailPage(array $request,array $filters): array
    {
        $selectedDate=trim((string)($filters['selected_date']??''));
        if($selectedDate==='')return ['records_total'=>0,'records_filtered'=>0,'rows'=>[],'summary'=>['account_count'=>0,'voucher_count'=>0,'line_count'=>0,'debit_total'=>0.0,'credit_total'=>0.0,'difference'=>0.0]];
        [$scopeWhere,$scopeParams]=$this->scopeWhere();[$filterWhere,$filterParams]=$this->filterWhere($filters,$request);
        $where=$scopeWhere.$filterWhere.' AND v.voucher_date=:selected_date';$params=$scopeParams+$filterParams+[':selected_date'=>$selectedDate];
        $sql="SELECT a.id AS account_id,a.account_code,a.account_name,a.normal_balance,COUNT(DISTINCT v.id) AS voucher_count,COUNT(*) AS line_count,COALESCE(SUM(l.debit),0) AS debit_total,COALESCE(SUM(l.credit),0) AS credit_total FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} GROUP BY a.id,a.account_code,a.account_name,a.normal_balance ORDER BY a.account_code ASC";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$debit=0.0;$credit=0.0;
        foreach($rows as &$row){$row['voucher_count']=(int)$row['voucher_count'];$row['line_count']=(int)$row['line_count'];$row['debit_total']=(float)$row['debit_total'];$row['credit_total']=(float)$row['credit_total'];$normal=strtoupper((string)$row['normal_balance']);$row['net_change']=$normal==='CREDIT'?$row['credit_total']-$row['debit_total']:$row['debit_total']-$row['credit_total'];$debit+=$row['debit_total'];$credit+=$row['credit_total'];}
        unset($row);$recordsTotal=count($rows);$voucherCountStmt=$this->db->prepare("SELECT COUNT(DISTINCT v.id) FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where}");$voucherCountStmt->execute($params);$summary=['account_count'=>$recordsTotal,'voucher_count'=>(int)$voucherCountStmt->fetchColumn(),'line_count'=>array_sum(array_column($rows,'line_count')),'debit_total'=>$debit,'credit_total'=>$credit,'difference'=>$debit-$credit];
        $start=max(0,(int)($request['start']??0));$length=max(10,min(500,(int)($request['length']??100)));
        return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsTotal,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary];
    }

    public function getPurchaseSalesPage(array $request,array $filters): array
    {
        $statuses=['POSTED','CLOSED'];
        $statusSql=implode(',',array_fill(0,count($statuses),'?'));
        $sql="WITH evidence_rows AS (
            SELECT 'TAX_INVOICE' evidence_type,id,transaction_direction,client_id,project_id,raw_written_date evidence_date,NULL approval_number,raw_supplier_business_number supplier_number,raw_supplier_company_name supplier_name,raw_customer_business_number customer_number,raw_customer_company_name customer_name,raw_item_name item_name,raw_supply_amount supply_amount,raw_vat_amount vat_amount,raw_total_amount total_amount FROM ledger_evidence_tax_invoice WHERE deleted_at IS NULL
            UNION ALL
            SELECT 'TAX_INVOICE_MANUAL',id,transaction_direction,client_id,project_id,raw_written_date,NULL,raw_supplier_business_number,raw_supplier_company_name,raw_customer_business_number,raw_customer_company_name,raw_item_name,raw_supply_amount,raw_vat_amount,raw_total_amount FROM ledger_evidence_tax_invoice_manual WHERE deleted_at IS NULL
            UNION ALL
            SELECT 'CASH_RECEIPT',id,transaction_direction,client_id,project_id,DATE(raw_purchase_datetime),NULL,raw_merchant_business_number,raw_merchant_company_name,NULL,NULL,raw_business_name,raw_supply_amount,raw_vat_amount,raw_total_amount FROM ledger_evidence_cash_receipt WHERE deleted_at IS NULL
            UNION ALL
            SELECT 'CARD_HOMETAX',id,transaction_direction,client_id,project_id,raw_approval_date,NULL,raw_merchant_business_number,raw_merchant_company_name,NULL,NULL,raw_business_name,raw_supply_amount,raw_vat_amount,raw_total_amount FROM ledger_evidence_card_hometax WHERE deleted_at IS NULL
            UNION ALL
            SELECT 'CARD_STATEMENT',id,transaction_direction,client_id,project_id,raw_approval_date,raw_approval_number,raw_merchant_business_number,raw_merchant_company_name,NULL,NULL,raw_merchant_business_name,raw_transaction_amount_krw-raw_vat_amount-raw_service_fee_amount,raw_vat_amount,raw_transaction_amount_krw FROM ledger_evidence_card_statement WHERE deleted_at IS NULL
        ), voucher_links AS (
            SELECT evidence_type,evidence_id,MIN(target_id) voucher_id FROM ledger_evidence_links WHERE target_type='VOUCHER' AND deleted_at IS NULL GROUP BY evidence_type,evidence_id
        ), transaction_links AS (
            SELECT evidence_type,evidence_id,MIN(target_id) transaction_id FROM ledger_evidence_links WHERE target_type='TRANSACTION' AND deleted_at IS NULL GROUP BY evidence_type,evidence_id
        )
        SELECT e.*,v.id voucher_id,v.voucher_no,v.voucher_date,UPPER(v.status) voucher_status,t.id transaction_id,
          COALESCE(NULLIF(e.transaction_direction,''),NULLIF(t.transaction_direction,''),CASE WHEN REPLACE(e.customer_number,'-','')=REPLACE(c.biz_number,'-','') THEN 'EXPENSE' WHEN REPLACE(e.supplier_number,'-','')=REPLACE(c.biz_number,'-','') THEN 'INCOME' ELSE '' END) direction_code,
          COALESCE(NULLIF(sc.client_name,''),NULLIF(sc.company_name,''),CASE WHEN COALESCE(NULLIF(e.transaction_direction,''),NULLIF(t.transaction_direction,''))='INCOME' OR REPLACE(e.supplier_number,'-','')=REPLACE(c.biz_number,'-','') THEN e.customer_name ELSE e.supplier_name END,e.supplier_name,e.customer_name,'') counterparty_name,
          COALESCE(NULLIF(sc.business_number,''),CASE WHEN COALESCE(NULLIF(e.transaction_direction,''),NULLIF(t.transaction_direction,''))='INCOME' OR REPLACE(e.supplier_number,'-','')=REPLACE(c.biz_number,'-','') THEN e.customer_number ELSE e.supplier_number END,e.supplier_number,e.customer_number,'') business_number,
          COALESCE(sp.project_name,'') project_name
        FROM evidence_rows e JOIN voucher_links vl ON vl.evidence_type=e.evidence_type AND vl.evidence_id=e.id JOIN ledger_vouchers v ON v.id=vl.voucher_id AND v.deleted_at IS NULL
        LEFT JOIN transaction_links tl ON tl.evidence_type=e.evidence_type AND tl.evidence_id=e.id LEFT JOIN ledger_transactions t ON t.id=tl.transaction_id AND t.deleted_at IS NULL
        LEFT JOIN system_clients sc ON sc.id=COALESCE(e.client_id,t.client_id) AND sc.deleted_at IS NULL LEFT JOIN system_projects sp ON sp.id=COALESCE(e.project_id,t.project_id) AND sp.deleted_at IS NULL
        LEFT JOIN (SELECT biz_number FROM system_company ORDER BY created_at LIMIT 1) c ON 1=1 WHERE UPPER(v.status) IN ({$statusSql})";
        $stmt=$this->db->prepare($sql);$stmt->execute($statuses);$all=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$recordsTotal=count($all);
        $normalized=$this->normalizePurchaseSalesFilters($filters);$global=trim((string)($request['search']['value']??''));if($global!==''&&empty($normalized['keyword']))$normalized['keyword']=$global;
        $rows=array_values(array_filter($all,static function(array $row)use($normalized):bool{
            $date=(string)$row['evidence_date'];if(($normalized['date_from']??'')!==''&&$date<(string)$normalized['date_from'])return false;if(($normalized['date_to']??'')!==''&&$date>(string)$normalized['date_to'])return false;
            if(($normalized['direction_code']??'')!==''&&(string)$row['direction_code']!==(string)$normalized['direction_code'])return false;
            if(($normalized['evidence_type']??'')!==''&&(string)$row['evidence_type']!==(string)$normalized['evidence_type'])return false;
            foreach(['counterparty_name','business_number','voucher_no','project_name'] as $field){$value=trim((string)($normalized[$field]??''));if($value!==''&&!str_contains((string)($row[$field]??''),$value))return false;}
            $keyword=trim((string)($normalized['keyword']??''));if($keyword!==''&&!str_contains(implode(' ',array_map('strval',[$row['counterparty_name'],$row['business_number'],$row['voucher_no'],$row['item_name'],$row['project_name']])), $keyword))return false;return true;
        }));
        $summary=['row_count'=>count($rows),'sales_supply'=>0.0,'sales_vat'=>0.0,'sales_total'=>0.0,'purchase_supply'=>0.0,'purchase_vat'=>0.0,'purchase_total'=>0.0];
        foreach($rows as &$row){$row['supply_amount']=(float)$row['supply_amount'];$row['vat_amount']=(float)$row['vat_amount'];$row['total_amount']=(float)$row['total_amount'];$prefix=(string)$row['direction_code']==='INCOME'?'sales':'purchase';$summary[$prefix.'_supply']+=$row['supply_amount'];$summary[$prefix.'_vat']+=$row['vat_amount'];$summary[$prefix.'_total']+=$row['total_amount'];}
        unset($row);$recordsFiltered=count($rows);$order=$request['order'][0]??[];$index=(int)($order['column']??-1);$field=(string)($request['columns'][$index]['data']??'evidence_date');$allowed=['evidence_date','direction_code','evidence_type','counterparty_name','business_number','supply_amount','vat_amount','total_amount','voucher_no','voucher_status','project_name'];$field=in_array($field,$allowed,true)?$field:'evidence_date';$direction=strtolower((string)($order['dir']??'desc'))==='asc'?1:-1;usort($rows,static fn(array $a,array $b):int=>(is_numeric($a[$field]??null)&&is_numeric($b[$field]??null)?((float)$a[$field]<=>(float)$b[$field]):strnatcasecmp((string)($a[$field]??''),(string)($b[$field]??'')))*$direction);
        $start=max(0,(int)($request['start']??0));$length=max(10,min(500,(int)($request['length']??100)));return ['records_total'=>$recordsTotal,'records_filtered'=>$recordsFiltered,'rows'=>array_slice($rows,$start,$length),'summary'=>$summary];
    }

    private function normalizePurchaseSalesFilters(array $filters): array
    {
        $result=$filters;
        foreach($filters as $filter){
            if(!is_array($filter))continue;
            $field=trim((string)($filter['field']??''));$value=$filter['value']??'';
            if(is_array($value)&&$field==='evidence_date'){
                $result['date_from']=trim((string)($value['start']??''));
                $result['date_to']=trim((string)($value['end']??''));
            }elseif(!is_array($value)&&$field!==''){
                $result[$field]=trim((string)$value);
            }
        }
        $direction=trim((string)($result['direction_code']??''));
        if($direction==='매출')$result['direction_code']='INCOME';
        if($direction==='매입')$result['direction_code']='EXPENSE';
        $type=trim((string)($result['evidence_type']??''));
        $typeMap=['전자세금계산서'=>'TAX_INVOICE','수기 세금계산서'=>'TAX_INVOICE_MANUAL','현금영수증'=>'CASH_RECEIPT','카드 국세청'=>'CARD_HOMETAX','카드명세'=>'CARD_STATEMENT'];
        if(isset($typeMap[$type]))$result['evidence_type']=$typeMap[$type];
        return $result;
    }

    private function scopeWhere(): array
    {
        $statuses = ['POSTED', 'CLOSED'];
        $params = []; $holders = [];
        foreach ($statuses as $index => $status) {
            $key = ':scope_status_' . $index; $holders[] = $key; $params[$key] = $status;
        }
        return [' WHERE v.deleted_at IS NULL AND UPPER(v.status) IN (' . implode(', ', $holders) . ')', $params];
    }

    private function filterWhere(array $filters, array $request): array
    {
        $where = ''; $params = []; $normalized = $this->normalizeFilters($filters);
        $global = trim((string) ($request['search']['value'] ?? ''));
        if ($global !== '' && empty($normalized['keyword'])) $normalized['keyword'] = $global;
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = trim((string) ($normalized[$key] ?? ''));
            if ($value !== '') { $where .= " AND v.voucher_date {$operator} :{$key}"; $params[':' . $key] = $value; }
        }
        $accountId = trim((string) ($normalized['account_id'] ?? ''));
        if ($accountId !== '') {
            $where .= ' AND l.account_id = :account_id';
            $params[':account_id'] = $accountId;
        }
        $fieldMap = ['voucher_no'=>'v.voucher_no','voucher_status'=>'v.status','account_code'=>'a.account_code','account_name'=>'a.account_name','line_summary'=>'l.line_summary'];
        foreach ($fieldMap as $field => $column) {
            $value = trim((string) ($normalized[$field] ?? ''));
            if ($value === '') continue;
            $key = ':filter_' . $field; $where .= " AND COALESCE({$column}, '') LIKE {$key}"; $params[$key] = '%' . $value . '%';
        }
        $keyword = trim((string) ($normalized['keyword'] ?? ''));
        if ($keyword !== '') {
            $where .= " AND (v.voucher_no LIKE :keyword_no OR COALESCE(v.summary, '') LIKE :keyword_voucher OR COALESCE(l.line_summary, '') LIKE :keyword_line OR a.account_code LIKE :keyword_code OR a.account_name LIKE :keyword_account)";
            foreach ([':keyword_no',':keyword_voucher',':keyword_line',':keyword_code',':keyword_account'] as $key) $params[$key] = '%' . $keyword . '%';
        }
        return [$where, $params];
    }

    private function normalizeFilters(array $filters): array
    {
        $result = $filters;
        foreach ($filters as $filter) {
            if (!is_array($filter)) continue;
            $field = trim((string) ($filter['field'] ?? '')); $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === []) continue;
            if (is_array($value) && $field === 'voucher_date') {
                $result['date_from'] = trim((string) ($value['start'] ?? ''));
                $result['date_to'] = trim((string) ($value['end'] ?? ''));
            } elseif (!is_array($value)) $result[$field] = trim((string) $value);
        }
        return $result;
    }

    private function countRows(string $where, array $params): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id' . $where);
        $stmt->execute($params); return (int) $stmt->fetchColumn();
    }

    private function fetchRows(string $where, array $params, string $orderBy, int $start, int $length): array
    {
        $sql = "SELECT l.id,l.line_no,l.account_id,l.debit,l.credit,l.line_summary,v.id AS voucher_id,v.voucher_no,v.voucher_date,UPPER(v.status) AS voucher_status,v.summary AS voucher_summary,a.account_code,a.account_name,a.normal_balance,(SELECT COUNT(*) FROM ledger_evidence_links el WHERE el.target_type='VOUCHER' AND el.target_id=v.id AND el.deleted_at IS NULL) AS evidence_count FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where} {$orderBy} LIMIT :page_length OFFSET :page_start";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':page_length', $length, PDO::PARAM_INT); $stmt->bindValue(':page_start', $start, PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function summary(string $where, array $params): array
    {
        $sql = "SELECT COUNT(DISTINCT v.id) AS voucher_count,COUNT(*) AS line_count,COALESCE(SUM(l.debit),0) AS debit_total,COALESCE(SUM(l.credit),0) AS credit_total FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$where}";
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function orderBy(array $request): string
    {
        $order = $request['order'][0] ?? []; $index = (int) ($order['column'] ?? -1);
        $field = trim((string) ($request['columns'][$index]['data'] ?? ''));
        $column = self::ORDER_COLUMNS[$field] ?? 'v.voucher_date';
        $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        return " ORDER BY {$column} {$direction},v.voucher_no ASC,l.line_no ASC,l.id ASC";
    }

    private function generalLedgerRows(array $filters): array
    {
        [$scopeWhere, $scopeParams] = $this->scopeWhere();
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $movementWhere = $scopeWhere;
        $movementParams = $scopeParams;
        if ($dateFrom !== '') {
            $movementWhere .= ' AND v.voucher_date >= :general_date_from';
            $movementParams[':general_date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $movementWhere .= ' AND v.voucher_date <= :general_date_to';
            $movementParams[':general_date_to'] = $dateTo;
        }
        if ($dateFrom !== '') {
            $movementWhere .= ' AND NOT EXISTS (SELECT 1 FROM ledger_opening_balances obx WHERE obx.voucher_id=v.id AND obx.opening_date<=:opening_cutoff AND obx.period_end_date>=:opening_cutoff_end)';
            $movementParams[':opening_cutoff'] = $dateFrom;
            $movementParams[':opening_cutoff_end'] = $dateFrom;
        }
        $movementSql = "SELECT l.account_id,SUM(l.debit) AS period_debit,SUM(l.credit) AS period_credit,COUNT(*) AS line_count FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id JOIN ledger_accounts a ON a.id=l.account_id {$movementWhere} GROUP BY l.account_id";
        $movementStmt = $this->db->prepare($movementSql);
        $movementStmt->execute($movementParams);
        $movements = [];
        foreach ($movementStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $movements[(string) $row['account_id']] = $row;

        $openings = [];
        if ($dateFrom !== '') {
            $openingSql = "SELECT l.account_id,SUM(l.debit-l.credit) AS opening_net FROM ledger_opening_balances ob JOIN ledger_vouchers v ON v.id=ob.voucher_id AND v.deleted_at IS NULL JOIN ledger_voucher_lines l ON l.voucher_id=v.id WHERE ob.opening_date<=:opening_date AND ob.period_end_date>=:period_date AND UPPER(v.status) IN ('POSTED','CLOSED') GROUP BY l.account_id";
            $openingStmt = $this->db->prepare($openingSql);
            $openingStmt->execute([':opening_date'=>$dateFrom, ':period_date'=>$dateFrom]);
            foreach ($openingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $openings[(string) $row['account_id']] = (float) $row['opening_net'];
        }

        $accounts = $this->db->query("SELECT id,account_code,account_name,normal_balance FROM ledger_accounts WHERE deleted_at IS NULL AND COALESCE(is_posting,0)=1 ORDER BY account_code,id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = [];
        foreach ($accounts as $account) {
            $id = (string) $account['id'];
            $movement = $movements[$id] ?? [];
            $openingNet = (float) ($openings[$id] ?? 0);
            $periodDebit = (float) ($movement['period_debit'] ?? 0);
            $periodCredit = (float) ($movement['period_credit'] ?? 0);
            if ($openingNet == 0.0 && $periodDebit == 0.0 && $periodCredit == 0.0) continue;
            $normal = strtoupper((string) ($account['normal_balance'] ?? 'DEBIT'));
            $openingBalance = $normal === 'CREDIT' ? -$openingNet : $openingNet;
            $movementBalance = $normal === 'CREDIT' ? $periodCredit-$periodDebit : $periodDebit-$periodCredit;
            $rows[] = $account + [
                'opening_balance'=>$openingBalance,
                'period_debit'=>$periodDebit,
                'period_credit'=>$periodCredit,
                'ending_balance'=>$openingBalance+$movementBalance,
                'line_count'=>(int) ($movement['line_count'] ?? 0),
            ];
        }
        return $rows;
    }

    private function generalLedgerSummary(array $rows): array
    {
        $summary = ['account_count'=>count($rows),'opening_debit'=>0.0,'opening_credit'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'ending_debit'=>0.0,'ending_credit'=>0.0];
        foreach ($rows as $row) {
            $normal = strtoupper((string) ($row['normal_balance'] ?? 'DEBIT'));
            $opening = (float) $row['opening_balance']; $ending = (float) $row['ending_balance'];
            $summary[$normal === 'CREDIT' ? 'opening_credit' : 'opening_debit'] += $opening;
            $summary['period_debit'] += (float) $row['period_debit'];
            $summary['period_credit'] += (float) $row['period_credit'];
            $summary[$normal === 'CREDIT' ? 'ending_credit' : 'ending_debit'] += $ending;
        }
        $summary['difference'] = ($summary['opening_debit']+$summary['period_debit']-$summary['period_credit'])-($summary['opening_credit']+$summary['ending_debit']-$summary['ending_credit']);
        return $summary;
    }

    private function accountOpeningBalance(string $accountId, string $dateFrom, string $normalBalance): float
    {
        if ($dateFrom === '') return 0.0;
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM ledger_opening_balances ob JOIN ledger_vouchers v ON v.id=ob.voucher_id AND v.deleted_at IS NULL JOIN ledger_voucher_lines l ON l.voucher_id=v.id WHERE l.account_id=:account_id AND ob.opening_date<=:opening_date AND ob.period_end_date>=:period_date AND UPPER(v.status) IN ('POSTED','CLOSED')");
        $stmt->execute([':account_id'=>$accountId,':opening_date'=>$dateFrom,':period_date'=>$dateFrom]);
        $net = (float)$stmt->fetchColumn();
        return strtoupper($normalBalance)==='CREDIT' ? -$net : $net;
    }

    private function accountSummary(array $rows, array $account, float $openingBalance): array
    {
        $debit=0.0; $credit=0.0;
        foreach ($rows as $row) { $debit+=(float)$row['debit']; $credit+=(float)$row['credit']; }
        $normal = strtoupper((string)$account['normal_balance']);
        $ending = $openingBalance + ($normal==='CREDIT' ? $credit-$debit : $debit-$credit);
        return ['account_id'=>$account['id'],'account_code'=>$account['account_code'],'account_name'=>$account['account_name'],'normal_balance'=>$normal,'opening_balance'=>$openingBalance,'period_debit'=>$debit,'period_credit'=>$credit,'ending_balance'=>$ending,'voucher_count'=>count(array_unique(array_column($rows,'voucher_id'))),'line_count'=>count($rows)];
    }

    private function emptyAccountSummary(): array
    {
        return ['account_id'=>'','account_code'=>'','account_name'=>'','normal_balance'=>'DEBIT','opening_balance'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'ending_balance'=>0.0,'voucher_count'=>0,'line_count'=>0];
    }

    private function clientOpeningBalances(string $clientId,string $dateFrom): array
    {
        if($dateFrom==='')return [];
        $sql="SELECT l.account_id,a.normal_balance,SUM(l.debit-l.credit) AS opening_net FROM ledger_opening_balances ob JOIN ledger_vouchers v ON v.id=ob.voucher_id AND v.deleted_at IS NULL JOIN ledger_voucher_lines l ON l.voucher_id=v.id JOIN ledger_accounts a ON a.id=l.account_id JOIN ledger_voucher_line_refs r ON r.voucher_line_id=l.id AND r.ref_target IN ('CLIENT','CUSTOMER','VENDOR','COUNTERPARTY') AND r.ref_id=:client_id WHERE ob.opening_date<=:opening_date AND ob.period_end_date>=:period_date AND UPPER(v.status) IN ('POSTED','CLOSED') GROUP BY l.account_id,a.normal_balance";
        $stmt=$this->db->prepare($sql);$stmt->execute([':client_id'=>$clientId,':opening_date'=>$dateFrom,':period_date'=>$dateFrom]);$balances=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$net=(float)$row['opening_net'];$balances[(string)$row['account_id']]=strtoupper((string)$row['normal_balance'])==='CREDIT'?-$net:$net;}
        return $balances;
    }

    private function clientSummary(array $rows,array $openings): array
    {
        $accounts=[];$voucherIds=[];$debit=0.0;$credit=0.0;
        foreach($rows as $row){
            $id=(string)$row['account_id'];$normal=strtoupper((string)$row['normal_balance']);
            $accounts[$id]??=['normal'=>$normal,'opening'=>(float)($openings[$id]??0),'debit'=>0.0,'credit'=>0.0];
            $accounts[$id]['debit']+=(float)$row['debit'];$accounts[$id]['credit']+=(float)$row['credit'];
            $debit+=(float)$row['debit'];$credit+=(float)$row['credit'];$voucherIds[(string)$row['voucher_id']]=true;
        }
        $summary=['account_count'=>count($accounts),'voucher_count'=>count($voucherIds),'line_count'=>count($rows),'opening_debit'=>0.0,'opening_credit'=>0.0,'period_debit'=>$debit,'period_credit'=>$credit,'ending_debit'=>0.0,'ending_credit'=>0.0];
        foreach($accounts as $account){
            $ending=$account['opening']+($account['normal']==='CREDIT'?$account['credit']-$account['debit']:$account['debit']-$account['credit']);
            $summary[$account['normal']==='CREDIT'?'opening_credit':'opening_debit']+=$account['opening'];
            $summary[$account['normal']==='CREDIT'?'ending_credit':'ending_debit']+=$ending;
        }
        return $summary;
    }

    private function emptyClientSummary(): array
    {
        return ['account_count'=>0,'voucher_count'=>0,'line_count'=>0,'opening_debit'=>0.0,'opening_credit'=>0.0,'period_debit'=>0.0,'period_credit'=>0.0,'ending_debit'=>0.0,'ending_credit'=>0.0];
    }

    private function projectOpeningBalances(string $projectId,string $dateFrom): array
    {
        if($dateFrom==='')return [];
        $sql="SELECT l.account_id,a.normal_balance,SUM(l.debit-l.credit) AS opening_net FROM ledger_opening_balances ob JOIN ledger_vouchers v ON v.id=ob.voucher_id AND v.deleted_at IS NULL JOIN ledger_voucher_lines l ON l.voucher_id=v.id JOIN ledger_accounts a ON a.id=l.account_id JOIN ledger_voucher_line_refs r ON r.voucher_line_id=l.id AND r.ref_target='PROJECT' AND r.ref_id=:project_id WHERE ob.opening_date<=:opening_date AND ob.period_end_date>=:period_date AND UPPER(v.status) IN ('POSTED','CLOSED') GROUP BY l.account_id,a.normal_balance";
        $stmt=$this->db->prepare($sql);$stmt->execute([':project_id'=>$projectId,':opening_date'=>$dateFrom,':period_date'=>$dateFrom]);$balances=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$net=(float)$row['opening_net'];$balances[(string)$row['account_id']]=strtoupper((string)$row['normal_balance'])==='CREDIT'?-$net:$net;}
        return $balances;
    }
}
