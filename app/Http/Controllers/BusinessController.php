<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $businesses = Business::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            $businesses,
            'Empresas obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:50'],
                'prelation_order' => ['nullable', 'integer', 'min:0'],
            ]);

            $business = Business::create($validated);

            return ResponseBase::success(
                $business,
                'Empresa creada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating business', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al crear la empresa',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Business $business)
    {
        return ResponseBase::success(
            $business,
            'Empresa obtenida correctamente'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:50'],
                'prelation_order' => ['nullable', 'integer', 'min:0'],
            ]);

            $business->update($validated);

            return ResponseBase::success(
                $business,
                'Empresa actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating business', [
                'message' => $e->getMessage(),
                'business_id' => $business->id
            ]);

            return ResponseBase::error(
                'Error al actualizar la empresa',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        try {
            $business->delete();

            return ResponseBase::success(
                null,
                'Empresa eliminada exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error deleting business', [
                'message' => $e->getMessage(),
                'business_id' => $business->id
            ]);

            return ResponseBase::error(
                'Error al eliminar la empresa',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}