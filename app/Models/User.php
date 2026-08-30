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

    /**
     * Phase 6 correction — memoizes activeStaffAssignment() for the
     * lifetime of this model instance only (never persisted, never
     * shared across requests/users). Avoids running the same "active
     * assignment" query twice in one request (e.g. sidebar.blade.php
     * and mobile-nav.blade.php both rendering off Auth::user()).
     */
    private bool $activeAssignmentResolved = false;

    private ?StaffAssignment $cachedActiveAssignment = null;

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

    /**
     * Phase 5.2 — additive only. Mirrors patient() above. A row here
     * is optional (self-service doctor_profiles, see DoctorProfile's
     * own docblock) — a null result is expected and normal for any
     * user who hasn't created one, not an error condition.
     */
    public function doctorProfile()
    {
        return $this->hasOne(DoctorProfile::class, 'user_id');
    }

    /**
     * The signed-in user's single resolved "active" staff_assignments
     * row, with its role eager-loaded — or null if they have none.
     *
     * This is the EXACT SAME resolution query as
     * App\Http\Middleware\EnsureUserHasRole and DashboardController
     * (deleted_at is null; valid_until is null or in the future;
     * highest is_primary first) — introduced here only to give
     * Blade views a single, memoized place to read it from, never a
     * second/independent notion of "active assignment". Reading this
     * has no bearing on route authorization or RLS, which remain the
     * only real enforcement (see EnsureUserHasRole's own docblock).
     */
    public function activeStaffAssignment(): ?StaffAssignment
    {
        if (! $this->activeAssignmentResolved) {
            $this->cachedActiveAssignment = $this->staffAssignments()
                ->with('role')
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
                })
                ->orderByDesc('is_primary')
                ->first();

            $this->activeAssignmentResolved = true;
        }

        return $this->cachedActiveAssignment;
    }

    /**
     * Phase 5.1 — nav-visibility helper only. Now backed by
     * activeStaffAssignment() above (same query, memoized) rather than
     * running its own copy of it. Behavior is unchanged: true iff the
     * user has at least one active staff_assignments row, of any role.
     *
     * Hiding a link based on this has no bearing on whether the
     * underlying route allows the request — it is UX only. The actual
     * authorization check remains solely EnsureUserHasRole + the live
     * RLS policies.
     */
    public function hasActiveStaffAssignment(): bool
    {
        return $this->activeStaffAssignment() !== null;
    }

    /**
     * Phase 6 correction — WS1 (role-based navigation). True iff the
     * user's active staff_assignments row resolves to one of the given
     * `roles.code` values. Unlike hasActiveStaffAssignment(), this can
     * tell a doctor apart from a nurse/hospital_admin/etc. — needed
     * because "My Doctor Profile" must only show for an actual doctor,
     * not for "any staff member" (that heuristic is what caused a
     * Hospital Admin account to see it).
     *
     * The codes passed in must be real, already-verified `roles.code`
     * values (queried live from Supabase before use), never guessed —
     * same discipline EnsureUserHasRole already requires of its own
     * `role:...` route-middleware parameters. This method does not
     * itself invent or hardcode which codes are valid.
     */
    public function hasActiveRole(string ...$codes): bool
    {
        $code = $this->activeStaffAssignment()?->role?->code;

        return $code !== null && in_array($code, $codes, true);
    }

    /**
     * Phase 6 correction — WS2 (booking permissions). True for any
     * administrative role that should use the staff-facing "create an
     * appointment for a patient" workflow instead of the patient
     * self-booking form on a doctor's detail page: hospital_admin
     * (facility-tier) or any platform-tier role
     * (`roles.is_platform_role`, e.g. super_admin/national_admin/
     * state_admin/district_admin/city_admin). Driven by the verified
     * `is_platform_role` boolean plus one specific, verified role code
     * — not a guess, and not every staff role (a nurse or receptionist
     * booking a walk-in patient is a legitimate, different case, out of
     * scope for this fix — see PHASE 6 — CORRECTION REPORT).
     */
    public function isAdministrator(): bool
    {
        $assignment = $this->activeStaffAssignment();

        if (! $assignment || ! $assignment->role) {
            return false;
        }

        return $assignment->role->code === 'hospital_admin' || $assignment->role->is_platform_role === true;
    }
}
