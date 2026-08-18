<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardService;
use Core\Helpers\ActorHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$apply = in_array('--apply', $argv, true);
$db = Core\DbPdo::conn();
$service = new StatutoryStandardService($db, ActorHelper::system('STATUTORY_OFFICIAL_DATA_AUDIT'));

$targets = [
    '65f41a97-85d4-4ea5-8bbc-d8e2fe058d45' => static function (array &$row): void {
        $row['effective_from'] = '2012-07-01';
        $row['note'] = '국민연금 보험료율 및 기준소득월액 상·하한(2012.7.~2013.6.)';
        foreach ($row['sources'] as &$source) {
            $source['source_name'] = '기준소득월액 상한액과 하한액 적용기간 연혁';
            $source['note'] = '국민연금공단 공식 연혁에서 2012.7.~2013.6. 하한 24만원, 상한 389만원 확인';
        }
        unset($source);
    },
    '98a8912d-4f9e-4899-a881-26fd6d1b81b6' => static function (array &$row): void {
        $row['effective_from'] = '1977-07-01';
        $row['note'] = '부가가치세법 시행일부터 적용된 일반과세 세율 10%';
        foreach ($row['sources'] as &$source) {
            $source['source_name'] = '부가가치세법 제정 및 일반세율 시행 근거';
            $source['organization_name'] = '대한민국 정부';
            $source['law_name'] = '부가가치세법';
            $source['notice_no'] = '법률 제2934호';
            $source['published_at'] = '1976-12-22';
            $source['source_url'] = 'https://www.law.go.kr/LSW/lsRvsDocListP.do?chrClsCd=010202&lsId=001571&lsRvsGubun=all';
            $source['note'] = '1977-07-01 시행된 제정 부가가치세법의 일반세율 10% 연혁';
        }
        unset($source);
    },
    '25e161ec-56a2-41bf-987d-df5acb82f9b8' => static function (array &$row): void {
        foreach ($row['sources'] as &$source) {
            $source['source_url'] = 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=1356915637062';
            $source['note'] = '2013년도 사업종류별 산재보험료율 고시 원문';
        }
        unset($source);
    },
];

$changes = [];
foreach ($targets as $id => $transform) {
    $detail = $service->detail($id)['data'];
    $before = ['effective_from' => $detail['effective_from'], 'effective_to' => $detail['effective_to'], 'sources' => $detail['sources']];
    $transform($detail);
    $input = [
        'id' => $id,
        'standard_type_code' => $detail['standard_type_code'],
        'effective_from' => $detail['effective_from'],
        'effective_to' => $detail['effective_to'] ?? '',
        'value_data' => $detail['value_data'],
        'note' => $detail['note'] ?? '',
        'sources' => $detail['sources'],
    ];
    if ($apply) {
        $service->save($input);
    }
    $changes[] = [
        'id' => $id,
        'type' => $detail['standard_type_code'],
        'before' => $before,
        'after' => ['effective_from' => $detail['effective_from'], 'effective_to' => $detail['effective_to'], 'sources' => $detail['sources']],
    ];
}

echo json_encode([
    'mode' => $apply ? 'apply' : 'dry-run',
    'actor' => ActorHelper::system('STATUTORY_OFFICIAL_DATA_AUDIT'),
    'changes' => $changes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
