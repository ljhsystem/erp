<?php

namespace App\Services\Institution;

final class PersonnelActionChangePolicy
{
    public const EMPLOYMENT_STATUS = 'EMPLOYMENT_STATUS';
    public const DEPARTMENT = 'DEPARTMENT';
    public const POSITION = 'POSITION';
    public const JOB = 'JOB';
    public const PROJECT_ASSIGNMENT = 'PROJECT_ASSIGNMENT';
    public const PROJECT_RELEASE = 'PROJECT_RELEASE';
    public const WORKPLACE = 'WORKPLACE';
    public const LEAVE = 'LEAVE';
    public const RETURN_FROM_LEAVE = 'RETURN_FROM_LEAVE';
    public const HIRE_DATE = 'HIRE_DATE';
    public const RETIRE_DATE = 'RETIRE_DATE';

    private const COMMANDS = [
        self::EMPLOYMENT_STATUS => ['label' => '재직상태', 'input_type' => 'employment_status', 'before_source' => 'employment_status'],
        self::DEPARTMENT => ['label' => '부서', 'input_type' => 'department', 'before_source' => 'department'],
        self::POSITION => ['label' => '직위·직책', 'input_type' => 'position', 'before_source' => 'position'],
        self::JOB => ['label' => '직무', 'input_type' => 'job', 'before_source' => 'job'],
        self::PROJECT_ASSIGNMENT => ['label' => '프로젝트배치', 'input_type' => 'project_assignment'],
        self::PROJECT_RELEASE => ['label' => '프로젝트해제', 'input_type' => 'project_release'],
        self::WORKPLACE => ['label' => '근무지', 'input_type' => 'workplace'],
        self::LEAVE => ['label' => '휴직', 'input_type' => 'leave'],
        self::RETURN_FROM_LEAVE => ['label' => '복직', 'input_type' => 'return_from_leave'],
        self::HIRE_DATE => ['label' => '입사일', 'input_type' => 'employment_date', 'date_kind' => 'hire', 'before_source' => 'hire_date'],
        self::RETIRE_DATE => ['label' => '퇴직일', 'input_type' => 'employment_date', 'date_kind' => 'retire', 'before_source' => 'retire_date'],
    ];

    private const ACTIONS = [
        'HIRE' => [
            'allowed' => [self::EMPLOYMENT_STATUS, self::HIRE_DATE, self::DEPARTMENT, self::POSITION, self::JOB, self::WORKPLACE],
            'required_all' => [self::EMPLOYMENT_STATUS, self::HIRE_DATE],
            'required_any' => [],
            'required_values' => [self::EMPLOYMENT_STATUS => ['after_employment_status' => 'ACTIVE']],
        ],
        'DEPARTMENT_TRANSFER' => ['allowed' => [self::DEPARTMENT], 'required_all' => [self::DEPARTMENT], 'required_any' => [], 'required_values' => []],
        'POSITION_CHANGE' => ['allowed' => [self::POSITION], 'required_all' => [self::POSITION], 'required_any' => [], 'required_values' => []],
        'PROMOTION' => ['allowed' => [self::POSITION], 'required_all' => [self::POSITION], 'required_any' => [], 'required_values' => []],
        'JOB_CHANGE' => ['allowed' => [self::JOB], 'required_all' => [self::JOB], 'required_any' => [], 'required_values' => []],
        'PROJECT_ASSIGN' => ['allowed' => [self::PROJECT_ASSIGNMENT], 'required_all' => [self::PROJECT_ASSIGNMENT], 'required_any' => [], 'required_values' => []],
        'PROJECT_RELEASE' => ['allowed' => [self::PROJECT_RELEASE], 'required_all' => [self::PROJECT_RELEASE], 'required_any' => [], 'required_values' => []],
        'WORKPLACE_CHANGE' => ['allowed' => [self::WORKPLACE], 'required_all' => [self::WORKPLACE], 'required_any' => [], 'required_values' => []],
        'TRANSFER' => ['allowed' => [self::DEPARTMENT, self::POSITION, self::JOB, self::WORKPLACE], 'required_all' => [], 'required_any' => [self::DEPARTMENT, self::POSITION, self::JOB, self::WORKPLACE], 'required_values' => []],
        'LEAVE_OF_ABSENCE' => [
            'allowed' => [self::LEAVE, self::EMPLOYMENT_STATUS],
            'required_all' => [self::LEAVE, self::EMPLOYMENT_STATUS],
            'required_any' => [],
            'required_values' => [self::EMPLOYMENT_STATUS => ['after_employment_status' => 'ON_LEAVE']],
        ],
        'REINSTATEMENT' => [
            'allowed' => [self::RETURN_FROM_LEAVE, self::EMPLOYMENT_STATUS],
            'required_all' => [self::RETURN_FROM_LEAVE, self::EMPLOYMENT_STATUS],
            'required_any' => [],
            'required_values' => [self::EMPLOYMENT_STATUS => ['after_employment_status' => 'ACTIVE']],
        ],
        'RETIREMENT' => [
            'allowed' => [self::RETIRE_DATE, self::EMPLOYMENT_STATUS],
            'required_all' => [self::RETIRE_DATE, self::EMPLOYMENT_STATUS],
            'required_any' => [],
            'required_values' => [self::EMPLOYMENT_STATUS => ['after_employment_status' => 'RETIRED']],
        ],
        'OTHER' => [
            'allowed' => [self::EMPLOYMENT_STATUS, self::DEPARTMENT, self::POSITION, self::JOB, self::PROJECT_ASSIGNMENT, self::PROJECT_RELEASE, self::WORKPLACE, self::LEAVE, self::RETURN_FROM_LEAVE, self::HIRE_DATE, self::RETIRE_DATE],
            'required_all' => [],
            'required_any' => [self::EMPLOYMENT_STATUS, self::DEPARTMENT, self::POSITION, self::JOB, self::PROJECT_ASSIGNMENT, self::PROJECT_RELEASE, self::WORKPLACE, self::LEAVE, self::RETURN_FROM_LEAVE, self::HIRE_DATE, self::RETIRE_DATE],
            'required_values' => [],
        ],
    ];

    public static function commandCodes(): array
    {
        return array_keys(self::COMMANDS);
    }

    public static function actionTypes(): array
    {
        return array_keys(self::ACTIONS);
    }

    public static function hasCommand(string $command): bool
    {
        return isset(self::COMMANDS[$command]);
    }

    public static function supportsActionType(string $actionType): bool
    {
        return isset(self::ACTIONS[$actionType]);
    }

    public static function label(string $command): string
    {
        return self::COMMANDS[$command]['label'] ?? $command;
    }

    public static function metadata(): array
    {
        $commands = [];
        foreach (self::COMMANDS as $value => $metadata) {
            $commands[] = ['value' => $value] + $metadata;
        }
        return ['commands' => $commands, 'actions' => self::ACTIONS];
    }

    public static function assertSupportedActionType(string $actionType): void
    {
        if (!self::supportsActionType($actionType)) {
            throw new \InvalidArgumentException('지원하지 않는 발령유형입니다.');
        }
    }

    public static function assertAllowed(string $actionType, string $command): void
    {
        self::assertSupportedActionType($actionType);
        if (!self::hasCommand($command)) {
            throw new \InvalidArgumentException('변경구분을 확인해 주세요.');
        }
        if (!in_array($command, self::ACTIONS[$actionType]['allowed'], true)) {
            throw new \InvalidArgumentException('선택한 발령유형에서 사용할 수 없는 변경구분입니다.');
        }
    }

    public static function assertCommandSet(string $actionType, array $changes): void
    {
        self::assertSupportedActionType($actionType);
        $byType = [];
        foreach ($changes as $change) {
            $command = strtoupper(trim((string) ($change['change_type_code'] ?? '')));
            self::assertAllowed($actionType, $command);
            if (isset($byType[$command])) {
                throw new \InvalidArgumentException('동일 대상자의 변경구분을 중복 등록할 수 없습니다.');
            }
            $byType[$command] = $change;
        }
        foreach (self::ACTIONS[$actionType]['required_all'] as $required) {
            if (!isset($byType[$required])) {
                throw new \InvalidArgumentException(self::label($required) . ' 변경명령은 필수입니다.');
            }
        }
        $requiredAny = self::ACTIONS[$actionType]['required_any'];
        if ($requiredAny !== [] && array_intersect(array_keys($byType), $requiredAny) === []) {
            throw new \InvalidArgumentException('허용된 변경명령을 한 개 이상 등록해 주세요.');
        }
        foreach (self::ACTIONS[$actionType]['required_values'] as $command => $requiredValues) {
            if (!isset($byType[$command])) continue;
            foreach ($requiredValues as $field => $expected) {
                if (($byType[$command][$field] ?? null) !== $expected) {
                    throw new \InvalidArgumentException(self::label($command) . ' 변경값이 발령유형 정책과 일치하지 않습니다.');
                }
            }
        }
    }
}
