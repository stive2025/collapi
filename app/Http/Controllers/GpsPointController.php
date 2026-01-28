<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\GpsPoint;
use Illuminate\Http\Request;

class GpsPointController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/api/gps-points",
     *     summary="Listar puntos GPS",
     *     description="Obtiene una lista paginada de puntos GPS con filtros opcionales. Puede agrupar por type_status.",
     *     operationId="getGpsPointsList",
     *     tags={"Puntos GPS"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Número de puntos GPS por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filtrar por ID del usuario",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type_status",
     *         in="query",
     *         description="Filtrar por tipo de estado",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Fecha inicial del rango (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha final del rango (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="group_by_type_status",
     *         in="query",
     *         description="Agrupar resultados por type_status",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Parameter(
     *         name="order_by",
     *         in="query",
     *         description="Campo para ordenar",
     *         required=false,
     *         @OA\Schema(type="string", default="recorded_at")
     *     ),
     *     @OA\Parameter(
     *         name="order_dir",
     *         in="query",
     *         description="Dirección del ordenamiento",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de puntos GPS obtenida correctamente. Si group_by_type_status=true, retorna datos agrupados con locations array.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Puntos GPS obtenidos correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Respuesta paginada (sin group_by_type_status) o array agrupado (con group_by_type_status=true)",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="user_id", type="integer"),
     *                         @OA\Property(property="latitude", type="number", format="float"),
     *                         @OA\Property(property="longitude", type="number", format="float"),
     *                         @OA\Property(property="recorded_at", type="string", format="date-time"),
     *                         @OA\Property(property="accuracy", type="string"),
     *                         @OA\Property(property="battery_percentage", type="string"),
     *                         @OA\Property(property="type_status", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener puntos GPS"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = GpsPoint::query();

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }

            if ($request->filled('type_status')) {
                $query->where('type_status', $request->query('type_status'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('recorded_at', '>=', $request->query('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('recorded_at', '<=', $request->query('date_to'));
            }

            if ($request->boolean('group_by_type_status')) {
                $gpsPoints = $query->get();

                $grouped = $gpsPoints->groupBy('type_status')->map(function ($items, $typeStatus) {
                    return [
                        'type_status' => $typeStatus,
                        'total' => $items->count(),
                        'locations' => $items->map(function ($item) {
                            return [
                                'latitude' => $item->latitude,
                                'longitude' => $item->longitude,
                                'battery_percentage' => $item->battery_percentage,
                                'hour' => $item->recorded_at->format('H:i:s'),
                            ];
                        })->values()
                    ];
                })->values();

                return ResponseBase::success(
                    $grouped,
                    'Puntos GPS agrupados por type_status obtenidos correctamente'
                );
            }

            $orderBy = $request->query('order_by', 'recorded_at');
            $orderDir = $request->query('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $gpsPoints = $query->with('user')->paginate($perPage);

            return ResponseBase::success(
                [
                    'data' => $gpsPoints->items(),
                    'current_page' => $gpsPoints->currentPage(),
                    'last_page' => $gpsPoints->lastPage(),
                    'per_page' => $gpsPoints->perPage(),
                    'total' => $gpsPoints->total(),
                ],
                'Puntos GPS obtenidos correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener puntos GPS',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/gps-points",
     *     summary="Crear un nuevo punto GPS",
     *     description="Crea un nuevo registro de punto GPS con coordenadas y metadatos",
     *     operationId="createGpsPoint",
     *     tags={"Puntos GPS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"latitude", "longitude", "recorded_at"},
     *             @OA\Property(property="latitude", type="number", format="float", example=-17.7833, description="Latitud (-90 a 90)"),
     *             @OA\Property(property="longitude", type="number", format="float", example=-63.1821, description="Longitud (-180 a 180)"),
     *             @OA\Property(property="recorded_at", type="string", format="date-time", example="2026-01-27T10:30:00Z", description="Fecha y hora del registro"),
     *             @OA\Property(property="accuracy", type="string", example="10m", description="Precisión del GPS"),
     *             @OA\Property(property="battery_percentage", type="string", example="85%", description="Porcentaje de batería"),
     *             @OA\Property(property="type_status", type="string", example="ACTIVE", description="Tipo de estado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Punto GPS creado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Punto GPS creado correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="latitude", type="number", format="float"),
     *                 @OA\Property(property="longitude", type="number", format="float"),
     *                 @OA\Property(property="recorded_at", type="string", format="date-time"),
     *                 @OA\Property(property="accuracy", type="string"),
     *                 @OA\Property(property="battery_percentage", type="string"),
     *                 @OA\Property(property="type_status", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al crear punto GPS"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'recorded_at' => 'required|date',
                'accuracy' => 'nullable|string',
                'battery_percentage' => 'nullable|string',
                'type_status' => 'required|string',
            ]);

            $validated['user_id'] = $request->user()->id;

            $gpsPoint = GpsPoint::create($validated);

            return ResponseBase::success(
                $gpsPoint,
                'Punto GPS creado correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear punto GPS',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
