<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRole extends Model
{
    protected $fillable = ['company_id', 'key', 'name', 'name_ar', 'permissions', 'is_system'];

    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];
}
