<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.services_catalog table. Columns verified
 * live against Supabase: id, code, name — no timestamp columns exist,
 * so $timestamps is disabled rather than assumed.
 */
class Service extends Model
{
    use HasUuids;

    protected $table = 'services_catalog';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];
}
