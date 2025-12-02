<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Campain;
use Illuminate\Http\Request;

class CampainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $campains = Campain::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            $campains,
            'Campañas obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string'],
                'state' => ['nullable', 'string'],
                'type' => ['required', 'string'],
                'begin_time' => ['required', 'date'],
                'end_time' => ['required', 'date'],
                'agents' => ['nullable', 'string'],
                'business_id' => ['required', 'integer', 'exists:businesses,id'],
            ]);
            
            if (!isset($validated['state'])) {
                $validated['state'] = 'ACTIVE';
            }

            $campain = Campain::create($validated);

            return ResponseBase::success(
                $campain,
                'Campaña creada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $campain = Campain::find($id);

        if (!$campain) {
            return ResponseBase::notFound('Campaña no encontrada');
        }

        return ResponseBase::success($campain, 'Campaña obtenida correctamente');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $campain = Campain::find($id);

        if (!$campain) {
            return ResponseBase::notFound('Campaña no encontrada');
        }
        
        try {
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

            return ResponseBase::success(
                $campain,
                'Campaña actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al actualizar la campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
