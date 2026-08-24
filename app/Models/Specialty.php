<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.specialties catalog table. Columns
 * verified live against Supabase: id, code, name — no timestamp
 * columns exist on this table, so $timestamps is disabled rather
 * than assumed.
 */
class Specialty extends Model
{
    use HasUuids;

    protected $table = 'specialties';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];
}
