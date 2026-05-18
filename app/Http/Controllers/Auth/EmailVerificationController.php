<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    use HttpResponses;

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->error([], __('messages.unauthorized'), 401);
        }

        // If email is already verified
        if ($user->email_verified_at !== null) {
            return $this->error([], __('messages.email_already_verified'), 400);
        }

        // Generate verification token
        $token = Str::random(64);

        // Store verification token
        $user->emailVerificationTokens()->create([
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        // Generate verification URL (adjust domain as needed)
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;

        // Send email
        try {
            Mail::send(new EmailVerificationMail($user->email, $token, $verificationUrl));
        } catch (\Exception $e) {
            return $this->error([], __('messages.email_send_failed'), 500);
        }

        return $this->success([], __('messages.verification_email_sent'));
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ], [
            'token.required' => __('validation.required', ['attribute' => 'token']),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.validation_error'), 422);
        }

        // Find verification token
        $verificationToken = \App\Models\EmailVerificationToken::where('token', $request->token)
            ->whereNull('verified_at')
            ->first();

        if (!$verificationToken) {
            return $this->error([], __('messages.invalid_token'), 400);
        }

        // Check if token is expired
        if (now()->isAfter($verificationToken->expires_at)) {
            $verificationToken->delete();
            return $this->error([], __('messages.token_expired'), 400);
        }

        // Get user and mark email as verified
        $user = $verificationToken->user;
        $user->update([
            'email_verified_at' => now(),
            'is_verified' => 1,
        ]);

        // Mark token as verified
        $verificationToken->update(['verified_at' => now()]);

        return $this->success([], __('messages.email_verified_successfully'));
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => __('validation.required', ['attribute' => 'email']),
            'email.email' => __('validation.email'),
            'email.exists' => __('validation.exists', ['attribute' => 'email']),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.validation_error'), 422);
        }

        $user = User::where('email', $request->email)->first();

        // If email is already verified
        if ($user->email_verified_at !== null) {
            return $this->error([], __('messages.email_already_verified'), 400);
        }

        // Generate verification token
        $token = Str::random(64);

        // Delete old tokens
        $user->emailVerificationTokens()->delete();

        // Store new verification token
        $user->emailVerificationTokens()->create([
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        // Generate verification URL (adjust domain as needed)
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;

        // Send email
        try {
            Mail::send(new EmailVerificationMail($user->email, $token, $verificationUrl));
        } catch (\Exception $e) {
            return $this->error([], __('messages.email_send_failed'), 500);
        }

        return $this->success([], __('messages.verification_email_sent'));
    }
}
