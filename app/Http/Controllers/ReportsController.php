<?php

namespace App\Http\Controllers;

use App\Models\Sales;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ReportsController extends Controller
{

    public function reportSales(Request $request)
    {
        $filter = $request->input('filter', 'daily'); // daily, weekly, yearly, customer
        $range  = $request->input('range', null);     // today, week, month, quarter, year, custom
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = Sales::query()
            ->where('status', 'completed');

        // ✅ Apply range filters
        switch ($range) {
            case 'today':
                $query->whereDate('created_at', now());
                break;

            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;

            case 'month':
                $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                break;

            case 'quarter':
                $query->whereBetween('created_at', [now()->firstOfQuarter(), now()->lastOfQuarter()]);
                break;

            case 'year':
                $query->whereYear('created_at', now()->year);
                break;

            case 'custom':
                if ($dateFrom && $dateTo) {
                    $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                }
                break;
        }

        // ✅ Build report based on filter type
        switch ($filter) {
            case 'daily':
                $report = $query->selectRaw('DATE(created_at) as date, 
                                         SUM(net_amount) as total_sales, 
                                         COUNT(*) as total_transactions,
                                         (SUM(net_amount) / COUNT(*)) as avg_order_value')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
                break;

            case 'weekly':
                $report = $query->selectRaw('YEARWEEK(created_at, 1) as week, 
                                         SUM(net_amount) as total_sales, 
                                         COUNT(*) as total_transactions,
                                         (SUM(net_amount) / COUNT(*)) as avg_order_value')
                    ->groupBy('week')
                    ->orderBy('week', 'desc')
                    ->get();
                break;

            case 'yearly':
                $report = $query->selectRaw('YEAR(created_at) as year, 
                                         SUM(net_amount) as total_sales, 
                                         COUNT(*) as total_transactions,
                                         (SUM(net_amount) / COUNT(*)) as avg_order_value')
                    ->groupBy('year')
                    ->orderBy('year', 'desc')
                    ->get();
                break;

            case 'customer':
                $report = $query->selectRaw('customer_id, 
                                         SUM(net_amount) as total_sales, 
                                         COUNT(*) as total_transactions,
                                         (SUM(net_amount) / COUNT(*)) as avg_order_value')
                    ->groupBy('customer_id')
                    ->with('customer:id,first_name,last_name,email')
                    ->orderByDesc('total_sales')
                    ->get();
                break;

            default:
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Invalid filter. Use daily, weekly, yearly, or customer.'
                ], 400);
        }

        // ✅ Items Sold (from sales_items)
        $itemsSold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed');

        if ($range === 'today') {
            $itemsSold->whereDate('sales.created_at', now());
        } elseif ($range === 'week') {
            $itemsSold->whereBetween('sales.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($range === 'month') {
            $itemsSold->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($range === 'quarter') {
            $itemsSold->whereBetween('sales.created_at', [now()->firstOfQuarter(), now()->lastOfQuarter()]);
        } elseif ($range === 'year') {
            $itemsSold->whereYear('sales.created_at', now()->year);
        } elseif ($range === 'custom' && $dateFrom && $dateTo) {
            $itemsSold->whereBetween('sales.created_at', [$dateFrom, $dateTo]);
        }

        $itemsSold = $itemsSold->sum('sale_items.quantity');

        return response()->json([
            'isSuccess' => true,
            'filter'    => $filter,
            'range'     => $range,
            'report'    => $report,
            'items_sold' => $itemsSold
        ]);
    }

    public function salesAnalytics(Request $request)
    {
        // ---- DAILY SALES OVERVIEW ----
        $today = now()->toDateString();

        $daily = Sales::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->selectRaw('
            SUM(net_amount) as total_sales,
            COUNT(*) as total_transactions,
            (SUM(net_amount) / NULLIF(COUNT(*),0)) as avg_order_value
        ')
            ->first();

        $itemsSoldToday = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', $today)
            ->sum('sale_items.quantity');

        $profitToday = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('items', 'items.id', '=', 'sale_items.item_id') // assuming "items" table has cost
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', $today)
            ->selectRaw('SUM((sale_items.price - items.cost) * sale_items.quantity) as profit')
            ->value('profit');


        // ---- MONTHLY REVENUE & PROFIT ----
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        $monthly = Sales::where('status', 'completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('
            SUM(net_amount) as total_revenue,
            COUNT(*) as total_transactions,
            (SUM(net_amount) / NULLIF(COUNT(*),0)) as avg_order_value
        ')
            ->first();

        $itemsSoldMonth = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->sum('sale_items.quantity');

        $profitMonth = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('items', 'items.id', '=', 'sale_items.item_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->selectRaw('SUM((sale_items.price - items.cost) * sale_items.quantity) as profit')
            ->value('profit');

        return response()->json([
            'isSuccess' => true,
            'daily_overview' => [
                'date'              => $today,
                'day_of_week'       => now()->format('l'),
                'total_sales'       => $daily->total_sales ?? 0,
                'total_transactions' => $daily->total_transactions ?? 0,
                'avg_order_value'   => $daily->avg_order_value ?? 0,
                'items_sold'        => $itemsSoldToday ?? 0,
                'profit'            => $profitToday ?? 0,
            ],
            'monthly_summary' => [
                'month'             => now()->format('F Y'),
                'total_revenue'     => $monthly->total_revenue ?? 0,
                'total_profit'      => $profitMonth ?? 0,
                'total_transactions' => $monthly->total_transactions ?? 0,
                'avg_order_value'   => $monthly->avg_order_value ?? 0,
                'items_sold'        => $itemsSoldMonth ?? 0,
            ]
        ]);
    }


    public function itemPerformanceReport()
    {
        // ✅ Top Selling Items by Quantity & Revenue
        $topItems = DB::table('sale_items')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'items.id',
                'items.item_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total) as total_revenue')
            )
            ->where('sales.status', 'completed')
            ->groupBy('items.id', 'items.item_name')
            ->orderByDesc('total_quantity')
            ->limit(10) // top 10 best sellers
            ->get();

        // ✅ Sales by Category (Revenue distribution)
        $salesByCategory = DB::table('sale_items')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'categories.id',
                'categories.category_name',
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('SUM(sale_items.quantity) as total_quantity')
            )
            ->where('sales.status', 'completed')
            ->groupBy('categories.id', 'categories.category_name')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'top_items' => $topItems,
            'sales_by_category' => $salesByCategory,
        ]);
    }

    public function getPaymentAnalysisReport(Request $request)
    {


        $paymentAnalysis = DB::table('sales')
            ->select(
                'payment_type',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(net_amount) as total_revenue')
            )
            ->where('status', 'completed')
            ->groupBy('payment_type')
            ->get();

        return response()->json([
            'report_type' => 'Payment Method Analysis',

            'data' => $paymentAnalysis
        ]);
    }



    public function getBusinessTrendsForecast()
    {
        $today = now();
        $lastYear = $today->copy()->subYear();

        // === Revenue Growth YoY ===
        $currentRevenue = DB::table('sales')
            ->where('status', 'completed')
            ->whereYear('created_at', $today->year)
            ->sum('net_amount');

        $lastYearRevenue = DB::table('sales')
            ->where('status', 'completed')
            ->whereYear('created_at', $lastYear->year)
            ->sum('net_amount');

        $revenueGrowth = $lastYearRevenue > 0
            ? (($currentRevenue - $lastYearRevenue) / $lastYearRevenue) * 100
            : 0;

        // === Customer Growth YoY ===
        $currentCustomers = DB::table('sales')
            ->whereYear('created_at', $today->year)
            ->distinct('customer_id')
            ->count('customer_id');

        $lastYearCustomers = DB::table('sales')
            ->whereYear('created_at', $lastYear->year)
            ->distinct('customer_id')
            ->count('customer_id');

        $customerGrowth = $lastYearCustomers > 0
            ? (($currentCustomers - $lastYearCustomers) / $lastYearCustomers) * 100
            : 0;

        // === Average Order Value (YoY change) ===
        $currentAOV = DB::table('sales')
            ->where('status', 'completed')
            ->whereYear('created_at', $today->year)
            ->avg('net_amount');

        $lastYearAOV = DB::table('sales')
            ->where('status', 'completed')
            ->whereYear('created_at', $lastYear->year)
            ->avg('net_amount');

        $aovChange = $lastYearAOV > 0
            ? (($currentAOV - $lastYearAOV) / $lastYearAOV) * 100
            : 0;

        // === Forecasts (Next 30 Days) ===
        $avgDailyRevenue = DB::table('sales')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today->copy()->subDays(30), $today])
            ->avg('net_amount');

        $avgDailyTransactions = DB::table('sales')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today->copy()->subDays(30), $today])
            ->count() / 30;

        $forecastRevenue = round($avgDailyRevenue * 30, 2);
        $forecastTransactions = round($avgDailyTransactions * 30);

        // === Top Category (By Revenue) ===
        $topCategory = DB::table('sale_items')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->select('categories.category_name', DB::raw('SUM(sale_items.total) as revenue'))
            ->groupBy('categories.category_name')
            ->orderByDesc('revenue')
            ->first();

        return response()->json([
            'report_type' => 'Business Trends & Forecasts',
            'growth_trends' => [
                'revenue_growth_yoy' => round($revenueGrowth, 2) . '%',
                'customer_growth_yoy' => round($customerGrowth, 2) . '%',
                'avg_order_value_change' => round($aovChange, 2) . '%',
            ],
            'forecasts_next_30_days' => [
                'expected_revenue' => $forecastRevenue,
                'expected_transactions' => $forecastTransactions,
                'top_category' => $topCategory ? $topCategory->category_name : null,
            ]
        ]);
    }
}
