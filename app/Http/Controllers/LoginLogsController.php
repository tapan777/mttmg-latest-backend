<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginLogsController extends Controller
{
    public function admin_login(Request $request)
    {
        $input = $request->all();
        $validation = Validator::make($input, [
            'email' => 'required | email',
            'password' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => $validation->errors(),
                'code' => 500
            ], 500);
        } else {
            $admin = User::where([
                'email' => $input['email']
            ])
                ->orWhere('user_name', $request->email)
                ->first();

            if (!$admin) {
                return response()->json([
                    'message' => 'Username or Email is not found',
                    'code' => 500
                ], 200);
            }
            if ($admin && $admin['status'] == 1) {
                if ($admin && Hash::check($input['password'], $admin['password'])) {
                    $token = $admin->createToken('Login_token')->accessToken;
                    $admin['token'] = $token;

                    $ipAddress = $request->ip();


                    // Log login data
                    LoginLog::create([
                        'user_id' => $admin->id,
                        'email' => $admin->email,
                        'ip_address' => $ipAddress,
                        'browser_name' => $request->header('User-Agent'),
                        'city' => "Bhubaneswar",
                        'country' => "Odisha",
                        'login_time' => now(),
                        'logout_at' => null,
                    ]);

                    return response()->json([
                        'admin' => $admin,
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Invalid Credentials',
                        'code' => 500
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Inactive User',
                    'code' => 500
                ], 200);
            }
        }
    }
}
