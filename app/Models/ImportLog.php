<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'filename',
        'imported_by',
        'total_rows',
        'success_count',
        'error_count',
        'order_ids',
        'errors',
        'status',
    ];

    protected $casts = [
        'order_ids' => 'array',
        'errors'    => 'array',
    ];

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function orders()
    {
        return Order::whereIn('id', $this->order_ids ?? [])->with([
            'sender', 'recipient', 'originBranch',
        ]);
    }
}
