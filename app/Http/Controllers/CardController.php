<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Create new Card
    public function createCard(Request $request)
    {
        $validated = $request->validate([
            'card_name'   => 'required|string|max:150|unique:cards,card_name',
            'description' => 'nullable|string|max:255',
            'value'       => 'required|numeric|min:0',
            'is_active'   => 'required|boolean',
        ]);

        $card = Card::create($validated);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Card created successfully.',
            'card'      => $card,
        ], 201);
    }

    // Get all Cards
    public function getCards()
    {
        $cards = Card::all();

        return response()->json([
            'isSuccess' => true,
            'cards'     => $cards,
        ], 200);
    }

    // Get single Card by ID
    public function getCardById($id)
    {
        $card = Card::find($id);

        if (!$card) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Card not found.',
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'card'      => $card,
        ], 200);
    }

    // Update Card
    public function updateCard(Request $request, $id)
    {
        $card = Card::find($id);

        if (!$card) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Card not found.',
            ], 404);
        }

        $validated = $request->validate([
            'card_name'   => 'required|string|max:150|unique:cards,card_name,' . $id,
            'description' => 'nullable|string|max:255',
            'value'       => 'required|numeric|min:0',
            'is_active'   => 'required|boolean',
        ]);

        $card->update($validated);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Card updated successfully.',
            'card'      => $card,
        ], 200);
    }

    // Archive Card
    public function archiveCard($id)
    {
        $card = Card::where('id', $id)->where('is_archived', 0)->first();

        if (!$card) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Card not found or already archived.',
            ], 404);
        }

        $card->update(['is_archived' => 1]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Card archived successfully.',
        ], 200);
    }
}
