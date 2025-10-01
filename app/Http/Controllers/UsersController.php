<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UsersController extends Controller
{
    public function getAllUsers(Request $request)
    {
        // Default to 10 per page if not specified
        $perPage = $request->input('per_page', 10);

        $users = User::where('is_archived', 0)->paginate($perPage);

        if ($users->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'No active users found.',
            ], 404);
        }

        // Transform each user to include profile_picture with asset path
        $users->getCollection()->transform(function ($user) {
            $user->profile_picture = $user->profile_picture
                ? asset($user->profile_picture)
                : null;
            return $user;
        });

        return response()->json([
            'isSuccess'  => true,
            'users'      => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }



    public function getUserById($id)
    {
        $user = User::where('id', $id)->where('is_archived', 0)->first();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'User not found or archived.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'user'      => $user
        ]);
    }

    public function createUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:150',
                'last_name'  => 'required|string|max:150',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // only images
                'email'      => 'required|email|unique:users,email',
                'username'   => 'required|string|unique:users,username',
                'role_id'    => 'required|integer|exists:roles,id',
                'password'   => 'required|string|min:6',
                'phone'      => 'nullable|string|max:50',
                'address'    => 'nullable|string|max:255',
                'city'       => 'nullable|string|max:100',
                'state'      => 'nullable|string|max:100',
                'zip'        => 'nullable|string|max:20',
                'country'    => 'nullable|string|max:100',
                'comments'   => 'nullable|string',
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_archived'] = 0;

            // Auto-generate employee_number
            $lastUser = User::orderBy('id', 'desc')->first();
            $lastNumber = $lastUser ? intval(substr($lastUser->employee_number, 3)) : 0;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $validated['employee_number'] = 'EMP' . $newNumber;

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $path = $this->saveFileToPublic($request, 'profile_picture', 'profile');
                $validated['profile_picture'] = $path;
            }


            $user = User::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'User created successfully.',
                'user'      => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create user.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    public function updateUser(Request $request, $id)
    {
        $user = User::where('id', $id)->where('is_archived', 0)->first();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'User not found or archived.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:150',
                'last_name'  => 'sometimes|string|max:150',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'email'      => 'sometimes|email|unique:users,email,' . $id,
                'username'   => 'sometimes|string|unique:users,username,' . $id,
                'role_id'    => 'sometimes|integer|exists:roles,id',
                'password'   => 'nullable|string|min:6',
                'phone'      => 'nullable|string|max:50',
                'address'    => 'nullable|string|max:255',
                'city'       => 'nullable|string|max:100',
                'state'      => 'nullable|string|max:100',
                'zip'        => 'nullable|string|max:20',
                'country'    => 'nullable|string|max:100',
                'comments'   => 'nullable|string',
            ]);

            if (!empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // delete old file if it exists
                if ($user->profile_picture && file_exists(public_path($user->profile_picture))) {
                    unlink(public_path($user->profile_picture));
                }

                $path = $this->saveFileToPublic($request, 'profile_picture', 'profile');
                $validated['profile_picture'] = $path;
            }

            $user->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'User updated successfully.',
                'user'      => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update user.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    public function archiveUser($id)
    {
        $user = User::find($id);

        if (!$user || $user->is_archived) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'User not found or already archived.',
            ], 404);
        }

        $user->update(['is_archived' => 1]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'User archived successfully.'
        ]);
    }


    //HELPERS
    private function saveFileToPublic(Request $request, $field, $prefix)
    {
        if ($request->hasFile($field)) {
            $file = $request->file($field);

            // Directory inside /public
            $directory = public_path('pos_files');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Generate filename: prefix + unique id + original extension
            $filename = $prefix . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Move file to public/admission_files
            $file->move($directory, $filename);

            // Return relative path (to store in DB)
            return 'pos_files/' . $filename;
        }

        return null;
    }
}
