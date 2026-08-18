<?php

declare(strict_types=1);

use App\Services\System\CodeService;
use App\Services\System\StatutoryStandardService;
use Core\Helpers\ActorHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$apply = in_array('--apply', $argv, true);
$db = Core\DbPdo::conn();
$actorContext = 'STATUTORY_OFFICIAL_DATA_AUDIT';
$service = new StatutoryStandardService($db, ActorHelper::system($actorContext));
$codeService = new CodeService($db);
$matrixPayload = json_decode((string) file_get_contents(
    PROJECT_ROOT . '/tools/data/statutory-income-tax-tables.json'
), true, 512, JSON_THROW_ON_ERROR);

$additionalEmployerRates = [
    ['business_size_name' => '상시근로자 150명 미만', 'employer_rate' => 0.0025],
    ['business_size_name' => '상시근로자 150명 이상 우선지원대상기업', 'employer_rate' => 0.0045],
    ['business_size_name' => '상시근로자 150명 이상 1,000명 미만', 'employer_rate' => 0.0065],
    ['business_size_name' => '상시근로자 1,000명 이상 및 국가·지방자치단체 직접사업', 'employer_rate' => 0.0085],
];

$employmentTemplate = [
    'preserve_schema_in_value' => true,
    'fields' => [
        ['code' => 'employee_rate', 'name' => '실업급여 근로자 부담률', 'type' => 'rate', 'required' => true],
        ['code' => 'employer_rate', 'name' => '실업급여 사업주 부담률', 'type' => 'rate', 'required' => true],
        [
            'code' => 'additional_employer_rates',
            'name' => '고용안정·직업능력개발사업 사업주 부담률',
            'type' => 'matrix',
            'required' => true,
            'columns' => [
                ['code' => 'business_size_name', 'name' => '법정 사업규모 구분', 'type' => 'text',
                    'required' => true, 'key_part' => true, 'width' => 360],
                ['code' => 'employer_rate', 'name' => '사업주 추가 부담률', 'type' => 'rate',
                    'required' => true, 'min' => 0, 'max' => 1, 'width' => 180],
            ],
            'ui' => [
                'collapsible' => true,
                'default_expanded' => true,
                'allow_paste' => false,
                'title' => '사업규모별 사업주 추가 부담률',
                'description' => '회사 자체 규모코드로 추정하지 않고 법령상 사업규모 구분과 부담률을 함께 저장합니다.',
            ],
        ],
    ],
    'calculation_policy' => ['fields' => []],
];

$incomePolicies = [
    'stage' => 'AFTER_TABLE_OR_EXCESS_CALCULATION',
    'base_value_code' => 'TABLE_LOOKUP_OR_EXCESS_RULE_RESULT',
    'aggregation_unit' => 'WITHHOLDING_AGENT_RECIPIENT_PAYMENT_MONTH',
    'threshold' => 1000,
    'threshold_comparison' => 'LESS_THAN',
    'application_order' => 1,
];

$incomeExcessRules = [
    '2014' => [
        [10000000, 14000000, 10000000, 0.98, 0.35, 0],
        [14000000, null, 10000000, 0.98, 0.38, 1372000],
    ],
    '2017' => [
        [10000000, 14000000, 10000000, 0.98, 0.35, 0],
        [14000000, 45000000, 10000000, 0.98, 0.38, 1372000],
        [45000000, null, 10000000, 0.98, 0.40, 12916400],
    ],
    '2020' => [
        [10000000, 14000000, 10000000, 0.98, 0.35, 0],
        [14000000, 28000000, 10000000, 0.98, 0.38, 1372000],
        [28000000, 30000000, 10000000, 0.98, 0.40, 6585600],
        [30000000, 45000000, 10000000, 1.00, 0.40, 7385600],
        [45000000, null, 10000000, 1.00, 0.42, 13385600],
    ],
    '2023' => [
        [10000000, 14000000, 10000000, 0.98, 0.35, 25000],
        [14000000, 28000000, 10000000, 0.98, 0.38, 1397000],
        [28000000, 30000000, 10000000, 0.98, 0.40, 6610600],
        [30000000, 45000000, 10000000, 1.00, 0.40, 7394600],
        [45000000, 87000000, 10000000, 1.00, 0.42, 13394600],
        [87000000, null, 10000000, 1.00, 0.45, 31034600],
    ],
];

$toExcessRules = static fn(array $rules): array => array_map(
    static fn(array $rule): array => [
        'salary_from' => $rule[0],
        'salary_to' => $rule[1],
        'base_salary' => $rule[2],
        'base_tax_reference' => 'TABLE',
        'excess_base_rate' => $rule[3],
        'tax_rate' => $rule[4],
        'fixed_addition' => $rule[5],
    ],
    $rules
);

$lawSource = static fn(string $noticeNo, string $publishedAt, string $url, string $note): array => [
    'source_name' => '소득세법 시행령 별표 2 근로소득 간이세액표',
    'organization_name' => '대한민국 정부',
    'law_name' => '소득세법 시행령',
    'notice_no' => $noticeNo,
    'published_at' => $publishedAt,
    'source_url' => $url,
    'note' => $note,
];

$incomeRows = [
    [
        'year' => '2014', 'from' => '2014-02-21', 'to' => '2017-02-02',
        'note' => '2014.2.21 개정 근로소득 간이세액표',
        'sources' => [$lawSource('대통령령 제25193호', '2014-02-21',
            'https://www.law.go.kr/LSW/lsInfoP.do?ancNo=25193&ancYd=20140221&chrClsCd=010202&lsiSeq=151424',
            '2014-02-21 시행 별표 2 원표와 표 상한 초과 계산식')],
    ],
    [
        'year' => '2017', 'from' => '2017-02-03', 'to' => '2020-02-10',
        'note' => '2017.2.3 개정 근로소득 간이세액표',
        'sources' => [$lawSource('대통령령 제27829호', '2017-02-03',
            'https://www.law.go.kr/LSW/lsInfoP.do?efYd=20170203&lsiSeq=191522',
            '2017-02-03 시행 별표 2 원표와 표 상한 초과 계산식')],
    ],
    [
        'year' => '2020', 'from' => '2020-02-11', 'to' => '2023-02-27',
        'note' => '2020.2.11 개정 근로소득 간이세액표',
        'sources' => [$lawSource('대통령령 제30395호', '2020-02-11',
            'https://law.go.kr/lsInfoP.do?ancNo=30395&ancYd=20200211&lsiSeq=214261',
            '2020-02-11 시행 별표 2 원표와 표 상한 초과 계산식')],
    ],
    [
        'year' => '2023', 'from' => '2023-02-28', 'to' => null,
        'note' => '2023.2.28 개정 근로소득 간이세액표',
        'sources' => [
            $lawSource('대통령령 제33267호', '2023-02-28',
                'https://www.law.go.kr/LSW/lsInfoP.do?efYd=20230228&lsiSeq=248191',
                '2023-02-28 시행 별표 2와 표 상한 초과 계산식'),
            [
                'source_name' => '2023년 근로소득 간이세액표 원본 Excel',
                'organization_name' => '국세청',
                'law_name' => '소득세법 시행령 별표 2',
                'notice_no' => null,
                'published_at' => '2023-03-09',
                'source_url' => 'https://www.nts.go.kr/nts/na/ntt/selectNttInfo.do?mi=6647&nttSn=1322151',
                'note' => '국세청이 게시한 공식 Excel 원표 및 자녀수별 세액 조정 안내',
            ],
        ],
    ],
];

$employmentRows = [
    [
        'from' => '2013-01-01', 'to' => '2013-06-30', 'employee_rate' => 0.0055, 'employer_rate' => 0.0055,
        'notice_no' => '대통령령 제22807호', 'published_at' => '2011-03-30',
        'url' => 'https://www.law.go.kr/LSW/lsInfoP.do?lsiSeq=111614',
        'note' => '2013년 1~6월 고용보험료율',
    ],
    [
        'from' => '2013-07-01', 'to' => '2019-09-30', 'employee_rate' => 0.0065, 'employer_rate' => 0.0065,
        'notice_no' => '대통령령 제24650호', 'published_at' => '2013-06-28',
        'url' => 'https://www.law.go.kr/LSW/lsInfoP.do?lsiSeq=141599',
        'note' => '2013.7.1 인상 고용보험료율',
    ],
    [
        'from' => '2019-10-01', 'to' => '2022-06-30', 'employee_rate' => 0.008, 'employer_rate' => 0.008,
        'notice_no' => '대통령령 제30084호', 'published_at' => '2019-09-17',
        'url' => 'https://www.law.go.kr/LSW/lsInfoP.do?lsiSeq=210503',
        'note' => '2019.10.1 인상 고용보험료율',
    ],
    [
        'from' => '2022-07-01', 'to' => null, 'employee_rate' => 0.009, 'employer_rate' => 0.009,
        'notice_no' => '대통령령 제32731호', 'published_at' => '2022-06-28',
        'url' => 'https://www.law.go.kr/LSW/lsInfoP.do?efYd=20220701&lsiSeq=243399',
        'note' => '2022.7.1 인상 고용보험료율',
    ],
];

$industrialRows = [
    ['2013', 0.037, '고용노동부고시 제2012-139호', '2012-12-31', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=1356915637062'],
    ['2014', 0.038, '고용노동부고시 제2013-56호', '2013-12-31', 'https://www.law.go.kr/LSW/precInfoP.do?mode=0&precSeq=408126'],
    ['2015', 0.038, '고용노동부고시 제2014-58호', '2014-12-31', 'https://www.law.go.kr/LSW/precInfoP.do?mode=0&precSeq=408126'],
    ['2016', 0.038, '고용노동부고시 제2015-101호', '2015-12-31', 'https://www.moel.go.kr/info/lawinfo/instruction/list.do?pageIndex=169'],
    ['2017', 0.039, '고용노동부고시 제2016-57호', '2016-12-30', 'https://www.law.go.kr/LSW/precInfoP.do?mode=0&precSeq=405112'],
    ['2018', 0.0405, '고용노동부고시 제2017-75호', '2017-12-29', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20171200402'],
    ['2019', 0.0375, '고용노동부고시 제2018-90호', '2018-12-31', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20181200847'],
    ['2020', 0.0373, '고용노동부고시 제2019-73호', '2019-12-27', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20191200930'],
    ['2021', 0.037, '고용노동부고시 제2020-145호', '2020-12-29', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20201201861'],
    ['2022', 0.037, '고용노동부고시 제2021-95호', '2021-12-29', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20211201784'],
    ['2023', 0.037, '고용노동부고시 제2022-82호', '2022-12-29', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20221202041'],
    ['2024', 0.0356, '고용노동부고시 제2024-1호', '2024-01-05', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20240100303'],
    ['2025', 0.0356, '고용노동부고시 제2024-77호', '2024-12-30', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20241201937'],
    ['2026', 0.0356, '고용노동부고시 제2025-91호', '2025-12-31', 'https://www.moel.go.kr/info/lawinfo/instruction/view.do?bbs_seq=20251201757'],
];

$findStandard = static function (string $type, string $from) use ($db): ?array {
    $statement = $db->prepare(
        'SELECT id FROM system_statutory_standards WHERE standard_type_code=:type AND effective_from=:effective_from LIMIT 1'
    );
    $statement->execute([':type' => $type, ':effective_from' => $from]);
    $id = $statement->fetchColumn();
    return $id ? ['id' => (string) $id] : null;
};

$saveStandard = static function (array $row) use ($apply, $findStandard, $service): array {
    $existing = $findStandard($row['standard_type_code'], $row['effective_from']);
    if ($existing) {
        $row['id'] = $existing['id'];
        $existingDetail = $service->detail($existing['id'])['data'];
        foreach ((array) ($row['sources'] ?? []) as $index => &$source) {
            if (isset($existingDetail['sources'][$index]['id'])) {
                $source['id'] = $existingDetail['sources'][$index]['id'];
            }
        }
        unset($source);
    }
    if ($apply) {
        $result = $service->save($row);
        return ['action' => $existing ? 'updated' : 'created', 'id' => $result['data']['id'] ?? $existing['id'] ?? null];
    }
    return ['action' => $existing ? 'would-update' : 'would-create', 'id' => $existing['id'] ?? null];
};

$changes = [];

$codeRows = $codeService->getList([
    ['field' => 'code_group', 'value' => 'STATUTORY_STANDARD_TYPE'],
    ['field' => 'code', 'value' => 'EMPLOYMENT_INSURANCE'],
]);
$employmentCode = array_values(array_filter(
    $codeRows,
    static fn(array $row): bool => ($row['code'] ?? '') === 'EMPLOYMENT_INSURANCE'
))[0] ?? null;
if (!$employmentCode) {
    throw new RuntimeException('고용보험 법정기준 Type을 찾을 수 없습니다.');
}
if ($apply) {
    $employmentCode['extra_data'] = json_encode($employmentTemplate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $templateResult = $codeService->save($employmentCode, 'SYSTEM_' . $actorContext);
    if (empty($templateResult['success'])) {
        throw new RuntimeException((string) ($templateResult['message'] ?? '고용보험 Template 저장 실패'));
    }
}
$changes[] = ['type' => 'EMPLOYMENT_INSURANCE_TEMPLATE', 'action' => $apply ? 'updated' : 'would-update'];

foreach ($employmentRows as $row) {
    $changes[] = ['type' => 'EMPLOYMENT_INSURANCE', 'from' => $row['from']] + $saveStandard([
        'standard_type_code' => 'EMPLOYMENT_INSURANCE',
        'effective_from' => $row['from'],
        'effective_to' => $row['to'] ?? '',
        'value_data' => [
            'employee_rate' => $row['employee_rate'],
            'employer_rate' => $row['employer_rate'],
            'additional_employer_rates' => $additionalEmployerRates,
        ],
        'note' => $row['note'],
        'sources' => [[
            'source_name' => '고용보험료율 법령 연혁',
            'organization_name' => '대한민국 정부',
            'law_name' => '고용보험 및 산업재해보상보험의 보험료징수 등에 관한 법률 시행령 제12조',
            'notice_no' => $row['notice_no'],
            'published_at' => $row['published_at'],
            'source_url' => $row['url'],
            'note' => '실업급여 총 보험료율과 노사 균등 부담, 사업규모별 고용안정·직업능력개발사업 부담률 근거',
        ]],
    ]);
}

foreach ($incomeRows as $row) {
    $adjustmentRules = $row['year'] === '2023' ? [
        ['rule_type' => 'CHILD_COUNT_DEDUCTION', 'eligible_age_from' => 8, 'eligible_age_to' => 20,
            'child_count_from' => 1, 'child_count_to' => 1, 'fixed_deduction' => 12500,
            'additional_per_child' => 0, 'minimum_tax' => 0],
        ['rule_type' => 'CHILD_COUNT_DEDUCTION', 'eligible_age_from' => 8, 'eligible_age_to' => 20,
            'child_count_from' => 2, 'child_count_to' => 2, 'fixed_deduction' => 29160,
            'additional_per_child' => 0, 'minimum_tax' => 0],
        ['rule_type' => 'CHILD_COUNT_DEDUCTION', 'eligible_age_from' => 8, 'eligible_age_to' => 20,
            'child_count_from' => 3, 'child_count_to' => null, 'fixed_deduction' => 29160,
            'additional_per_child' => 25000, 'minimum_tax' => 0],
    ] : [];
    $changes[] = ['type' => 'EMPLOYMENT_INCOME_TAX_TABLE', 'from' => $row['from']] + $saveStandard([
        'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
        'effective_from' => $row['from'],
        'effective_to' => $row['to'] ?? '',
        'value_data' => [
            'table' => ['salary_unit' => 'KRW', 'dependent_counts' => array_map('strval', range(1, 11)),
                'rows' => $matrixPayload[$row['year']]],
            'excess_rules' => $toExcessRules($incomeExcessRules[$row['year']]),
            'adjustment_rules' => $adjustmentRules,
            'calculation_policy' => $incomePolicies,
        ],
        'note' => $row['note'],
        'sources' => $row['sources'],
    ]);
}

foreach ($industrialRows as $row) {
    [$year, $rate, $noticeNo, $publishedAt, $url] = $row;
    $changes[] = ['type' => 'INDUSTRIAL_ACCIDENT', 'from' => $year . '-01-01'] + $saveStandard([
        'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
        'effective_from' => $year . '-01-01',
        'effective_to' => $year === '2026' ? '' : $year . '-12-31',
        'value_data' => ['industry_rates' => [['industry_name' => '건설업', 'employer_rate' => $rate]]],
        'note' => $year . '년도 건설업 산재보험료율'
            . ((int) $year >= 2018 ? '(사업종류별 요율과 출퇴근재해 요율 합계)' : ''),
        'sources' => [[
            'source_name' => $year . '년도 사업종류별 산재보험료율',
            'organization_name' => '고용노동부',
            'law_name' => '고용보험 및 산업재해보상보험의 보험료징수 등에 관한 법률 제14조',
            'notice_no' => $noticeNo,
            'published_at' => $publishedAt,
            'source_url' => $url,
            'note' => $year . '년 건설업에 적용되는 공식 산재보험료율'
                . ((int) $year >= 2018 ? ' 및 전 업종 출퇴근재해 보험료율 합산 근거' : ''),
        ]],
    ]);
}

echo json_encode([
    'mode' => $apply ? 'apply' : 'dry-run',
    'actor' => ActorHelper::system($actorContext),
    'changes' => $changes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
