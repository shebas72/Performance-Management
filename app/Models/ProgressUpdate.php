<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressUpdate extends Model
{
    protected $fillable = ['company_id', 'subject_type', 'subject_id', 'year', 'month', 'completion_pct', 'note', 'source', 'updated_by'];

    protected $casts = ['completion_pct' => 'decimal:2'];
}
