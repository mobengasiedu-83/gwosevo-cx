<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'middleware' => ['web'], 'auth_middleware' => 'auth',
    'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'views' => true,
    'home' => '/admin', 'prefix' => '', 'domain' => null, 'lowercase_usernames' => true,
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'mfa_enabled' => env('MFA_ENABLED', true),
    'features' => env('MFA_ENABLED', true)
        ? [Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]
        : [],
];
