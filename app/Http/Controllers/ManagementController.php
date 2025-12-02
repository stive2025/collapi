<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagementRequest;
use App\Http\Resources\ManagementResource;
use App\Http\Responses\ResponseBase;
use App\Models\Management;
use Illuminate\Http\Request;

class ManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $managements = Management::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            ManagementResource::collection($managements)->response()->getData(),
            'Gestiones obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagementRequest $request)
    {
        try {
            $management = Management::create($request->validated());
            
            return ResponseBase::success(
                new ManagementResource($management),
                'Gestión creada correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la gestión',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Management $management)
    {
        $management->load(['client', 'credit', 'campain']);
        
        return ResponseBase::success(
            new ManagementResource($management),
            'Gestión obtenida correctamente'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Management $management)
    {
        //
    }
}