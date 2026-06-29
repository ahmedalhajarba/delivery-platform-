<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationApiClient extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'key_hash',
        'status',
        'allowed_scopes',
        'last_used_at',
        'last_ip',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allowed_scopes' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationApiLog::class, 'client_id');
    }
}
