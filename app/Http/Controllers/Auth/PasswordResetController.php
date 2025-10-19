<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends BaseController
{
    public function __construct(
        private PasswordResetService $passwordResetService
    ) {}

    /**
     * Send OTP to email for password reset
     * 
     * @param PasswordResetRequest $request
     * @return JsonResponse
     */
    public function sendResetOtp(PasswordResetRequest $request): JsonResponse
    {
        try {
            $result = $this->passwordResetService->sendResetLink(
                $request->validated()['email']
            );

            return $this->successResponse(
                null,
                $result['message'],
                200
            );
        } catch (\Exception $e) {
            Log::error('Error sending password reset OTP: ' . $e->getMessage());
            
            // Handle specific error cases
            $message = $e->getMessage();
            if (str_contains($message, 'not available for this user type')) {
                return $this->errorResponse($message, null, 403);
            }
            
            return $this->errorResponse(
                'Failed to send password reset OTP. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Reset password using OTP
     * 
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->passwordResetService->resetPassword(
                $validated['email'],
                $validated['otp'],
                $validated['password']
            );

            return $this->successResponse(
                null,
                $result['message'],
                200
            );
        } catch (\Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());
            
            $message = $e->getMessage();
            $statusCode = 400;
            
            // Handle specific error cases
            if (str_contains($message, 'expired')) {
                $statusCode = 410; // Gone
            } elseif (str_contains($message, 'Invalid OTP')) {
                $statusCode = 400; // Bad Request
            }
            
            return $this->errorResponse($message, null, $statusCode);
        }
    }

    /**
     * Verify OTP
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        try {
            $isValid = $this->passwordResetService->verifyOtp(
                $request->email,
                $request->otp
            );

            if ($isValid) {
                return $this->successResponse(
                    ['valid' => true],
                    'OTP is valid',
                    200
                );
            }

            return $this->errorResponse(
                'Invalid or expired OTP',
                ['valid' => false],
                400
            );
        } catch (\Exception $e) {
            Log::error('Error verifying OTP: ' . $e->getMessage());
            return $this->errorResponse(
                'Failed to verify OTP',
                null,
                500
            );
        }
    }
}
