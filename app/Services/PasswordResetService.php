<?php

namespace App\Services;

use App\Models\User;
use App\Mail\PasswordResetOtpMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PasswordResetService
{
    /**
     * Generate 6-digit OTP
     */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP to email for password reset
     */
    public function sendResetLink(string $email): array
    {
        try {
            // Find user by email
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Only allow password reset for admin, teacher, and parent
            if (!in_array($user->user_type, ['admin', 'teacher', 'parent'])) {
                throw new \Exception('Password reset is not available for this user type: ' . $user->user_type);
            }

            // Delete existing tokens for this email
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // Generate 6-digit OTP
            $otp = $this->generateOtp();

            // Store OTP in database (Laravel's default table)
            // Token is hashed for security
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($otp),
                'created_at' => Carbon::now(),
            ]);

            // Send email with OTP
            Mail::to($email)->send(new PasswordResetOtpMail($user, $otp));

            Log::info('Password reset OTP sent', [
                'email' => $email,
                'user_type' => $user->user_type,
            ]);

            return [
                'success' => true,
                'message' => 'A 6-digit OTP has been sent to your email. It will expire in 15 minutes.',
            ];
        } catch (\Exception $e) {
            Log::error('Password reset OTP send failed: ' . $e->getMessage(), [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Reset password using OTP
     */
    public function resetPassword(string $email, string $otp, string $password): array
    {
        try {
            // Find reset token
            $resetToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetToken) {
                throw new \Exception('No password reset request found for this email');
            }

            // Check if OTP has expired (15 minutes)
            $createdAt = Carbon::parse($resetToken->created_at);
            if ($createdAt->addMinutes(15)->isPast()) {
                // Delete expired token
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                throw new \Exception('OTP has expired. Please request a new one.');
            }

            // Verify OTP
            if (!Hash::check($otp, $resetToken->token)) {
                throw new \Exception('Invalid OTP');
            }

            // Find user
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Update password
            $user->password = Hash::make($password);
            $user->save();

            // Delete the used token
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            Log::info('Password reset successful', [
                'email' => $email,
                'user_type' => $user->user_type,
            ]);

            return [
                'success' => true,
                'message' => 'Password has been reset successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage(), [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $email, string $otp): bool
    {
        try {
            $resetToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetToken) {
                return false;
            }

            // Check if OTP has expired (15 minutes)
            $createdAt = Carbon::parse($resetToken->created_at);
            if ($createdAt->addMinutes(15)->isPast()) {
                // Delete expired token
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                return false;
            }

            // Verify OTP
            return Hash::check($otp, $resetToken->token);
        } catch (\Exception $e) {
            Log::error('OTP verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired tokens (can be called via scheduled job)
     */
    public function cleanupExpiredTokens(): int
    {
        // Delete tokens older than 15 minutes
        $deleted = DB::table('password_reset_tokens')
            ->where('created_at', '<', Carbon::now()->subMinutes(15))
            ->delete();
        
        Log::info('Cleaned up expired password reset tokens', [
            'count' => $deleted,
        ]);

        return $deleted;
    }
}
