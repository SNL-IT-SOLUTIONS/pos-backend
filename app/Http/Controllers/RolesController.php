<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Roles;

class RolesController extends Controller
{
    public function __construct()
    {
        //  Protect all endpoints with Sanctum auth
        $this->middleware('auth:sanctum');
    }

    //  Create new role
    public function createRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'role_name'   => 'required|string|max:100|unique:roles,role_name',
                'description' => 'nullable|string|max:255',
                'is_active'   => 'required|boolean',
            ]);

            $validated['is_archived'] = 0;

            $role = Roles::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Role created successfully.',
                'role'      => $role
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create role.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    // Get all roles (exclude archived)
    public function getRoles(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);

            $roles = Roles::where('is_archived', 0)->paginate($perPage);

            if ($roles->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No active roles found.',
                ], 404);
            }

            return response()->json([
                'isSuccess'  => true,
                'roles'      => $roles->items(),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'per_page'     => $roles->perPage(),
                    'total'        => $roles->total(),
                    'last_page'    => $roles->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch roles.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    // Get single role
    public function getRoleById($id)
    {
        try {
            $role = Roles::where('id', $id)->where('is_archived', 0)->first();

            if (!$role) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Role not found.'
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'role'      => $role
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch role.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    //  Update role
    public function updateRole(Request $request, $id)
    {
        try {
            $role = Roles::where('id', $id)->where('is_archived', 0)->first();

            if (!$role) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Role not found.'
                ], 404);
            }

            $validated = $request->validate([
                'role_name'   => 'required|string|max:100|unique:roles,role_name,' . $id,
                'description' => 'nullable|string|max:255',
                'is_active'   => 'required|boolean',
            ]);

            $role->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Role updated successfully.',
                'role'      => $role
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update role.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    //  Soft delete role (set is_archived = 1)
    public function archiveRole($id)
    {
        try {
            $role = Roles::where('id', $id)->where('is_archived', 0)->first();

            if (!$role) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Role not found or already archived.'
                ], 404);
            }

            $role->update(['is_archived' => 1]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Role archived successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to archive role.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }
}
