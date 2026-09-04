<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Services\Institution\BusinessIncomeService;
use PDO;

final class BusinessIncomeApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private BusinessIncomeService $service;
    public function __construct(PDO $db){$this->service=new BusinessIncomeService($db);}
    public function documentType():string{return BusinessIncomeService::DOCUMENT_TYPE;}
    public function uiMetadata():array{return ['document_type'=>$this->documentType(),'display_name'=>'사업소득','detail_section_title'=>'소득자별 외주 정산 내역','item_columns'=>[['sort_no','순번'],['client_name','소득자'],['service_description','작업내용'],['work_line_count','작업 건수'],['gross_payment_amount','총지급액'],['income_tax_amount','소득세'],['local_income_tax_amount','개인지방소득세'],['net_payment_amount','실지급액']],'total_fields'=>[['gross_payment_amount','총지급액','amount'],['total_deduction_amount','총 공제액','amount'],['net_payment_amount','실지급액','amount']],'final_approval_message'=>'최종 승인하면 소득자별 Evidence와 작업내역별 지급 Transaction 품목이 생성됩니다. 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array $request):array{return $this->service->approvalDetail($request);}
    public function act(string $stepId,string $decision,?string $comment):array{return $this->service->act($stepId,$decision,$comment);}
}
