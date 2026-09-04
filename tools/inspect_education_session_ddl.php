<?php
declare(strict_types=1);
use Core\DbPdo;
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
$db=DbPdo::conn();$result=[];
foreach(['institution_educations_sessions','institution_educations_session_targets','institution_educations_employee_records'] as$table){$row=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC);$result[$table]=$row['Create Table']??null;}
$result['counts']=['courses'=>(int)$db->query('SELECT COUNT(*) FROM institution_educations_courses')->fetchColumn(),'employee_records'=>(int)$db->query('SELECT COUNT(*) FROM institution_educations_employee_records')->fetchColumn(),'fixture_sessions'=>(int)$db->query("SELECT COUNT(*) FROM institution_educations_sessions WHERE request_key LIKE 'FIXTURE-%'")->fetchColumn(),'fixture_targets'=>(int)$db->query("SELECT COUNT(*) FROM institution_educations_session_targets WHERE request_key LIKE 'FIXTURE-%'")->fetchColumn()];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
