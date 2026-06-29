<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'roles';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'title',             // الاسم الأساسي المتوافق مع الشاشات الحالية
        'name',              // اسم الدور بالعربية (مثل: مدير النظام)
        'display_name',      // اسم معروض
        'label',             // وصف قصير
        'slug',              // معرف نصي إنجليزي (مثل: admin, customer, employee)
        'user_type_value',   // قيمة user_type المرتبطة (1,2,3,4,5)
        'is_default',        // هل هذا هو الدور الافتراضي (0 أو 1)
        'is_system',         // هل هو دور أساسي في النظام
        'description',       // وصف الدور
        'guard_name',        // اسم الـ Guard (افتراضي: web)
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_system' => 'boolean',
    ];

    // ============== العلاقات ==============

    /**
     * علاقة أنواع المستخدمين المرتبطة بالدور
     */
    public function userTypes()
    {
        return $this->belongsToMany(UserType::class, 'role_user_type', 'role_id', 'user_type_id');
    }

    /**
     * علاقة الصلاحيات
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id');
    }

    /**
     * علاقة المستخدمين
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role', 'role_id', 'user_id');
    }

    // ============== دوال الحصول على الدور ==============

    /**
     * الحصول على الدور المناسب بناءً على user_type
     */
    public static function getRoleByUserType($userType)
    {
        // البحث عن دور مرتبط بنوع المستخدم هذا
        $role = self::where('user_type_value', $userType)->first();
        
        // إذا لم يوجد، ارجع الدور الافتراضي
        if (!$role) {
            $role = self::where('is_default', true)->first();
        }
        
        // إذا لم يوجد دور افتراضي، خذ أول دور
        if (!$role) {
            $role = self::first();
        }
        
        return $role;
    }

    /**
     * الحصول على الدور بواسطة slug
     */
    public static function getBySlug($slug)
    {
        return self::where('slug', $slug)->orWhere('name', $slug)->first();
    }

    // ============== دوال الصلاحيات ==============

    /**
     * التحقق من وجود صلاحية معينة للدور
     */
    public function hasPermission($permissionName)
    {
        return $this->permissions()->where(function ($query) use ($permissionName) {
            $query->where('name', $permissionName)
                  ->orWhere('slug', $permissionName);
        })->exists();
    }

    /**
     * التحقق من وجود أي صلاحية من القائمة
     */
    public function hasAnyPermission(array $permissionNames)
    {
        foreach ($permissionNames as $permissionName) {
            if ($this->hasPermission($permissionName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * إضافة صلاحية للدور
     */
    public function givePermissionTo($permissionName)
    {
        $permission = Permission::where('name', $permissionName)
                               ->orWhere('slug', $permissionName)
                               ->first();
        if ($permission && !$this->hasPermission($permissionName)) {
            $this->permissions()->attach($permission->id);
        }
        return $this;
    }

    /**
     * إضافة صلاحيات متعددة للدور
     */
    public function givePermissionsTo(array $permissionNames)
    {
        foreach ($permissionNames as $permissionName) {
            $this->givePermissionTo($permissionName);
        }
        return $this;
    }

    /**
     * سحب صلاحية من الدور
     */
    public function revokePermissionTo($permissionName)
    {
        $permission = Permission::where('name', $permissionName)
                               ->orWhere('slug', $permissionName)
                               ->first();
        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
        return $this;
    }

    /**
     * سحب صلاحيات متعددة من الدور
     */
    public function revokePermissionsTo(array $permissionNames)
    {
        foreach ($permissionNames as $permissionName) {
            $this->revokePermissionTo($permissionName);
        }
        return $this;
    }

    /**
     * مزامنة الصلاحيات (حذف القديم وإضافة الجديد)
     */
    public function syncPermissions(array $permissionNames)
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)
                                  ->orWhereIn('slug', $permissionNames)
                                  ->pluck('id')
                                  ->toArray();
        $this->permissions()->sync($permissionIds);
        return $this;
    }

    /**
     * الحصول على جميع أسماء الصلاحيات
     */
    public function getPermissionNamesAttribute()
    {
        return $this->permissions->pluck('name')->toArray();
    }

    /**
     * الحصول على جميع أسماء الصلاحيات كـ string
     */
    public function getPermissionsStringAttribute()
    {
        return implode(', ', $this->permission_names);
    }

    // ============== دوال مساعدة ==============

    /**
     * الحصول على الاسم المعروض
     */
    public function getDisplayNameAttribute()
    {
        return $this->display_name ?? $this->title ?? $this->name ?? $this->label ?? $this->slug ?? '';
    }

    /**
     * التحقق من أن الدور أساسي
     */
    public function isSystem()
    {
        return (bool) $this->is_system;
    }

    /**
     * التحقق من أن الدور افتراضي
     */
    public function isDefault()
    {
        return (bool) $this->is_default;
    }

    // ============== الأحداث ==============

    protected static function booted()
    {
        static::saving(function (self $role) {
            // إنشاء slug إذا لم يكن موجوداً
            if (blank($role->slug) && filled($role->name)) {
                $role->slug = \Illuminate\Support\Str::slug($role->name, '_');
            }
        });

        static::deleting(function (self $role) {
            // منع حذف الأدوار الأساسية
            if ($role->is_system) {
                throw new \Exception('لا يمكن حذف دور أساسي في النظام');
            }
        });
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}