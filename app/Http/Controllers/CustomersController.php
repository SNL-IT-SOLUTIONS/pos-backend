<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }

    // ✅ Create new customer
    public function createCustomer(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name'       => 'required|string|max:150',
                'last_name'        => 'required|string|max:150',
                'profile_picture'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // file upload
                'email'            => 'required|email|unique:customers,email',
                'phone'            => 'nullable|string|max:50',
                'address'          => 'nullable|string|max:255',
                'city'             => 'nullable|string|max:100',
                'state'            => 'nullable|string|max:100',
                'zip'              => 'nullable|string|max:20',
                'country'          => 'nullable|string|max:100',
                'comments'         => 'nullable|string',
            ]);

            $validated['is_archived'] = 0;

            // Auto-generate customer number
            $lastCustomer = Customers::orderBy('id', 'desc')->first();
            $nextId = $lastCustomer ? $lastCustomer->id + 1 : 1;
            $validated['customer_number'] = 'CUST-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            // Handle profile picture upload using helper
            if ($request->hasFile('profile_picture')) {
                $validated['profile_picture'] = $this->saveFileToPublic($request, 'profile_picture', 'customer');
            }

            $customer = Customers::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Customer created successfully.',
                'customer'  => $customer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create customer.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    // ✅ Get all customers (exclude archived)
    public function getCustomers(Request $request)
    {
        // Default per page = 10 if not specified
        $perPage = $request->input('per_page', 10);

        $customers = Customers::where('is_archived', 0)->paginate($perPage);

        // Transform each customer to include profile_picture full URL
        $customers->getCollection()->transform(function ($customer) {
            $customer->profile_picture = $customer->profile_picture
                ? asset($customer->profile_picture)
                : null;
            return $customer;
        });

        return response()->json([
            'isSuccess'  => true,
            'customers'  => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
                'last_page'    => $customers->lastPage(),
            ],
        ], 200);
    }




    // ✅ Get single customer
    public function getCustomerById($id)
    {
        $customer = Customers::where('id', $id)->where('is_archived', 0)->first();

        if (!$customer) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Customer not found.'
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'customer'  => $customer
        ], 200);
    }

    // ✅ Update customer
    public function updateCustomer(Request $request, $id)
    {
        $customer = Customers::where('id', $id)->where('is_archived', 0)->first();

        if (!$customer) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Customer not found or archived.'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'first_name'     => 'sometimes|string|max:150',
                'last_name'      => 'sometimes|string|max:150',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'email'          => 'sometimes|email|unique:customers,email,' . $id,
                'phone'          => 'nullable|string|max:50',
                'address'        => 'nullable|string|max:255',
                'city'           => 'nullable|string|max:100',
                'state'          => 'nullable|string|max:100',
                'zip'            => 'nullable|string|max:20',
                'country'        => 'nullable|string|max:100',
                'comments'       => 'nullable|string',
            ]);

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $path = $this->saveFileToPublic($request, 'profile_picture', 'customer');

                // optionally delete old file if exists
                if ($customer->profile_picture && file_exists(public_path($customer->profile_picture))) {
                    unlink(public_path($customer->profile_picture));
                }

                $validated['profile_picture'] = $path;
            }

            $customer->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Customer updated successfully.',
                'customer'  => $customer
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update customer.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    // ✅ Soft delete customer (set is_archived = 1)
    public function archiveCustomer($id)
    {
        $customer = Customers::where('id', $id)->where('is_archived', 0)->first();

        if (!$customer) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Customer not found or already archived.'
            ], 404);
        }

        $customer->update(['is_archived' => 1]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Customer archived successfully.'
        ], 200);
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
