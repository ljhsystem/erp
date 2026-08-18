<?php

namespace App\Services\System;

use PDO;

class StatutoryStandardTemplateService
{
    private const FIELD_TYPES = ['text', 'select', 'boolean', 'amount', 'rate', 'number', 'rounding', 'json', 'matrix', 'bracket'];
    private const COLUMN_TYPES = ['text', 'amount', 'rate', 'number', 'select'];

    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT code,code_name,note,extra_data FROM system_codes"
            . " WHERE code_group='STATUTORY_STANDARD_TYPE' AND is_active=1 ORDER BY sort_no"
        );

        return array_map(fn(array $row): array => $this->normalize($row), $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function find(string $code): array
    {
        foreach ($this->all() as $template) {
            if ($template['code'] === $code) {
                return $template;
            }
        }

        throw new \InvalidArgumentException('법정기준 종류가 올바르지 않습니다.');
    }

    private function normalize(array $row): array
    {
        $data = json_decode((string) ($row['extra_data'] ?? ''), true);
        if (!is_array($data)) {
            throw new \RuntimeException((string) $row['code'] . ' 입력 템플릿이 올바르지 않습니다.');
        }

        $data['fields'] = is_array($data['fields'] ?? null) ? array_values($data['fields']) : [];
        $data['calculation_policy'] = is_array($data['calculation_policy'] ?? null)
            ? $data['calculation_policy']
            : ['fields' => []];
        $data['calculation_policy']['fields'] = is_array($data['calculation_policy']['fields'] ?? null)
            ? array_values($data['calculation_policy']['fields'])
            : [];
        $data['fields'] = array_map(
            fn(array $field): array => ($field['type'] ?? '') === 'bracket'
                ? $this->normalizeBracketField($field)
                : $field,
            $data['fields']
        );
        $this->validateFields((string) $row['code'], $data['fields']);
        $this->validateFields((string) $row['code'] . ':calculation_policy', $data['calculation_policy']['fields']);

        return [
            'code' => (string) $row['code'],
            'name' => (string) $row['code_name'],
            'description' => (string) ($row['note'] ?? ''),
        ] + $data;
    }

    private function validateFields(string $templateCode, array $fields): void
    {
        $codes = [];
        foreach ($fields as $field) {
            $code = trim((string) ($field['code'] ?? ''));
            $name = trim((string) ($field['name'] ?? ''));
            $type = (string) ($field['type'] ?? '');
            if ($code === '' || $name === '' || !in_array($type, self::FIELD_TYPES, true)
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
        if (!is_array($options) || $options === []) return false;
        $values = [];
        foreach ($options as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? ''));
            if ($value === '' || $label === '' || isset($values[$value])) return false;
            $values[$value] = true;
        }
        return true;
    }

    private function normalizeBracketField(array $field): array
    {
        $columns = array_values((array) ($field['columns'] ?? []));
        foreach ($columns as $index => &$column) {
            $code = (string) ($column['code'] ?? '');
            if (str_ends_with($code, '_from')) {
                $column += ['range_role' => 'from', 'key_part' => true, 'sort_order' => 1];
            } elseif (str_ends_with($code, '_to')) {
                $column += ['range_role' => 'to', 'nullable' => true, 'sort_order' => 2];
            }
            $column += ['width' => $index < 2 ? 180 : 150];
        }
        unset($column);
        $field['columns'] = $columns;
        $field['ui'] = array_replace([
            'collapsible' => false,
            'allow_paste' => true,
            'pinned_column_count' => 2,
            'strict_contiguous' => true,
        ], (array) ($field['ui'] ?? []));
        return $field;
    }

    private function validateDynamicDimension(string $templateCode, mixed $dimension): void
    {
        if ($dimension === null) {
            return;
        }
        if (!is_array($dimension)
            || trim((string) ($dimension['key'] ?? '')) === ''
            || trim((string) ($dimension['row_map_key'] ?? '')) === ''
            || !is_array($dimension['column'] ?? null)
            || !in_array((string) ($dimension['column']['type'] ?? ''), self::COLUMN_TYPES, true)) {
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
            $code = trim((string) ($column['code'] ?? ''));
            $name = trim((string) ($column['name'] ?? ''));
            $type = (string) ($column['type'] ?? '');
            if ($code === '' || $name === '' || isset($codes[$code]) || !in_array($type, self::COLUMN_TYPES, true)
                || !array_key_exists('required', $column)) {
                throw new \RuntimeException($templateCode . ' Matrix 컬럼 계약이 올바르지 않습니다.');
            }
            $codes[$code] = true;
        }
    }

}
