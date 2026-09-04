<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Institution\InsuranceEligibilityConditionEvaluator;
use App\Services\Institution\InsuranceEligibilityResolver;

$evaluator = new InsuranceEligibilityConditionEvaluator();
$cases = [
    ['ALL', ['FALSE', 'UNKNOWN'], 'FALSE'],
    ['ALL', ['TRUE', 'UNKNOWN'], 'UNKNOWN'],
    ['ALL', ['TRUE', 'TRUE'], 'TRUE'],
    ['ANY', ['TRUE', 'UNKNOWN'], 'TRUE'],
    ['ANY', ['FALSE', 'UNKNOWN'], 'UNKNOWN'],
    ['ANY', ['FALSE', 'FALSE'], 'FALSE'],
    ['NONE', ['TRUE', 'FALSE'], 'FALSE'],
    ['NONE', ['FALSE', 'UNKNOWN'], 'UNKNOWN'],
    ['NONE', ['FALSE', 'FALSE'], 'TRUE'],
    ['NONE', ['UNKNOWN'], 'UNKNOWN'],
];

foreach ($cases as [$combination, $states, $expected]) {
    $actual = $evaluator->combine($states, $combination);
    if ($actual !== $expected) {
        throw new RuntimeException($combination . ' ' . implode('+', $states) . ': ' . $actual . ' != ' . $expected);
    }
}

$resolverReflection = new ReflectionClass(InsuranceEligibilityResolver::class);
$resolver = $resolverReflection->newInstanceWithoutConstructor();
$conditionProperty = $resolverReflection->getProperty('conditionEvaluator');
$conditionProperty->setValue($resolver, $evaluator);
$ageMethod = $resolverReflection->getMethod('evaluateAge');
$employmentMethod = $resolverReflection->getMethod('evaluateEmploymentPeriod');
$monthlyMethod = $resolverReflection->getMethod('evaluateMonthlyConditions');

$shortTermContext = [
    'attribution_date'=>'2013-08-06',
    'birth_date'=>null,
    'employment_start_date'=>'2013-08-06',
    'employment_end_date'=>'2013-08-10',
    'employment_end_open'=>0,
    'continuous_employment_confirmed'=>0,
    'monthly_work_days'=>5,
    'monthly_work_minutes'=>2400,
    'monthly_income_amount'=>452940,
];
$agePolicy = ['minimum_age'=>18, 'maximum_age_exclusive'=>60];
$employmentPolicy = ['minimum_continuous_months'=>1];
$monthlyPolicy = ['combination_code'=>'ANY', 'minimum_work_days'=>8, 'minimum_work_minutes'=>3600, 'minimum_income_amount'=>null];
$shortTermStates = [
    $ageMethod->invoke($resolver, $agePolicy, $shortTermContext)['state'],
    $employmentMethod->invoke($resolver, $employmentPolicy, $shortTermContext)['state'],
    $monthlyMethod->invoke($resolver, $monthlyPolicy, $shortTermContext)['state'],
];
if ($shortTermStates !== ['UNKNOWN', 'FALSE', 'FALSE'] || $evaluator->combine($shortTermStates, 'ALL') !== 'FALSE') {
    throw new RuntimeException('정순옥 단기고용 Fixture가 NOT_ELIGIBLE 판정으로 수렴하지 않습니다.');
}

$longTermContext = $shortTermContext;
$longTermContext['employment_end_date'] = '2013-09-06';
$longTermContext['monthly_work_days'] = 8;
$longTermContext['monthly_work_minutes'] = 3600;
$longTermStates = [
    $ageMethod->invoke($resolver, $agePolicy, $longTermContext)['state'],
    $employmentMethod->invoke($resolver, $employmentPolicy, $longTermContext)['state'],
    $monthlyMethod->invoke($resolver, $monthlyPolicy, $longTermContext)['state'],
];
if ($longTermStates !== ['UNKNOWN', 'TRUE', 'TRUE'] || $evaluator->combine($longTermStates, 'ALL') !== 'UNKNOWN') {
    throw new RuntimeException('1개월 이상 생년월일 누락 Fixture가 CONFIRMATION_REQUIRED로 수렴하지 않습니다.');
}

echo "가입자격 ALL/ANY/NONE 3값 논리 및 단기·장기 Fixture: PASS\n";
