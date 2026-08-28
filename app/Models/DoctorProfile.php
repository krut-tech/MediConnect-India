<?php

namespace App\Models;

use App\Casts\PostgresTextArrayCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps to the existing public.doctor_profiles table (Phase 5.2).
 *
 * See the model-addition commit message for the full write-path / RLS
 * audit. Summary: unlike `patients`, this table's RLS (`doctor_profiles_
 * write_own`) permits a signed-in user to INSERT/UPDATE/DELETE their own
 * row (`user_id = auth.uid()`), or a super admin any row. Publicly
 * SELECT-able (subject to `deleted_at IS NULL`) to any authenticated
 * user, same tier as `facilities` per DATABASE_MAPPING.md.
 */
class DoctorProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'doctor_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'qualifications',
        'specialties',
        'years_experience',
        'languages_spoken',
        'registration_number',
    ];

    protected function casts(): array
    {
        return [
            'qualifications' => PostgresTextArrayCast::class,
            'specialties' => PostgresTextArrayCast::class,
            'languages_spoken' => PostgresTextArrayCast::class,
            'years_experience' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
