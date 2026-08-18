<?php
namespace App\Services\Approval;
use App\Services\Institution\RegularEmploymentIncomeService;
use PDO;
class RegularEmploymentIncomeApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private RegularEmploymentIncomeService $service;
    public function __construct(PDO$pdo){$this->service=new RegularEmploymentIncomeService($pdo);}
    public function documentType():string{return RegularEmploymentIncomeService::DOCUMENT_TYPE;}
    public function uiMetadata():array{return['document_type'=>$this->documentType(),'display_name'=>'상용근로소득','detail_section_title'=>'직원별 소득내역','item_columns'=>[['sort_no','순번'],['employee_name_snapshot','직원'],['department_name_snapshot','부서'],['base_salary_amount','기본급'],['allowance_amount','수당'],['bonus_amount','상여'],['gross_amount','지급총액'],['deduction_amount','공제합계'],['net_payment_amount','실지급액']],'total_fields'=>[['total_amount','지급총액','amount'],['deduction_amount','공제합계','amount'],['net_payment_amount','실지급액','amount']],'final_approval_message'=>'최종 승인하면 상용근로소득 내용을 기준으로 증빙원본과 거래가 생성됩니다. 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array$request):array{return$this->service->approvalDetail($request);}
    public function act(string$stepId,string$decision,?string$comment):array{return$this->service->act($stepId,$decision,$comment);}
}
