<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    // 📦 Get all items with optional filters
    public function getAllItems(Request $request)
    {
        $query = Item::with(['category', 'supplier'])
            ->where('is_active', 1); // only active by default

        // 🔎 Search (by name, barcode, or supplier)
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'LIKE', "%$search%")
                    ->orWhere('barcode', 'LIKE', "%$search%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('company_name', 'LIKE', "%$search%");
                    });
            });
        }

        // 🎯 Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // ⚠️ Low stock filter
        if ($request->has('low_stock') && $request->low_stock == true) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'No items found.',
            ], 404);
        }

        // Add margin to each item
        $items->map(function ($item) {
            $item->margin = $item->price - $item->cost;
            return $item;
        });

        return response()->json([
            'isSuccess' => true,
            'items'     => $items,
        ]);
    }

    // 📦 Get single item
    public function getItemById($id)
    {
        $item = Item::with(['category', 'supplier'])->find($id);

        if (!$item) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Item not found.',
            ], 404);
        }

        $item->margin = $item->price - $item->cost;

        return response()->json([
            'isSuccess' => true,
            'item'      => $item,
        ]);
    }

    //  Create item
    public function createItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'item_name'   => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|integer|exists:categories,id',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'cost'        => 'required|numeric|min:0',
                'price'       => 'required|numeric|min:0',
                'stock'       => 'required|integer|min:0',
                'min_stock'   => 'nullable|integer|min:0',
                'barcode'     => 'nullable|string|max:100|unique:items,barcode',
                'is_active'   => 'nullable|boolean',
            ]);

            $validated['is_active'] = $validated['is_active'] ?? 1;

            $item = Item::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Item created successfully.',
                'item'      => $item,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create item.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // ✏️ Update item
    public function updateItem(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Item not found.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'item_name'   => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|integer|exists:categories,id',
                'supplier_id' => 'sometimes|integer|exists:suppliers,id',
                'cost'        => 'sometimes|numeric|min:0',
                'price'       => 'sometimes|numeric|min:0',
                'stock'       => 'sometimes|integer|min:0',
                'min_stock'   => 'nullable|integer|min:0',
                'barcode'     => 'nullable|string|max:100|unique:items,barcode,' . $id,
                'is_active'   => 'nullable|boolean',
            ]);

            $item->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Item updated successfully.',
                'item'      => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update item.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // 🗑️ Archive item (soft deactivate)
    public function archiveItem($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Item not found.',
            ], 404);
        }

        $item->update(['is_active' => 0]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Item archived successfully.',
        ]);
    }
}
