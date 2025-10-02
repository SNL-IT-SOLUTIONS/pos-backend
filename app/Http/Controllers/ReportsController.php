<?php

namespace App\Http\Controllers;

use App\Models\Sales;

use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function reportSales(Request $request)
    {
        $filter = $request->input('filter', 'daily'); // daily, weekly, yearly, customer

        $query = Sales::query()
            ->where('status', 'completed'); // only count finished sales

        switch ($filter) {
            case 'daily':
                $report = $query->selectRaw('DATE(created_at) as date, SUM(net_amount) as total_sales, COUNT(*) as total_transactions')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
                break;

            case 'weekly':
                $report = $query->selectRaw('YEARWEEK(created_at, 1) as week, SUM(net_amount) as total_sales, COUNT(*) as total_transactions')
                    ->groupBy('week')
                    ->orderBy('week', 'desc')
                    ->get();
                break;

            case 'yearly':
                $report = $query->selectRaw('YEAR(created_at) as year, SUM(net_amount) as total_sales, COUNT(*) as total_transactions')
                    ->groupBy('year')
                    ->orderBy('year', 'desc')
                    ->get();
                break;

            case 'customer':
                $report = $query->selectRaw('customer_id, SUM(net_amount) as total_sales, COUNT(*) as total_transactions')
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

        return response()->json([
            'isSuccess' => true,
            'filter'    => $filter,
            'report'    => $report
        ]);
    }
}
