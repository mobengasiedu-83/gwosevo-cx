<?php

namespace App\Http\Responses;

use App\Services\Navigation;

class LoginResponse implements \Laravel\Fortify\Contracts\LoginResponse
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Navigation::home($request->user()));
    }
}
