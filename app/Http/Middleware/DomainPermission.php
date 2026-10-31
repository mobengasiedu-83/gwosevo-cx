<?php

namespace App\Http\Middleware;

use App\Services\Navigation;
use Closure;
use Illuminate\Http\Request;

class DomainPermission
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Navigation::resourceEnabled($request->route('resource')), 404);
        $verb = match ($request->method()) {
            'POST' => 'create', 'PUT', 'PATCH' => 'update', 'DELETE' => 'delete', default => 'view'
        };
        $user = $request->user();
        abort_unless($user->can($request->route('resource').'.'.$verb) || $user->can($verb === 'view' ? 'contact-center.view' : 'contact-center.manage'), 403);

        return $next($request);
    }
}
