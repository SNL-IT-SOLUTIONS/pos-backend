<?php

namespace App\Http\Controllers;

use App\Models\Receiving;
use App\Models\Item;
use App\Models\ReceivingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceivingController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }
    // Get all receivings with supplier + items
    public function getAllReceivings(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $query = Receiving::with(['supplier', 'items.item']);

        // 🔎 Optional filters
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $receivings = $query->paginate($perPage);

        return response()->json([
            'isSuccess'  => true,
            'receivings' => $receivings->items(),
            'pagination' => [
                'current_page' => $receivings->currentPage(),
                'per_page'     => $receivings->perPage(),
                'total'        => $receivings->total(),
                'last_page'    => $receivings->lastPage(),
            ],
        ]);
    }


    //  Get single receiving with items
    public function getReceivingById($id)
    {
        $receiving = Receiving::with(['supplier', 'items.item'])->find($id);

        if (!$receiving) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Receiving not found.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'receiving' => $receiving
        ]);
    }

    // Create receiving with items
    public function createReceiving(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'expected_delivery_date' => 'nullable|date',
                'order_notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => [
                    'required',
                    'integer',
                    Rule::exists('items', 'id'), // basic exists validation
                ],
                'items.*.qty' => 'required|integer|min:1',
                'items.*.discount' => 'nullable|numeric|min:0|max:100',
            ]);

            // Extra validation: ensure items belong to supplier
            foreach ($validated['items'] as $itemData) {
                $item = Item::find($itemData['item_id']);
                if ($item->supplier_id != $validated['supplier_id']) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => "Item {$item->id} does not belong to the selected supplier."
                    ], 422);
                }
            }

            // Create receiving header
            $receiving = Receiving::create([
                'supplier_id' => $validated['supplier_id'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'order_notes' => $validated['order_notes'] ?? null,
                'total' => 0,
                'discount_total' => 0,
                'amount_due' => 0,
                'status' => 'Pending'
            ]);

            $total = 0;
            $discountTotal = 0;

            foreach ($validated['items'] as $itemData) {
                $item = Item::findOrFail($itemData['item_id']);
                $lineTotal = $item->cost * $itemData['qty'];
                $discount = isset($itemData['discount']) ? ($lineTotal * ($itemData['discount'] / 100)) : 0;
                $finalTotal = $lineTotal - $discount;

                ReceivingItem::create([
                    'receiving_id' => $receiving->id,
                    'item_id' => $item->id,
                    'cost' => $item->cost, // pulled from DB
                    'qty' => $itemData['qty'],
                    'discount' => $itemData['discount'] ?? 0,
                    'total' => $finalTotal,
                ]);

                $total += $lineTotal;
                $discountTotal += $discount;
            }

            // Update header totals
            $receiving->update([
                'total' => $total,
                'discount_total' => $discountTotal,
                'amount_due' => $total - $discountTotal,
            ]);

            DB::commit();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Receiving created successfully.',
                'receiving' => $receiving->load('items.item')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create receiving.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // ✅ Mark receiving as completed (update stock)
    public function completeReceiving($id)
    {
        $receiving = Receiving::with('items.item')->find($id);

        if (!$receiving) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Receiving not found.',
            ], 404);
        }

        if ($receiving->status === 'Completed') {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Receiving already completed.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($receiving->items as $line) {
                $line->item->increment('stock', $line->qty);
            }

            $receiving->update(['status' => 'Completed']);

            DB::commit();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Receiving completed and stock updated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to complete receiving.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
