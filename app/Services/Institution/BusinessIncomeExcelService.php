<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\BusinessIncomeModel;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;
use Psr\Log\LoggerInterface;

final class BusinessIncomeExcelService
{
    private LoggerInterface $logger;
    private const COLUMNS = [
        'income_year_month' => '귀속연월',
        'group_no' => '지급그룹번호',
        'recipient_no' => '소득자번호',
        'transaction_date' => '거래일',
        'business_unit' => '사업구분코드',
        'project_id' => '프로젝트ID',
        'work_team_id' => '작업팀ID',
        'group_description' => '그룹 작업내용',
        'recipient_id' => '사업소득자ID',
        'recipient_name' => '사업소득자명',
        'service_type_code' => '용역구분코드',
        'service_description' => '용역내용',
        'item_name' => '품명',
        'item_specification' => '규격',
        'item_unit_name' => '단위',
        'item_quantity' => '수량',
        'item_unit_price' => '단가',
        'adjustment_amount' => '증감액',
        'adjustment_reason' => '증감사유',
    ];

    public function __construct(private readonly PDO $db)
    {
        $this->logger = LoggerFactory::getLogger('service-institution-business-income-excel');
    }

    public function createTemplate(): Spreadsheet
    {
        return $this->logged('BUSINESS_INCOME_EXCEL_TEMPLATE', 'template', [], fn(): Spreadsheet => $this->createTemplateInternal());
    }

    private function createTemplateInternal(): Spreadsheet
    {
        $spreadsheet = $this->spreadsheet('사업소득 입력양식', [[
            'income_year_month' => date('Y-m'),
            'group_no' => 1,
            'recipient_no' => 1,
            'transaction_date' => date('Y-m-d'),
            'business_unit' => '',
            'project_id' => '',
            'work_team_id' => '',
            'group_description' => '디자인 용역',
            'recipient_id' => '',
            'recipient_name' => '',
            'service_type_code' => '',
            'service_description' => '디자인 결과물 제작',
            'item_name' => '디자인 결과물',
            'item_specification' => '메인 시안 2종',
            'item_unit_name' => '식',
            'item_quantity' => 1,
            'item_unit_price' => 1000000,
            'adjustment_amount' => 0,
            'adjustment_reason' => '',
        ]]);
        $this->appendReferences($spreadsheet);
        return $spreadsheet;
    }

    public function createDownload(array $groups, array $header): Spreadsheet
    {
        return $this->logged('BUSINESS_INCOME_EXCEL_DOWNLOAD', 'download', ['group_count' => count($groups)], fn(): Spreadsheet => $this->createDownloadInternal($groups, $header));
    }

    private function createDownloadInternal(array $groups, array $header): Spreadsheet
    {
        $rows = [];
        foreach (array_values($groups) as $groupIndex => $group) {
            foreach (array_values(is_array($group['items'] ?? null) ? $group['items'] : []) as $itemIndex=>$item) {
                foreach(array_values($item['work_lines']??[]) as $workLine)$rows[] = [
                    'income_year_month' => $header['income_year_month'] ?? '',
                    'group_no' => $groupIndex + 1,
                    'recipient_no'=>$itemIndex+1,
                    'business_unit' => $group['business_unit'] ?? '',
                    'project_id' => $group['project_id'] ?? '',
                    'work_team_id' => $group['work_team_id'] ?? '',
                    'group_description' => $group['group_description'] ?? '',
                    'recipient_id' => $item['client_id'] ?? '',
                    'recipient_name' => $item['client_name'] ?? '',
                    'transaction_date' => $item['transaction_date'] ?? '',
                    'service_type_code' => $item['service_type_code'] ?? '',
                    'service_description' => $item['service_description'] ?? '',
                    'item_name'=>$workLine['item_name']??'','item_specification'=>$workLine['item_specification']??'','item_unit_name'=>$workLine['item_unit_name']??'',
                    'item_quantity'=>$workLine['item_quantity']??0,'item_unit_price'=>$workLine['item_unit_price']??0,'adjustment_amount'=>$workLine['adjustment_amount']??0,
                    'adjustment_reason'=>$workLine['adjustment_reason']??'',
                ];
            }
        }
        return $this->spreadsheet('사업소득 문서', $rows);
    }

    public function preview(string $filePath, string $incomeYearMonth): array
    {
        return $this->logged('BUSINESS_INCOME_EXCEL_PREVIEW', 'preview', ['income_year_month' => $incomeYearMonth], fn(): array => $this->previewInternal($filePath, $incomeYearMonth));
    }

    private function previewInternal(string $filePath, string $incomeYearMonth): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $incomeYearMonth)) {
            throw new \InvalidArgumentException('귀속연월을 먼저 선택해 주세요.');
        }
        $rows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(static fn($value): string => trim((string) $value), array_shift($rows) ?: []);
        $keyByHeader = array_flip(self::COLUMNS);
        $groups = [];
        $errors = [];
        $rowCount = 0;
        foreach ($rows as $offset => $cells) {
            if (count(array_filter($cells, static fn($value): bool => trim((string) $value) !== '')) === 0) continue;
            $rowCount++;
            $row = [];
            foreach ($headers as $columnIndex => $header) {
                if (isset($keyByHeader[$header])) $row[$keyByHeader[$header]] = $cells[$columnIndex] ?? null;
            }
            $rowNumber = $offset + 2;
            $groupNo = trim((string) ($row['group_no'] ?? ''));
            foreach (['group_no','recipient_no','transaction_date','business_unit','recipient_id','item_name','item_unit_name','item_quantity','item_unit_price'] as $required) {
                if (trim((string) ($row[$required] ?? '')) === '') $errors[] = ['row' => $rowNumber, 'field' => $required, 'message' => '필수값이 없습니다.'];
            }
            if ($groupNo === '') continue;
            $signature = implode('|', [
                strtoupper(trim((string) ($row['business_unit'] ?? ''))),
                trim((string) ($row['project_id'] ?? '')),
                trim((string) ($row['work_team_id'] ?? '')),
                trim((string) ($row['group_description'] ?? '')),
            ]);
            if (isset($groups[$groupNo]) && $groups[$groupNo]['signature'] !== $signature) {
                $errors[] = ['row' => $rowNumber, 'field' => 'group_no', 'message' => '같은 지급그룹번호의 지급조건이 서로 다릅니다.'];
                continue;
            }
            $groups[$groupNo] ??= [
                'signature' => $signature,
                'business_unit' => strtoupper(trim((string) ($row['business_unit'] ?? ''))),
                'project_id' => trim((string) ($row['project_id'] ?? '')) ?: null,
                'work_team_id' => trim((string) ($row['work_team_id'] ?? '')) ?: null,
                'group_description' => trim((string) ($row['group_description'] ?? '')) ?: null,
                'items' => [],
            ];
            $recipientNo=trim((string)($row['recipient_no']??''));$recipientKey=$groupNo.'|'.$recipientNo;
            $recipientSignature=implode('|',[trim((string)($row['recipient_id']??'')),trim((string)($row['transaction_date']??'')),trim((string)($row['service_type_code']??'')),trim((string)($row['service_description']??''))]);
            $groups[$groupNo]['recipient_signatures']??=[];$groups[$groupNo]['recipient_indexes']??=[];
            if(isset($groups[$groupNo]['recipient_signatures'][$recipientKey])&&$groups[$groupNo]['recipient_signatures'][$recipientKey]!==$recipientSignature){$errors[]=['row'=>$rowNumber,'field'=>'recipient_no','message'=>'같은 소득자번호의 거래일·소득자·공종·작업내용이 서로 다릅니다.'];continue;}
            $groups[$groupNo]['recipient_signatures'][$recipientKey]=$recipientSignature;
            if(!isset($groups[$groupNo]['recipient_indexes'][$recipientKey])){
                $groups[$groupNo]['recipient_indexes'][$recipientKey]=count($groups[$groupNo]['items']);
                $groups[$groupNo]['items'][] = [
                'client_id' => trim((string) ($row['recipient_id'] ?? '')),
                'client_name' => trim((string) ($row['recipient_name'] ?? '')),
                'transaction_date' => trim((string) ($row['transaction_date'] ?? '')),
                'service_type_code' => trim((string) ($row['service_type_code'] ?? '')),
                'service_description' => trim((string) ($row['service_description'] ?? '')),
                'work_lines'=>[],
                ];
            }
            $recipientIndex=$groups[$groupNo]['recipient_indexes'][$recipientKey];
            $groups[$groupNo]['items'][$recipientIndex]['work_lines'][]=['item_name'=>trim((string)($row['item_name']??'')),'item_specification'=>trim((string)($row['item_specification']??'')),'item_unit_name'=>trim((string)($row['item_unit_name']??'')),'item_quantity'=>(float)($row['item_quantity']??0),'item_unit_price'=>(float)($row['item_unit_price']??0),'adjustment_amount'=>(float)($row['adjustment_amount']??0),'adjustment_reason'=>trim((string)($row['adjustment_reason']??''))];
        }
        if ($rowCount === 0) throw new \InvalidArgumentException('업로드할 사업소득 행이 없습니다.');
        if ($errors !== []) return ['success' => true, 'data' => ['valid' => false, 'errors' => $errors, 'groups' => [], 'summary' => ['group_count' => 0, 'row_count' => $rowCount]]];
        $normalized = array_values(array_map(static function (array $group): array {
            unset($group['signature'],$group['recipient_signatures'],$group['recipient_indexes']);
            return $group;
        }, $groups));
        try {
            $calculated = (new BusinessIncomeService($this->db))->calculate(['income_year_month' => $incomeYearMonth, 'groups' => $normalized]);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return ['success' => true, 'data' => ['valid' => false, 'errors' => [['row' => null, 'field' => 'calculation', 'message' => $exception->getMessage()]], 'groups' => [], 'summary' => ['group_count' => count($normalized), 'row_count' => $rowCount]]];
        }
        return ['success' => true, 'data' => ['valid' => true, 'errors' => [], 'groups' => $calculated['data']['groups'], 'totals' => $calculated['data']['totals'], 'summary' => ['group_count' => count($normalized), 'row_count' => $rowCount]]];
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try {
            $result = $operation();
            $this->logger->info('사업소득 엑셀 처리를 완료했습니다.', ['event_code' => $eventCode, 'result' => 'SUCCESS', 'action' => $action] + $context);
            return $result;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->logger->warning('사업소득 엑셀 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('사업소득 엑셀 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        }
    }

    private function spreadsheet(string $title, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $headers = array_values(self::COLUMNS);
        $sheet->fromArray($headers, null, 'A1');
        foreach (array_values($rows) as $index => $row) {
            $sheet->fromArray(array_map(static fn(string $key) => $row[$key] ?? '', array_keys(self::COLUMNS)), null, 'A' . ($index + 2));
        }
        $sheet->freezePane('A2');
        $lastColumn=$sheet->getHighestColumn();$sheet->setAutoFilter('A1:'.$lastColumn.'1');
        foreach (range('A', $lastColumn) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        return $spreadsheet;
    }

    private function appendReferences(Spreadsheet $spreadsheet): void
    {
        $options = (new BusinessIncomeModel($this->db))->options();
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('참조정보');
        $sheet->fromArray(['구분', 'ID 또는 코드', '표시명', '사업구분'], null, 'A1');
        $row = 2;
        foreach (['business_units' => '사업구분', 'projects' => '프로젝트', 'work_teams' => '작업팀', 'recipients' => '사업소득자'] as $key => $label) {
            foreach ($options[$key] ?? [] as $option) {
                $sheet->fromArray([$label, $option['id'] ?? '', $option['name'] ?? '', $option['business_unit'] ?? ''], null, 'A' . $row++);
            }
        }
        foreach (range('A', 'D') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $spreadsheet->setActiveSheetIndex(0);
    }

}
