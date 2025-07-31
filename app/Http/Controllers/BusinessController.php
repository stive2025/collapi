<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $businesses=Business::paginate(10);
        return response()->json($businesses,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $business_create=Business::create($request->all());
        return response()->json($business_create,200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Business $business)
    {
        return response()->json($business,200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {   
        $business_update=$business->update($request->all());
        return response()->json($business_update,200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        $business_delete=$business->delete();
        return response()->json($business_delete,200);
    }
}