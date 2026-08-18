<?php
namespace App\Services\System;

use App\Models\System\CodeModel;

final class CodeReferenceService
{
    public function __construct(private readonly CodeModel $model)
    {
    }

    public function inspect(string $codeGroup, string $code, string $codeName = ''): array
    {
        $policy = CodeReferenceRegistry::policy($codeGroup);
        if ($policy === null) {
            return ['checked' => false, 'references' => []];
        }

        $references = [];
        foreach ($policy['columns'] as $target) {
            [$table, $column, $label, $matchDisplayName] = array_pad($target, 4, false);
            if (!$this->model->tableExists($table) || !$this->model->columnExists($table, $column)) {
                return ['checked' => false, 'references' => []];
            }
            $count = $this->model->countValueReferences($table, $column, $code);
            if ($matchDisplayName && $codeName !== '' && $codeName !== $code) {
                $count += $this->model->countValueReferences($table, $column, $codeName);
            }
            if ($count > 0) {
                $references[] = ['label' => $label, 'count' => $count];
            }
        }

        foreach ($policy['json'] as [$table, $column, $jsonKey, $label]) {
            if (!$this->model->tableExists($table) || !$this->model->columnExists($table, $column)) {
                return ['checked' => false, 'references' => []];
            }
            $count = $jsonKey === null
                ? $this->model->countJsonReferences($table, $column, $code)
                : $this->model->countJsonValueReferences($table, $column, $jsonKey, $code);
            if ($count > 0) {
                $references[] = ['label' => $label, 'count' => $count];
            }
        }

        return ['checked' => true, 'references' => $this->merge($references)];
    }

    private function merge(array $references): array
    {
        $counts = [];
        foreach ($references as $reference) {
            $label = trim((string) ($reference['label'] ?? ''));
            if ($label !== '') {
                $counts[$label] = ($counts[$label] ?? 0) + (int) ($reference['count'] ?? 0);
            }
        }

        $result = [];
        foreach ($counts as $label => $count) {
            $result[] = ['label' => $label, 'count' => $count];
        }
        return $result;
    }
}
