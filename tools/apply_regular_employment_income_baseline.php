<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;

$db = Core\DbPdo::conn();
$actor = ActorHelper::system('REGULAR_EMPLOYMENT_INCOME_BASELINE');
$now = date('Y-m-d H:i:s');

$db->beginTransaction();
try {
    $metadataStatement = $db->prepare(
        'SELECT id FROM ledger_evidence_metadata WHERE import_type=:import_type LIMIT 1 FOR UPDATE'
    );
    $metadataStatement->execute([':import_type' => 'PAYROLL_REPORT']);
    $metadataId = (string) ($metadataStatement->fetchColumn() ?: '');
    if ($metadataId === '') {
        $metadataId = UuidHelper::generate();
        $sortNo = (int) $db->query(
            'SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_evidence_metadata FOR UPDATE'
        )->fetchColumn();
        $statement = $db->prepare(
            'INSERT INTO ledger_evidence_metadata '
            . '(id,sort_no,import_type,source_table,evidence_type,process_role,created_at,created_by,updated_at,updated_by) '
            . 'VALUES (:id,:sort_no,:import_type,:source_table,:evidence_type,:process_role,:created_at,:created_by,:updated_at,:updated_by)'
        );
        $statement->execute([
            ':id' => $metadataId,
            ':sort_no' => $sortNo,
            ':import_type' => 'PAYROLL_REPORT',
            ':source_table' => 'ledger_evidence_salary_report',
            ':evidence_type' => 'DATA',
            ':process_role' => 'TRANSACTION_REPORT_SSOT',
            ':created_at' => $now,
            ':created_by' => $actor,
            ':updated_at' => $now,
            ':updated_by' => $actor,
        ]);
    }

    $mappings = [
        ['BASE_DATE', 'raw_payment_date', null, 'Y', '급여 지급일'],
        ['DESCRIPTION', 'raw_description', null, 'N', '승인 문서 비고'],
        ['PRE_TAX_AMOUNT', 'raw_gross_amount', null, 'Y', '지급총액'],
        ['POST_TAX_AMOUNT', 'raw_gross_amount', null, 'Y', '거래 기준 지급총액'],
    ];
    foreach ($mappings as $index => [$semanticKey, $column, $direction, $required, $remark]) {
        $statement = $db->prepare(
            'SELECT id FROM ledger_evidence_metadata_columns '
            . 'WHERE metadata_id=:metadata_id AND semantic_key=:semantic_key AND physical_column=:physical_column LIMIT 1'
        );
        $statement->execute([
            ':metadata_id' => $metadataId,
            ':semantic_key' => $semanticKey,
            ':physical_column' => $column,
        ]);
        if ($statement->fetchColumn()) {
            continue;
        }
        $statement = $db->prepare(
            'INSERT INTO ledger_evidence_metadata_columns '
            . '(id,sort_no,metadata_id,semantic_key,physical_column,adjustment_direction,is_required,remark,created_at,created_by,updated_at,updated_by) '
            . 'VALUES (:id,:sort_no,:metadata_id,:semantic_key,:physical_column,:adjustment_direction,:is_required,:remark,:created_at,:created_by,:updated_at,:updated_by)'
        );
        $statement->execute([
            ':id' => UuidHelper::generate(),
            ':sort_no' => $index + 1,
            ':metadata_id' => $metadataId,
            ':semantic_key' => $semanticKey,
            ':physical_column' => $column,
            ':adjustment_direction' => $direction,
            ':is_required' => $required,
            ':remark' => $remark,
            ':created_at' => $now,
            ':created_by' => $actor,
            ':updated_at' => $now,
            ':updated_by' => $actor,
        ]);
    }

    $templateStatement = $db->prepare(
        'SELECT id FROM user_approval_templates WHERE document_type=:document_type AND is_active=1 LIMIT 1 FOR UPDATE'
    );
    $templateStatement->execute([':document_type' => 'REGULAR_EMPLOYMENT_INCOME']);
    $templateId = (string) ($templateStatement->fetchColumn() ?: '');
    if ($templateId === '') {
        $templateId = UuidHelper::generate();
        $sortNo = (int) $db->query(
            'SELECT COALESCE(MAX(sort_no),0)+1 FROM user_approval_templates FOR UPDATE'
        )->fetchColumn();
        $statement = $db->prepare(
            'INSERT INTO user_approval_templates '
            . '(id,sort_no,template_key,template_name,document_type,description,is_active,created_at,created_by,updated_at,updated_by) '
            . 'VALUES (:id,:sort_no,:template_key,:template_name,:document_type,:description,1,:created_at,:created_by,:updated_at,:updated_by)'
        );
        $statement->execute([
            ':id' => $templateId,
            ':sort_no' => $sortNo,
            ':template_key' => 'regular_employment_income',
            ':template_name' => '상용근로소득 결재',
            ':document_type' => 'REGULAR_EMPLOYMENT_INCOME',
            ':description' => '귀속월별 상용근로소득 묶음 문서 결재',
            ':created_at' => $now,
            ':created_by' => $actor,
            ':updated_at' => $now,
            ':updated_by' => $actor,
        ]);

        $sourceStatement = $db->query(
            "SELECT s.* FROM user_approval_template_steps s "
            . "JOIN user_approval_templates t ON t.id=s.template_id "
            . "WHERE t.document_type='PERSONAL_EXPENSE' AND t.is_active=1 AND s.is_active=1 ORDER BY s.sort_no"
        );
        $sourceSteps = $sourceStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($sourceSteps === []) {
            throw new RuntimeException('복제할 공용 개인경비 결재단계를 찾을 수 없습니다.');
        }
        foreach ($sourceSteps as $step) {
            $statement = $db->prepare(
                'INSERT INTO user_approval_template_steps '
                . '(id,sort_no,template_id,step_name,step_type,role_id,approver_id,is_active,created_at,created_by,updated_at,updated_by) '
                . 'VALUES (:id,:sort_no,:template_id,:step_name,:step_type,:role_id,:approver_id,1,:created_at,:created_by,:updated_at,:updated_by)'
            );
            $statement->execute([
                ':id' => UuidHelper::generate(),
                ':sort_no' => $step['sort_no'],
                ':template_id' => $templateId,
                ':step_name' => $step['step_name'],
                ':step_type' => $step['step_type'],
                ':role_id' => $step['role_id'],
                ':approver_id' => $step['approver_id'],
                ':created_at' => $now,
                ':created_by' => $actor,
                ':updated_at' => $now,
                ':updated_by' => $actor,
            ]);
        }
    }

    $db->commit();
    echo json_encode([
        'success' => true,
        'metadata_id' => $metadataId,
        'template_id' => $templateId,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $throwable) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
