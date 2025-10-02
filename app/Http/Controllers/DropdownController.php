<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Customers;
use App\Models\GiftCards;
use App\Models\Roles;
use App\Models\Sales;
use App\Models\Category;
use App\Models\Card;
use App\Models\Supplier;
use App\Models\User;

class DropdownController extends Controller
{
    // Get all active categories
    public function getCategories()
    {
        $categories = Category::select('id', 'category_name', 'description')
            ->where('is_archived', 0) // only active categories
            ->get();

        return response()->json([
            'isSuccess' => true,
            'categories' => $categories
        ]);
    }

    // Get all active cards (gift/loyalty cards)
    public function getCards()
    {
        $cards = Card::select('id', 'card_name', 'description', 'value')
            ->where('is_active', 1)
            ->where('is_archived', 0)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'cards' => $cards
        ]);
    }


    public function getRoles()
    {
        $roles = Roles::select('id', 'role_name', 'description')
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'roles' => $roles
        ]);
    }

    // Get all active items
    public function getItems()
    {
        $items = Item::select('id', 'name', 'price', 'stock')
            ->where('stock', '>', 0)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'items' => $items
        ]);
    }

    // Get all customers
    public function getCustomers()
    {
        $customers = Customers::select('id', 'first_name', 'last_name')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'customers' => $customers
        ]);
    }

    // Get all active gift cards
    public function getGiftCards()
    {
        $giftCards = GiftCards::select('id', 'gift_card_number', 'gift_card_name', 'balance')
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'gift_cards' => $giftCards
        ]);
    }

    // Get all past sales (optional, for dropdown)
    public function getSales()
    {
        $sales = Sales::select('id', 'customer_id', 'net_amount', 'created_at')
            ->with('items') // include items
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'sales' => $sales
        ]);
    }

    public function getSuppliers()
    {
        $suppliers = Supplier::with(['items' => function ($query) {
            $query->select(
                'id',
                'item_name',
                'product_image',
                'description',
                'category_id',
                'supplier_id',
                'cost',
                'discount',
                'price',
                'margin',
                'stock',
                'min_stock',
                'barcode',
                'is_active',
                'is_archived'
            );
        }])
            ->select('id', 'company_name', 'contact_person', 'phone', 'email')
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'suppliers' => $suppliers
        ]);
    }

    public function getUsers()
    {

        $users = User::select('id', 'first_name', 'last_name', 'email')
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'users' => $users
        ]);
    }
}
