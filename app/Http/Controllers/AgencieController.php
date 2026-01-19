<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Http\Resources\AgencieResource;
use App\Models\Agencie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgencieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Agencie::query();

            if ($request->filled('name')) {
                $query->where('name', 'LIKE', '%' . $request->query('name') . '%');
            }

            $agencies = $query->orderBy('name')->get();

            return ResponseBase::success(
                AgencieResource::collection($agencies)->response()->getData(),
                'Agencias obtenidas correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching agencies', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error(
                'Error al obtener agencias',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:agencies,name'],
            ]);

            $agencie = Agencie::create($validated);

            return ResponseBase::success(
                new AgencieResource($agencie),
                'Agencia creada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la agencia',
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
        try {
            $agencie = Agencie::find($id);

            if (!$agencie) {
                return ResponseBase::notFound('Agencia no encontrada');
            }

            return ResponseBase::success(
                new AgencieResource($agencie),
                'Agencia obtenida correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener la agencia',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $agencie = Agencie::find($id);

            if (!$agencie) {
                return ResponseBase::notFound('Agencia no encontrada');
            }

            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255', 'unique:agencies,name,' . $id],
            ]);

            $agencie->update($validated);

            return ResponseBase::success(
                new AgencieResource($agencie),
                'Agencia actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {

            return ResponseBase::error(
                'Error al actualizar la agencia',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $agencie = Agencie::find($id);

            if (!$agencie) {
                return ResponseBase::notFound('Agencia no encontrada');
            }

            $agencie->delete();

            return ResponseBase::success(
                null,
                'Agencia eliminada exitosamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al eliminar la agencia',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}