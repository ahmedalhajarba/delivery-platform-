<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerNote extends Model
{
    use SoftDeletes;

    public $table = 'customer_notes';

    const TYPES = [
        'general'    => 'عام',
        'financial'  => 'مالي',
        'complaint'  => 'شكوى',
        'follow_up'  => 'متابعة',
        'important'  => 'مهم',
    ];

    const TYPE_COLORS = [
        'general'    => 'secondary',
        'financial'  => 'primary',
        'complaint'  => 'danger',
        'follow_up'  => 'warning',
        'important'  => 'dark',
    ];

    protected $fillable = [
        'user_id', 'author_id', 'type', 'body', 'pinned',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'secondary';
    }
}
