<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PermissionSyncService
{
    public const CACHE_KEY = 'auth_gates.permissions_map.v1';

    public function discoverPermissionKeys(): array
    {
        $codeScanPaths = [
            app_path(),
            resource_path('views'),
        ];

        $codePatterns = [
            '/@can\(\s*[\'\"]([a-z0-9_\.\-]+)[\'\"]\s*\)/i',
            '/Gate::(?:allows|denies|define)\(\s*[\'\"]([a-z0-9_\.\-]+)[\'\"]\s*/i',
            '/hasPermissionTo\(\s*[\'\"]([a-z0-9_\.\-]+)[\'\"]\s*\)/i',
            '/->can\(\s*[\'\"]([a-z0-9_\.\-]+)[\'\"]\s*\)/i',
        ];

        $keys = [];
        foreach ($codeScanPaths as $path) {
            if (!File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['php', 'blade.php'], true) && !Str::endsWith($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = File::get($file->getPathname());
                foreach ($codePatterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        foreach ($matches[1] as $candidate) {
                            $value = trim((string) $candidate);
                            if ($value !== '' && preg_match('/^[a-z0-9_\.\-]+$/i', $value)) {
                                $keys[] = $value;
                            }
                        }
                    }
                }
            }
        }

        $keys = array_merge($keys, $this->discoverPermissionKeysFromRoutes());

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    private function discoverPermissionKeysFromRoutes(): array
    {
        $routeFile = base_path('routes/web.php');
        if (!File::exists($routeFile)) {
            return [];
        }

        $content = File::get($routeFile);
        preg_match_all('/->name\(\s*[\'\"]([a-z0-9_\.\-]+)[\'\"]\s*\)/i', $content, $matches);

        $routeNames = array_values(array_unique($matches[1] ?? []));
        $keys = [];

        foreach ($routeNames as $routeName) {
            $key = $this->permissionKeyFromRouteName((string) $routeName);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function permissionKeyFromRouteName(string $routeName): ?string
    {
        $parts = array_values(array_filter(explode('.', strtolower(trim($routeName)))));
        if (count($parts) < 2) {
            return null;
        }

        $prefix = $parts[0];
        if (!in_array($prefix, ['admin', 'user'], true)) {
            return null;
        }

        $section = str_replace('-', '_', $parts[1] ?? 'other');
        if ($prefix === 'user') {
            $section = 'client_' . $section;
        }

        $routeAction = end($parts) ?: 'index';
        $actionMap = [
            'index' => 'access',
            'create' => 'create',
            'store' => 'create',
            'show' => 'show',
            'edit' => 'edit',
            'update' => 'edit',
            'destroy' => 'delete',
            'massdestroy' => 'delete',
            'print' => 'print',
            'export' => 'export',
            'import' => 'import',
            'approve' => 'approve',
            'sync-catalog' => 'manage',
            'audit' => 'access',
            'matrix' => 'manage',
            'update' => 'edit',
        ];

        $routeAction = str_replace('-', '_', $routeAction);
        $action = $actionMap[$routeAction] ?? 'access';

        return $section . '_' . $action;
    }

    public function syncFromCodebase(bool $dryRun = false): array
    {

        $discovered = $this->discoverPermissionKeys();
        $catalog = $this->buildCatalog($discovered);
        $existing = Permission::withTrashed()->whereIn('action', array_column($catalog, 'action'))->get()->keyBy('action');

        $created = 0;
        $updated = 0;
        $restored = 0;
        foreach ($catalog as $meta) {
            $sectionName = $meta['section'] ?? 'other';
            $sectionModel = DB::table('permission_sections')->where('name', $sectionName)->first();
            if (!$sectionModel) {
                $sectionId = DB::table('permission_sections')->insertGetId([
                    'name' => $sectionName,
                    'label' => config('permission_domains.labels.' . $sectionName, str_replace('_', ' ', $sectionName)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $sectionId = $sectionModel->id;
            }

            $row = [
                'section_id' => $sectionId,
                'action' => $meta['action'],
                'label' => $meta['label'] ?? $meta['action'],
            ];

            $actionKey = $meta['action'];
            if (!$existing->has($actionKey)) {
                $created++;
                if (!$dryRun) {
                    Permission::create($row);
                }
                continue;
            }

            $permission = $existing->get($actionKey);
            $changes = [];
            if (blank($permission->section_id) || $permission->section_id !== $row['section_id']) {
                $changes['section_id'] = $row['section_id'];
            }
            if (blank($permission->action) || $permission->action !== $row['action']) {
                $changes['action'] = $row['action'];
            }
            if (blank($permission->label) || $permission->label !== $row['label']) {
                $changes['label'] = $row['label'];
            }

            if (!empty($changes)) {
                $updated++;
                if (!$dryRun) {
                    $permission->update($changes);
                }
            }
        }

        $catalogActions = array_column($catalog, 'action');
        $stalePermissions = Permission::query()
            ->whereNotNull('section_id')
            ->whereNotNull('action')
            ->whereNotIn('action', $catalogActions)
            ->get();

        $pruned = $stalePermissions->count();
        if (!$dryRun && $pruned > 0) {
            foreach ($stalePermissions as $stalePermission) {
                $stalePermission->delete();
            }
        }

        $sections = [];
        foreach ($catalog as $item) {
            $sections[$item['section']] = true;
        }

        return [
            'discovered_keys' => count($discovered),
            'catalog_permissions' => count($catalog),
            'sections_count' => count($sections),
            'created' => $created,
            'updated' => $updated,
            'restored' => $restored,
            'pruned' => $pruned,
            'dry_run' => $dryRun,
        ];
    }

    private function buildCatalog(array $keys): array
    {
        $defaultActions = config('permission_sections.default_actions', ['access', 'create', 'show', 'edit', 'delete']);

        $sectionActions = [];
        foreach ($keys as $key) {
            [$section, $action] = Permission::parseTitle($key);
            $section = trim((string) $section);
            if ($section === '') {
                continue;
            }

            if (!isset($sectionActions[$section])) {
                $sectionActions[$section] = [];
            }

            if ($action && in_array($action, $defaultActions, true)) {
                foreach ($defaultActions as $baseAction) {
                    $sectionActions[$section][$baseAction] = true;
                }
            } elseif ($action) {
                $sectionActions[$section][$action] = true;
                $sectionActions[$section]['access'] = true;
            } else {
                $sectionActions[$section]['access'] = true;
            }
        }

        $catalog = [];
        foreach ($sectionActions as $section => $actions) {
            foreach (array_keys($actions) as $action) {
                $title = strtolower($section . '_' . $action);
                $catalog[$title] = [
                    'section' => $section,
                    'action' => $action,
                    'label' => $this->toArabicLabel($section, $action),
                ];
            }
        }

        ksort($catalog);

        return $catalog;
    }

    private function toArabicLabel(string $section, ?string $action): string
    {
        $sectionsMap = config('permission_sections.arabic_sections', config('permission_domains.labels', []));
        $sectionAr = $sectionsMap[$section] ?? str_replace('_', ' ', $section);

        $actionAr = Permission::ACTION_LABELS[$action] ?? str_replace('_', ' ', (string) $action);

        return trim($sectionAr . ' - ' . $actionAr);
    }
}
