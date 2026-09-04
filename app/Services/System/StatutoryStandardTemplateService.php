<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\System\CodeModel;
use PDO;

class StatutoryStandardTemplateService
{
    private const FIELD_TYPES = ['text', 'select', 'boolean', 'amount', 'rate', 'number', 'rounding', 'json', 'matrix', 'bracket'];
    private const COLUMN_TYPES = ['text', 'amount', 'rate', 'number', 'select'];

    private SystemCodeOptionService $codeOptions;
    private CodeModel $codeModel;

    public function __construct(private PDO $db)
    {
        $this->codeModel = new CodeModel($db);
        $this->codeOptions = new SystemCodeOptionService($db);
    }

    public function all(): array
    {
        return array_map(fn(array $row): array => $this->normalize($row), $this->codeModel->getStatutoryStandardTemplates());
    }

    /**
     * 목록 Summary에 필요한 첫 필드 계약만 조회한다.
     * 상세 편집용 선택코드 options는 조립하지 않는다.
     *
     * @return array<string,array<string,mixed>|null>
     */
    public function summaryFields(): array
    {
        $result = [];
        foreach ($this->codeModel->getStatutoryStandardTemplates(false) as $row) {
            $code = (string)($row['code'] ?? '');
            $data = json_decode((string)($row['extra_data'] ?? ''), true);
            if ($code === '' || !is_array($data)) {
                throw new \RuntimeException($code . ' 입력 템플릿이 올바르지 않습니다.');
            }
            $result[$this->summaryFieldKey($code, null, null, null)] = $this->firstRawField($data['fields'] ?? []);
            $fieldSets = (array)($data['field_sets'] ?? []);
            foreach ((array)($data['component_templates'] ?? []) as $componentTemplate) {
                if (!is_array($componentTemplate)) continue;
                $fieldSetCode = (string)($componentTemplate['field_set_code'] ?? '');
                $fields = $fieldSetCode !== ''
                    ? ($fieldSets[$fieldSetCode] ?? [])
                    : ($componentTemplate['fields'] ?? []);
                $key = $this->summaryFieldKey(
                    $code,
                    (string)($componentTemplate['policy_component_code'] ?? ''),
                    (string)($componentTemplate['employment_type_code'] ?? ''),
                    (string)($componentTemplate['work_scope_code'] ?? '')
                );
                $result[$key] = $this->firstRawField($fields);
            }
        }
        return $result;
    }

    public function summaryFieldKey(string $code, ?string $component, ?string $employmentType, ?string $workScope): string
    {
        return implode('|', [$code, $component ?? '', $employmentType ?? '', $workScope ?? '']);
    }

    private function firstRawField(mixed $fields): ?array
    {
        if (!is_array($fields)) return null;
        $field = array_values($fields)[0] ?? null;
        if (!is_array($field)) return null;
        if (($field['type'] ?? '') === 'bracket') return $this->normalizeBracketField($field);
        return $field;
    }

    public function find(
        string $code,
        ?string $component = null,
        ?string $employmentType = null,
        ?string $workScope = null
    ): array {
        return $this->findFrom($this->all(), $code, $component, $employmentType, $workScope);
    }

    public function findFrom(
        array $templates,
        string $code,
        ?string $component = null,
        ?string $employmentType = null,
        ?string $workScope = null
    ): array {
        foreach ($templates as $template) {
            if ($template['code'] !== $code) {
                continue;
            }
            if ($component === null) {
                return $template;
            }
            foreach ((array)($template['component_templates'] ?? []) as $componentTemplate) {
                if (($componentTemplate['policy_component_code'] ?? null) === $component
                    && ($componentTemplate['employment_type_code'] ?? null) === $employmentType
                    && ($componentTemplate['work_scope_code'] ?? null) === $workScope) {
                    return array_replace($template, $componentTemplate, [
                        'fields'=>(array)($componentTemplate['fields'] ?? []),
                        'calculation_policy'=>(array)($componentTemplate['calculation_policy'] ?? ['fields'=>[]]),
                    ]);
                }
            }
            throw new \InvalidArgumentException('선택한 정책 Dimension의 입력 템플릿이 없습니다.');
        }
        throw new \InvalidArgumentException('법정기준 종류가 올바르지 않습니다.');
    }

    private function normalize(array $row): array
    {
        $data = json_decode((string)($row['extra_data'] ?? ''), true);
        if (!is_array($data)) {
            throw new \RuntimeException((string)$row['code'] . ' 입력 템플릿이 올바르지 않습니다.');
        }
        $data['fields'] = array_values((array)($data['fields'] ?? []));
        $data['calculation_policy'] = (array)($data['calculation_policy'] ?? ['fields'=>[]]);
        $data['calculation_policy']['fields'] = array_values((array)($data['calculation_policy']['fields'] ?? []));
        $data['fields'] = $this->normalizeFields($data['fields']);
        $this->validateFields((string)$row['code'], $data['fields']);
        $this->validateFields((string)$row['code'] . ':calculation_policy', $data['calculation_policy']['fields']);

        $fieldSets = (array)($data['field_sets'] ?? []);
        $componentTemplates = [];
        foreach ((array)($data['component_templates'] ?? []) as $index => $componentTemplate) {
            if (!is_array($componentTemplate)) {
                throw new \RuntimeException((string)$row['code'] . ' 구성요소 템플릿이 올바르지 않습니다.');
            }
            $fieldSetCode = (string)($componentTemplate['field_set_code'] ?? '');
            $fields = $fieldSetCode !== '' ? ($fieldSets[$fieldSetCode] ?? null) : ($componentTemplate['fields'] ?? []);
            if (!is_array($fields)) {
                throw new \RuntimeException((string)$row['code'] . ' 필드 집합을 찾을 수 없습니다.');
            }
            $fields = $this->normalizeFields(array_values($fields));
            $this->validateFields((string)$row['code'] . ':component:' . $index, $fields);
            $componentTemplate['fields'] = $fields;
            $componentTemplate['calculation_policy'] = (array)($componentTemplate['calculation_policy'] ?? ['fields'=>[]]);
            $componentTemplate['calculation_policy']['fields'] = array_values((array)($componentTemplate['calculation_policy']['fields'] ?? []));
            $this->validateComponentDimensions((string)$row['code'], $componentTemplate);
            $componentTemplates[] = $componentTemplate;
        }
        $data['component_templates'] = $componentTemplates;

        return [
            'code'=>(string)$row['code'],
            'name'=>(string)$row['code_name'],
            'description'=>(string)($row['note'] ?? ''),
        ] + $data;
    }

    private function normalizeFields(array $fields): array
    {
        return array_map(function (array $field): array {
            if (($field['type'] ?? '') === 'bracket') $field = $this->normalizeBracketField($field);
            if (($field['type'] ?? '') === 'select') $field = $this->normalizeSelectField($field);
            if (isset($field['columns']) && is_array($field['columns'])) {
                $field['columns'] = array_map(fn(array $column): array => ($column['type'] ?? '') === 'select'
                    ? $this->normalizeSelectField($column) : $column, $field['columns']);
            }
            return $field;
        }, $fields);
    }

    private function normalizeSelectField(array $field): array
    {
        if (($field['option_source'] ?? null) !== 'SYSTEM_CODES') return $field;
        $group = strtoupper(trim((string)($field['option_code_group'] ?? '')));
        $allowed = array_values(array_map('strval', (array)($field['allowed_codes'] ?? [])));
        if ($group === '' || $allowed === [] || !array_key_exists('allow_inactive_for_existing_value', $field)
            || !array_key_exists('nullable', $field)) {
            throw new \RuntimeException('SYSTEM_CODES 선택필드 계약이 올바르지 않습니다.');
        }
        $field['options'] = $this->codeOptions->options($group, $allowed, (bool)$field['allow_inactive_for_existing_value']);
        return $field;
    }

    public function isActiveSelectValue(array $field, string $value): bool
    {
        if (($field['option_source'] ?? null) !== 'SYSTEM_CODES') {
            return in_array($value, array_map('strval', array_column((array)($field['options'] ?? []), 'value')), true);
        }
        return $this->codeOptions->isActiveAllowed(
            (string)$field['option_code_group'], (array)$field['allowed_codes'], $value
        );
    }

    private function validateComponentDimensions(string $templateCode, array $template): void
    {
        if (!in_array($template['policy_component_code'] ?? null, ['PREMIUM', 'ELIGIBILITY'], true)
            || !in_array($template['employment_type_code'] ?? null, ['ALL', 'REGULAR', 'DAILY'], true)
            || !in_array($template['work_scope_code'] ?? null, ['ALL', 'HEAD_OFFICE', 'CONSTRUCTION_SITE'], true)) {
            throw new \RuntimeException($templateCode . ' 구성요소 Dimension이 올바르지 않습니다.');
        }
    }

    private function validateFields(string $templateCode, array $fields): void
    {
        $codes = [];
        foreach ($fields as $field) {
            $code = trim((string)($field['code'] ?? ''));
            $name = trim((string)($field['name'] ?? ''));
            $type = (string)($field['type'] ?? '');
            $valuePath = trim((string)($field['value_path'] ?? $code));
            if ($code === '' || $name === '' || $valuePath === '' || !in_array($type, self::FIELD_TYPES, true)
                || !array_key_exists('required', $field) || isset($codes[$code])) {
                throw new \RuntimeException($templateCode . ' 입력필드 계약이 올바르지 않습니다.');
            }
            if (in_array($type, ['matrix', 'bracket'], true)) {
                $this->validateMatrixColumns($templateCode, $field['columns'] ?? null);
                if ($type === 'matrix') {
                    $this->validateDynamicDimension($templateCode, $field['dynamic_dimension'] ?? null);
                }
            }
            if ($type === 'select' && !$this->validOptions($field['options'] ?? null)) {
                throw new \RuntimeException($templateCode . ' 선택형 입력필드 계약이 올바르지 않습니다.');
            }
            $codes[$code] = true;
        }
    }

    private function validOptions(mixed $options): bool
    {
        if (!is_array($options) || $options === []) {
            return false;
        }
        $values = [];
        foreach ($options as $option) {
            $value = trim((string)($option['value'] ?? ''));
            $label = trim((string)($option['label'] ?? ''));
            if ($value === '' || $label === '' || isset($values[$value])) {
                return false;
            }
            $values[$value] = true;
        }
        return true;
    }

    private function normalizeBracketField(array $field): array
    {
        $columns = array_values((array)($field['columns'] ?? []));
        foreach ($columns as $index => &$column) {
            $code = (string)($column['code'] ?? '');
            if (str_ends_with($code, '_from')) {
                $column += ['range_role'=>'from', 'key_part'=>true, 'sort_order'=>1];
            } elseif (str_ends_with($code, '_to')) {
                $column += ['range_role'=>'to', 'nullable'=>true, 'sort_order'=>2];
            }
            $column += ['width'=>$index < 2 ? 180 : 150];
        }
        unset($column);
        $field['columns'] = $columns;
        $field['ui'] = array_replace(['collapsible'=>false, 'allow_paste'=>true, 'pinned_column_count'=>2, 'strict_contiguous'=>true], (array)($field['ui'] ?? []));
        return $field;
    }

    private function validateDynamicDimension(string $templateCode, mixed $dimension): void
    {
        if ($dimension === null) return;
        if (!is_array($dimension)
            || trim((string)($dimension['key'] ?? '')) === ''
            || trim((string)($dimension['row_map_key'] ?? '')) === ''
            || !is_array($dimension['column'] ?? null)
            || !in_array((string)($dimension['column']['type'] ?? ''), self::COLUMN_TYPES, true)) {
            throw new \RuntimeException($templateCode . ' Matrix 동적 열 계약이 올바르지 않습니다.');
        }
    }

    private function validateMatrixColumns(string $templateCode, mixed $columns): void
    {
        if (!is_array($columns) || $columns === []) {
            throw new \RuntimeException($templateCode . ' Matrix 컬럼 계약이 필요합니다.');
        }
        $codes = [];
        foreach ($columns as $column) {
            $code = trim((string)($column['code'] ?? ''));
            $name = trim((string)($column['name'] ?? ''));
            $type = (string)($column['type'] ?? '');
            if ($code === '' || $name === '' || isset($codes[$code]) || !in_array($type, self::COLUMN_TYPES, true)
                || !array_key_exists('required', $column)) {
                throw new \RuntimeException($templateCode . ' Matrix 컬럼 계약이 올바르지 않습니다.');
            }
            $codes[$code] = true;
        }
    }
}
