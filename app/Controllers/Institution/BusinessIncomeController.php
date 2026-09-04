<?php

declare(strict_types=1);

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Institution\BusinessIncomeService;
use App\Services\Institution\BusinessIncomeExcelService;
use Core\DbPdo;
use Core\Helpers\ApiErrorResponseHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class BusinessIncomeController
{
    private PDO $db; private BusinessIncomeService $service; private BusinessIncomeExcelService $excel;
    public function __construct(?PDO $db=null){$this->db=$db??DbPdo::conn();$this->service=new BusinessIncomeService($this->db);$this->excel=new BusinessIncomeExcelService($this->db);}
    public function webIndex():void{ob_start();require PROJECT_ROOT.'/app/views/institution/business-income/index.php';$content=ob_get_clean();(new LayoutController($this->db))->render(['pageTitle'=>'사업소득','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']);}
    public function apiList():void{$this->respond(fn()=>$this->service->page(\Core\Helpers\DataTableRequestHelper::input()));}
    public function apiDetail():void{$this->respond(fn()=>$this->service->detail(trim((string)($_GET['id']??''))));}
    public function apiOptions():void{$this->respond(fn()=>$this->service->options($_GET));}
    public function apiCalculate():void{$this->respond(fn()=>$this->service->calculate($this->input()));}
    public function apiPreflight():void{$this->respond(fn()=>$this->service->submissionPreflight(trim((string)($_GET['id']??''))));}
    public function apiSave():void{$this->respond(fn()=>$this->service->save($this->input()),'저장 중 오류가 발생했습니다.');}
    public function apiSubmit():void{$input=$this->input();$this->respond(fn()=>$this->service->submit(trim((string)($input['id']??''))),'결재요청 중 오류가 발생했습니다.');}
    public function apiWithdraw():void{$input=$this->input();$this->respond(fn()=>$this->service->withdraw(trim((string)($input['request_id']??''))),'기안회수 중 오류가 발생했습니다.');}
    public function apiDelete():void{$input=$this->input();$this->respond(fn()=>$this->service->delete(trim((string)($input['id']??''))),'삭제 중 오류가 발생했습니다.');}
    public function apiTrashList():void{$this->respond(fn()=>$this->service->trash());}
    public function apiRestore():void{$input=$this->input();$this->respond(fn()=>$this->service->restore(trim((string)($input['id']??''))),'복구 중 오류가 발생했습니다.');}
    public function apiPurge():void{$input=$this->input();$this->respond(fn()=>$this->service->purge(trim((string)($input['id']??''))),'영구삭제 중 오류가 발생했습니다.');}
    public function apiTemplate():void{$this->downloadSpreadsheet($this->excel->createTemplate(),'business_income_template.xlsx');}
    public function apiExcel():void{$input=$this->input();$groups=$input['groups']??[];$header=$input['header']??[];if(is_string($groups))$groups=json_decode($groups,true)?:[];if(is_string($header))$header=json_decode($header,true)?:[];$this->downloadSpreadsheet($this->excel->createDownload(is_array($groups)?$groups:[],is_array($header)?$header:[]),'business_income.xlsx');}
    public function apiExcelUploadPreview():void{$this->respond(function():array{foreach($_FILES as $file){if(is_array($file)&&!empty($file['tmp_name'])&&is_uploaded_file((string)$file['tmp_name']))return $this->excel->preview((string)$file['tmp_name'],trim((string)($_POST['income_year_month']??'')));}throw new \InvalidArgumentException('업로드할 엑셀 파일을 선택해 주세요.');},'엑셀 업로드 중 오류가 발생했습니다.');}
    private function input():array{$decoded=json_decode((string)file_get_contents('php://input'),true);return is_array($decoded)?$decoded:$_POST;}
    private function respond(callable $callback,string $fallback='사업소득 처리 중 오류가 발생했습니다.'):void{try{$result=$callback();$status=empty($result['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException $exception){$result=ApiErrorResponseHelper::exception($exception,$fallback);$status=400;}catch(\Throwable){$result=ApiErrorResponseHelper::payload('INTERNAL_ERROR',$fallback);$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private function downloadSpreadsheet(Spreadsheet $spreadsheet,string $filename):void{header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: max-age=0');(new Xlsx($spreadsheet))->save('php://output');}
}
