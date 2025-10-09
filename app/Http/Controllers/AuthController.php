<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\BusinessInformation;
use App\Models\Roles;
use Carbon\Carbon;
use App\Models\DtrRecord;
use App\Models\Sales;
use App\Models\SaleItem;
use App\Models\Customers;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * User Login (allow email or username)
     */


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => 'required|string', // email or username
            'password' => 'required|string'
        ]);

        $fieldType = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$fieldType => $credentials['login'], 'password' => $credentials['password']])) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user()->load('role');
        $token = $user->createToken('auth_token')->plainTextToken;

        $businessInfo = BusinessInformation::first();

        // 📝 Set remarks based on role
        $remarks = match ($user->role->role_name ?? '') {
            'Admin'   => 'Admin shift',
            'Cashier' => 'Regular shift',
            default   => 'Login'
        };

        // ✅ Update user login status
        $user->update(['is_login' => true]);

        // 📝 Insert DTR record on login
        DtrRecord::create([
            'user_id'          => $user->id,
            'login_start_time' => Carbon::now(),
            'remarks'          => $remarks,
        ]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Login successful.',
            'user'      => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'username'   => $user->username,
                'role_id'    => $user->role_id,
                'role_name'  => $user->role ? $user->role->role_name : null,
                'is_login'   => $user->is_login, //return login status
            ],
            'token'     => $token,
            'business'  => $businessInfo,
        ]);
    }

    /**
     * User Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // ✅ Update user login status
        $user->update(['is_login' => false]);

        // ⏱ Update latest DTR record
        $lastRecord = DtrRecord::where('user_id', $user->id)
            ->latest()
            ->first();

        if ($lastRecord && !$lastRecord->login_end_time) {
            $lastRecord->login_end_time = Carbon::now();

            // Calculate total hours worked
            $hours = Carbon::parse($lastRecord->login_start_time)->diffInHours(Carbon::now());
            $lastRecord->total_hours = $hours;

            // Update remarks based on hours
            if ($hours < 6) {
                $lastRecord->remarks = 'Part-time shift';
            } elseif ($hours <= 9) {
                $lastRecord->remarks = 'Regular shift';
            } else {
                $lastRecord->remarks = 'Overtime shift';
            }

            $lastRecord->save();
        }

        // 🔑 Revoke tokens
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'isSuccess' => true,
            'message'   => 'Logged out successfully'
        ]);
    }


    //DASHBOARD
    public function getDashboard(Request $request)
    {
        // ---- TODAY'S PERFORMANCE ----
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $todaySales = Sales::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->selectRaw('SUM(net_amount) as total_revenue, COUNT(*) as total_orders')
            ->first();

        $yesterdaySales = Sales::where('status', 'completed')
            ->whereDate('created_at', $yesterday)
            ->selectRaw('SUM(net_amount) as total_revenue, COUNT(*) as total_orders')
            ->first();

        $todayRevenue = $todaySales->total_revenue ?? 0;
        $yesterdayRevenue = $yesterdaySales->total_revenue ?? 0;
        $todayOrders = $todaySales->total_orders ?? 0;
        $yesterdayOrders = $yesterdaySales->total_orders ?? 0;

        // 👥 Active Customers
        $activeCustomers = Customers::count();

        // ---- TREND CALCULATIONS ----
        $revenueTrend = $yesterdayRevenue > 0 ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 : 100;
        $orderTrend = $yesterdayOrders > 0 ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 : 100;
        $customerTrend = $activeCustomers > 0 ? ($activeCustomers / 100) * 100 : 100;

        // ---- MONTHLY OVERVIEW ----
        $monthlyRevenue = Sales::selectRaw('MONTH(created_at) as month, SUM(net_amount) as total')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyOrders = Sales::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // clean numeric arrays (no "months" key)
        $revenueData = [];
        $orderData = [];
        for ($i = 1; $i <= 12; $i++) {
            $revenueData[] = (float) ($monthlyRevenue[$i] ?? 0);
            $orderData[]   = (int) ($monthlyOrders[$i] ?? 0);
        }

        // ---- WEEKLY SALES TREND ----
        $weeklySales = Sales::selectRaw('DAYNAME(created_at) as day, SUM(net_amount) as total')
            ->where('status', 'completed')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weeklyTrend = array_map(fn($day) => (float)($weeklySales[$day] ?? 0), $days);

        // ---- TOP SELLING PRODUCTS ----
        $topProducts = SaleItem::select('item_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('item_id')
            ->orderByDesc('total_sold')
            ->with('item:id,item_name,stock')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'product' => $item->item->item_name ?? 'Unknown',
                'sold' => (int) $item->total_sold,
                'stock' => (int) $item->item->stock ?? 0,
            ]);

        // ---- INVENTORY ALERTS ----
        $inventoryAlerts = Item::orderBy('stock', 'asc')
            ->take(5)
            ->get(['item_name', 'stock'])
            ->map(fn($item) => [
                'product' => $item->item_name,
                'stock' => (int) $item->stock,
                'status' => match (true) {
                    $item->stock <= 2 => 'Critical',
                    $item->stock <= 5 => 'Low Stock',
                    default => 'Good',
                },
            ]);

        // ---- FINAL RESPONSE ----
        return response()->json([
            'isSuccess' => true,
            'daily_summary' => [
                'date' => $today,
                'total_revenue' => (float) $todayRevenue,
                'total_orders' => (int) $todayOrders,
                'revenue_trend' => round($revenueTrend, 2),
                'orders_trend' => round($orderTrend, 2),
                'active_customers' => (int) $activeCustomers,
                'customer_trend' => round($customerTrend, 2),
            ],
            'monthly_overview' => [
                'revenue' => $revenueData,
                'orders' => $orderData,
            ],
            'weekly_sales_trend' => [
                'days' => $days,
                'sales' => $weeklyTrend,
            ],
            'top_selling_products' => $topProducts,
            'inventory_alerts' => $inventoryAlerts,
        ]);
    }




    /**
     * Get Authenticated User
     */
    public function me(Request $request)
    {
        return response()->json([
            'isSuccess' => true,
            'user'      => $request->user()
        ]);
    }
}
