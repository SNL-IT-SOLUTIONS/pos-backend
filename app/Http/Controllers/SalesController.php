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

    public function getAllSales(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $sales = Sales::with([
            'customer:id,first_name,last_name,email', // assuming customers table has these
            'items.item:id,item_name,price'          // load items with minimal fields
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'isSuccess'  => true,
            'sales'      => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'per_page'     => $sales->perPage(),
                'total'        => $sales->total(),
                'last_page'    => $sales->lastPage(),
            ]
        ]);
    }

    public function getSaleById($id)
    {
        $sale = Sales::with([
            'customer:id,first_name,last_name,email',
            'items.item:id,item_name,price'
        ])->find($id);

        if (!$sale) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Sale not found.'
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'sale'      => $sale
        ]);
    }

    public function getHeldSales(Request $request)
    {
        $perPage = $request->input('per_page', 10); // default 10 if not provided

        $sales = Sales::with('items.item')
            ->where('status', 'held')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'isSuccess'  => true,
            'held_sales' => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'per_page'     => $sales->perPage(),
                'total'        => $sales->total(),
                'last_page'    => $sales->lastPage(),
            ]
        ]);
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
                    'status'  => 'held',
                    'held_by' => auth()->id(),
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



    //Hold Sale
    public function holdSale(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'items'       => 'required|array|min:1',
            'items.*.id'  => 'required|integer|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {

            // Calculate total only, no stock deduction
            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);
                $total += $product->price * $item['qty'];
            }

            $sale = Sales::create([
                'customer_id'  => $validated['customer_id'] ?? null,
                'total_amount' => $total,
                'discount'     => 0,
                'net_amount'   => $total,
                'payment_type' => null,
                'status'       => 'held',
                'held_by'      => auth()->id(), // track who held it
            ]);

            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);

                SaleItem::create([
                    'sale_id'  => $sale->id,
                    'item_id'  => $product->id,
                    'quantity' => $item['qty'],
                    'price'    => $product->price,
                    'total'    => $product->price * $item['qty'],
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Sale placed on hold successfully.',
                'sale'      => $sale->load('items'),
            ], 201);
        });
    }



    //Complete Held Sale
    public function completeHeldSale($id, Request $request)
    {
        $sale = Sales::with('items')->where('status', 'held')->findOrFail($id);

        return DB::transaction(function () use ($sale, $request) {
            $discount = 0;
            $paymentType = $request->input('payment_type', 'cash');

            if ($request->filled('gift_card_id')) {
                $giftCard = GiftCards::where('id', $request->gift_card_id)
                    ->where('is_active', 1)
                    ->firstOrFail();

                $discount = min($giftCard->balance, $sale->total_amount);

                if ($giftCard->balance <= $sale->total_amount) {
                    $giftCard->is_active = 0;
                    $giftCard->balance = 0;
                } else {
                    $giftCard->balance -= $discount;
                }
                $giftCard->save();
            }

            $sale->discount = $discount;
            $sale->net_amount = $sale->total_amount - $discount;
            $sale->payment_type = $paymentType;
            $sale->status = 'completed';
            $sale->save();

            foreach ($sale->items as $saleItem) {
                $product = Item::findOrFail($saleItem->item_id);
                if ($saleItem->quantity > $product->stock) {
                    throw new \Exception("Not enough stock for item {$product->item_name}");
                }
                $product->stock -= $saleItem->quantity;
                $product->save();
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Held sale completed successfully.',
                'sale'      => $sale->load('items'),
            ]);
        });
    }
}
