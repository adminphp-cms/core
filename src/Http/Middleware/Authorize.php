<?php

namespace Gmarineau\AdminPhp\Http\Middleware;

use Closure;
use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs deactivated admin users out, then runs the AdminPhp::auth() callback
 * once the admin user is authenticated.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminPhp = app(AdminPhp::class);

        if ($adminPhp->user()?->isActive() === false) {
            $adminPhp->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(401);
        }

        abort_unless($adminPhp->check($request), 403);

        return $next($request);
    }
}
