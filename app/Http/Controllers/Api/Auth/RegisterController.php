<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Concerns\IssuesTokenResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    use IssuesTokenResponses;

    public function __construct(private readonly TokenServiceInterface $tokens) {}

    /**
     * Create the account, send the verification email, and sign the user in.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        event(new Registered($user));

        Log::info('user.registered', ['user_id' => $user->id, 'client' => $request->client()->value]);

        $pair = $this->tokens->issuePair($user, $request->input('device_name'));

        return $this->tokenResponse($user, $pair, $request->client(), 201);
    }
}
