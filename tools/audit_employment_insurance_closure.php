<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use Core\DbPdo;
$db = DbPdo::conn();
$rows = $db->query("SELECT s.*,COUNT(src.id) source_count FROM system_statutory_standards s LEFT JOIN system_statutory_standard_sources src ON src.standard_id=s.id WHERE s.standard_type_code='EMPLOYMENT_INSURANCE' GROUP BY s.id ORDER BY s.effective_from,s.id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$row) {
    $row['value_data'] = json_decode((string)$row['value_data'], true);
    $stmt = $db->prepare('SELECT id,sort_no,organization_name,source_name,law_name,notice_no,published_at,source_url,note,created_at,created_by,updated_at,updated_by FROM system_statutory_standard_sources WHERE standard_id=? ORDER BY sort_no,id');
    $stmt->execute([$row['id']]);
    $row['sources'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
unset($row);
$code = $db->query("SELECT code,code_name,extra_data,created_at,created_by,updated_at,updated_by FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='EMPLOYMENT_INSURANCE'")->fetch(PDO::FETCH_ASSOC) ?: [];
if ($code) $code['extra_data'] = json_decode((string)$code['extra_data'], true);
$employeeIds = ['ce50c61c-8b08-4f58-b8bc-e11f1dbafb84','6e8fb7ef-ea70-4d37-9aed-74f33b355127'];
$employeeStmt = $db->prepare('SELECT e.*,u.username,u.role_id FROM user_employees e LEFT JOIN auth_users u ON u.id=e.user_id WHERE e.id IN (?,?) ORDER BY e.employee_name');
$employeeStmt->execute($employeeIds);
$qualification = $db->query("SELECT r.*,t.qualification_code,t.qualification_name type_name FROM institution_qualifications_employee_records r LEFT JOIN institution_qualifications_types t ON t.id=r.qualification_type_id WHERE r.id='5e930c77-912f-11f1-a4cd-001132f96337'")->fetch(PDO::FETCH_ASSOC) ?: [];
$companyColumns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_company' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$company = $db->query('SELECT * FROM system_company ORDER BY created_at,id LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
echo json_encode(['type'=>$code,'revisions'=>$rows,'employees'=>$employeeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],'representative_qualification'=>$qualification,'company_columns'=>$companyColumns,'company'=>$company], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
