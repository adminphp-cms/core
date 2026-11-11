<?php

namespace Gmarineau\AdminPhp\Http\Middleware;

use Closure;
use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the AdminPhp::auth() callback once the admin user is authenticated.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(AdminPhp::class)->check($request), 403);

        return $next($request);
    }
}
