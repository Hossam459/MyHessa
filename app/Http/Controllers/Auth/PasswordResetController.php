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
use OpenApi\Annotations as OA;

class PasswordResetController extends Controller
{
    use HttpResponses;

    /**
     * Send password reset code to user email
     *
     * @OA\Post(
     *     path="/api/auth/forgot-password",
     *     operationId="forgotPassword",
     *     tags={"Authentication"},
     *     summary="Request password reset",
     *     description="Send a password reset code to the user's email address",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User email",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="User's email address",
     *                 example="user@example.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset code has been sent to your email."),
     *             @OA\Property(property="data", type="array")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Email sending failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to send email"),
     *             @OA\Property(property="data", type="array")
     *         )
     *     )
     * )
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

        // Generate reset code
        $code = (string) random_int(100000, 999999);

        // Delete existing reset tokens for this user
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        // Store new reset code
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $code,
            'created_at' => now(),
        ]);

        // Send email
        try {
            Mail::to($user->email)->send(
                new PasswordResetMail(
                    $user->email,
                    $code
                )
            );
        } catch (\Exception $e) {
            return $this->error([], __('messages.email_send_failed'), 500);
        }

        return $this->success([], __('messages.password_reset_link_sent'));
    }

    /**
     * Reset password using code
     *
     * @OA\Post(
     *     path="/api/auth/reset-password",
     *     operationId="resetPassword",
     *     tags={"Authentication"},
     *     summary="Reset password with code",
     *     description="Reset user password using a valid reset code",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Reset password credentials",
     *         @OA\JsonContent(
     *             required={"email", "token", "password", "password_confirmation"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="User's email address",
     *                 example="user@example.com"
     *             ),
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 description="Password reset code",
     *                 example="123456"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="New password (minimum 8 characters)",
     *                 example="newpassword123"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 format="password",
     *                 description="Password confirmation",
     *                 example="newpassword123"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password has been reset successfully."),
     *             @OA\Property(property="data", type="array")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid or expired token"),
     *             @OA\Property(property="data", type="array")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => __('validation.required', ['attribute' => 'email']),
            'email.email' => __('validation.email'),
            'email.exists' => __('validation.exists', ['attribute' => 'email']),
            'token.required' => __('validation.required', ['attribute' => 'token']),
            'password.required' => __('validation.required', ['attribute' => 'password']),
            'password.min' => __('validation.min.string', ['attribute' => 'password', 'min' => '6']),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.validation_error'), 422);
        }

        // Check if reset code is valid
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetToken) {
            return $this->error([], __('messages.invalid_token'), 400);
        }

        // Check if code is expired (15 minutes)
        if (now()->diffInMinutes($resetToken->created_at) > 15) {
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
