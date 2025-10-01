<?php

namespace App\Http\Controllers;

use App\Models\GiftCards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftCardsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Create new GiftCard
    public function createGiftCard(Request $request)
    {
        $validated = $request->validate([
            'card_id'          => 'required|integer|exists:cards,id',
            'gift_card_name'   => 'required|string|max:150|unique:gift_cards,gift_card_name',
            'description'      => 'nullable|string|max:255',
            'value'            => 'required|numeric|min:0',
            'balance'          => 'nullable|numeric|min:0',
            'expiration_date'  => 'nullable|date|after:today',
            'customer_id'      => 'required|integer|exists:customers,id',
            'is_active'        => 'required|boolean',
        ]);

        // Generate gift card number format: GC{card_id}-{year}
        $year = date('Y');
        $validated['gift_card_number'] = sprintf("GC%03d-%s", $validated['card_id'], $year);

        $giftCard = GiftCards::create($validated);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Gift card created successfully.',
            'gift_card' => $giftCard,
        ], 201);
    }


    // Get all GiftCards
    public function getGiftCards(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $giftCards = GiftCards::with(['customer', 'card'])->paginate($perPage);

        return response()->json([
            'isSuccess'  => true,
            'gift_cards' => $giftCards->items(),
            'pagination' => [
                'current_page' => $giftCards->currentPage(),
                'per_page'     => $giftCards->perPage(),
                'total'        => $giftCards->total(),
                'last_page'    => $giftCards->lastPage(),
            ],
        ]);
    }


    // Get single GiftCard by ID
    public function getGiftCardById($id)
    {
        $giftCard = GiftCards::with(['customer', 'card'])->find($id);

        if (!$giftCard) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Gift card not found.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'gift_card' => $giftCard,
        ], 200);
    }

    // Update GiftCard
    public function updateGiftCard(Request $request, $id)
    {
        $giftCard = GiftCards::find($id);

        if (!$giftCard) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Gift card not found.',
            ], 404);
        }

        $validated = $request->validate([
            'card_id'          => 'required|integer|exists:cards,id',
            'gift_card_name'   => 'required|string|max:150|unique:gift_cards,gift_card_name,' . $id,
            'description'      => 'nullable|string|max:255',
            'value'            => 'required|numeric|min:0',
            'balance'          => 'nullable|numeric|min:0',
            'expiration_date'  => 'nullable|date|after:today',
            'customer_id'      => 'required|integer|exists:customers,id',
            'is_active'        => 'required|boolean',
        ]);

        $giftCard->update($validated);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Gift card updated successfully.',
            'gift_card' => $giftCard,
        ], 200);
    }

    // Archive GiftCard (soft delete style)
    public function archiveGiftCard($id)
    {
        $giftCard = GiftCards::where('id', $id)->where('is_archived', 0)->first();

        if (!$giftCard) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Gift card not found or already archived.',
            ], 404);
        }

        $giftCard->update(['is_archived' => 1]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Gift card archived successfully.',
        ], 200);
    }
}
