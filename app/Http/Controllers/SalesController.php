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
        $user = auth()->user();
        $perPage = $request->input('per_page', 10);

        $salesQuery = Sales::with('items.item')
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc');

        // ✅ Check role by role_name (non-admins only see their own sales)
        if (strtolower($user->role->role_name) !== 'admin') {
            $salesQuery->where('held_by', $user->id);
        }

        // ⚡ Cursor pagination for infinite scroll
        $sales = $salesQuery->cursorPaginate($perPage);

        if ($sales->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'No sales found.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'sales'     => $sales->items(), // current batch of sales
            'pagination' => [
                'per_page'    => $sales->perPage(),
                'next_cursor' => $sales->nextCursor()?->encode(),
                'prev_cursor' => $sales->previousCursor()?->encode(),
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
        $perPage = $request->input('per_page', 10); // default 10 per page

        $sales = Sales::with([
            'items.item',
            'customer:id,first_name,last_name' // eager load both name parts
        ])
            ->where('status', 'held')
            ->orderBy('created_at', 'asc')
            ->cursorPaginate($perPage);

        $formattedSales = $sales->map(function ($sale) {
            $customerName = $sale->customer
                ? trim($sale->customer->first_name . ' ' . $sale->customer->last_name)
                : 'Walk-in Customer';

            return [
                'id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' => $customerName,
                'total_amount' => $sale->total_amount,
                'discount' => $sale->discount,
                'net_amount' => $sale->net_amount,
                'payment_type' => $sale->payment_type,
                'amount_paid' => $sale->amount_paid,
                'change' => $sale->change,
                'status' => $sale->status,
                'held_by' => $sale->held_by,
                'items' => $sale->items,
                'created_at' => $sale->created_at,
            ];
        });

        return response()->json([
            'isSuccess'  => true,
            'held_sales' => $formattedSales,
            'pagination' => [
                'per_page'    => $sales->perPage(),
                'next_cursor' => $sales->nextCursor()?->encode(),
                'prev_cursor' => $sales->previousCursor()?->encode(),
            ],
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
            'payment_type'   => 'nullable|string|in:cash,card,gcash',
            'amount_paid'    => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {

            //  Calculate total
            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);
                if ($item['qty'] > $product->stock) {
                    throw new \Exception("Not enough stock for item {$product->item_name}");
                }
                $total += $product->price * $item['qty'];
            }

            // Apply gift card if provided
            $discount = 0;
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

            // Net amount and payment logic
            $net = $total - $discount;
            $paymentType = $validated['payment_type'] ?? 'cash';
            $amountPaid = $validated['amount_paid'];
            $change = $amountPaid - $net;

            if ($amountPaid < $net) {
                throw new \Exception("Insufficient payment. Customer must pay at least ₱" . number_format($net, 2));
            }

            //  Create sale record
            $sale = Sales::create([
                'customer_id'  => $validated['customer_id'] ?? null,
                'total_amount' => $total,
                'discount'     => $discount,
                'net_amount'   => $net,
                'payment_type' => $paymentType,
                'amount_paid'  => $amountPaid,
                'change'       => $change,
                'status'       => 'completed',
            ]);

            //  Create sale items and decrease stock
            foreach ($validated['items'] as $item) {
                $product = Item::findOrFail($item['id']);

                SaleItem::create([
                    'sale_id'  => $sale->id,
                    'item_id'  => $product->id,
                    'quantity' => $item['qty'],
                    'price'    => $product->price,
                    'total'    => $product->price * $item['qty'],
                    'status'   => 'completed',
                    'held_by'  => auth()->id(),
                ]);

                $product->stock -= $item['qty'];
                $product->save();
            }

            // Build receipt data
            $receipt = [
                'sale_id'       => $sale->id,
                'date'          => now()->format('M d, Y h:i A'),
                'items'         => $sale->items->map(function ($i) {
                    return [
                        'item_name' => $i->item->item_name ?? 'N/A',
                        'quantity'  => $i->quantity,
                        'price'     => number_format($i->price, 2),
                        'total'     => number_format($i->total, 2),
                    ];
                }),
                'summary' => [
                    'total_amount' => '₱' . number_format($total, 2),
                    'discount'     => '₱' . number_format($discount, 2),
                    'net_amount'   => '₱' . number_format($net, 2),
                    'amount_paid'  => '₱' . number_format($amountPaid, 2),
                    'change'       => '₱' . number_format($change, 2),
                    'payment_type' => ucfirst($paymentType),
                ],
            ];

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Sale completed successfully.',
                'sale'      => $sale->load('items.item'),
                'receipt'   => $receipt,
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
            $amountPaid = $request->input('amount_paid', 0);

            // Gift card logic
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

            // Compute totals
            $sale->discount = $discount;
            $sale->net_amount = $sale->total_amount - $discount;
            $sale->payment_type = $paymentType;
            $sale->amount_paid = $amountPaid;
            $sale->change = max($amountPaid - $sale->net_amount, 0); // prevent negative change
            $sale->status = 'completed';
            $sale->save();

            //  Deduct stock for each item
            foreach ($sale->items as $saleItem) {
                $product = Item::findOrFail($saleItem->item_id);
                if ($saleItem->quantity > $product->stock) {
                    throw new \Exception("Not enough stock for item {$product->item_name}");
                }
                $product->stock -= $saleItem->quantity;
                $product->save();
            }

            //  Receipt summary
            $receipt = [
                'sale_id'      => $sale->id,
                'customer_id'  => $sale->customer_id,
                'total_amount' => $sale->total_amount,
                'discount'     => $sale->discount,
                'net_amount'   => $sale->net_amount,
                'amount_paid'  => $sale->amount_paid,
                'change'       => $sale->change,
                'payment_type' => $sale->payment_type,
                'items'        => $sale->items->map(function ($item) {
                    return [
                        'item_name' => $item->item->item_name,
                        'quantity'  => $item->quantity,
                        'price'     => $item->price,
                        'total'     => $item->total,
                    ];
                }),
            ];

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Held sale completed successfully.',
                'receipt'   => $receipt,
            ]);
        });
    }
}
