<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
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
            // Ensure errors are logged even in Octane environment
            if (config('octane.server')) {
                Log::error('Octane Error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Also log to stderr for console visibility
                error_log('Laravel Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        });
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Since this is an API-only application, always return a JSON response
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthenticated or token expired 5',
        ], 401);
    }
}
