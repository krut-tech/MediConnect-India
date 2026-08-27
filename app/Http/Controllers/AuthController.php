<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupabaseAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Milestone 1 — Public Auth Foundation.
 *
 * Scope, deliberately: registration creates ONLY auth.users -> public.users
 * (via the already-verified handle_new_auth_user trigger). No role is
 * ever accepted from the registration form, and no patients/
 * staff_assignments row is created here — those are separate, later,
 * approval-gated milestones (Option C, staged).
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, SupabaseAuthService $supabase): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $tokenResponse = $supabase->signInWithPassword($validated['email'], $validated['password']);
        } catch (RuntimeException $e) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => $e->getMessage()]);
        }

        $this->establishSession($request, $supabase, $tokenResponse);

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, SupabaseAuthService $supabase): RedirectResponse
    {
        // Deliberately no 'role' field accepted here, even if present in
        // the raw request — per Milestone 1 rules, public registration
        // must not be able to select any role.
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $signUpResponse = $supabase->signUp(
                $validated['email'],
                $validated['phone'] ?? null,
                $validated['password'],
                $validated['full_name'],
            );
        } catch (RuntimeException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['auth' => $e->getMessage()]);
        }

        // Supabase's own Auth settings (not something this app can see —
        // see the pre-implementation audit) determine whether email
        // confirmation is required. Handle both honestly rather than
        // assuming one.
        if (! empty($signUpResponse['access_token'])) {
            $this->establishSession($request, $supabase, $signUpResponse);

            return redirect()->route('dashboard');
        }

        return redirect()->route('login')
            ->with('status', 'Account created. Please check your email to confirm your address before signing in.');
    }

    public function logout(Request $request, SupabaseAuthService $supabase): RedirectResponse
    {
        $accessToken = $request->session()->get('supabase.access_token');

        if ($accessToken) {
            // Best-effort — local logout must proceed regardless of
            // whether Supabase's own revocation call succeeds.
            try {
                $supabase->signOut($accessToken);
            } catch (\Throwable $e) {
                // Intentionally swallowed: this is a remote revocation
                // call, not the source of truth for "is this browser
                // still logged in" — the local session invalidation
                // below is what actually matters here.
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Verifies the access token, fetches the user's own profile via
     * PostgREST (their own token — RLS-scoped, never a direct DB
     * connection), hydrates a User model instance without touching
     * Eloquent's DB connection, and establishes the Laravel session.
     */
    private function establishSession(Request $request, SupabaseAuthService $supabase, array $tokenResponse): void
    {
        $accessToken = $tokenResponse['access_token'] ?? null;
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresIn = $tokenResponse['expires_in'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Supabase did not return an access token.');
        }

        // Never trust the token just because Supabase's HTTP response
        // said so — verify signature/expiry/audience/issuer ourselves.
        // This is the ONLY place in the codebase a raw Supabase access
        // token is decoded/verified — Phase 5 Step 3's RLS context
        // (App\Services\SupabaseRlsContext) reuses these exact claims
        // via the session cache below rather than re-verifying the
        // token itself.
        $claims = $supabase->verifyAccessToken($accessToken);

        $profile = $supabase->fetchOwnProfile($accessToken, $claims['sub']);

        if (! $profile) {
            throw new RuntimeException('Signed in, but no matching account profile was found.');
        }

        $user = User::make()->forceFill($profile);
        $user->exists = true;
        $user->syncOriginal();

        $request->session()->regenerate();

        Auth::guard('web')->login($user);

        $request->session()->put('supabase.access_token', $accessToken);
        $request->session()->put('supabase.refresh_token', $refreshToken);
        $request->session()->put('supabase.expires_at', $expiresIn ? now()->addSeconds((int) $expiresIn)->timestamp : null);
        // Cached so VerifySupabaseSession can rehydrate the User model on
        // every subsequent request WITHOUT querying Eloquent's direct DB
        // connection — that connection has no way to carry this specific
        // user's auth.uid() context (Option A was not approved), so it
        // must never be used to resolve "who is logged in". The trade-off
        // (documented in MIGRATION_PROGRESS.md) is that profile edits
        // made elsewhere won't reflect here until the token is refreshed
        // or the user logs in again — acceptable for Milestone 1.
        $request->session()->put('supabase.profile', $profile);
        // Phase 5 Step 3: cache only the specific already-verified claims
        // App\Services\SupabaseRlsContext needs to establish a Postgres
        // RLS context (SET LOCAL ROLE authenticated + request.jwt.claims)
        // on the direct `pgsql`/mediconnect_app connection for
        // RLS-protected Eloquent queries (staff_assignments, patients,
        // facility staff assignments). This is the SAME verified-claims
        // array produced by verifyAccessToken() above — never a second,
        // independent JWT verification, and never anything sourced from
        // request input. Cleared on logout/session invalidation below,
        // same as supabase.profile.
        $request->session()->put('supabase.jwt_claims', [
            'sub' => $claims['sub'],
            'role' => $claims['role'] ?? 'authenticated',
            'aud' => $claims['aud'] ?? null,
            'iss' => $claims['iss'] ?? null,
            'exp' => $claims['exp'] ?? null,
        ]);
    }
}
