<?php

namespace App\Http\Controllers;

use App\Models\ReceivingItem;
use Illuminate\Http\Request;

class ReceivingItemController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }
    //  Get all receiving items for a given receiving
    public function getByReceiving($receivingId)
    {
        $items = ReceivingItem::with('item')->where('receiving_id', $receivingId)->get();

        return response()->json([
            'isSuccess' => true,
            'items' => $items
        ]);
    }

    //  Update a receiving item
    public function updateReceivingItem(Request $request, $id)
    {
        $item = ReceivingItem::find($id);

        if (!$item) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Receiving item not found.',
            ], 404);
        }

        $validated = $request->validate([
            'cost' => 'sometimes|numeric|min:0',
            'qty' => 'sometimes|integer|min:1',
            'discount' => 'nullable|numeric|min:0|max:100',
        ]);

        $lineTotal = ($validated['cost'] ?? $item->cost) * ($validated['qty'] ?? $item->qty);
        $discount = isset($validated['discount']) ? ($lineTotal * ($validated['discount'] / 100)) : ($item->discount);
        $finalTotal = $lineTotal - $discount;

        $item->update(array_merge($validated, ['total' => $finalTotal]));

        return response()->json([
            'isSuccess' => true,
            'message' => 'Receiving item updated successfully.',
            'item' => $item
        ]);
    }

    // Delete receiving item
    public function deleteReceivingItem($id)
    {
        $item = ReceivingItem::find($id);

        if (!$item) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Receiving item not found.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Receiving item deleted successfully.',
        ]);
    }
}
