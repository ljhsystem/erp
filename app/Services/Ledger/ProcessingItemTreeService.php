<?php

namespace App\Services\Ledger;

class ProcessingItemTreeService
{
    public function buildTree(array $items, string $rootDisplayNo = '', bool $preferSavedPath = true): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $item['children'] = [];
            $item['level'] = 1;
            $item['depth'] = 1;
            $item['is_current'] = (bool) (int) ($item['is_current'] ?? $this->isCurrentByStatus((string) ($item['item_status'] ?? '')));
            $normalized[$id] = $item;
        }

        $childrenByParent = [];
        $roots = [];
        foreach ($normalized as $id => $item) {
            $parentId = (string) ($item['parent_item_id'] ?? '');
            if ($parentId !== '' && isset($normalized[$parentId])) {
                $childrenByParent[$parentId][] = $id;
                continue;
            }
            $roots[] = $id;
        }

        $sort = static function (array $a, array $b): int {
            $sortA = (int) ($a['sort_no'] ?? 0);
            $sortB = (int) ($b['sort_no'] ?? 0);
            if ($sortA === $sortB) {
                return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
            }
            return $sortA <=> $sortB;
        };

        usort($roots, fn(string $a, string $b): int => $sort($normalized[$a], $normalized[$b]));
        foreach ($childrenByParent as &$childIds) {
            usort($childIds, fn(string $a, string $b): int => $sort($normalized[$a], $normalized[$b]));
        }
        unset($childIds);

        $tree = [];
        foreach ($roots as $index => $rootId) {
            $savedPath = $preferSavedPath ? trim((string) ($normalized[$rootId]['display_path'] ?? '')) : '';
            $displayNo = $savedPath !== ''
                ? $savedPath
                : ($rootDisplayNo !== '' ? ($index === 0 ? $rootDisplayNo : $rootDisplayNo . '-' . ($index + 1)) : (string) ($index + 1));
            $tree[] = $this->buildNode($rootId, $normalized, $childrenByParent, 1, $displayNo, $preferSavedPath);
        }

        return $tree;
    }

    public function flattenTree(array $tree): array
    {
        $flat = [];
        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            $node['children'] = $children;
            $flat[] = $node;
            if (is_array($children) && $children !== []) {
                array_push($flat, ...$this->flattenTree($children));
            }
        }

        return $flat;
    }

    private function buildNode(string $id, array $items, array $childrenByParent, int $level, string $displayNo, bool $preferSavedPath): array
    {
        $node = $items[$id];
        $node['level'] = $level;
        $node['depth'] = $level;
        $savedPath = $preferSavedPath ? trim((string) ($node['display_path'] ?? '')) : '';
        $node['display_no'] = $savedPath !== '' ? $savedPath : $displayNo;
        $node['children'] = [];

        foreach ($childrenByParent[$id] ?? [] as $index => $childId) {
            $childSavedPath = $preferSavedPath ? trim((string) ($items[$childId]['display_path'] ?? '')) : '';
            $childDisplayNo = $childSavedPath !== ''
                ? $childSavedPath
                : $node['display_no'] . '-' . ($index + 1);
            $node['children'][] = $this->buildNode(
                $childId,
                $items,
                $childrenByParent,
                $level + 1,
                $childDisplayNo,
                $preferSavedPath
            );
        }

        return $node;
    }

    private function isCurrentByStatus(string $status): bool
    {
        return !in_array($status, ['SPLIT', 'MERGED', 'INACTIVE', 'DELETED', 'IGNORED'], true);
    }
}
