<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionSection extends Model
{
    protected $table = 'permission_sections';
    protected $fillable = ['name', 'label'];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'section_id');
    }
}
