<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\BusinessInformation;
use App\Models\Roles;
use Carbon\Carbon;
use App\Models\DtrRecord;

class AuthController extends Controller
{
    /**
     * User Login (allow email or username)
     */

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string', // email or username
            'password' => 'required|string'
        ]);

        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']])) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user()->load('role');
        $token = $user->createToken('auth_token')->plainTextToken;

        $businessInfo = BusinessInformation::first();

        // 📝 Insert DTR record on login
        DtrRecord::create([
            'user_id'          => $user->id,
            'login_start_time' => Carbon::now(),
            'remarks'          => 'Login',
        ]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Login successful.',
            'user'      => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'username'   => $user->username,
                'role_id'    => $user->role_id,
                'role_name'  => $user->role ? $user->role->role_name : null,
            ],
            'token'     => $token,
            'business'  => $businessInfo,
        ]);
    }

    /**
     * User Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // ⏱ Update latest DTR record
        $lastRecord = DtrRecord::where('user_id', $user->id)
            ->latest()
            ->first();

        if ($lastRecord && !$lastRecord->login_end_time) {
            $lastRecord->login_end_time = Carbon::now();
            $lastRecord->total_hours = Carbon::parse($lastRecord->login_start_time)
                ->diffInHours(Carbon::now()); // you can use diffInMinutes if needed
            $lastRecord->remarks = 'Logout';
            $lastRecord->save();
        }

        // 🔑 Revoke tokens
        $user->tokens()->delete();

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Logged out successfully'
        ]);
    }


    /**
     * Get Authenticated User
     */
    public function me(Request $request)
    {
        return response()->json([
            'isSuccess' => true,
            'user'      => $request->user()
        ]);
    }
}
