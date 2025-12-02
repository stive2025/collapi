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
     * Display a listing of the resource.
     */
    public function index()
    {
        $credits = Credit::with(['clients' => function($query) {
                $query->select('clients.id', 'clients.name', 'clients.ci')
                    ->withPivot('type');
            }])
            ->when(request()->filled('user_id'), function($query) {
                $query->where('user_id', request('user_id'));
            })
            ->when(request()->filled('sync_id'), function($query) {
                $query->where('sync_id', request('sync_id'));
            })
            ->when(request()->filled('client_name'), function($query) {
                $query->whereHas('clients', function($q) {
                    $q->where('name', 'REGEXP', request('client_name'));
                });
            })
            ->when(request()->filled('client_ci'), function($query) {
                $query->whereHas('clients', function($q) {
                    $q->where('ci', 'REGEXP', request('client_ci'));
                });
            })
            ->when(request()->filled('client_role'), function($query) {
                $query->whereHas('clients', function($q) {
                    $q->wherePivot('type', request('client_role'));
                });
            })
            ->when(request()->filled('business_id'), function($query) {
                $query->where('business_id', request('business_id'));
            })
            ->when(request()->filled('sync_status'), function($query) {
                $query->where('sync_status', request('sync_status'));
            })
            ->when(request()->filled('collection_state'), function($query) {
                $query->where('collection_state', request('collection_state'));
            })
            ->paginate(request('per_page', 15));

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
        $credit->load(['clients' => function($query) {
            $query->withPivot('type');
        }, 'collectionManagements', 'collectionCalls', 'collectionPayments']);
        
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
