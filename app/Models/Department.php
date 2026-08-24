<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.departments table. Columns verified live
 * against Supabase (information_schema) before writing this model:
 * id, facility_id, name, created_at, deleted_at — there is no
 * updated_at column, so UPDATED_AT is disabled rather than assumed.
 */
class Department extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'departments';

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'facility_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }
}
