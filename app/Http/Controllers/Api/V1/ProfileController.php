<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Self-service: any signed-in user edits their own name and email and changes their own password. */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ]);

        // Changing the sign-in email needs the current password.
        if (Str::lower($data['email']) !== Str::lower($user->email)) {
            $this->requirePassword($user, $data['current_password'] ?? '');
        }
        $user->forceFill(['name' => $data['name'], 'email' => Str::lower($data['email'])])->save();

        return response()->json(app(AuthController::class)->payload($user->fresh()));
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $this->requirePassword($user, $data['current_password']);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        // Sign out every other session, keep this one.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->noContent();
    }

    private function requirePassword($user, string $given): void
    {
        if (! Hash::check($given, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }
    }
}
