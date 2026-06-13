<?php

namespace App\Services\Ledger;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EvidenceTemplateService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function downloadTemplate(string $filename, string $title, array $headers, array $samples, array $required = [], array $fields = [], string $dataType = ''): void
    {
        $spreadsheet = new Spreadsheet();
        $tempFile = tempnam(sys_get_temp_dir(), 'ledger_template_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary XLSX file.');
        }

        try {
            if ($this->isWorkbookTemplateSpec($headers)) {
                foreach ($headers as $index => $spec) {
                    $sheet = $index === 0
                        ? $spreadsheet->getActiveSheet()
                        : $spreadsheet->createSheet($index);
                    $this->fillTemplateSheet(
                        $sheet,
                        (string) ($spec['title'] ?? 'sheet' . ($index + 1)),
                        is_array($spec['headers'] ?? null) ? $spec['headers'] : [],
                        is_array($spec['samples'] ?? null) ? $spec['samples'] : [],
                        is_array($spec['required'] ?? null) ? $spec['required'] : [],
                        is_array($spec['fields'] ?? null) ? $spec['fields'] : [],
                        $dataType
                    );
                }
                $this->call('applyBankTemplateDropdowns', $spreadsheet, $headers);
                $spreadsheet->setActiveSheetIndex(0);
            } else {
                $sheet = $spreadsheet->getActiveSheet();
                $this->fillTemplateSheet($sheet, $title, $headers, $samples, $required, $fields, $dataType);
                $this->call('applyTemplateDropdowns', $spreadsheet, $sheet, $fields, $headers, $dataType);
            }

            (new Xlsx($spreadsheet))->save($tempFile);
            $spreadsheet->disconnectWorksheets();

            $filename = $this->call('safeFilename', $filename);
            if (!str_ends_with(strtolower($filename), '.xlsx')) {
                $filename .= '.xlsx';
            }
            $asciiFallback = preg_replace('#[^A-Za-z0-9_.-]+#', '_', $filename) ?: 'upload_template.xlsx';
            $encodedFilename = rawurlencode($filename);

            if (headers_sent($sentFile, $sentLine)) {
                throw new \RuntimeException("Headers already sent at {$sentFile}:{$sentLine}");
            }

            $this->call('clearOutputBuffers');
            header_remove('Content-Type');
            header_remove('Content-Disposition');
            header_remove('Cache-Control');
            header_remove('Pragma');
            header_remove('Expires');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$asciiFallback}\"; filename*=UTF-8''{$encodedFilename}");
            header('Content-Transfer-Encoding: binary');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: max-age=0');
            header('Content-Length: ' . filesize($tempFile));

            if (readfile($tempFile) === false) {
                error_log('[EvidenceTemplateService] Template download failed while streaming XLSX.');
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
        exit;
    }

    public function fillTemplateSheet(Worksheet $sheet, string $title, array $headers, array $samples, array $required = [], array $fields = [], string $dataType = ''): void
    {
        $sheet->setTitle($this->call('safeSheetTitle', $title));
        foreach (array_values($headers) as $index => $header) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $header = (string) $header;
            $requirementMode = $this->call('normalizeRequirementMode', $required[$index] ?? 0);
            if ($requirementMode !== 0 && $header !== '') {
                $richText = new RichText();
                $richText->createText($header . ' ');
                $asterisk = $richText->createTextRun('*');
                $asterisk->getFont()->setBold(true)->getColor()->setARGB($requirementMode === 1 ? 'FFDC2626' : 'FF2563EB');
                $sheet->setCellValue($cell, $richText);
            } else {
                $sheet->setCellValue($cell, $header);
            }
        }
        if ($samples !== []) {
            $sheet->fromArray($samples, null, 'A2');
        }

        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF6FF');

        foreach (array_values($headers) as $index => $header) {
            $field = (string) ($fields[$index] ?? '');
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            if ($this->call('isStandardInfoTemplateColumn', $field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF3E8FF');
                continue;
            }
            if ($this->call('isBasicInfoTemplateColumn', $field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE2F0D9');
                continue;
            }
            if ($this->call('isVoucherTemplateColumn', $field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFB91C1C');
            }
        }
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    public function templateSpec(string $type): array
    {
        $label = $this->call('dataTypeLabel', $type);
        if ($type === 'BANK_TRANSACTION') {
            return [
                'bank_upload_template.xlsx',
                'BANK template',
                ['Transaction Date', 'Bank Direction', 'Business Unit', 'Transaction Type', 'Client Name', 'Project Name', 'Employee Name', 'Bank Account Name', 'Card Name', 'Amount', 'Balance Amount', 'Description', 'Counterparty Name', 'Memo', 'Note'],
                [
                    ['2026-05-04', 'Deposit', 'HQ', 'GENERAL', 'Sample Client A', 'Sample Project A', 'Sample Employee A', 'Sample Account A', '', '55000', '1055000', 'Sample Description A', 'Sample Counterparty A', 'Sample Memo A', ''],
                    ['2026-05-04', 'Withdraw', 'HQ', 'GENERAL', 'Sample Client B', 'Sample Project B', 'Sample Employee B', 'Sample Account B', 'Sample Card B', '120000', '935000', 'Sample Description B', 'Sample Counterparty B', 'Sample Memo B', ''],
                ],
            ];
        }

        return [
            strtolower($type) . '_upload_template.xlsx',
            $label . ' template',
            ['Evidence Date', 'Supplier Business No', 'Supplier Name', 'Customer Business No', 'Customer Name', 'Business Unit', 'Summary', 'Amount', 'Tax Amount', 'Total Amount', 'Note', 'Memo', 'Line Note'],
            [
                ['2026-05-04', '123-45-67890', 'Sample Supplier A', '000-00-00000', 'Sample Customer A', 'Sample Business Unit', 'Sample Summary A', '50000', '5000', '55000', 'Sample Note A', 'Sample Memo A', ''],
                ['2026-05-04', '000-00-00000', 'Sample Supplier B', '987-65-43210', 'Sample Customer B', 'Sample Business Unit', 'Sample Summary B', '120000', '0', '120000', 'Sample Note B', 'Sample Memo B', ''],
            ],
        ];
    }

    public function sampleRowForColumns(array $columns, string $dataType): array
    {
        $samples = [
            'transaction_date' => '2026-05-04',
            'supplier_business_number' => '123-45-67890',
            'supplier_branch_number' => '0000',
            'supplier_company_name' => 'Sample Supplier Co',
            'supplier_ceo_name' => 'Sample Supplier CEO',
            'supplier_address' => 'Sample Supplier Address',
            'supplier_email' => 'supplier@example.com',
            'customer_business_number' => '000-00-00000',
            'customer_branch_number' => '0000',
            'customer_company_name' => 'Sample Customer Co',
            'customer_ceo_name' => 'Sample Customer CEO',
            'customer_address' => 'Sample Customer Address',
            'customer_email_1' => 'tax@example.com',
            'customer_email_2' => '',
            'broker_business_number' => '',
            'broker_company_name' => '',
            'approval_number' => '20260504-0001',
            'issue_date' => '2026-05-04',
            'transmit_date' => '2026-05-04',
            'tax_invoice_category' => 'General',
            'tax_invoice_type' => 'Normal',
            'issue_type' => 'Issued',
            'receipt_claim_type' => 'Receipt',
            'project_name' => 'Sample Project',
            'description' => $this->call('dataTypeLabel', $dataType) . ' Sample',
            'supply_amount' => 50000,
            'vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'total_amount' => 55000,
            'transaction_datetime' => '2026-05-04 09:30:00',
            'bank_direction' => 'Deposit',
            'business_unit' => 'GENERAL',
            'transaction_type' => 'GENERAL',
            'deposit_amount' => 55000,
            'withdraw_amount' => '',
            'balance_amount' => 1055000,
            'check_bill_amount' => 0,
            'bank_account_name' => '',
            'currency_code' => 'KRW',
            'counterparty_account_number' => '123-456-789012',
            'counterparty_bank_name' => 'Sample Bank',
            'counterparty_name' => 'Sample Counterparty',
            'bank_reference_no' => 'CMS-20260504-0001',
            'voucher_date' => '2026-05-04',
            'voucher_no' => '',
            'summary_text' => 'Sample Transaction Summary',
            'voucher_memo' => '',
            'debit_account_id' => '1000',
            'debit_amount' => 55000,
            'debit_line_summary' => 'Sample Debit Summary',
            'credit_account_id' => '4000',
            'credit_amount' => 55000,
            'credit_line_summary' => 'Sample Credit Summary',
            'item_date' => '2026-05-04',
            'item_name' => 'Sample Item',
            'item_spec' => 'EA',
            'item_qty' => 1,
            'item_price' => 50000,
            'item_supply_amount' => 50000,
            'item_vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'item_note' => '',
            'note' => 'Sample Note',
            'memo' => '',
        ];

        $row = [];
        foreach ($columns as $column) {
            $field = (string) ($column['system_field_name'] ?? '');
            $row[] = $samples[$field] ?? '';
        }

        return $row;
    }

    public function templateColumnsInFormatOrder(array $columns, string $dataType = ''): array
    {
        $columns = $this->call('formatColumnsInOrder', $columns);

        return array_values(array_filter(
            $columns,
            fn(array $column): bool => trim((string) ($column['excel_column_name'] ?? '')) !== ''
                && !$this->callOptional('isHiddenFormatColumn', false, $column, $dataType)
        ));
    }

    public function excelHeadersForColumns(array $columns): array
    {
        return array_map(
            static fn(array $column): string => trim((string) ($column['excel_column_name'] ?? '')),
            $columns
        );
    }

    public function isWorkbookTemplateSpec(array $headers): bool
    {
        return isset($headers[0]) && is_array($headers[0]) && array_key_exists('headers', $headers[0]);
    }

    public function looksLikeBankTemplateHeaders(array $headers): bool
    {
        $names = array_map(
            static fn(mixed $header): string => preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header),
            $headers
        );
        $nameSet = array_flip($names);

        foreach (['?????????????????????嚥싲갭큔?댁쉩??????', '???????????', '?????????????????????嚥싲갭큔?댁쉩?????', '??????????', '???????????????????????????????ш끽維뽳쭩?뱀땡???얩맪???????????????????轅붽틓??섑떊???⑤챷????', '??????????????', '????????????????????????????????????袁⑸즴筌?씛彛???돗?????????????????癲?????????', '???????????????????????????????????????????嫄?????????', '?????????????'] as $header) {
            if (isset($nameSet[$header])) {
                return true;
            }
        }

        return isset($nameSet['???????????????????????????????????⑤슦????????????????????癰귙룗???癲ル슢??????']) && (isset($nameSet['??????????????????????????????????????????']) || isset($nameSet['????????????거?????????????????']));
    }

    public function sampleBankVoucherLineRows(array $columns): array
    {
        $samples = [
            [
                'header_row_no' => 2,
                'line_no' => 1,
                'line_row_type' => '??????????????',
                'account_id' => '1000',
                'debit' => 55000,
                'credit' => '',
                'line_summary' => '?????????????????????????濚밸Ŧ援욃퐲????',
            ],
            [
                'header_row_no' => 2,
                'line_no' => 2,
                'line_row_type' => '??????????????',
                'account_id' => '4000',
                'debit' => '',
                'credit' => 55000,
                'line_summary' => '???????????????????',
            ],
        ];

        return array_map(function (array $sample) use ($columns): array {
            $row = [];
            foreach ($columns as $column) {
                $field = (string) ($column['system_field_name'] ?? '');
                $row[] = $sample[$field] ?? '';
            }
            return $row;
        }, $samples);
    }

    public function uniqueSheetTitle(Spreadsheet $spreadsheet, string $baseTitle): string
    {
        $baseTitle = $this->call('safeSheetTitle', $baseTitle);
        $title = $baseTitle;
        $index = 1;
        while ($spreadsheet->sheetNameExists($title)) {
            $suffix = '_' . $index;
            $title = $this->call('safeSheetTitle', substr($baseTitle, 0, 31 - strlen($suffix)) . $suffix);
            $index++;
        }

        return $title;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }

    private function callOptional(string $name, mixed $default, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            return $default;
        }

        return ($this->callbacks[$name])(...$args);
    }
}
