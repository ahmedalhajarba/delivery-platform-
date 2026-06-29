<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Permission extends Model
{
    use SoftDeletes;
    use HasFactory;

    public $table = 'permissions';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const ACTION_LABELS = [
        'access' => 'الدخول',
        'create' => 'إضافة',
        'edit' => 'تعديل',
        'show' => 'عرض',
        'delete' => 'حذف',
        'print' => 'طباعة',
        'view' => 'عرض',
        'manage' => 'إدارة',
        'approve' => 'موافقة',
        'export' => 'تصدير',
        'import' => 'استيراد',
    ];

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'label',
        'section_id',
        'module',
        'action',
        'action_key',
        'title',
        'description',
        'guard_name',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============== العلاقات ==============

    /**
     * العلاقة مع قسم الصلاحية
     */
    public function section()
    {
        return $this->belongsTo(PermissionSection::class, 'section_id');
    }

    /**
     * العلاقة مع الأدوار
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    // ============== دوال مساعدة ==============

    /**
     * الحصول على اسم الصلاحية المعروض
     */
    public function getDisplayNameAttribute()
    {
        // التحقق من وجود العمود display_name
        if (isset($this->attributes['display_name']) && $this->attributes['display_name']) {
            return $this->attributes['display_name'];
        }

        // استخدام label أو name أو title
        if (isset($this->label) && $this->label) {
            return $this->label;
        }

        if (isset($this->name) && $this->name) {
            return $this->name;
        }

        if (isset($this->title) && $this->title) {
            return $this->title;
        }

        // استخدام section_label و action_label
        $sectionLabel = $this->section_label ?? '';
        $actionLabel = $this->action_label ?? '';

        if (filled($sectionLabel) && filled($actionLabel)) {
            return static::sanitizeUiLabel($sectionLabel . ' - ' . $actionLabel);
        }

        if (filled($sectionLabel)) {
            return static::sanitizeUiLabel($sectionLabel);
        }

        return static::sanitizeUiLabel((string) Str::of((string) ($this->name ?? $this->title ?? ''))->replace('_', ' '));
    }

    /**
     * الحصول على تسمية القسم
     */
    public function getSectionLabelAttribute(): string
    {
        $relationSection = $this->getRelationValue('section');

        $sectionName = '';
        $sectionLabel = '';

        if ($relationSection instanceof PermissionSection) {
            $sectionName = (string) ($relationSection->name ?? '');
            $sectionLabel = (string) ($relationSection->label ?? '');
        }

        if ($sectionName === '' || $sectionLabel === '') {
            $rawSection = (string) ($this->attributes['section'] ?? '');
            if ($rawSection !== '') {
                $sectionName = $sectionName !== '' ? $sectionName : $rawSection;
            }
        }

        if ($sectionName === '') {
            [$parsedSection] = static::parseTitle($this->name ?? $this->title ?? '');
            $sectionName = (string) $parsedSection;
        }

        $normalizedSectionKey = strtolower(trim(str_replace(['-', ' '], '_', $sectionName)));

        if (filled($sectionLabel)) {
            return static::sanitizeUiLabel($sectionLabel);
        }

        $mapped = config('permission_sections.arabic_sections.' . $normalizedSectionKey)
            ?? config('permission_sections.arabic_sections.' . $sectionName);
        if (filled($mapped)) {
            return static::sanitizeUiLabel($mapped);
        }

        return static::sanitizeUiLabel(str_replace('_', ' ', (string) ($normalizedSectionKey !== '' ? $normalizedSectionKey : $sectionName)));
    }

    /**
     * الحصول على تسمية الإجراء
     */
    public function getActionLabelAttribute(): string
    {
        $actionKey = $this->action_key ?? $this->action ?? null;
        if (blank($actionKey)) {
            [, $actionKey] = static::parseTitle($this->name ?? $this->title ?? '');
        }

        return self::ACTION_LABELS[$actionKey] ?? (string) $actionKey;
    }

    /**
     * الحصول على تسمية الوحدة (Module)
     */
    public function getModuleLabelAttribute()
    {
        $modules = [
            'dashboard' => 'لوحة التحكم',
            'users' => 'المستخدمين',
            'roles' => 'الأدوار',
            'permissions' => 'الصلاحيات',
            'orders' => 'الطلبات',
            'finance' => 'المالية',
            'hr' => 'الموارد البشرية',
            'settings' => 'الإعدادات',
            'reports' => 'التقارير',
            'customers' => 'العملاء',
            'couriers' => 'المناديب',
            'branches' => 'الفروع',
            'subscriptions' => 'الاشتراكات',
            'sales' => 'المبيعات',
            'marketing' => 'التسويق',
            'contracts' => 'العقود',
            'invoices' => 'الفواتير',
        ];
        return $modules[$this->module] ?? $this->module ?? 'أخرى';
    }

    /**
     * الحصول على لون الإجراء
     */
    public function getActionColorAttribute()
    {
        $colors = [
            'view' => 'info',
            'create' => 'success',
            'edit' => 'warning',
            'delete' => 'danger',
            'approve' => 'primary',
            'manage' => 'dark',
            'export' => 'secondary',
            'import' => 'secondary',
            'print' => 'secondary',
            'access' => 'info',
            'show' => 'info',
        ];
        return $colors[$this->action ?? 'view'] ?? 'secondary';
    }

    // ============== دوال التحليل ==============

    public static function parseTitle(?string $title): array
    {
        $normalized = trim((string) $title);
        if ($normalized === '') {
            return ['', null];
        }

        $parts = explode('_', $normalized);
        $action = end($parts);
        if (array_key_exists($action, self::ACTION_LABELS) && count($parts) > 1) {
            array_pop($parts);
            return [implode('_', $parts), $action];
        }

        return [$normalized, null];
    }

    public static function actionOptions(): array
    {
        return self::ACTION_LABELS;
    }

    public static function domainLabels(): array
    {
        return config('permission_domains.labels', []);
    }

    public static function sanitizeUiLabel(?string $value): string
    {
        $label = trim(strip_tags((string) $value));
        $label = preg_replace('/\bbi-[a-z0-9-]+\b/ui', '', $label);
        $label = preg_replace('/\s*[|:]+\s*/u', ' ', $label);
        $label = preg_replace('/\s{2,}/u', ' ', $label);

        return trim($label, " \t\n\r\0\x0B-_");
    }

    public static function resolveDomainKeyForSection(?string $section): string
    {
        $section = strtolower(trim((string) $section));
        if ($section === '') {
            return 'other';
        }

        $exactMap = config('permission_domains.section_domain_map', []);
        if (array_key_exists($section, $exactMap)) {
            return $exactMap[$section];
        }

        $prefixMap = config('permission_domains.prefix_domain_map', []);
        foreach ($prefixMap as $prefix => $domainKey) {
            if (Str::startsWith($section, $prefix)) {
                return $domainKey;
            }
        }

        return 'other';
    }

    public static function resolveDomainLabelForSection(?string $section): string
    {
        $key = self::resolveDomainKeyForSection($section);
        $labels = self::domainLabels();

        return $labels[$key] ?? ($labels['other'] ?? 'اقسام اخرى');
    }

    public function getDomainKeyAttribute(): string
    {
        return self::resolveDomainKeyForSection($this->section);
    }

    public function getDomainLabelAttribute(): string
    {
        return self::resolveDomainLabelForSection($this->section);
    }

    // ============== الأحداث ==============

    protected static function booted()
    {
        static::saving(function (self $permission) {
            // إنشاء slug إذا لم يكن موجوداً
            if (blank($permission->slug) && filled($permission->name)) {
                $permission->slug = Str::slug($permission->name, '_');
            }

            // إنشاء name إذا لم يكن موجوداً
            if (blank($permission->name) && filled($permission->section) && filled($permission->action_key)) {
                $permission->name = strtolower(trim($permission->section . '_' . $permission->action_key));
            }

            if ((blank($permission->section) || blank($permission->action_key)) && filled($permission->name)) {
                [$section, $action] = static::parseTitle($permission->name);
                $permission->section = $permission->section ?: $section;
                $permission->action_key = $permission->action_key ?: $action;
                $permission->action = $permission->action ?: $action;
            }
        });
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}