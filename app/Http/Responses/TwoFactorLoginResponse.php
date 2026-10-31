<?php

namespace App\Http\Responses;

use App\Services\Navigation;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Fortify's default two-factor response redirects to fortify.home (/admin),
 * which is forbidden for Agent and Supervisor accounts. Send every account to
 * its first permitted workspace instead, matching LoginResponse.
 */
class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Navigation::home($request->user()));
    }
}
