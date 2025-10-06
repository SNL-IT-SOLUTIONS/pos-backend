<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DtrRecord;
use Illuminate\Support\Carbon;

class DtrRecordController extends Controller
{
    public function __construct()
    {
        // All routes in this controller require authentication
        $this->middleware('auth:sanctum');
    }

    public function getDtrRecords(Request $request)
    {
        $query = DtrRecord::with(['user.role'])
            ->orderBy('login_start_time', 'desc');

        // 🔍 Search
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('username', 'LIKE', "%$search%");
            })
                ->orWhere('remarks', 'LIKE', "%$search%");
        }

        // 📄 Pagination
        $records = $query->paginate($request->get('per_page', 10));

        // 🧠 Transform data for display
        $data = $records->getCollection()->transform(function ($record) {
            return [
                'id'           => $record->id,
                'employee'     => $record->user
                    ? $record->user->first_name . ' ' . $record->user->last_name
                    : null,
                'position'     => $record->user && $record->user->role
                    ? $record->user->role->role_name
                    : null,
                'login_start'  => $record->login_start_time
                    ? Carbon::parse($record->login_start_time)->format('M d, Y, h:i A')
                    : null,
                'login_end'    => $record->login_end_time
                    ? Carbon::parse($record->login_end_time)->format('M d, Y, h:i A')
                    : null,
                'total_hours'  => $record->total_hours
                    ? number_format($record->total_hours, 2) . 'h'
                    : null,
                'remarks'      => $record->remarks,
                'status'       => $record->user && $record->user->is_login ? 'Online' : 'Offline',
            ];
        });

        // 📊 Summary Section
        $currentlyWorking = \App\Models\User::where('is_login', 1)->count(); // 👈 logged in users
        $totalRecords = DtrRecord::count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalHoursThisPeriod = DtrRecord::whereBetween('login_start_time', [$startOfMonth, $endOfMonth])
            ->sum('total_hours');

        // 🧮 Average Hours (based on total records, since active count removed)
        $averageHoursPerEmployee = $totalRecords > 0
            ? number_format($totalHoursThisPeriod / $totalRecords, 2)
            : 0;

        return response()->json([
            'isSuccess'   => true,
            'records'     => $data,
            'summary'     => [
                'currently_working'        => $currentlyWorking,
                'total_logged_in'          => $currentlyWorking,
                'total_records'            => $totalRecords,
                'total_hours_this_period'  => number_format($totalHoursThisPeriod, 2) . 'h',
                'average_hours_per_day'    => $averageHoursPerEmployee . 'h',
            ],
            'pagination'  => [
                'current_page' => $records->currentPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
                'last_page'    => $records->lastPage(),
            ]
        ]);
    }
}
