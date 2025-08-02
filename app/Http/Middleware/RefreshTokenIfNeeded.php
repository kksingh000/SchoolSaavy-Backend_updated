<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenIfNeeded
{
    /**
     * Handle an incoming request.
     * 
     * This middleware checks if the current token is close to expiration
     * and automatically refreshes it if needed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process if user is authenticated and request was successful
        if ($request->user() && $response->getStatusCode() === 200) {
            $token = $request->user()->currentAccessToken();

            if ($token && $this->shouldRefreshToken($token)) {
                $newTokenResult = $request->user()->createToken('auth-token');
                $newToken = $newTokenResult->accessToken ?? $newTokenResult->plainTextToken;

                // Delete the old token
                $token->delete();

                // Add new token to response headers
                $response->headers->set('X-New-Token', $newToken);
                $response->headers->set('X-Token-Refreshed', 'true');
                $response->headers->set('X-Token-Expires-At', now()->addMinutes(config('sanctum.expiration', 1440))->toISOString());
            }
        }

        return $response;
    }

    /**
     * Determine if the token should be refreshed.
     * Refresh if the token expires within the next 2 hours.
     */
    private function shouldRefreshToken($token): bool
    {
        if (!$token->expires_at) {
            return false; // Token doesn't expire
        }

        $expiresAt = $token->expires_at;
        $refreshThreshold = now()->addHours(2); // Refresh if expires within 2 hours

        return $expiresAt->lte($refreshThreshold);
    }
}
