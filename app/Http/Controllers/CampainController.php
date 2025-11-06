<?php

namespace App\Http\Controllers;

use App\Models\Campain;
use Illuminate\Http\Request;

class CampainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $managements = Campain::paginate(request('per_page'));
        return response()->json($managements,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'state' => ['nullable', 'string'],
            'type' => ['required', 'string'],
            'begin_time' => ['required', 'date'],
            'end_time' => ['required', 'date'],
            'agents' => ['nullable', 'string'],
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
        ]);

        // Establecer estado por defecto si no se envía
        if (!isset($validated['state'])) {
            $validated['state'] = 'ACTIVE';
        }

        $campain = Campain::create($validated);

        return response()->json([
            'message' => 'Campaña creada exitosamente',
            'data' => $campain
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $campain = Campain::find($id);

        if (!$campain) {
            return response()->json([
                'message' => 'Campaña no encontrada'
            ], 404);
        }

        return response()->json([
            'data' => $campain
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $campain = Campain::find($id);

        if (!$campain) {
            return response()->json([
                'message' => 'Campaña no encontrada'
            ], 404);
        }
        
        $validated = $request->validate([
            'name' => ['sometimes', 'string'],
            'state' => ['nullable', 'string'],
            'type' => ['sometimes', 'string'],
            'begin_time' => ['sometimes', 'date'],
            'end_time' => ['sometimes', 'date'],
            'agents' => ['nullable', 'string'],
            'business_id' => ['sometimes', 'integer', 'exists:businesses,id'],
        ]);

        $campain->update($validated);

        return response()->json([
            'message' => 'Campaña actualizada exitosamente',
            'data' => $campain
        ]);
    }
}
