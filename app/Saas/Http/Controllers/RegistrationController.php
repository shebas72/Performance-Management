<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Saas\Services\TenantSignup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Public sign-up. A new company registers with its first admin, verifies the email address, and then waits
 * for the super admin to approve it. Nobody gets a token until all of that is done (sign in as usual afterwards).
 */
class RegistrationController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $company = new Company();
            $company->forceFill([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'is_active' => false,
                'approval_status' => 'pending',
            ])->save();

            $user = new User();
            $user->forceFill([
                'company_id' => $company->id,
                'name'       => $data['name'],
                'email'      => Str::lower($data['email']),
                'password'   => Hash::make($data['password']),
                'role_key'   => 'admin',
                'is_active'  => true,
            ])->save();

            Role::findOrCreate('admin');
            $user->assignRole('admin');

            return $user;
        });

        TenantSignup::sendVerification($user);

        return response()->json([
            'message' => 'Registration received. Please check your email to verify your address; your organization will then be reviewed for approval.',
            'data' => ['status' => 'awaiting_email', 'email' => $user->email],
        ], 201);
    }

    /** The link in the verification email. Verifies, then sends the person to the sign-in page with the outcome. */
    public function verify(string $token)
    {
        $result = TenantSignup::verify($token);

        return redirect()->away(rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').'/login?verified='.$result);
    }

    /** Always answers the same way, so it cannot be used to find out which emails are registered. */
    public function resend(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', Str::lower($data['email']))->whereNotNull('email_verification_token')->first();

        if ($user) {
            TenantSignup::sendVerification($user);
        }

        return response()->json(['message' => 'If that address is waiting for verification, a new email has been sent.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
