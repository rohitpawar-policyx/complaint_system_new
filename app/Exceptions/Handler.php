<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Laravel 10 has no bootstrap/app.php exception config (that's an
            // 11+ thing) - Sentry's own Integration::handles() shortcut
            // requires that, so on 10 the documented hookup is this direct
            // call instead. No-ops safely when SENTRY_LARAVEL_DSN is unset
            // (e.g. local dev without a Sentry project configured).
            Integration::captureUnhandledException($e);
        });
    }
}
