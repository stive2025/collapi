<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\CollectionSync;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $syncs = CollectionSync::all();
        return ResponseBase::success($syncs, 'Lista de sincronizaciones obtenida');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $new_sync = CollectionSync::create($request->all());
        return ResponseBase::success($new_sync, 'Sincronización creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $sync_type = $request->sync_type;

        if($sync_type === 'SYNC-CREDITS'){
            $this->processCreditSync();
        } else if ($sync_type === 'SYNC-PAYMENTS'){
            $this->processPaymentSync();
        } else {
            return ResponseBase::validationError(['sync_type' => 'Tipo de sincronización inválido']);
        }

    }

    private function processCreditSync()
    {
        $sync = CollectionSync::where('sync_type', 'SYNC-CREDITS')
            ->where('state', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sync) {
            return ResponseBase::notFound('No se encontró una sincronización activa de créditos');
        }else{
            return ResponseBase::success($sync, 'Sincronización de créditos encontrada');
        }
    }

    private function processPaymentSync()
    {
        $sync = CollectionSync::where('sync_type', 'SYNC-PAYMENTS')
            ->where('state', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sync) {
            return ResponseBase::notFound('No se encontró una sincronización activa de pagos');
        }else{
            return ResponseBase::success($sync, 'Sincronización de pagos encontrada');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $update_sync = CollectionSync::find($id);
        if (!$update_sync) {
            return ResponseBase::notFound('Sincronización no encontrada');
        }else{
            $update_sync->update($request->all());
            return ResponseBase::success($update_sync, 'Sincronización actualizada correctamente');
        }
    }
}
