<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clients = Client::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            $clients,
            'Clientes obtenidos correctamente'
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
                'ci' => ['required', 'string', 'max:20', 'unique:clients,ci'],
                'type' => ['nullable', 'string', 'in:natural,juridica'],
                'gender' => ['nullable', 'string', 'in:M,F'],
                'civil_status' => ['nullable', 'string', 'in:soltero,casado,divorciado,viudo,union_libre'],
                'economic_activity' => ['nullable', 'string', 'max:255'],
            ]);

            $client = Client::create($validated);

            return ResponseBase::success(
                $client,
                'Cliente creado exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating client', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al crear el cliente',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        return ResponseBase::success(
            $client,
            'Cliente obtenido correctamente'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'ci' => ['sometimes', 'string', 'max:20', 'unique:clients,ci,' . $client->id],
                'type' => ['nullable', 'string', 'in:natural,juridica'],
                'gender' => ['nullable', 'string', 'in:M,F'],
                'civil_status' => ['nullable', 'string', 'in:soltero,casado,divorciado,viudo,union_libre'],
                'economic_activity' => ['nullable', 'string', 'max:255'],
            ]);

            $client->update($validated);

            return ResponseBase::success(
                $client,
                'Cliente actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating client', [
                'message' => $e->getMessage(),
                'client_id' => $client->id
            ]);

            return ResponseBase::error(
                'Error al actualizar el cliente',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}