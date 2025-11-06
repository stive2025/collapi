<?php

namespace App\Http\Controllers;

use App\Models\Campain;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Management;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $managements = Management::paginate(request('per_page'));
        return response()->json($managements,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'state' => ['required', 'string'],
            'substate' => ['required', 'string'],
            'observation' => ['nullable', 'string'],
            'promise_date' => ['required', 'date'],
            'promise_amount' => ['nullable', 'numeric'],
            'created_by' => ['required', 'integer'],
            'call_id' => ['nullable', 'integer'],
            'call_collection' => ['nullable', 'string'],
            'days_past_due' => ['required', 'integer'],
            'paid_fees' => ['required', 'integer'],
            'pending_fees' => ['required', 'integer'],
            'managed_amount' => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->state, ['CONTACTADO EFECTIVO', 'LOCALIZADO']);
                }),
                'nullable',
                'numeric'
            ],
            'client_id' => ['required', 'integer'],
            'credit_id' => ['required', 'integer'],
            'campain_id' => ['required', 'integer'],
        ]);

        // Validar existencia de relaciones
        $clientExists = Client::find($validated['client_id']);
        $creditExists = Credit::find($validated['credit_id']);
        $campainExists = Campain::find($validated['campain_id']);
        
        if (!$clientExists || !$creditExists || !$campainExists) {
            return response()->json([
                'message' => 'La gestión no se creó porque una de las relaciones no existe.',
                'missing' => [
                    'client' => !$clientExists,
                    'credit' => !$creditExists,
                    'campain' => !$campainExists,
                ]
            ], 200); // Estado 200 con advertencia
        }

        $management = Management::create($validated);

        return response()->json([
            'message' => 'Gestión registrada exitosamente',
            'data' => $management
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
         $management = Management::find($id);

        if (!$management) {
            return response()->json([
                'message' => 'Gestión no encontrada'
            ], 404);
        }

        return response()->json([
            'data' => $management
        ], 200);
    }
}