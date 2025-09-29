<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * User Login (allow email or username)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string', // can be email or username
            'password' => 'required|string'
        ]);

        // Check if the login input is an email
        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']])) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Login successful.',
            'user'      => $user,
            'token'     => $token
        ]);
    }

    /**
     * User Logout
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

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
