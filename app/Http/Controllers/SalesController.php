<?php

namespace App\Http\Controllers;

use App\Models\Sales;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\GiftCards;

class SalesController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }
    public function createSale(Request $request)
    {
        $validated = $request->validate([
            'customer_id'    => 'nullable|integer|exists:customers,id',
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|integer|exists:items,id',
            'items.*.qty'    => 'required|integer|min:1',
            'gift_card_id'   => 'nullable|integer|exists:gift_cards,id',
        ]);

        return DB::transaction(function () use ($validated) {

            // Calculate total from item prices in DB
            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);
                if ($item['qty'] > $product->stock) {
                    throw new \Exception("Not enough stock for item {$product->name}");
                }
                $total += $product->price * $item['qty'];
            }

            $discount = 0;

            // Apply gift card if provided and active
            if (!empty($validated['gift_card_id'])) {
                $giftCard = GiftCards::where('id', $validated['gift_card_id'])
                    ->where('is_active', 1)
                    ->firstOrFail();

                $discount = min($giftCard->balance, $total);

                if ($giftCard->balance <= $total) {
                    $giftCard->is_active = 0;
                    $giftCard->balance = 0;
                } else {
                    $giftCard->balance -= $discount;
                }
                $giftCard->save();
            }

            $net = $total - $discount;

            // Default payment_type to 'cash'
            $paymentType = $validated['payment_type'] ?? 'cash';

            // Create sale record
            $sale = Sales::create([
                'customer_id'  => $validated['customer_id'] ?? null,
                'total_amount' => $total,
                'discount'     => $discount,
                'net_amount'   => $net,
                'payment_type' => $paymentType,
            ]);

            // Create sale items and decrease stock
            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);

                SaleItem::create([
                    'sale_id'  => $sale->id,
                    'item_id'  => $product->id,
                    'quantity' => $item['qty'],
                    'price'    => $product->price,
                    'total'    => $product->price * $item['qty'],
                ]);

                // Decrease stock
                $product->stock -= $item['qty'];
                $product->save();
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Sale created successfully.',
                'sale'      => $sale->load('items'),
            ], 201);
        });
    }
}
