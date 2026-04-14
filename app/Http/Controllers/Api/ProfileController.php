<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileLocaleRequest;
use App\Http\Requests\ProfileThemeRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->noContent();
    }

    /**
     * Update the user's locale preference.
     */
    public function updateLocale(ProfileLocaleRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->refresh());
    }

    /**
     * Update the user's theme preference.
     */
    public function updateTheme(ProfileThemeRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->refresh());
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::guard('web')->logout();
        $user->delete();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
