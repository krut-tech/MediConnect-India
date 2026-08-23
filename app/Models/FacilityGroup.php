<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.facility_groups table — hospital-chain
 * parent entity. A standalone clinic has no row here (per the table's
 * own comment in Supabase).
 */
class FacilityGroup extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'facility_groups';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function facilities()
    {
        return $this->hasMany(Facility::class, 'facility_group_id');
    }
}
