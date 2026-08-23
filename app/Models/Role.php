<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.roles table — a fixed vocabulary of the
 * 20 PRD roles, stored as data rather than hardcoded strings (per the
 * table's own comment in Supabase). Read-mostly.
 */
class Role extends Model
{
    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'label',
        'is_platform_role',
    ];

    protected function casts(): array
    {
        return [
            'is_platform_role' => 'boolean',
        ];
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class, 'role_id');
    }
}
