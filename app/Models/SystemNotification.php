<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    protected $table = 'system_notifications';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'icon',
        'link',
        'link_text',
        'module',
        'module_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /* ── Relations ─────────────────────────────────────────────── */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── Scopes ────────────────────────────────────────────────── */
    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function scopeForUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────────────────── */
    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /* ── Type badge colour map ─────────────────────────────────── */
    public static function typeColorMap(): array
    {
        return [
            'info'    => 'primary',
            'success' => 'success',
            'warning' => 'warning',
            'danger'  => 'danger',
        ];
    }

    /* ── Default icon per type ─────────────────────────────────── */
    public static function typeIconMap(): array
    {
        return [
            'info'    => 'bi-info-circle-fill',
            'success' => 'bi-check-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'danger'  => 'bi-x-circle-fill',
        ];
    }

    public function badgeColor(): string
    {
        return self::typeColorMap()[$this->type] ?? 'primary';
    }

    public function displayIcon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }
        return self::typeIconMap()[$this->type] ?? 'bi-bell-fill';
    }
}
