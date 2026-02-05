<?php

namespace App\Http\Controllers;

use App\Http\Requests\DirectionRequest;
use App\Http\Resources\CollectionDirectionResource;
use Illuminate\Support\Facades\Log;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionDirection;
use Illuminate\Http\Request;

class DirectionController extends Controller
{
    /**
     * Listar direcciones
     *
     * @OA\Get(
     *     path="/api/directions",
     *     summary="Listar direcciones",
     *     description="Obtiene listado de direcciones con filtros opcionales",
     *     operationId="getDirectionsList",
     *     tags={"Direcciones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="client_id",
     *         in="query",
     *         description="Filtrar por ID del cliente",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="client_name",
     *         in="query",
     *         description="Filtrar por nombre del cliente",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="client_ci",
     *         in="query",
     *         description="Filtrar por CI del cliente",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrar por tipo (DOMICILIO, TRABAJO, etc.)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="province",
     *         in="query",
     *         description="Filtrar por provincia",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="canton",
     *         in="query",
     *         description="Filtrar por canton",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Elementos por pagina",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de direcciones obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Direcciones obtenidas correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="client_id", type="integer"),
     *                     @OA\Property(property="direction", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="province", type="string"),
     *                     @OA\Property(property="canton", type="string"),
     *                     @OA\Property(property="parish", type="string"),
     *                     @OA\Property(property="neighborhood", type="string"),
     *                     @OA\Property(property="latitude", type="string"),
     *                     @OA\Property(property="longitude", type="string"),
     *                     @OA\Property(property="client_name", type="string"),
     *                     @OA\Property(property="client_ci", type="string")
     *                 )),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
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
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = CollectionDirection::query();

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

            if ($request->filled('type')) {
                $query->where('type', $request->query('type'));
            }

            if ($request->filled('province')) {
                $query->where('province', 'LIKE', '%' . $request->query('province') . '%');
            }

            if ($request->filled('canton')) {
                $query->where('canton', 'LIKE', '%' . $request->query('canton') . '%');
            }

            $orderBy = $request->query('order_by', 'created_at');
            $orderDir = $request->query('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);
            $directions = $query->with(['client.credits'])->paginate($perPage);

            return ResponseBase::success(
                [
                    'data' => CollectionDirectionResource::collection($directions),
                    'current_page' => $directions->currentPage(),
                    'last_page' => $directions->lastPage(),
                    'per_page' => $directions->perPage(),
                    'total' => $directions->total(),
                ],
                'Direcciones obtenidas correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener direcciones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Crear una nueva direccion
     *
     * @OA\Post(
     *     path="/api/directions",
     *     summary="Crear direccion",
     *     description="Crea una nueva direccion asociada a un cliente",
     *     operationId="storeDirection",
     *     tags={"Direcciones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"client_id", "direction", "type"},
     *             @OA\Property(property="client_id", type="integer", example=1, description="ID del cliente"),
     *             @OA\Property(property="address", type="string", maxLength=500, example="Av. Principal 123", description="Direccion completa"),
     *             @OA\Property(property="type", type="string", maxLength=50, example="DOMICILIO", description="Tipo de direccion (DOMICILIO, TRABAJO, etc.)"),
     *             @OA\Property(property="province", type="string", maxLength=100, example="Pichincha", description="Provincia"),
     *             @OA\Property(property="canton", type="string", maxLength=100, example="Quito", description="Canton"),
     *             @OA\Property(property="parish", type="string", maxLength=100, example="La Mariscal", description="Parroquia"),
     *             @OA\Property(property="neighborhood", type="string", maxLength=255, example="Centro Norte", description="Barrio"),
     *             @OA\Property(property="latitude", type="string", maxLength=50, example="-0.1807", description="Latitud"),
     *             @OA\Property(property="longitude", type="string", maxLength=50, example="-78.4678", description="Longitud")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Direccion creada correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Direccion creada correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="client_id", type="integer"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="province", type="string"),
     *                 @OA\Property(property="canton", type="string"),
     *                 @OA\Property(property="parish", type="string"),
     *                 @OA\Property(property="neighborhood", type="string"),
     *                 @OA\Property(property="latitude", type="string"),
     *                 @OA\Property(property="longitude", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validacion"
     *     )
     * )
     */
    public function store(DirectionRequest $request)
    {
        try {
            $data = $request->validated();
            Log::info('Creating direction with data: ', $data);
            $direction = CollectionDirection::create($data);

            return ResponseBase::success(
                new CollectionDirectionResource($direction),
                'Direccion creada correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la direccion',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function show(CollectionDirection $direction)
    {
        try {
            $direction->load(['client.credits']);

            return ResponseBase::success(
                new CollectionDirectionResource($direction),
                'Direccion obtenida correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener la direccion',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function update(DirectionRequest $request, CollectionDirection $direction)
    {
        try {
            $direction->update($request->validated());

            return ResponseBase::success(
                new CollectionDirectionResource($direction),
                'Direccion actualizada correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al actualizar la direccion',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
    
    public function destroy(CollectionDirection $direction)
    {
        try {
            $direction->delete();

            return ResponseBase::success(
                null,
                'Direccion eliminada correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al eliminar la direccion',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
