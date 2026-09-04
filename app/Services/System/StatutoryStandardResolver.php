<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\System\StatutoryStandardModel;
use PDO;

class StatutoryStandardResolver
{
    private const INSURANCE_TYPES = [
        'NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE',
        'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT',
    ];

    private StatutoryStandardModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new StatutoryStandardModel($db);
    }

    public function resolve(string $type, string $date): array
    {
        if (!in_array($type, self::INSURANCE_TYPES, true)) {
            $standard = $this->resolveOptionalLegacyType($type, $date);
            if ($standard === null) throw new \RuntimeException('기준일에 적용할 법정기준이 없습니다.');
            return $standard;
        }
        return $this->resolveComponent($type, 'PREMIUM', 'ALL', 'ALL', $date);
    }

    public function resolveOptional(string $type, string $date): ?array
    {
        if (!in_array($type, self::INSURANCE_TYPES, true)) return $this->resolveOptionalLegacyType($type, $date);
        return $this->resolveOptionalComponent($type, 'PREMIUM', 'ALL', 'ALL', $date);
    }

    public function resolveComponent(
        string $type,
        string $component,
        string $employmentType,
        string $workScope,
        string $date,
        array $additionalDimensions = []
    ): array {
        $standard = $this->resolveOptionalComponent($type, $component, $employmentType, $workScope, $date, $additionalDimensions);
        if ($standard === null) {
            throw new \RuntimeException('기준일과 정책 Dimension에 맞는 법정기준이 없습니다.');
        }
        return $standard;
    }

    public function resolveOptionalComponent(
        string $type,
        string $component,
        string $employmentType,
        string $workScope,
        string $date,
        array $additionalDimensions = []
    ): ?array {
        $component = strtoupper(trim($component));
        $employmentType = strtoupper(trim($employmentType));
        $workScope = strtoupper(trim($workScope));
        if ($type === '' || !$this->isDate($date)
            || !in_array($component, ['PREMIUM', 'ELIGIBILITY'], true)
            || !in_array($employmentType, ['ALL', 'REGULAR', 'DAILY'], true)
            || !in_array($workScope, ['ALL', 'HEAD_OFFICE', 'CONSTRUCTION_SITE'], true)) {
            throw new \InvalidArgumentException('법정기준 종류, 정책 구성요소, 고용형태, 업무 Scope와 기준일이 필요합니다.');
        }

        $additionalDimensionData = $this->canonicalDimensions($additionalDimensions);
        $rows = $this->model->rows(
            'SELECT * FROM system_statutory_standards WHERE standard_type_code=:type'
            . ' AND policy_component_code=:component AND employment_type_code=:employment_type AND work_scope_code=:work_scope'
            . ' AND additional_dimension_key=:additional_dimension_key'
            . ' AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)',
            [
                ':type'=>$type,
                ':component'=>$component,
                ':employment_type'=>$employmentType,
                ':work_scope'=>$workScope,
                ':additional_dimension_key'=>hash('sha256', $additionalDimensionData),
                ':date_from'=>$date,
                ':date_to'=>$date,
            ]
        );
        return $this->selectEffectiveLeaf(
            $rows,
            $this->model->supersessionEdges($type, $component, $employmentType, $workScope, hash('sha256', $additionalDimensionData))
        );
    }

    private function canonicalDimensions(array $dimensions): string
    {
        if ($dimensions === []) {
            return '{}';
        }
        ksort($dimensions, SORT_STRING);
        return (string)json_encode($dimensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function resolveOptionalLegacyType(string $type, string $date): ?array
    {
        if ($type === '' || !$this->isDate($date)) throw new \InvalidArgumentException('법정기준 종류와 기준일이 필요합니다.');
        $rows = $this->model->rows(
            'SELECT * FROM system_statutory_standards WHERE standard_type_code=:type'
            . ' AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)',
            [':type'=>$type, ':date_from'=>$date, ':date_to'=>$date]
        );
        return $this->selectEffectiveLeaf($rows, $this->model->supersessionEdges($type, null, null, null, null));
    }

    private function selectEffectiveLeaf(array $candidates, array $edges): ?array
    {
        if ($candidates === []) {
            return null;
        }
        $validIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $candidates), true);
        $successors = [];
        foreach ($edges as $edge) {
            $successors[(string)$edge['predecessor_revision_id']][] = (string)$edge['successor_revision_id'];
        }
        $leaves = [];
        foreach ($candidates as $candidate) {
            if (!$this->hasValidDescendant((string)$candidate['id'], $successors, $validIds)) {
                $leaves[] = $candidate;
            }
        }
        if (count($leaves) > 1) {
            throw new \RuntimeException('AMBIGUOUS_POLICY: 기준일에 최종 유효한 법정기준 Revision이 여러 건입니다.');
        }
        if ($leaves === []) {
            throw new \RuntimeException('POLICY_NOT_FOUND: 기준일에 최종 유효한 법정기준 Revision이 없습니다.');
        }
        $leaf = $leaves[0];
        $leaf['value_data'] = json_decode((string)$leaf['value_data'], true) ?: [];
        $leaf['additional_dimension_data'] = json_decode((string)($leaf['additional_dimension_data'] ?? ''), true) ?: [];
        return $leaf;
    }

    private function hasValidDescendant(string $revisionId, array $successors, array $validIds): bool
    {
        $pending = $successors[$revisionId] ?? [];
        $visited = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current])) {
                throw new \RuntimeException('AMBIGUOUS_POLICY: 법정기준 Revision 대체 체인에 cycle이 있습니다.');
            }
            $visited[$current] = true;
            if (isset($validIds[$current])) {
                return true;
            }
            array_push($pending, ...($successors[$current] ?? []));
        }
        return false;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
