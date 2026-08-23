<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Maps to the existing public.users table (verified via Supabase audit —
 * see DATABASE_MAPPING.md). Columns below match the live schema exactly;
 * none were invented. 1:1 with auth.users (Supabase Auth) via `id`.
 */
class User extends Authenticatable
{
    use HasUuids, SoftDeletes;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'full_name',
        'phone',
        'email',
        'preferred_language',
        'is_active',
        'abha_id',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class, 'user_id');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }
}
