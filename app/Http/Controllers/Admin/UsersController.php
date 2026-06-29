<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserType;
use App\Services\Validation\ContactValidation;
use App\Services\UserDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class UsersController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('user_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax() || $request->has('draw')) {
            // ===== عرض جميع المستخدمين =====
            // استخدام withoutGlobalScopes لتجاوز أي فلتر تلقائي
            // و withTrashed لعرض المحذوفين أيضاً (اختياري)
            $query = User::withoutGlobalScopes()
                ->with(['city', 'roles'])
                ->whereNull('users.deleted_at')
                ->select('users.*');
            
            // ===== إزالة أي فلتر تلقائي =====
            // يمكنك إضافة فلتر حسب الحاجة مثل:
            // if ($request->has('user_type') && $request->user_type) {
            //     $query->where('user_type', $request->user_type);
            // }
            
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $html = '<div class="d-flex gap-1 flex-nowrap">';

                if (!empty($row->login_code)) {
                    $escapedCode = e($row->login_code);
                    $html .= '<button type="button" class="btn btn-xs btn-outline-dark" title="نسخ رقم الدخول" onclick="copyLoginCode(\''.$escapedCode.'\')"><i class="bi bi-clipboard"></i></button>';
                }

                $hasCustomerRole = $row->roles->contains(function ($role) {
                    return in_array(strtolower((string) $role->title), ['customer', 'coustomer'], true);
                });
                $isCustomerUser = $this->isCustomerUserType($row->user_type) || $hasCustomerRole;

                if ($isCustomerUser && Route::has('admin.customers.show')) {
                    $html .= '<a href="'.route('admin.customers.show', $row->id).'" class="btn btn-xs btn-outline-info" title="ملف العميل"><i class="bi bi-person-badge"></i></a>';
                }

                if (request()->user()->can('user_show')) {
                    $html .= '<a href="'.route('admin.users.show', $row->id).'" class="btn btn-xs btn-outline-primary" title="عرض"><i class="bi bi-eye"></i></a>';
                }
                if (request()->user()->can('user_edit')) {
                    $html .= '<a href="'.route('admin.users.edit', $row->id).'" class="btn btn-xs btn-outline-warning" title="تعديل"><i class="bi bi-pencil"></i></a>';
                }
                if (request()->user()->can('user_edit')) {
                    if ((int) $row->status === 0) {
                        $html .= '<form action="'.route('admin.users.activate', $row->id).'" method="POST" class="d-inline">'.csrf_field().'<button type="submit" class="btn btn-xs btn-success" title="تفعيل"><i class="bi bi-check2-circle"></i></button></form>';
                    } else {
                        $html .= '<form action="'.route('admin.users.deactivate', $row->id).'" method="POST" class="d-inline">'.csrf_field().'<button type="submit" class="btn btn-xs btn-outline-secondary" title="تعطيل"><i class="bi bi-pause-circle"></i></button></form>';
                    }

                    if ($row->is_blocked) {
                        $html .= '<form action="'.route('admin.users.unblock', $row->id).'" method="POST" class="d-inline">'.csrf_field().'<button type="submit" class="btn btn-xs btn-success" title="رفع الحظر"><i class="bi bi-unlock-fill"></i></button></form>';
                    } else {
                        $html .= '<button type="button" class="btn btn-xs btn-outline-purple" style="color:#6f42c1;border-color:#6f42c1" title="حظر" onclick="blockUser('.$row->id.')"><i class="bi bi-ban"></i></button>';
                    }
                    if ($row->is_frozen) {
                        $html .= '<form action="'.route('admin.users.unfreeze', $row->id).'" method="POST" class="d-inline">'.csrf_field().'<button type="submit" class="btn btn-xs btn-info" title="إلغاء تجميد"><i class="bi bi-play-fill"></i></button></form>';
                    } else {
                        $html .= '<button type="button" class="btn btn-xs btn-outline-info" title="تجميد" onclick="freezeUser('.$row->id.')"><i class="bi bi-snow"></i></button>';
                    }
                }
                if (request()->user()->can('user_delete')) {
                    $html .= '<form action="'.route('admin.users.destroy', $row->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'هل أنت متأكد؟\')">'.csrf_field().'<input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-xs btn-outline-danger" title="حذف"><i class="bi bi-trash"></i></button></form>';
                }

                $html .= '</div>';
                return $html;
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('name', function ($row) {
                return $row->name ? $row->name : '';
            });
            $table->editColumn('username', function ($row) {
                return $row->username ? $row->username : '';
            });
            $table->editColumn('login_code', function ($row) {
                return $row->login_code ? $row->login_code : '';
            });
            $table->editColumn('last_name', function ($row) {
                return $row->last_name ? $row->last_name : '';
            });
            $table->editColumn('mobile', function ($row) {
                return $row->mobile ? $row->mobile : '';
            });
            $table->addColumn('city_title_ar', function ($row) {
                return $row->city ? $row->city->title_ar : '';
            });

            $table->editColumn('city.title_en', function ($row) {
                return $row->city ? (is_string($row->city) ? $row->city : $row->city->title_en) : '';
            });
            $table->editColumn('verified', function ($row) {
                return '<input type="checkbox" disabled ' . ($row->verified ? 'checked' : null) . '>';
            });
            $table->editColumn('email', function ($row) {
                return $row->email ? $row->email : '';
            });

            $table->editColumn('roles', function ($row) {
                $labels = [];
                foreach ($row->roles as $role) {
                    $labels[] = sprintf('<span class="label label-info label-many">%s</span>', $role->title);
                }

                return implode(' ', $labels);
            });

            $table->rawColumns(['actions', 'placeholder', 'city', 'verified', 'roles']);

            return $table->make(true);
        }

        $cities = City::get();
        $roles  = Role::get();
        $users = User::withoutGlobalScopes()
            ->with(['city', 'roles'])
            ->whereNull('users.deleted_at')
            ->orderByDesc('id')
            ->get();

        $baseStatsQuery = User::withoutGlobalScopes();

        $stats = [
            'total'       => (clone $baseStatsQuery)->count(),
            'verified'    => (clone $baseStatsQuery)->where('verified', true)->count(),
            'new_today'   => (clone $baseStatsQuery)->whereDate('created_at', today())->count(),
            'with_orders' => (clone $baseStatsQuery)->where('order_count', '>', 0)->count(),
        ];

        return view('admin.users.index', compact('cities', 'roles', 'stats', 'users'));
    }

    public function create()
    {
        abort_if(Gate::denies('user_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $roles = Role::pluck('title', 'id');
        $userTypes = UserType::select('id', 'title_ar', 'title_en', 'icon', 'color')->get();

        return view('admin.users.create', compact('cities', 'roles', 'userTypes'));
    }

    public function store(StoreUserRequest $request)
    {
        $userType = UserType::with('roles')->find($request->input('user_type_id'));

        $data = $request->except('roles', 'permissions', 'password_confirmation', 'mobile_country_code');

        if (empty($data['username'])) {
            $data['username'] = User::generateUniqueUsername($data['name'] ?? null);
        }

        if (empty($data['login_code'])) {
            $data['login_code'] = User::generateUniqueLoginCode();
        }

        $data['status'] = array_key_exists('status', $data) ? $data['status'] : 1;
        $data['verified'] = 1;
        $data['verified_at'] = now();
        $data['email_verified_at'] = now();

        if ($userType) {
            $data['user_type'] = $userType->title_en ?: $userType->title_ar;
        }

        $data['user_type'] = $this->normalizeUserType($data['user_type'] ?? null);

        $user = User::create($data);

        $roles = $request->input('roles', []);
        if (empty($roles) && $userType) {
            $roles = $userType->roles->pluck('id')->toArray();
        }

        $customerRoleId = $this->customerRoleId();
        if ($customerRoleId && $this->isCustomerUserType($data['user_type'] ?? null)) {
            $roles[] = $customerRoleId;
            $roles = array_values(array_unique($roles));
        }

        $user->roles()->sync($roles);

        if ($customerRoleId && in_array($customerRoleId, $roles, true) && !$this->isCustomerUserType($user->user_type)) {
            $user->update(['user_type' => 'customer']);
        }

        $this->syncCustomerProfileForCustomerUser($user, $roles);

        return redirect()->route('admin.users.index');
    }

    public function edit(User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $roles = Role::pluck('title', 'id');
        $permissions = Permission::query()->get()->sortBy(function ($permission) {
            $section = (string) ($permission->section_label ?? $permission->section ?? $permission->section_id ?? 'other');
            $title = (string) ($permission->title ?? $permission->label ?? $permission->name ?? '');

            return mb_strtolower($section . '|' . $title);
        });

        $permissionGroups = $permissions->groupBy(function ($permission) {
            return (string) ($permission->section_label ?? $permission->section ?? $permission->section_id ?? 'other');
        });
        $userTypes = UserType::select('id', 'title_ar', 'title_en', 'icon', 'color')->get();
        $user->load('city', 'roles');

        $userPermissionPivotTable = Schema::hasTable('permission_user')
            ? 'permission_user'
            : (Schema::hasTable('user_permissions') ? 'user_permissions' : null);

        $selectedPermissionIds = [];
        if ($userPermissionPivotTable !== null) {
            $selectedPermissionIds = DB::table($userPermissionPivotTable)
                ->where('user_id', $user->id)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $dialCodeList = collect(config('country_dial_codes.list', []))
            ->pluck('dial_code')
            ->unique()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values()
            ->all();

        $mobileParts = ContactValidation::splitDialCodeAndLocalNumber($user->mobile, $dialCodeList, config('country_dial_codes.default', ContactValidation::COUNTRY_CODE));

        $customerProfileRoute = $this->customerProfileRoute($user);

        return view('admin.users.edit', compact('cities', 'roles', 'permissionGroups', 'user', 'userTypes', 'mobileParts', 'customerProfileRoute', 'selectedPermissionIds', 'userPermissionPivotTable'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $userType = null;
        if ($request->filled('user_type_id')) {
            $userType = UserType::find($request->input('user_type_id'));
        }

        $data = $request->except('roles', 'permissions', 'mobile_country_code');

        if (empty($data['username'])) {
            $data['username'] = User::generateUniqueUsername($data['name'] ?? $user->name, $user->id);
        }

        if (empty($data['login_code'])) {
            $data['login_code'] = $user->login_code ?: User::generateUniqueLoginCode();
        }

        if (!array_key_exists('status', $data)) {
            $data['status'] = $user->status ?? 1;
        }

        if ($userType) {
            $data['user_type'] = $userType->title_en ?: $userType->title_ar;
        }

        $data['user_type'] = $this->normalizeUserType($data['user_type'] ?? null);

        $user->update($data);

        $roles = $request->input('roles', []);
        $customerRoleId = $this->customerRoleId();

        if ($customerRoleId && $this->isCustomerUserType($data['user_type'] ?? null)) {
            $roles[] = $customerRoleId;
            $roles = array_values(array_unique($roles));
        }

        $user->roles()->sync($roles);

        if ($customerRoleId && in_array($customerRoleId, $roles, true) && !$this->isCustomerUserType($user->user_type)) {
            $user->update(['user_type' => 'customer']);
        }

        $this->syncCustomerProfileForCustomerUser($user, $roles);

        $userPermissionPivotTable = Schema::hasTable('permission_user')
            ? 'permission_user'
            : (Schema::hasTable('user_permissions') ? 'user_permissions' : null);

        if ($userPermissionPivotTable !== null) {
            DB::table($userPermissionPivotTable)->where('user_id', $user->id)->delete();

            $permissionIds = collect($request->input('permissions', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($permissionIds->isNotEmpty()) {
                $insertRows = $permissionIds->map(fn ($permissionId) => [
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                ])->all();

                DB::table($userPermissionPivotTable)->insert($insertRows);
            }
        }

        return redirect()->route('admin.users.index');
    }

    private function usersExcludingCustomers(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereNull('user_type')
                    ->orWhereNotIn(DB::raw('LOWER(user_type)'), ['customer', 'coustomer']);
            })
            ->whereDoesntHave('roles', function (Builder $roleQuery) {
                $roleQuery->whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer']);
            });
    }

    private function normalizeUserType(?string $userType): ?string
    {
        if ($userType === null) {
            return null;
        }

        $normalized = strtolower(trim($userType));

        if (in_array($normalized, ['customer', 'coustomer'], true)) {
            return 'customer';
        }

        return $userType;
    }

    private function isCustomerUserType(?string $userType): bool
    {
        if ($userType === null) {
            return false;
        }

        return in_array(strtolower(trim($userType)), ['customer', 'coustomer'], true);
    }

    private function customerRoleId(): ?int
    {
        $roleId = Role::whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer'])->value('id');

        return $roleId ? (int) $roleId : null;
    }

    private function isCustomerRoleAssigned(array $roles): bool
    {
        $customerRoleId = $this->customerRoleId();

        return $customerRoleId !== null && in_array($customerRoleId, $roles, true);
    }

    private function syncCustomerProfileForCustomerUser(User $user, array $roles = []): void
    {
        $isCustomerUser = $this->isCustomerUserType($user->user_type) || $this->isCustomerRoleAssigned($roles);

        if (!$isCustomerUser) {
            return;
        }

        $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'customer_code' => CustomerProfile::generateCode(),
                'city_id' => $user->city_id,
                'address_line1' => $user->user_address,
                'account_status' => 'pending',
            ]
        );
    }

    private function customerProfileRoute(User $user): ?string
    {
        $isCustomerUser = $this->isCustomerUserType($user->user_type)
            || $user->roles()->whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer'])->exists();

        if (!$isCustomerUser || !Route::has('admin.customers.show')) {
            return null;
        }

        return route('admin.customers.show', $user->id);
    }

    public function show(User $user)
    {
        abort_if(Gate::denies('user_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $relations = ['city', 'roles', 'permissions'];

        $optionalRelations = [
            'profile' => 'customer_profiles',
            'userBranchSections' => 'branch_sections',
            'userAddresses' => 'addresses',
            'userWalletTitles' => 'wallet_titles',
            'userWalletHistories' => 'wallet_histories',
            'userUserSubscriptions' => 'user_subscriptions',
            'userOrders' => 'orders',
            'userUserAlerts' => 'user_alert_user',
        ];

        foreach ($optionalRelations as $relation => $table) {
            if (Schema::hasTable($table)) {
                $relations[] = $relation;
            }
        }

        $user->load($relations);

        $deletionStatus = (new UserDeletionService())->canDelete($user);

        $customerProfileRoute = $this->customerProfileRoute($user);

        return view('admin.users.show', compact('user', 'deletionStatus', 'customerProfileRoute'));
    }

    public function destroy(User $user)
    {
        abort_if(Gate::denies('user_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $deletion = (new UserDeletionService())->canDelete($user);

        if (!$deletion['allowed']) {
            return back()->withErrors(['delete' => implode(' | ', $deletion['reasons'])]);
        }

        $user->delete();

        return back()->with('message', 'تم حذف المستخدم بنجاح.');
    }

    public function massDestroy(MassDestroyUserRequest $request)
    {
        User::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function block(Request $request, User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $user->update([
            'is_blocked'   => 1,
            'block_reason' => $request->input('reason'),
            'blocked_at'   => now(),
        ]);
        return back()->with('success', 'تم حظر المستخدم بنجاح');
    }

    public function unblock(Request $request, User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $user->update([
            'is_blocked'   => 0,
            'block_reason' => null,
            'blocked_at'   => null,
        ]);
        return back()->with('success', 'تم رفع الحظر عن المستخدم');
    }

    public function freeze(Request $request, User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $user->update([
            'is_frozen'     => 1,
            'freeze_reason' => $request->input('reason'),
            'frozen_at'     => now(),
        ]);
        return back()->with('success', 'تم تجميد الحساب بنجاح');
    }

    public function unfreeze(Request $request, User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $user->update([
            'is_frozen'     => 0,
            'freeze_reason' => null,
            'frozen_at'     => null,
        ]);
        return back()->with('success', 'تم إلغاء تجميد الحساب');
    }

    public function activate(User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->update([
            'status' => 1,
            'verified' => 1,
            'verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'تم تفعيل المستخدم بنجاح.');
    }

    public function deactivate(User $user)
    {
        abort_if(Gate::denies('user_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->update(['status' => 0]);

        return back()->with('success', 'تم تعطيل المستخدم بنجاح.');
    }
}