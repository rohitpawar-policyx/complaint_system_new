<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors the previous require_authenticated()'s extra DB check: being
 * logged in is not enough, the account must currently be 'approved'. If a
 * pending/blocked user has an active session (e.g. an admin blocked them
 * mid-session), they are logged out immediately rather than trusting a
 * stale session.
 */
class EnsureAccountIsApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->isApproved()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not currently active. Contact an administrator.',
            ]);
        }

        return $next($request);
    }
}
