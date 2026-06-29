<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerActivityLog extends Model
{
    public $table = 'customer_activity_logs';
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'performed_by', 'action', 'meta', 'ip_address', 'note',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public static function log(int $userId, string $action, array $meta = [], string $note = ''): void
    {
        static::create([
            'user_id'      => $userId,
            'performed_by' => auth()->id(),
            'action'       => $action,
            'meta'         => $meta ?: null,
            'ip_address'   => request()->ip(),
            'note'         => $note ?: null,
        ]);
    }
}
