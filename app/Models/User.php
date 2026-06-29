<?php

namespace App\Models;

use \DateTimeInterface;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use SoftDeletes, Notifiable, HasFactory;

    public $table = 'users';

    protected $hidden = [
        'remember_token',
        'password',
    ];

    protected $dates = [
        'verified_at',
        'email_verified_at',
        'blocked_at',
        'frozen_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'name',
        'last_name',
        'username',
        'login_code',
        'mobile',
        'city_id',
        'verified',
        'verified_at',
        'verification_token',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'order_count',
        'user_address',
        'user_type',
        'user_type_id',
        'status',
        'is_blocked',
        'is_frozen',
        'block_reason',
        'freeze_reason',
        'blocked_at',
        'frozen_at',
        'employee_status',
        'employee_position',
        'is_admin',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============== العلاقات ==============
    
    /**
     * العلاقة مع الأدوار
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user', 'user_id', 'permission_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function userOrders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function profile()
    {
        return $this->hasOne(CustomerProfile::class, 'user_id', 'id');
    }

    public function senderOrders()
    {
        return $this->hasMany(Order::class, 'sender_id', 'id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function userCompanies()
    {
        return $this->hasMany(Company::class, 'user_id', 'id');
    }

    public function userBranchSections()
    {
        return $this->hasMany(BranchSection::class, 'user_id', 'id');
    }

    public function userAddresses()
    {
        return $this->hasMany(Address::class, 'user_id', 'id');
    }

    public function userWalletTitles()
    {
        return $this->hasMany(WalletTitle::class, 'user_id', 'id');
    }

    public function userWalletHistories()
    {
        return $this->hasMany(WalletHistory::class, 'user_id', 'id');
    }

    public function userUserSubscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'user_id', 'id');
    }

    public function userUserAlerts()
    {
        return $this->belongsToMany(UserAlert::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // ============== دوال الصلاحيات والأدوار ==============

    /**
     * مزامنة الدور بناءً على user_type من قاعدة البيانات
     */
    public function syncRoleBasedOnUserType()
    {
        $role = Role::getRoleByUserType($this->user_type);
        
        if (!$role) {
            return false;
        }
        
        // التحقق إذا كان المستخدم لديه هذا الدور بالفعل
        $hasRole = $this->roles()->where('role_id', $role->id)->exists();
        
        if (!$hasRole) {
            // حذف الأدوار القديمة وإضافة الدور الجديد
            $this->roles()->sync([$role->id]);
            return true;
        }
        
        return false;
    }

    /**
     * الحصول على جميع الصلاحيات للمستخدم
     */
    public function getAllPermissionsAttribute()
    {
        // إذا كان المستخدم أدمن، يحصل على كل الصلاحيات
        if ($this->is_admin || $this->hasRole('admin')) {
            return Permission::all();
        }
        
        $permissions = collect();

        if ($this->relationLoaded('permissions')) {
            $permissions = $permissions->merge($this->permissions);
        } else {
            $permissions = $permissions->merge($this->permissions()->get());
        }

        foreach ($this->roles as $role) {
            if ($role->permissions) {
                $permissions = $permissions->merge($role->permissions);
            }
        }
        return $permissions->unique('id');
    }

    /**
     * الحصول على أسماء جميع الصلاحيات
     */
    public function getPermissionNamesAttribute()
    {
        return $this->all_permissions->pluck('name')->toArray();
    }

    /**
     * التحقق من وجود صلاحية معينة
     * @param string $permissionName - اسم الصلاحية (مثل: view_users)
     */
    public function hasPermission($permissionName)
    {
        // إذا كان المستخدم أدمن، لديه كل الصلاحيات
        if ($this->is_admin || $this->hasRole('admin')) {
            return true;
        }
        
        // التحقق من الصلاحية المباشرة عبر الدور
        return $this->all_permissions->contains(function ($permission) use ($permissionName) {
            return $permission->name === $permissionName || $permission->slug === $permissionName;
        });
    }

    /**
     * التحقق من وجود صلاحية معينة (Alias متوافق مع Laravel)
     * @param string|array $abilities
     * @param array $arguments
     */
    public function can($abilities, $arguments = [])
    {
        // إذا كانت قيمة نصية واحدة
        if (is_string($abilities)) {
            return $this->hasPermission($abilities);
        }
        
        // إذا كانت مصفوفة من الصلاحيات
        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if ($this->hasPermission($ability)) {
                    return true;
                }
            }
            return false;
        }
        
        return false;
    }

    /**
     * التحقق من وجود دور معين
     * @param string $roleName - اسم الدور (مثل: admin, manager)
     */
    public function hasRole($roleName)
    {
        return $this->roles->contains(function ($role) use ($roleName) {
            return $role->name === $roleName || $role->slug === $roleName;
        });
    }

    /**
     * التحقق من وجود أي دور من قائمة
     */
    public function hasAnyRole(array $roleNames)
    {
        foreach ($roleNames as $roleName) {
            if ($this->hasRole($roleName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * إضافة دور للمستخدم
     */
    public function assignRole($roleName)
    {
        $role = Role::where('name', $roleName)->orWhere('slug', $roleName)->first();
        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
            return true;
        }
        return false;
    }

    /**
     * إزالة دور من المستخدم
     */
    public function removeRole($roleName)
    {
        $role = Role::where('name', $roleName)->orWhere('slug', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role->id);
            return true;
        }
        return false;
    }

    /**
     * مزامنة الأدوار (استبدال جميع الأدوار)
     */
    public function syncRoles(array $roleNames)
    {
        $roleIds = Role::whereIn('name', $roleNames)->orWhereIn('slug', $roleNames)->pluck('id')->toArray();
        $this->roles()->sync($roleIds);
        return $this;
    }

    /**
     * الحصول على الدور الرئيسي
     */
    public function getMainRoleAttribute()
    {
        return $this->roles()->first();
    }

    /**
     * الحصول على أسماء الأدوار
     */
    public function getRoleNamesAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * الحصول على أسماء الأدوار كـ string
     */
    public function getRolesStringAttribute()
    {
        return implode(', ', $this->role_names);
    }

    // ============== التحقق من أنواع المستخدمين ==============

    /**
     * التحقق من أن المستخدم أدمن
     */
    public function getIsAdminAttribute()
    {
        // طريقة 1: التحقق من slug
        if ($this->hasRole('admin')) {
            return true;
        }
        
        // طريقة 2: التحقق من user_type = 1
        if ((int)$this->user_type === 1) {
            return true;
        }
        
        // طريقة 3: التحقق من وجود دور admin في قاعدة البيانات
        $adminRole = Role::where('slug', 'admin')->orWhere('name', 'admin')->first();
        if ($adminRole && $this->roles->contains('id', $adminRole->id)) {
            return true;
        }
        
        return false;
    }

    /**
     * التحقق من أن المستخدم مدير
     */
    public function isManager()
    {
        return $this->hasRole('manager');
    }

    /**
     * التحقق من أن المستخدم موظف
     */
    public function isEmployee()
    {
        return $this->hasRole('employee');
    }

    /**
     * التحقق من أن المستخدم مندوب
     */
    public function isCourier()
    {
        return $this->hasRole('courier');
    }

    /**
     * التحقق من أن المستخدم عميل
     */
    public function isCustomer()
    {
        return $this->hasRole('customer') || $this->user_type === 'customer';
    }

    /**
     * التحقق من أن المستخدم نشط
     */
    public function isActive()
    {
        return $this->status === 'active' || $this->employee_status === 'active';
    }

    // ============== دوال المزامنة الجماعية ==============

    /**
     * مزامنة جميع المستخدمين
     */
    public static function syncAllUsersRoles()
    {
        $users = self::all();
        $updatedCount = 0;
        
        foreach ($users as $user) {
            if ($user->syncRoleBasedOnUserType()) {
                $updatedCount++;
            }
        }
        
        return $updatedCount;
    }

    /**
     * مزامنة مستخدمين حسب النوع
     */
    public static function syncUsersRolesByUserType($userType)
    {
        $users = self::where('user_type', $userType)->get();
        $updatedCount = 0;
        
        foreach ($users as $user) {
            if ($user->syncRoleBasedOnUserType()) {
                $updatedCount++;
            }
        }
        
        return $updatedCount;
    }

    // ============== الأحداث (Events) ==============

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // تسجيل الـ Observer
        static::observe(new \App\Observers\UserActionObserver());
        
        // حدث الإنشاء
        static::created(function (User $user) {
            $updates = [];

            if (empty($user->username)) {
                $updates['username'] = self::generateUniqueUsername($user->name, $user->id);
            }

            if (empty($user->login_code)) {
                $updates['login_code'] = self::generateUniqueLoginCode();
            }

            if (!$user->verified) {
                $updates['verified'] = 1;
                $updates['verified_at'] = now();
            }

            if (empty($user->email_verified_at)) {
                $updates['email_verified_at'] = now();
            }

            if (array_key_exists('status', $user->getAttributes()) && ($user->status === null || $user->status === '')) {
                $updates['status'] = 1;
            }

            if (!empty($updates)) {
                $user->fill($updates);
                $user->saveQuietly();
            }
            
            // مزامنة الدور
            $user->syncRoleBasedOnUserType();
            
            // تم تعطيل إشعارات التحقق بالبريد لأن التفعيل يتم من طرف الإدارة.
        });
        
        // حدث التحديث
        static::updating(function (User $user) {
            if ($user->isDirty('user_type')) {
                $user->syncRoleBasedOnUserType();
            }
        });
        
        // حدث الاستعادة
        static::restored(function (User $user) {
            $user->syncRoleBasedOnUserType();
        });
    }

    // ============== دوال مساعدة ==============

    public function getVerifiedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format(config('panel.date_format') . ' ' . config('panel.time_format'));
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function setVerifiedAtAttribute($value)
    {
        $this->attributes['verified_at'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }

    public function getEmailVerifiedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format(config('panel.date_format') . ' ' . config('panel.time_format'));
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public static function generateUniqueLoginCode(): string
    {
        do {
            $digits = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $code = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3);
        } while (self::where('login_code', $code)->exists());

        return $code;
    }

    public static function generateUniqueUsername(?string $name = null, ?int $id = null): string
    {
        $base = Str::slug((string) $name, '_');
        if ($base === '') {
            $base = 'user';
        }

        if ($id) {
            $base .= '_' . $id;
        }

        $candidate = $base;
        $suffix = 1;
        while (self::where('username', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function setEmailVerifiedAtAttribute($value)
    {
        $this->attributes['email_verified_at'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }

    public function setPasswordAttribute($input)
    {
        if ($input) {
            $this->attributes['password'] = app('hash')->needsRehash($input) ? Hash::make($input) : $input;
        }
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}