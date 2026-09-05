<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\BookService;
use Core\DbPdo;
use Core\Helpers\DataTableRequestHelper;
use PDO;
use Throwable;

final class BookController
{
    private PDO $pdo;
    private LayoutController $layout;
    private BookService $service;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
        $this->service = new BookService($this->pdo);
    }

    public function journal(): void
    {
        $pageTitle = '분개장';
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/book/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle', 'content', 'pageStyles', 'pageScripts', 'layoutOptions'));
    }

    public function general(): void
    {
        $pageTitle = '총계정원장';
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/book/general/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle', 'content', 'pageStyles', 'pageScripts', 'layoutOptions'));
    }

    public function account(): void
    {
        $pageTitle = '계정별원장';
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/book/account/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function partner(): void
    {
        $pageTitle='거래처원장';
        ob_start();
        require PROJECT_ROOT.'/app/views/ledger/book/partner/index.php';
        $content=ob_get_clean();
        $this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function project(): void
    {
        $pageTitle='프로젝트원장';ob_start();require PROJECT_ROOT.'/app/views/ledger/book/project/index.php';$content=ob_get_clean();
        $this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function daily(): void
    {
        $pageTitle='일계표';ob_start();require PROJECT_ROOT.'/app/views/ledger/book/daily/index.php';$content=ob_get_clean();$this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function purchaseSales(): void
    {
        $pageTitle='매입매출장';ob_start();require PROJECT_ROOT.'/app/views/ledger/book/purchase-sales/index.php';$content=ob_get_clean();$this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function apiJournalList(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $request = DataTableRequestHelper::input();
        try {
            $filters = json_decode((string) ($request['filters'] ?? '[]'), true);
            $filters = is_array($filters) ? $filters : [];
            $page = $this->service->getJournalPage($request, $filters);
            echo json_encode(['success'=>true,'message'=>'분개장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'분개장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiGeneralList(): void
    {
        $this->generalJsonResponse(false);
    }

    public function apiGeneralDetail(): void
    {
        $this->generalJsonResponse(true);
    }

    public function apiAccountList(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $request = DataTableRequestHelper::input();
        try {
            $filters = json_decode((string)($request['filters']??'[]'),true);
            $filters = is_array($filters)?$filters:[];
            $filters['account_id'] = trim((string)($request['account_id']??''));
            $page = $this->service->getAccountLedgerPage($request,$filters);
            echo json_encode(['success'=>true,'message'=>'계정별원장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'계정별원장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]],JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiPartnerList(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $request=DataTableRequestHelper::input();
        try{
            $filters=json_decode((string)($request['filters']??'[]'),true);$filters=is_array($filters)?$filters:[];
            $filters['client_id']=trim((string)($request['client_id']??''));
            $page=$this->service->getClientLedgerPage($request,$filters);
            echo json_encode(['success'=>true,'message'=>'거래처원장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        }catch(Throwable){
            http_response_code(500);echo json_encode(['success'=>false,'message'=>'거래처원장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]],JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiProjectList(): void
    {
        header('Content-Type: application/json; charset=utf-8');$request=DataTableRequestHelper::input();
        try{$filters=json_decode((string)($request['filters']??'[]'),true);$filters=is_array($filters)?$filters:[];$filters['project_id']=trim((string)($request['project_id']??''));$page=$this->service->getProjectLedgerPage($request,$filters);echo json_encode(['success'=>true,'message'=>'프로젝트원장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable){http_response_code(500);echo json_encode(['success'=>false,'message'=>'프로젝트원장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]],JSON_UNESCAPED_UNICODE);}
    }

    public function apiDailyList(): void
    {
        $this->dailyJsonResponse(false);
    }

    public function apiDailyDetail(): void
    {
        $this->dailyJsonResponse(true);
    }

    public function apiPurchaseSalesList(): void
    {
        header('Content-Type: application/json; charset=utf-8');$request=DataTableRequestHelper::input();
        try{$filters=json_decode((string)($request['filters']??'[]'),true);$filters=is_array($filters)?$filters:[];$page=$this->service->getPurchaseSalesPage($request,$filters);echo json_encode(['success'=>true,'message'=>'매입매출장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable){http_response_code(500);echo json_encode(['success'=>false,'message'=>'매입매출장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]],JSON_UNESCAPED_UNICODE);}
    }

    private function dailyJsonResponse(bool $detail): void
    {
        header('Content-Type: application/json; charset=utf-8');$request=DataTableRequestHelper::input();
        try{$filters=json_decode((string)($request['filters']??'[]'),true);$filters=is_array($filters)?$filters:[];if($detail)$filters['selected_date']=trim((string)($request['selected_date']??''));$page=$detail?$this->service->getDailyBookDetailPage($request,$filters):$this->service->getDailyBookPage($request,$filters);echo json_encode(['success'=>true,'message'=>$detail?'일계표 계정 상세를 불러왔습니다.':'일계표를 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable){http_response_code(500);echo json_encode(['success'=>false,'message'=>$detail?'일계표 계정 상세를 불러오지 못했습니다.':'일계표를 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]],JSON_UNESCAPED_UNICODE);}
    }

    private function generalJsonResponse(bool $detail): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $request = DataTableRequestHelper::input();
        try {
            $filters = json_decode((string) ($request['filters'] ?? '[]'), true);
            $filters = is_array($filters) ? $filters : [];
            if ($detail) $filters['account_id'] = trim((string) ($request['account_id'] ?? ''));
            $page = $detail
                ? $this->service->getGeneralLedgerDetailPage($request, $filters)
                : $this->service->getGeneralLedgerPage($request, $filters);
            echo json_encode(['success'=>true,'message'=>$detail?'계정 상세원장을 불러왔습니다.':'총계정원장을 불러왔습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>$page['records_total'],'recordsFiltered'=>$page['records_filtered'],'data'=>$page['rows'],'summary'=>$page['summary']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>$detail?'계정 상세원장을 불러오지 못했습니다.':'총계정원장을 불러오지 못했습니다.','draw'=>(int)($request['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]], JSON_UNESCAPED_UNICODE);
        }
    }
}
