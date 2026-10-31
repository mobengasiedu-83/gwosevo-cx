<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ContactCenterController;
use App\Services\Navigation;
use Closure;
use Illuminate\Http\Request;

class EnsureEnabledModule
{
    public function handle(Request $request, Closure $next)
    {
        $uri = $request->route()?->uri() ?? '';
        if (str_starts_with($uri, 'admin-api/')) {
            $resource = $request->route('resource') ?? explode('/', $uri)[1];
            $resource = $resource === 'users' ? 'accounts' : $resource;
            $known = isset(ContactCenterController::RESOURCES[$resource])
                || in_array($resource, ['accounts', 'roles', 'settings', 'audits'], true);
            if ($known) {
                abort_unless(Navigation::resourceEnabled($resource), 404);
            }
        }

        return $next($request);
    }
}
