<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = ['company_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at',
        'access_scope', 'manager_id', 'department_ids', 'access_department_ids',
    ];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime',
        'department_ids' => 'array', 'access_department_ids' => 'array',
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($q)
    {
        return $q->whereNull('accepted_at')->whereNull('revoked_at');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
