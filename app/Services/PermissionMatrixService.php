<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PermissionMatrixService
{
    /**
     * Build matrix payload consumed by roles/users permission selector.
     *
     * @param EloquentCollection<int, Permission>|Collection<int, Permission>|null $permissions
     * @return array<string, mixed>
     */
    public function build($permissions = null): array
    {
        $permissions = $permissions instanceof Collection
            ? $permissions
            : Permission::with('section')->get();

        $actionColumns = config('permission_sections.action_columns', [
            'access' => 'الدخول',
            'show' => 'العرض',
            'create' => 'الإضافة',
            'edit' => 'التعديل',
            'delete' => 'الحذف',
            'print' => 'الطباعة',
        ]);

        $domainLabels = config('permission_domains.labels', []);
        $domainMeta = config('permission_domains.meta', []);

        $matrix = [];

        foreach ($permissions as $permission) {
            [$sectionKey, $actionKey] = $this->extractSectionAndAction($permission);

            if (!array_key_exists($actionKey, $actionColumns)) {
                $actionColumns[$actionKey] = Permission::ACTION_LABELS[$actionKey] ?? ucfirst((string) $actionKey);
            }

            $domainKey = Permission::resolveDomainKeyForSection($sectionKey);
            $domainLabel = $domainLabels[$domainKey] ?? Permission::resolveDomainLabelForSection($sectionKey);
            $sectionLabel = (string) ($permission->section->label ?? '');
            if ($sectionLabel === '') {
                $sectionLabel = (string) ($permission->section_label ?? '');
            }
            if ($sectionLabel === '') {
                $sectionLabel = str_replace('_', ' ', $sectionKey);
            }

            if (!isset($matrix[$domainKey])) {
                $matrix[$domainKey] = [
                    'label' => $domainLabel,
                    'rows' => [],
                ];
            }

            if (!isset($matrix[$domainKey]['rows'][$sectionKey])) {
                $matrix[$domainKey]['rows'][$sectionKey] = [
                    'section_key' => $sectionKey,
                    'section_label' => $sectionLabel,
                    'permissions' => [],
                ];
            }

            if (!isset($matrix[$domainKey]['rows'][$sectionKey]['permissions'][$actionKey])) {
                $matrix[$domainKey]['rows'][$sectionKey]['permissions'][$actionKey] = $permission;
            }
        }

        foreach ($matrix as $domainKey => $domainData) {
            $rows = collect($domainData['rows'])
                ->sortBy(fn (array $row) => mb_strtolower((string) ($row['section_label'] ?? '')))
                ->values()
                ->all();

            $matrix[$domainKey]['rows'] = $rows;
        }

        $matrixByDomain = collect($matrix)
            ->sortBy(fn (array $domain) => mb_strtolower((string) ($domain['label'] ?? '')))
            ->all();

        return [
            'matrixByDomain' => $matrixByDomain,
            'domainMeta' => $domainMeta,
            'actionColumns' => $actionColumns,
        ];
    }

    private function extractSectionAndAction(Permission $permission): array
    {
        $sectionKey = (string) ($permission->section->name ?? $permission->section ?? '');
        $actionKey = (string) ($permission->action_key ?? $permission->action ?? '');

        if ($sectionKey === '' || $actionKey === '') {
            [$parsedSection, $parsedAction] = Permission::parseTitle((string) ($permission->name ?? $permission->title ?? ''));
            $sectionKey = $sectionKey !== '' ? $sectionKey : $parsedSection;
            $actionKey = $actionKey !== '' ? (string) $actionKey : (string) $parsedAction;
        }

        $sectionKey = strtolower(trim(str_replace(['-', ' '], '_', $sectionKey)));
        $actionKey = strtolower(trim(str_replace(['-', ' '], '_', $actionKey)));

        if ($sectionKey === '') {
            $sectionKey = 'other';
        }

        if ($actionKey === '') {
            $actionKey = 'access';
        }

        return [$sectionKey, $actionKey];
    }
}
