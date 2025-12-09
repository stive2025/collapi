<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Http\Resources\CreditResource;
use App\Http\Responses\ResponseBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    /**
     * Eager load relaciones estándar de créditos con clientes y direcciones
     */
    private function withClientsAndDirections($query)
    {
        return $query->with([
            'clients' => function($query) {
                $query->select('clients.id', 'clients.name', 'clients.ci')
                    ->withPivot('type');
            },
            'clients.directions' => function($query) {
                $query->select(
                    'collection_directions.id',
                    'collection_directions.client_id',
                    'collection_directions.direction',
                    'collection_directions.type',
                    'collection_directions.province',
                    'collection_directions.canton',
                    'collection_directions.parish',
                    'collection_directions.neighborhood',
                    'collection_directions.latitude',
                    'collection_directions.longitude'
                );
            }
        ]);
    }

    /**
     * Aplicar filtros comunes a la consulta de créditos
     */
    private function applyFilters($query)
    {
        return $query
            ->when(request()->filled('user_id'), fn($q) => 
                $q->where('user_id', request('user_id'))
            )
            ->when(request()->filled('agency'), fn($q) => 
                $q->where('agency', 'REGEXP', request('agency'))
            )
            ->when(request()->filled('sync_id'), fn($q) => 
                $q->where('sync_id', request('sync_id'))
            )
            ->when(request()->filled('client_name'), fn($q) => 
                $q->whereHas('clients', fn($subQ) => 
                    $subQ->where('name', 'REGEXP', request('client_name'))
                )
            )
            ->when(request()->filled('client_ci'), fn($q) => 
                $q->whereHas('clients', fn($subQ) => 
                    $subQ->where('ci', 'REGEXP', request('client_ci'))
                )
            )
            ->when(request()->filled('client_role'), fn($q) => 
                $q->whereHas('clients', fn($subQ) => 
                    $subQ->wherePivot('type', request('client_role'))
                )
            )
            ->when(request()->filled('province'), fn($q) => 
                $q->whereHas('clients.directions', fn($subQ) => 
                    $subQ->where('province', 'LIKE', '%' . request('province') . '%')
                )
            )
            ->when(request()->filled('canton'), fn($q) => 
                $q->whereHas('clients.directions', fn($subQ) => 
                    $subQ->where('canton', 'LIKE', '%' . request('canton') . '%')
                )
            )
            ->when(request()->filled('parish'), fn($q) => 
                $q->whereHas('clients.directions', fn($subQ) => 
                    $subQ->where('parish', 'LIKE', '%' . request('parish') . '%')
                )
            )
            ->when(request()->filled('neighborhood'), fn($q) => 
                $q->whereHas('clients.directions', fn($subQ) => 
                    $subQ->where('neighborhood', 'LIKE', '%' . request('neighborhood') . '%')
                )
            )
            ->when(request()->filled('direction_type'), fn($q) => 
                $q->whereHas('clients.directions', fn($subQ) => 
                    $subQ->where('type', request('direction_type'))
                )
            )
            ->when(request()->filled('total_amount_min'), fn($q) => 
                $q->where('total_amount', '>=', request('total_amount_min'))
            )
            ->when(request()->filled('total_amount_max'), fn($q) => 
                $q->where('total_amount', '<=', request('total_amount_max'))
            )
            ->when(request()->filled('pending_fees_min'), fn($q) => 
                $q->where('pending_fees', '>=', request('pending_fees_min'))
            )
            ->when(request()->filled('pending_fees_max'), fn($q) => 
                $q->where('pending_fees', '<=', request('pending_fees_max'))
            )
            ->when(request()->filled('days_past_due_min'), fn($q) => 
                $q->where('days_past_due', '>=', request('days_past_due_min'))
            )
            ->when(request()->filled('days_past_due_max'), fn($q) => 
                $q->where('days_past_due', '<=', request('days_past_due_max'))
            )
            ->when(request()->filled('management_status'), fn($q) => 
                $q->where('management_status', request('management_status'))
            )
            ->when(request()->filled('management_tray'), fn($q) => 
                $q->where('management_tray', request('management_tray'))
            )
            ->when(request()->filled('business_id'), fn($q) => 
                $q->where('business_id', request('business_id'))
            )
            ->when(request()->filled('sync_status'), fn($q) => 
                $q->where('sync_status', request('sync_status'))
            )
            ->when(request()->filled('collection_state'), fn($q) => 
                $q->where('collection_state', request('collection_state'))
            )
            ->when(request()->filled('management_promise'), fn($q) => 
                $q->where('management_promise', request('management_promise'))
            );
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Credit::query();
        
        $this->withClientsAndDirections($query);
        $this->applyFilters($query);
        
        $credits = $query->paginate(request('per_page', 15));

        return ResponseBase::success(
            CreditResource::collection($credits)->response()->getData(),
            'Créditos obtenidos correctamente'
        );
    }
    
    /**
     * Display the specified resource.
     */
    public function show(Credit $credit)
    {   
        $credit->load([
            'clients' => fn($query) => $query->withPivot('type'),
            'clients.directions',
            'collectionManagements',
            'collectionCalls',
            'collectionPayments'
        ]);
        
        return ResponseBase::success(
            new CreditResource($credit),
            'Crédito obtenido correctamente'
        );
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Credit $credit)
    {
        try {
            $validated = $request->validate([
                'collection_state' => ['sometimes', 'string', 'max:100'],
                'frequency' => ['sometimes', 'string', 'max:50'],
                'payment_date' => ['sometimes', 'integer', 'min:1', 'max:31'],
                'days_past_due' => ['sometimes', 'integer', 'min:0'],
                'paid_fees' => ['sometimes', 'integer', 'min:0'],
                'pending_fees' => ['sometimes', 'integer', 'min:0'],
                'monthly_fee_amount' => ['sometimes', 'numeric', 'min:0'],
                'total_amount' => ['sometimes', 'numeric', 'min:0'],
                'capital' => ['sometimes', 'numeric', 'min:0'],
                'interest' => ['sometimes', 'numeric', 'min:0'],
                'mora' => ['sometimes', 'numeric', 'min:0'],
                'safe' => ['sometimes', 'numeric', 'min:0'],
                'management_collection_expenses' => ['sometimes', 'numeric', 'min:0'],
                'collection_expenses' => ['sometimes', 'numeric', 'min:0'],
                'legal_expenses' => ['sometimes', 'numeric', 'min:0'],
                'other_values' => ['sometimes', 'numeric', 'min:0'],
                'sync_status' => ['sometimes', 'string', 'max:50'],
                'management_status' => ['sometimes', 'string', 'max:100'],
                'management_tray' => ['sometimes', 'string', 'max:100'],
                'management_promise' => ['sometimes', 'date'],
                'date_offer' => ['sometimes', 'date'],
                'date_promise' => ['sometimes', 'date'],
                'date_notification' => ['sometimes', 'date'],
            ]);

            $credit->update($validated);

            return ResponseBase::success(
                new CreditResource($credit),
                'Crédito actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating credit', [
                'message' => $e->getMessage(),
                'credit_id' => $credit->id
            ]);

            return ResponseBase::error(
                'Error al actualizar el crédito',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
