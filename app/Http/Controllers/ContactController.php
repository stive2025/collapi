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

            $contacts = $query->paginate($perPage);

            return ResponseBase::success(
                CollectionContactResource::collection($contacts)->response()->getData(),
                'Contactos obtenidos correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching contacts', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error(
                'Error al obtener contactos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

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
            Log::error('Error creating contact', [
                'message' => $e->getMessage(),
                'data' => $request->all()
            ]);

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
            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto obtenido correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching contact', [
                'message' => $e->getMessage(),
                'contact_id' => $contact->id
            ]);

            return ResponseBase::error(
                'Error al obtener el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function update(ContactRequest $request, CollectionContact $contact)
    {
        try {
            $contact->update($request->validated());

            return ResponseBase::success(
                new CollectionContactResource($contact),
                'Contacto actualizado correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error updating contact', [
                'message' => $e->getMessage(),
                'contact_id' => $contact->id,
                'data' => $request->all()
            ]);

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
            Log::error('Error deactivating contact', [
                'message' => $e->getMessage(),
                'contact_id' => $contact->id
            ]);

            return ResponseBase::error(
                'Error al inactivar el contacto',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}