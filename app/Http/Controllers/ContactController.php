<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
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

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->query('name') . '%');
            }

            $contacts = $query->paginate($perPage);

            return ResponseBase::success(
                $contacts,
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
                $contact,
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

    public function update(ContactRequest $request, CollectionContact $contact)
    {
        try {
            $contact->update($request->validated());

            return ResponseBase::success(
                $contact,
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
        $contact->update(['phone_status' => 'INACTIVE']);

        return ResponseBase::success(
            null,
            'Contacto inactivado correctamente'
        );
    }
}