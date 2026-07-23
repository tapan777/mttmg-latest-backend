<?php

namespace App\Http\Controllers;

use App\Events\SendEmailNotification;
use App\Mail\PasswordResetMail;
use App\Models\PasswordRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    // Step 1 — Send reset link to email
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'required|email',
            'redirect_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        $user = DB::table('users')->where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'No account found with this email address.',
            ], 200);
        }

        try {
            $randomNo  = mt_rand(100000, 999999);
            $token     = Crypt::encryptString($user->id . '~' . $user->email . '~' . $randomNo . '~' . now()->timestamp);

            PasswordRequestLog::create([
                'req_type' => 'reset',
                'user_id'  => $user->id,
                'email'    => $user->email,
                'token'    => $token,
            ]);

            $resetLink = rtrim($request->redirect_url, '/') . '?token=' . urlencode($token);

            // Send email async via event listener
            event(new SendEmailNotification(
                new PasswordResetMail($user->name, $resetLink),
                $user->email
            ));

            return response()->json([
                'code'    => 200,
                'message' => 'Password reset link has been sent to your email.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Forgot password error: ' . $e->getMessage());
            return response()->json([
                'code'    => 500,
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    // Step 2 — Set new password using token from email link
    public function setNewPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'                 => 'required|string',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        try {
            $token     = urldecode($request->token);
            $decrypted = Crypt::decryptString($token);

            [$userId, $email, , $timestamp] = explode('~', $decrypted);

            // Token expires after 60 minutes
            if (now()->timestamp - (int) $timestamp > 3600) {
                return response()->json([
                    'code'    => 422,
                    'message' => 'Reset link has expired. Please request a new one.',
                ], 200);
            }

            // Verify token exists in log (prevents reuse)
            $log = PasswordRequestLog::where('token', $request->token)
                ->where('user_id', $userId)
                ->where('req_type', 'reset')
                ->first();

            if (!$log) {
                return response()->json([
                    'code'    => 422,
                    'message' => 'Invalid or already used reset link.',
                ], 200);
            }

            DB::table('users')
                ->where('id', $userId)
                ->where('email', $email)
                ->update(['password' => Hash::make($request->password)]);

            $log->delete();

            return response()->json([
                'code'    => 200,
                'message' => 'Password updated successfully. You can now log in.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Set new password error: ' . $e->getMessage());
            return response()->json([
                'code'    => 422,
                'message' => 'Invalid or expired reset link.',
            ], 200);
        }
    }

    // Backward-compatible alias
    public function resetPassword(Request $request)
    {
        return $this->forgotPassword($request);
    }
}
