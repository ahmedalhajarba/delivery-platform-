<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletTitle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wallet_titles';

    // Keep model flexible because schema differs across environments.
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function histories()
    {
        return $this->hasMany(WalletHistory::class, 'wallet_id', 'id');
    }
}
