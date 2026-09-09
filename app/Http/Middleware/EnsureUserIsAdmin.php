<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks the role from the database on every request (via the already
 * loaded user + role relation), matching the previous require_admin()'s
 * fresh DB join rather than trusting anything cached in the session.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user()?->fresh('role');

        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
