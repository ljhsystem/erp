<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\FinancialStatementService;
use Core\DbPdo;
use Throwable;

final class FinancialStatementController
{
    private LayoutController $layout;private FinancialStatementService $service;
    private const META=[
        'trial-balance'=>['합계잔액시산표','계정별 기초잔액·당기 차대변·기말잔액을 대조합니다.'],
        'income-statement'=>['손익계산서','수익과 비용을 계층별로 집계해 당기손익을 확인합니다.'],
        'statement-position'=>['재무상태표','기준일 현재 자산·부채·자본과 회계등식을 확인합니다.'],
        'product-cost'=>['상품원가명세서','상품 관련 기초재고·매입·부대원가·기말재고를 확인합니다.'],
        'construction-cost'=>['공사원가명세서','공사 재료비·노무비·경비와 완성공사원가를 확인합니다.'],
        'retained-earnings'=>['이익잉여금처분계산서','이익잉여금과 당기순이익을 토대로 차기이월액을 확인합니다.'],
    ];
    public function __construct(){$pdo=DbPdo::conn();$this->layout=new LayoutController($pdo);$this->service=FinancialStatementService::fromPdo($pdo);}
    public function trialBalance():void{$this->render('trial-balance');}
    public function incomeStatement():void{$this->render('income-statement');}
    public function statementPosition():void{$this->render('statement-position');}
    public function productCost():void{$this->render('product-cost');}
    public function constructionCost():void{$this->render('construction-cost');}
    public function retainedEarnings():void{$this->render('retained-earnings');}
    public function apiReport():void{header('Content-Type: application/json; charset=utf-8');try{$type=trim((string)($_GET['type']??''));$report=$this->service->report($type,['date_from'=>$_GET['date_from']??'','date_to'=>$_GET['date_to']??'']);echo json_encode(['success'=>true,'data'=>$report,'message'=>'재무제표를 불러왔습니다.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'data'=>null,'message'=>$e instanceof \InvalidArgumentException?$e->getMessage():'재무제표를 불러오지 못했습니다.'],JSON_UNESCAPED_UNICODE);}}
    private function render(string $type):void{[$pageTitle,$pageDescription]=self::META[$type];ob_start();require PROJECT_ROOT.'/app/views/ledger/financial/report/index.php';$content=ob_get_clean();$this->layout->render(compact('pageTitle','pageDescription','type','content','pageStyles','pageScripts','layoutOptions'));}
}
