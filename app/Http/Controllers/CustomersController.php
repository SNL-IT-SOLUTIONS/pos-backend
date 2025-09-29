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
                'first_name' => 'required|string|max:150',
                'last_name'  => 'required|string|max:150',
                'email'      => 'required|email|unique:customers,email',
                'phone'      => 'nullable|string|max:50',
                'address'    => 'nullable|string|max:255',
                'city'       => 'nullable|string|max:100',
                'state'      => 'nullable|string|max:100',
                'zip'        => 'nullable|string|max:20',
                'country'    => 'nullable|string|max:100',
                'comments'   => 'nullable|string',
            ]);

            $validated['is_archived'] = 0;

            // Auto-generate customer number
            $lastCustomer = Customers::orderBy('id', 'desc')->first();
            $nextId = $lastCustomer ? $lastCustomer->id + 1 : 1;
            $validated['customer_number'] = 'CUST-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

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
    public function getCustomers()
    {
        $customers = Customers::where('is_archived', 0)->get();

        return response()->json([
            'isSuccess' => true,
            'customers' => $customers
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
                'first_name' => 'sometimes|string|max:150',
                'last_name'  => 'sometimes|string|max:150',
                'email'      => 'sometimes|email|unique:customers,email,' . $id,
                'phone'      => 'nullable|string|max:50',
                'address'    => 'nullable|string|max:255',
                'city'       => 'nullable|string|max:100',
                'state'      => 'nullable|string|max:100',
                'zip'        => 'nullable|string|max:20',
                'country'    => 'nullable|string|max:100',
                'comments'   => 'nullable|string',
            ]);

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
}
