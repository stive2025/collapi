<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Http\Resources\CollectionContactResource;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = CollectionContact::query();

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->query('client_id'));
            }

            if ($request->filled('client_name')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->query('client_name') . '%');
                });
            }

            if ($request->filled('client_ci')) {
                $query->whereHas('client', function ($q) use ($request) {
                    $q->where('ci', $request->query('client_ci'));
                });
            }

            if ($request->filled('phone_number')) {
                $query->where('phone_number', 'like', '%' . $request->query('phone_number') . '%');
            }

            if ($request->filled('phone_type')) {
                $query->where('phone_type', $request->query('phone_type'));
            }

            if ($request->filled('phone_status')) {
                $query->where('phone_status', $request->query('phone_status'));
            }

            $orderBy = $request->query('order_by', 'created_at');
            $orderDir = $request->query('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);
            $contacts = $query->with(['client.credits'])->paginate($perPage);

            return ResponseBase::success(
                [
                    'data' => CollectionContactResource::collection($contacts),
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
                'Contactos obtenidos correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener contactos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Crear un nuevo contacto
     *
     * @OA\Post(
     *     path="/api/contacts",
     *     summary="Crear contacto",
     *     description="Crea un nuevo contacto asociado a un cliente",
     *     operationId="storeContact",
     *     tags={"Contactos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number", "phone_type", "client_id"},
     *             @OA\Property(property="phone_number", type="string", maxLength=15, example="0991234567", description="Número de teléfono"),
     *             @OA\Property(property="phone_type", type="string", maxLength=50, example="CELULAR", description="Tipo de teléfono (CELULAR, FIJO, etc.)"),
     *             @OA\Property(property="client_id", type="integer", example=1, description="ID del cliente"),
     *             @OA\Property(property="name", type="string", maxLength=255, example="Juan Pérez", description="Nombre del contacto"),
     *             @OA\Property(property="phone_status", type="string", maxLength=50, example="ACTIVE", description="Estado del teléfono"),
     *             @OA\Property(property="calls_effective", type="integer", example=0, description="Cantidad de llamadas efectivas"),
     *             @OA\Property(property="calls_not_effective", type="integer", example=0, description="Cantidad de llamadas no efectivas"),
     *             @OA\Property(property="credit_id", type="integer", example=1, description="ID del crédito asociado (opcional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Contacto creado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contacto creado correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="phone_number", type="string"),
     *                 @OA\Property(property="phone_type", type="string"),
     *                 @OA\Property(property="phone_status", type="string"),
     *                 @OA\Property(property="calls_effective", type="integer"),
     *                 @OA\Property(property="calls_not_effective", type="integer"),
     *                 @OA\Property(property="client_id", type="integer"),
     *                 @OA\Property(property="credit_id", type="integer"),
     *                 @OA\Property(property="created_by", type="integer"),
     *                 @OA\Property(property="created_at", type="string"),
     *                 @OA\Property(property="updated_at", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="phone_number", type="array", @OA\Items(type="string", example="El número de teléfono es obligatorio.")),
     *                 @OA\Property(property="phone_type", type="array", @OA\Items(type="string", example="El tipo de teléfono es obligatorio.")),
     *                 @OA\Property(property="client_id", type="array", @OA\Items(type="string", example="El cliente es obligatorio."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
     * )
     */
    public function store(ContactRequest $request)
    {
        try {
            $data = $request->validated();

            $contact = CollectionContact::create($data);

            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto creado correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function show(CollectionContact $contact)
    {
        try {
            $contact->load(['client.credits']);

            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto obtenido correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Actualizar un contacto
     *
     * @OA\Put(
     *     path="/api/contacts/{id}",
     *     summary="Actualizar contacto",
     *     description="Actualiza un contacto existente",
     *     operationId="updateContact",
     *     tags={"Contactos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del contacto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Juan Perez", description="Nombre del contacto"),
     *             @OA\Property(property="phone_number", type="string", maxLength=15, example="0991234567", description="Numero de telefono"),
     *             @OA\Property(property="phone_type", type="string", maxLength=50, example="CELULAR", description="Tipo de telefono"),
     *             @OA\Property(property="phone_status", type="string", maxLength=50, example="ACTIVE", description="Estado del telefono"),
     *             @OA\Property(property="calls_effective", type="integer", example=5, description="Llamadas efectivas"),
     *             @OA\Property(property="calls_not_effective", type="integer", example=2, description="Llamadas no efectivas"),
     *             @OA\Property(property="client_id", type="integer", example=1, description="ID del cliente"),
     *             @OA\Property(property="credit_id", type="integer", example=1, description="ID del credito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contacto actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contacto actualizado correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="phone_number", type="string"),
     *                 @OA\Property(property="phone_type", type="string"),
     *                 @OA\Property(property="phone_status", type="string"),
     *                 @OA\Property(property="calls_effective", type="integer"),
     *                 @OA\Property(property="calls_not_effective", type="integer"),
     *                 @OA\Property(property="client_id", type="integer"),
     *                 @OA\Property(property="client_name", type="string"),
     *                 @OA\Property(property="client_ci", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Contacto no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validacion"
     *     )
     * )
     */
    public function update(ContactRequest $request, CollectionContact $contact)
    {
        try {
            $contact->update($request->validated());

            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto actualizado correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al actualizar el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function destroy(CollectionContact $contact)
    {
        try {
            $contact->update(['phone_status' => 'INACTIVE']);

            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto inactivado correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al inactivar el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}