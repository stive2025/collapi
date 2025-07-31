<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $credits=Credit::when(request()->filled('user_id'),function($query){
                $query->where('user_id',request('user_id'));
            })
            ->when(request()->filled('sync_id'),function($query){
                $query->where('sync_id',request('sync_id'));
            })
            ->when(request()->filled('client_type'),function($query){
                $query->where('client_type',request('client_type'));
            })
            ->when(request()->filled('client_name'),function($query){
                $query->where('client_name','REGEXP',request('client_name'));
            })
            ->when(request()->filled('client_ci'),function($query){
                $query->where('client_ci','REGEXP',request('client_ci'));
            })
            ->when(request()->filled('businesses_id'),function($query){
                $query->where('businesses_id',request('businesses_id'));
            })
            ->when(request()->filled('canton'),function($query){
                //$query->where('',request('businesses_id'));
            })
            ->when(request()->filled('sync_status'),function($query){
                $query->where('sync_status',request('sync_status'));
            })
            ->when(request()->filled('collection_state'),function($query){
                $query->where('collection_state',request('collection_state'));
            })
            ->paginate(request('per_page'));

        return response()->json($credits,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Credit $credit)
    {   
        //  Crear recurso para enviar al frontend:
        /*
            - Información del crédito
            - Información de clientes
            - Información de gestiones
            - Información de llamadas
            - Información de pagos
        */
        
        return response()->json($credit,200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Credit $credit)
    {
        //
    }
}
