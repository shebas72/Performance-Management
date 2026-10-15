<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** Public sign-up: anyone can register, which creates a new company (tenant) and its admin. */
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
            ])->save();

            $user = new User();
            $user->forceFill([
                'company_id' => $company->id,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
            ])->save();

            Role::findOrCreate('admin');
            $user->assignRole('admin');

            return $user;
        });

        return response()->json(app(AuthController::class)->payload($user, $user->createToken('spa')->plainTextToken), 201);
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
