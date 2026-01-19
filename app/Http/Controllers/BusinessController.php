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
     * 
     * @OA\Get(
     *     path="/api/businesses",
     *     summary="Listar empresas",
     *     description="Obtiene una lista paginada de todas las empresas",
     *     operationId="getBusinessesList",
     *     tags={"Empresas"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Número de empresas por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de empresas obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Empresas obtenidas correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Empresa ABC"),
     *                         @OA\Property(property="state", type="string", example="ACTIVO"),
     *                         @OA\Property(property="prelation_order", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
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
            return ResponseBase::error(
                'Error al actualizar la empresa',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update the prelation order of a business.
     */
    public function updatePrelation(Request $request, Business $business)
    {
        try {
            $validated = $request->validate([
                'prelation_order' => ['required', 'array'],
                'prelation_order.*' => ['string', 'in:capital,interest,mora,safe,legal_expenses,other_values,collection_expenses'],
            ]);

            // Convertir el array a JSON para guardar en la base de datos
            $business->update([
                'prelation_order' => json_encode($validated['prelation_order'])
            ]);

            return ResponseBase::success(
                $business,
                'Orden de prelación actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al actualizar el orden de prelación',
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
            return ResponseBase::error(
                'Error al eliminar la empresa',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}