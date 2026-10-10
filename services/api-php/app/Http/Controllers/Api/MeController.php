<?php

namespace App\Http\Controllers\Api;

use App\Auth\JwtUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeDeleteRequest;
use App\Http\Requests\MePasswordRequest;
use App\Http\Requests\MeUpdateRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in user: read, merge-patch, change password, delete.
 */
class MeController extends Controller
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('roles'));
    }

    /**
     * Apply only the fields present; a changed email un-verifies the account.
     */
    public function update(MeUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user->loadMissing('roles'));
    }

    /**
     * Change the password and revoke every other refresh-token family.
     */
    public function updatePassword(MePasswordRequest $request): Response
    {
        $request->user()->update([
            'password' => Hash::make($request->validated()['password']),
        ]);

        $this->tokens->revokeAllForUser(
            $request->user(),
            JwtUserResolver::claimsFrom($request)?->familyId,
        );

        return response()->noContent();
    }

    public function destroy(MeDeleteRequest $request): Response
    {
        $user = $request->user();

        $this->tokens->revokeAllForUser($user);
        $user->delete();

        return response()->noContent();
    }
}
