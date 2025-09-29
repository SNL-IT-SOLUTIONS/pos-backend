<?php

namespace App\Http\Controllers;

use App\Models\BusinessInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessInformationController extends Controller
{
    //TEST
    public function __construct()
    {
        // Protect all endpoints with authentication
        $this->middleware('auth:sanctum');
    }

    /**
     * Get the business information (single record).
     */
    public function getBusinessInformation()
    {
        $info = BusinessInformation::first(); // only one record
        return response()->json($info);
    }

    /**
     * Create or Update the business information (single record).
     */
    public function saveBusinessInformation(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|max:20',
            'phone_number'  => 'nullable|string|max:50',
            'email_address' => 'nullable|email|max:150',
            'website'       => 'nullable|string|max:150',
            'tax_id_ein'    => 'nullable|string|max:50',
        ]);

        // Create or update the first record only
        $info = BusinessInformation::updateOrCreate(
            ['id' => 1],
            array_merge($validated, [
                'updated_by' => Auth::id() // ✅ actually save in DB
            ]) // force only one record
        );

        return response()->json([
            'isSuccess' => true,
            'data' => $info,
            'updated_by' => Auth::user()->id, // shows who updated it
        ]);
    }
}
