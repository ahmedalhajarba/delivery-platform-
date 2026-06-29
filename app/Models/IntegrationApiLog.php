<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationApiLog extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'request_method',
        'request_path',
        'request_payload',
        'response_status',
        'response_body',
        'duration_ms',
        'ip_address',
    ];

    protected $casts = [
        'request_payload' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationApiClient::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
