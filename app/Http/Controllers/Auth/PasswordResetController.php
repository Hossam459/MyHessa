<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    use HttpResponses;

    /**
     * Send password reset link to user email
     */
    public function forgotPassword(Request $request): JsonResponse
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

        // Generate reset token
        $token = Str::random(64);

        // Delete existing reset tokens for this user
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        // Store new reset token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        // Generate reset URL (adjust domain as needed)
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        // Send email
        try {
            Mail::send(new PasswordResetMail($user->email, $token, $resetUrl));
        } catch (\Exception $e) {
            return $this->error([], __('messages.email_send_failed'), 500);
        }

        return $this->success([], __('messages.password_reset_link_sent'));
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => __('validation.required', ['attribute' => 'email']),
            'email.email' => __('validation.email'),
            'email.exists' => __('validation.exists', ['attribute' => 'email']),
            'token.required' => __('validation.required', ['attribute' => 'token']),
            'password.required' => __('validation.required', ['attribute' => 'password']),
            'password.min' => __('validation.min.string', ['attribute' => 'password', 'min' => '8']),
            'password.confirmed' => __('validation.confirmed', ['attribute' => 'password']),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.validation_error'), 422);
        }

        // Check if reset token is valid
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetToken) {
            return $this->error([], __('messages.invalid_token'), 400);
        }

        // Check if token is expired (24 hours)
        if (now()->diffInHours($resetToken->created_at) > 24) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return $this->error([], __('messages.token_expired'), 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->update(['password' => bcrypt($request->password)]);

        // Delete reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->success([], __('messages.password_reset_success'));
    }
}
