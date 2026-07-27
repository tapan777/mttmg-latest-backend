<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\Token;
use App\Models\User;
use App\Models\UserPageAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

use function PHPUnit\Framework\isEmpty;

class AdminLoginController extends Controller
{
    //admin registration(It will be delete during production)
    public function addUser(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'role' => 'required|string',
            'phone' => 'nullable|string',
            'user_name' => 'nullable|string',
            'page_access' => 'nullable|array',
            'page_access.*' => 'string|max:100',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'error' => $validation->errors(),
                'message' => "Validation failed",
                'code' => 500
            ], 200);
        }

        try {
            $userPayload = $request->only(['name', 'email', 'password', 'role', 'phone', 'user_name']);
            if (empty($userPayload['phone'])) {
                $userPayload['phone'] = '';
            }
            if (!isset($userPayload['user_name'])) {
                $userPayload['user_name'] = null;
            }

            DB::beginTransaction();

            $user = User::create($userPayload);
            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Registration Failed',
                    'code' => 500
                ], 200);
            }

            $pageAccess = $request->input('page_access', []);
            if (!empty($pageAccess)) {
                $pageAccess = array_values(array_unique(array_map('trim', $pageAccess)));
                foreach ($pageAccess as $page) {
                    if ($page !== '') {
                        UserPageAccess::create([
                            'user_id' => $user->id,
                            'page' => $page,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User Created Successfully',
                'code' => 200,
                'user_id' => $user->id,
                'page_access' => $pageAccess,
            ], 200);
        } catch (UniqueConstraintViolationException $e) {
            DB::rollBack();
            $errorMessage = $e->getMessage();
            $matches = [];
            preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $errorMessage, $matches);
            $duplicateEntry = $matches[1] ?? 'value';
            $keyName = $matches[2] ?? 'key';

            return response()->json([
                'message' => "Duplicate Entry '$duplicateEntry' for '$keyName'",
                'code' => 500
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    //update user
    public function userUpdate(Request $request)
    {
        $model = User::find($request->id);
        if ($model === null) {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500
            ], 200);
        }

        $payload = $request->all();
        Arr::forget($payload, ['id', 'auth_user_id']);

        $page_access = null;
        if (array_key_exists('page_access', $payload)) {
            $page_access = $payload['page_access'];
            unset($payload['page_access']);
        }

        $table_columns = Schema::getColumnListing($model->getTable());
        $invalid_columns = array_diff(array_keys($payload), $table_columns);

        if (!empty($invalid_columns)) {
            return response()->json([
                'message' => "Invalid Columns Provided : " . implode(", ", $invalid_columns),
                'code' => 500
            ], 200);
        }

        try {
            DB::beginTransaction();

            if (!empty($payload)) {
                $model->update($payload);
            }

            if ($page_access !== null) {
                UserPageAccess::where('user_id', $model->id)->delete();
                $pages = is_array($page_access) ? $page_access : [];
                $pages = array_values(array_unique(array_map('trim', $pages)));
                foreach ($pages as $page) {
                    if ($page !== '') {
                        UserPageAccess::create([
                            'user_id' => $model->id,
                            'page' => $page,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => "Data Updated Successfully",
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    //retrive user
    public function retriveUser(Request $request)
    {
        $search_text = $request->search_text;
        $user_id = $request->id;
        $limit = $request->limit > 0 ? $request->limit : 10;
        $index = $request->index > 0 ?  $request->index : 0;
        if ($search_text) {
            $search_data = User::with('pageAccesses')
                ->where('name', 'like', "%$search_text%")
                ->orWhere('user_name', 'like', "%$search_text%")
                ->orWhere('phone', 'like', "%$search_text%")
                ->orWhere('email', 'like', "%$search_text%")
                ->offset($index)
                ->limit($limit)
                ->orderBy('id', 'desc')
                ->get();
            $search_data->each(function ($u) {
                $u->page_access = $u->pageAccesses->pluck('page')->values()->all();
                $u->unsetRelation('pageAccesses');
            });

            if ($search_data->isNotEmpty()) {
                return response()->json([
                    'data' => $search_data,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No data Found",
                    'code' => 500
                ], 200);
            }
        } elseif ($user_id) {
            $user = User::with('pageAccesses')->find($user_id);
            if ($user) {
                $user->page_access = $user->pageAccesses->pluck('page')->values()->all();
                $user->unsetRelation('pageAccesses');
                return response()->json([
                    'data' => $user,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Invalid Id",
                    'code' => 500
                ], 200);
            }
        } else {
            $user_data = User::with('pageAccesses')->orderBy('id', 'desc')->get();
            $user_data->each(function ($u) {
                $u->page_access = $u->pageAccesses->pluck('page')->values()->all();
                $u->unsetRelation('pageAccesses');
            });
            return response()->json([
                'data' => $user_data,
                'code' => 200
            ], 200);
        }
    }
    // Auto Complete
    public function userAutoComplete(Request $request)
    {
        $search_text = $request->search_text;
        $limit = $request->limit > 0 ? $request->limit : 10;
        $index = $request->index > 0 ?  $request->index : 0;

        $data = DB::table('users')
            ->where('name', 'like', "%$search_text%")
            ->orWhere('user_name', 'like', "%$search_text%")
            ->orWhere('phone', 'like', "%$search_text%")
            ->orWhere('email', 'like', "%$search_text%")
            ->offset($index)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($search_text) {

                // If the name matches, return only the name
                if (strpos($item->name, $search_text) !== false) {
                    return ['search_data' => $item->name];
                    // If the duration matches, return only the duration
                } elseif (stripos($item->user_name, $search_text) !== false) {
                    return ['search_data' => $item->user_name];
                } elseif (stripos($item->phone, $search_text) !== false) {
                    return ['search_data' => $item->phone];
                } elseif (stripos($item->email, $search_text) !== false) {
                    return ['search_data' => $item->email];
                }
            })->toArray();
        if ($data != []) {
            return response()->json([
                'data' => $data,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'code' => 500,
                'message' => "No Record Found"
            ], 200);
        }
    }

    //active/inactive
    public function activeInactive(Request $request)
    {
        $model = User::find($request->id);
        if ($model != null) {
            $update_status = $model->update($request->all());
            if ($update_status) {
                return response()->json([
                    'message' => "Status Updated Successfully",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Something went Wrong",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500
            ], 200);
        }
    }

    //admin Login
    public function admin_login(Request $request)
    {
        $input = $request->all();
        $validation = Validator::make($input, [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => $validation->errors(),
                'code' => 500
            ], 500);
        } else {
            $admin = User::with('pageAccesses')
                ->where('email', $input['email'])
                ->orWhere('user_name', $request->email)
                ->orWhere('phone', $request->email)
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
                    // Include page_access in login response (for role "user" and others)
                    $admin['page_access'] = $admin->pageAccesses->pluck('page')->values()->all();
                    $admin->unsetRelation('pageAccesses');

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

                    Token::create([
                        'user_id' => $admin->id,
                        'token' => $token,
                        'expires_at' => now()->addHours(24) // Example of setting expiration
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

    //forgot Password
    public function forgot_password(Request $request)
    {
        $input = $request->all();
        $validation = Validator::make($input, [
            'email.required' => 'We need to know your email address!'
        ]);
        if ($validation->fails()) {
            return response()->json([
                'error' => $validation->errors()->get('email'),
                'code' => 500
            ], 500);
        } else {
            return response()->json([
                'message' => 'Validation Successful',
                'code' => 200
            ], 200);
        }
    }

    //delete user
    public function deleteUser(Request $request)
    {
        $model = User::find($request->id);
        if ($model != null) {
            if ($model->delete()) {
                return response()->json([
                    'message' => "Delete Successfull",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Failed to Delete",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500,
            ], 200);
        }
    }
}
