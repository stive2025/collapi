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