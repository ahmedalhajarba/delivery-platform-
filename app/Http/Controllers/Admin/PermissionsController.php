<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserType;
use App\Services\PermissionMatrixService;
use App\Services\PermissionSyncService;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    /**
     * صفحة إنشاء صلاحية (تحويل إلى الصفحة الرئيسية للصلاحيات)
     */
    public function create()
    {
        return redirect()->route('admin.permissions.index');
    }

    /**
     * تخزين صلاحية جديدة
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:100',
            'action_key' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:255',
        ]);

        $name = strtolower(trim(str_replace([' ', '-'], '_', (string) $data['name'])));

        Permission::query()->create([
            'name' => $name,
            'slug' => $name,
            'title' => $data['title'] ?? $name,
            'label' => $data['label'] ?? null,
            'action' => $data['action'] ?? ($data['action_key'] ?? 'access'),
            'action_key' => $data['action_key'] ?? ($data['action'] ?? 'access'),
        ]);

        return redirect()->route('admin.permissions.index')->with('success', 'تم إنشاء الصلاحية بنجاح.');
    }

    /**
     * عرض صلاحية
     */
    public function show(Permission $permission)
    {
        return redirect()->route('admin.permissions.index', ['permission_id' => $permission->id]);
    }

    /**
     * تعديل صلاحية (تحويل إلى الصفحة الرئيسية)
     */
    public function edit(Permission $permission)
    {
        return redirect()->route('admin.permissions.index', ['permission_id' => $permission->id]);
    }

    /**
     * تحديث صلاحية
     */
    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:100',
            'action_key' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:255',
        ]);

        if (array_key_exists('name', $data) && filled($data['name'])) {
            $normalized = strtolower(trim(str_replace([' ', '-'], '_', (string) $data['name'])));
            $data['name'] = $normalized;
            $data['slug'] = $normalized;
        }

        $permission->update($data);

        return redirect()->route('admin.permissions.index')->with('success', 'تم تحديث الصلاحية بنجاح.');
    }

    /**
     * حذف صلاحية
     */
    public function destroy(Permission $permission)
    {
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('success', 'تم حذف الصلاحية بنجاح.');
    }

    /**
     * حذف جماعي
     */
    public function massDestroy(Request $request)
    {
        $ids = collect((array) $request->input('ids', []))
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            Permission::query()->whereIn('id', $ids)->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * عرض صفحة الصلاحيات
     */
    public function index(Request $request)
    {
        $roles = Role::with('permissions', 'users')->get();
        $permissions = Permission::with('roles')->get();
        $userTypes = UserType::with('roles')->get();
        
        $selectedRole = null;
        if ($request->has('role_id')) {
            $selectedRole = Role::with('permissions')->find($request->role_id);
        }

        // تجميع الصلاحيات حسب الوحدة
        $permissionGroups = $permissions->groupBy('module')->map(function ($group) {
            return $group->sortBy('name');
        })->sortKeys();

        // تعريف المتغيرات قبل استخدامها في compact()
        $rolesCount = $roles->count();
        $permissionsCount = $permissions->count();
        $userTypesCount = $userTypes->count();

        return view('admin.permissions.index', compact(
            'roles', 
            'permissions', 
            'userTypes', 
            'selectedRole',
            'permissionGroups', 
            'rolesCount',
            'permissionsCount',
            'userTypesCount'
        ));
    }

    /**
     * عرض مصفوفة الصلاحيات
     */
    public function matrix(Request $request)
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'name', 'title', 'label']);
        $selectedRoleId = (int) $request->query('role_id', (int) ($roles->first()->id ?? 0));
        $selectedRole = $selectedRoleId > 0
            ? Role::with('permissions:id')->find($selectedRoleId)
            : null;

        $permissions = Permission::with('section')->get();
        $matrixPayload = app(PermissionMatrixService::class)->build($permissions);

        $selectedPermissionIds = $selectedRole
            ? $selectedRole->permissions->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all()
            : [];

        return view('admin.permissions.matrix', array_merge([
            'permissions' => $permissions,
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRoleId,
            'selectedPermissionIds' => $selectedPermissionIds,
        ], $matrixPayload));
    }

    /**
     * تحديث مصفوفة الصلاحيات
     */
    public function updateMatrix(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer',
        ]);

        $role = Role::findOrFail((int) $request->input('role_id'));
        $incomingIds = collect((array) $request->input('permissions', []))
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values();

        $validPermissionIds = Permission::query()
            ->whereIn('id', $incomingIds)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $role->permissions()->sync($validPermissionIds);

        return redirect()
            ->route('admin.permissions.matrix', ['role_id' => $role->id])
            ->with('success', 'تم تحديث صلاحيات الدور بنجاح.');
    }

    /**
     * إنشاء دور جديد (Inline)
     */
    public function storeRoleInline(Request $request)
    {
        $request->validate([
            'title' => 'required|unique:roles,name|max:255',
        ]);

        $role = Role::create([
            'name' => $request->title,
            'display_name' => $request->title,
        ]);

        return redirect()->route('admin.permissions.index', ['role_id' => $role->id])
            ->with('success', 'تم إنشاء الدور بنجاح');
    }

    /**
     * تحديث صلاحيات دور معين
     */
    public function updateRolePermissions(Request $request, Role $role)
    {
        $permissionIds = $request->permissions ?? [];
        $role->permissions()->sync($permissionIds);

        return redirect()->back()
            ->with('success', 'تم تحديث صلاحيات الدور بنجاح');
    }

    /**
     * تحديث أدوار نوع المستخدم
     */
    public function updateUserTypeRoles(Request $request, $userTypeId)
    {
        $userType = UserType::findOrFail($userTypeId);
        $roleIds = $request->roles ?? [];
        
        $userType->roles()->sync($roleIds);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث أدوار نوع المستخدم بنجاح'
        ]);
    }

    /**
     * مزامنة الصلاحيات من الكود
     */
    public function syncCatalog()
    {
        $summary = app(PermissionSyncService::class)->syncFromCodebase(false);

        return redirect()->back()
            ->with('success', 'تم مزامنة الصلاحيات بنجاح: ' . json_encode($summary, JSON_UNESCAPED_UNICODE));
    }

    /**
     * تصدير الصلاحيات
     */
    public function export()
    {
        $permissions = Permission::with('roles')->get();
        
        $data = [];
        foreach ($permissions as $perm) {
            $data[] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'display_name' => $perm->display_name,
                'module' => $perm->module,
                'action' => $perm->action,
                'roles' => $perm->roles->pluck('name')->implode(', '),
            ];
        }

        // تصدير CSV
        $filename = 'permissions_export_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://output', 'w');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        fputcsv($handle, array_keys($data[0] ?? []));
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        exit;
    }

    /**
     * مراجعة الصلاحيات
     */
    public function audit()
    {
        $users = \App\Models\User::with('roles.permissions')->get();
        $orphanedPermissions = Permission::doesntHave('roles')->get();
        $rolesWithoutPermissions = Role::doesntHave('permissions')->get();

        return view('admin.permissions.audit', compact(
            'users', 'orphanedPermissions', 'rolesWithoutPermissions'
        ));
    }
}