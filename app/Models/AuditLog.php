<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    use Prunable;

    protected static $existingColumns;

    public $table = 'audit_logs';

    protected $fillable = [
        'event_uuid',
        'action',
        'description',
        'subject_id',
        'subject_type',
        'user_id',
        'properties',
        'host',
        'request_method',
        'request_url',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'collection',
    ];

    public function prunable()
    {
        $days = (int) config('security.audit.retention_days', 365);

        return static::query()->where('created_at', '<', now()->subDays(max(1, $days)));
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function createSafely(array $attributes): self
    {
        $columns = self::existingColumns();
        $filtered = array_intersect_key($attributes, array_flip($columns));

        return static::query()->create($filtered);
    }

    public static function hasColumn(string $column): bool
    {
        return in_array($column, self::existingColumns(), true);
    }

    private static function existingColumns(): array
    {
        if (is_array(self::$existingColumns)) {
            return self::$existingColumns;
        }

        self::$existingColumns = Schema::getColumnListing((new static())->getTable());

        return self::$existingColumns;
    }
}
