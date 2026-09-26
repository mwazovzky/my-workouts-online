<?php

namespace App\Auth;

use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Request guard that re-resolves the bearer user whenever a new request is
 * bound, so a long-running worker or the test kernel never serves a user
 * cached from a previous request. An explicitly set user (actingAs) sticks.
 */
class JwtGuard extends RequestGuard
{
    private bool $userSetExplicitly = false;

    public function setUser(Authenticatable $user)
    {
        $this->userSetExplicitly = true;

        return parent::setUser($user);
    }

    public function setRequest(Request $request)
    {
        if (! $this->userSetExplicitly) {
            $this->user = null;
        }

        return parent::setRequest($request);
    }
}
