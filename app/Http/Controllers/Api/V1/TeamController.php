<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * A tenant's team: users, their roles, and invitations. Everything except the two public invitation endpoints is admin-only.
 * Add frontend_url to config/app.php: 'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173').
 */
class TeamController extends Controller
{
    use ResolvesTenant;

    private const ROLES = ['admin', 'manager', 'viewer'];
    private const INVITE_DAYS = 7;

    /* ---- users ---- */

    public function users(Request $request)
    {
        $cid = $this->requireAdmin($request);
        $users = User::where('company_id', $cid)->where('is_super_admin', false)->with('roles:id,name')->orderBy('name')->get();

        return response()->json([
            'data' => $users->map(fn ($u) => $this->shapeUser($u))->values(),
            'meta' => ['roles' => self::ROLES, 'me' => $request->user()->id],
        ]);
    }

    public function updateUser(Request $request, int $id)
    {
        $cid  = $this->requireAdmin($request);
        $user = User::where('company_id', $cid)->where('is_super_admin', false)->findOrFail($id);
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(self::ROLES)], 'is_active' => ['sometimes', 'boolean'], 'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);
        abort_if(isset($data['password']) && $user->id === $request->user()->id, 422, 'Change your own password from your profile.');

        $losesAdmin = $user->hasRole('admin') && ((isset($data['role']) && $data['role'] !== 'admin') || (isset($data['is_active']) && ! $data['is_active']));
        if ($user->id === $request->user()->id && (isset($data['role']) || isset($data['is_active']))) {
            abort(422, 'You cannot change your own role or deactivate your own account.');
        }
        if ($losesAdmin) {
            $others = User::where('company_id', $cid)->where('is_active', true)->where('id', '!=', $user->id)->role('admin')->count();
            abort_if($others < 1, 422, 'The company must keep at least one active admin.');
        }

        DB::transaction(function () use ($user, $data) {
            if (isset($data['name'])) $user->forceFill(['name' => $data['name']]);
            if (isset($data['email'])) $user->forceFill(['email' => Str::lower($data['email'])]);
            if (isset($data['password'])) $user->forceFill(['password' => Hash::make($data['password'])]);
            if (isset($data['is_active'])) $user->forceFill(['is_active' => $data['is_active']]);
            $user->save();
            if (isset($data['role'])) {
                Role::findOrCreate($data['role']);
                $user->syncRoles([$data['role']]);
            }
            if (isset($data['password']) || (isset($data['is_active']) && ! $data['is_active'])) {
                $user->tokens()->delete(); // signs them out everywhere
            }
        });

        return response()->json(['data' => $this->shapeUser($user->fresh('roles'))]);
    }

    /**
     * Admin adds a user directly (no invitation). With no password given, a temporary one is generated and returned once.
     * Plan limits on the number of users would be checked here.
     */
    public function createUser(Request $request)
    {
        $cid  = $this->requireAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)], 'password' => ['nullable', 'string', 'min:8'],
        ]);
        $plain = $data['password'] ?? Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($cid, $data, $plain) {
            $user = new User();
            $user->forceFill([
                'company_id' => $cid, 'name' => $data['name'], 'email' => Str::lower($data['email']),
                'password' => Hash::make($plain), 'email_verified_at' => now(), 'is_active' => true,
            ])->save();
            Role::findOrCreate($data['role']);
            $user->assignRole($data['role']);

            return $user;
        });

        return response()->json(['data' => $this->shapeUser($user->load('roles')) + ['temporary_password' => isset($data['password']) ? null : $plain]], 201);
    }

    /* ---- invitations ---- */

    public function invitations(Request $request)
    {
        $cid = $this->requireAdmin($request);
        $rows = Invitation::with('inviter:id,name')->where('company_id', $cid)->pending()->latest('id')->get();

        return response()->json(['data' => $rows->map(fn ($i) => $this->shapeInvitation($i))->values()]);
    }

    public function invite(Request $request)
    {
        $cid  = $this->requireAdmin($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(self::ROLES)]]);
        $email = Str::lower($data['email']);

        abort_if(User::where('email', $email)->exists(), 422, 'This email address is already registered.');
        abort_if(Invitation::where('company_id', $cid)->where('email', $email)->pending()->where('expires_at', '>', now())->exists(), 422, 'An invitation is already pending for this email. Resend it instead.');

        $token = Str::random(48);
        $inv   = new Invitation();
        $inv->forceFill([
            'company_id' => $cid, 'email' => $email, 'role' => $data['role'], 'token_hash' => hash('sha256', $token),
            'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(self::INVITE_DAYS),
        ])->save();

        return response()->json(['data' => $this->withLink($inv, $token, $request)], 201);
    }

    /** New link and new expiry; the old link stops working. */
    public function resend(Request $request, int $id)
    {
        $cid = $this->requireAdmin($request);
        $inv = Invitation::where('company_id', $cid)->pending()->findOrFail($id);
        $token = Str::random(48);
        $inv->forceFill(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(self::INVITE_DAYS)])->save();

        return response()->json(['data' => $this->withLink($inv, $token, $request)]);
    }

    public function revoke(Request $request, int $id)
    {
        $cid = $this->requireAdmin($request);
        Invitation::where('company_id', $cid)->pending()->findOrFail($id)->forceFill(['revoked_at' => now()])->save();

        return response()->noContent();
    }

    /* ---- public: the invited person ---- */

    public function showInvite(string $token)
    {
        $inv = $this->usable($token);

        return response()->json(['data' => ['email' => $inv->email, 'role' => $inv->role, 'company' => Company::find($inv->company_id)?->name]]);
    }

    public function accept(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $inv  = $this->usable($data['token']);
        abort_if(User::where('email', $inv->email)->exists(), 422, 'This email address is already registered.');

        $user = DB::transaction(function () use ($inv, $data) {
            $user = new User();
            $user->forceFill([
                'company_id' => $inv->company_id, 'name' => $data['name'], 'email' => $inv->email,
                'password' => Hash::make($data['password']), 'email_verified_at' => now(), 'is_active' => true,
            ])->save();
            Role::findOrCreate($inv->role);
            $user->assignRole($inv->role);
            $inv->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        return response()->json(app(AuthController::class)->payload($user, $user->createToken('spa')->plainTextToken), 201);
    }

    /* ---- helpers ---- */

    private function requireAdmin(Request $request): int
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only company admins can manage the team.');

        return $this->companyId($request);
    }

    private function usable(string $token): Invitation
    {
        $inv = Invitation::where('token_hash', hash('sha256', $token))->first();
        abort_if(! $inv || ! $inv->isUsable(), 404, 'This invitation is invalid or has expired. Ask your admin for a new one.');

        return $inv;
    }

    private function withLink(Invitation $inv, string $token, Request $request): array
    {
        $link = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').'/accept-invite?token='.$token;
        $sent = false;
        try {
            $company = Company::find($inv->company_id)?->name;
            Mail::raw("{$request->user()->name} invited you to join {$company} on the performance management system as {$inv->role}.\n\nAccept the invitation and set your password:\n{$link}\n\nThis link expires on {$inv->expires_at->toDayDateTimeString()}.",
                fn ($m) => $m->to($inv->email)->subject("You're invited to join {$company}"));
            $sent = true;
        } catch (\Throwable $e) {
            report($e); // mail not configured: the admin can still copy the link
        }

        return $this->shapeInvitation($inv->load('inviter:id,name')) + ['invite_url' => $link, 'emailed' => $sent];
    }

    private function shapeUser(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->roles->pluck('name')->first(), 'is_active' => (bool) ($u->is_active ?? true), 'created_at' => $u->created_at?->toDateString()];
    }

    private function shapeInvitation(Invitation $i): array
    {
        return ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'expires_at' => $i->expires_at->toDateString(), 'expired' => $i->expires_at->isPast(), 'invited_by' => $i->inviter?->name];
    }
}
