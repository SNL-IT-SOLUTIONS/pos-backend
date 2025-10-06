<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Category;

class SupplierController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }
    public function getAllSuppliers(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $suppliers = Supplier::where('is_active', 1)->paginate($perPage);

        if ($suppliers->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'No active suppliers found.',
            ], 404);
        }

        // 📊 Summary info
        $totalSuppliers = Supplier::count();
        $activeSuppliers = Supplier::where('is_active', 1)->count();

        // ✅ Count unique categories from suppliers
        $categoryCount = Supplier::whereNotNull('category_id')
            ->distinct('category_id')
            ->count('category_id');

        return response()->json([
            'isSuccess'  => true,
            'suppliers'  => $suppliers->items(),
            'summary'    => [
                'total_suppliers'  => $totalSuppliers,
                'active_suppliers' => $activeSuppliers,
                'categories'       => $categoryCount,
            ],
            'pagination' => [
                'current_page' => $suppliers->currentPage(),
                'per_page'     => $suppliers->perPage(),
                'total'        => $suppliers->total(),
                'last_page'    => $suppliers->lastPage(),
            ],
        ], 200);
    }



    public function getSupplierById($id)
    {
        $supplier = Supplier::where('id', $id)->where('is_active', 1)->first();

        if (!$supplier) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Supplier not found or inactive.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'supplier'  => $supplier
        ]);
    }

    public function createSupplier(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_name'   => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email'          => 'nullable|email|unique:suppliers,email',
                'phone'          => 'nullable|string|max:20',
                'address'        => 'nullable|string|max:255',
                'city'           => 'nullable|string|max:100',
                'state'          => 'nullable|string|max:100',
                'zipcode'        => 'nullable|string|max:50',
                'payment_terms'  => 'nullable|string|max:255',
                'category_id'    => 'nullable|integer|exists:categories,id',
                'certificates'   => 'nullable|string|max:255',
            ]);

            $validated['is_active'] = 1;

            $supplier = Supplier::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Supplier created successfully.',
                'supplier'  => $supplier
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create supplier.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    public function updateSupplier(Request $request, $id)
    {
        $supplier = Supplier::where('id', $id)->where('is_active', 1)->first();

        if (!$supplier) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Supplier not found or inactive.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'company_name'   => 'sometimes|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email'          => 'sometimes|email|unique:suppliers,email,' . $id,
                'phone'          => 'nullable|string|max:20',
                'address'        => 'nullable|string|max:255',
                'city'           => 'nullable|string|max:100',
                'state'          => 'nullable|string|max:100',
                'zipcode'        => 'nullable|string|max:50',
                'payment_terms'  => 'nullable|string|max:255',
                'category_id'    => 'nullable|integer|exists:categories,id',
                'certificates'   => 'nullable|string|max:255',
            ]);

            $supplier->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Supplier updated successfully.',
                'supplier'  => $supplier
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update supplier.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    public function archiveSupplier($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier || !$supplier->is_active) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Supplier not found or already inactive.',
            ], 404);
        }

        $supplier->update(['is_active' => 0]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Supplier archived successfully.'
        ]);
    }
}
