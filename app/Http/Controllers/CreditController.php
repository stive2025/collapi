<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Http\Resources\CreditResource;
use App\Http\Responses\ResponseBase;
use App\Services\UtilService;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    protected $utilService;

    public function __construct(UtilService $utilService)
    {
        $this->utilService = $utilService;
    }
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
                    'collection_directions.address',
                    'collection_directions.type',
                    'collection_directions.province',
                    'collection_directions.canton',
                    'collection_directions.parish',
                    'collection_directions.neighborhood',
                    'collection_directions.latitude',
                    'collection_directions.longitude'
                );
            },
            'clients.collectionContacts',
            'user',
            'business'
        ]);
    }

    /**
     * Convertir un valor a array si es necesario
     */
    private function toArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                return is_array($decoded) ? $decoded : [$value];
            }
            return explode(',', $value);
        }

        return [$value];
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
            ->when(request()->filled('user_ids'), fn($q) =>
                $q->whereIn('user_id', $this->toArray(request('user_ids')))
            )
            ->when(request()->filled('agency'), function($q) {
                $value = request('agency');
                if (is_array($value) || (is_string($value) && (str_starts_with(trim($value), '[') || str_contains($value, ',')))) {
                    return $q->whereIn('agency', $this->toArray($value));
                }
                return $q->where('agency', 'REGEXP', $value);
            })
            ->when(request()->filled('sync_id'), function($q) {
                $syncIdSearch = request('sync_id');
                $numericPart = $syncIdSearch;

                // Extraer la parte numérica si tiene prefijo (ej: LEGAL-2022081318 -> 2022081318)
                if (strpos($syncIdSearch, '-') !== false) {
                    $parts = explode('-', $syncIdSearch);
                    $numericPart = end($parts);
                }

                // Buscar coincidencia exacta o que termine con el número
                $q->where(function($subQ) use ($syncIdSearch, $numericPart) {
                    $subQ->where('sync_id', $syncIdSearch)
                        ->orWhere('sync_id', $numericPart)
                        ->orWhere('sync_id', 'LIKE', '%-' . $numericPart);
                });
            })
            ->when(request()->filled('sync_ids'), fn($q) =>
                $q->whereIn('sync_id', $this->toArray(request('sync_ids')))
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
            ->when(request()->filled('collection_state'), function($q) {
                $value = request('collection_state');
                if (is_array($value) || (is_string($value) && (str_starts_with(trim($value), '[') || str_contains($value, ',')))) {
                    return $q->whereIn('collection_state', $this->toArray($value));
                }
                return $q->where('collection_state', $value);
            })
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

        if (request()->filled('with_managements') && request('with_managements') === 'true') {
            $query->with(['collectionManagements.client', 'collectionManagements.creator', 'collectionManagements.campain']);
        }

        if (request()->filled('with_payments') && request('with_payments') === 'true') {
            $query->with(['collectionPayments.campain']);
        }

        $credits = $query->paginate(request('per_page', 15));

        // Calcular management_collection_expenses solo para SEFIL_1 y SEFIL_2
        $credits->getCollection()->transform(function ($credit) {
            // Verificar si la empresa del crédito es SEFIL_1 o SEFIL_2 y el estado no es Cancelado ni Convenio de pago
            $shouldCalculateExpenses = false;
            if ($credit->business &&
                in_array($credit->business->name, ['SEFIL_1', 'SEFIL_2']) &&
                !in_array($credit->collection_state, ['Cancelado', 'Convenio de pago','CANCELADO','CONVENIO DE PAGO'])) {
                $shouldCalculateExpenses = true;
            }

            if ($shouldCalculateExpenses) {
                $currentExpenses = floatval($credit->management_collection_expenses ?? 0);
                $calculatedExpenses = $this->utilService->calculateManagementCollectionExpenses(
                    $credit->total_amount ?? 0,
                    $credit->days_past_due ?? 0
                );
                $credit->management_collection_expenses = $currentExpenses + $calculatedExpenses;
                $credit->total_amount = floatval($credit->total_amount ?? 0) + $calculatedExpenses;
            } else {
                // Para empresas que no son SEFIL_1 o SEFIL_2, o créditos Cancelados/Convenio de pago, establecer gastos de cobranza en 0
                $credit->management_collection_expenses = 0;
            }

            return $credit;
        });

        return ResponseBase::success(
            CreditResource::collection($credits)->response()->getData(),
            'Créditos obtenidos correctamente'
        );
    }

    /**
     * Obtener el conteo de créditos agrupados por management_tray
     */
    public function indexNumberTrays(Request $request)
    {
        try {
            $query = Credit::query();

            $this->applyFilters($query);

            $traysGrouped = (clone $query)
                ->selectRaw('management_tray, COUNT(*) as count')
                ->groupBy('management_tray')
                ->where('sync_status', 'ACTIVE')
                ->get()
                ->pluck('count', 'management_tray')
                ->toArray();

            $totalCredits = $query->count();

            return ResponseBase::success(
                [
                    'total_credits' => $totalCredits,
                    'trays' => (object) $traysGrouped
                ],
                'Conteo de bandejas obtenido correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener el conteo de bandejas',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(Credit $credit)
    {
        $credit->load([
            'clients' => fn($query) => $query->withPivot('type'),
            'clients.directions',
            'clients.collectionContacts',
            'collectionManagements',
            'collectionCalls',
            'collectionPayments',
            'invoices',
            'user',
            'business'
        ]);

        // Calcular management_collection_expenses solo para SEFIL_1 y SEFIL_2 y el estado no es Cancelado ni Convenio de pago
        $shouldCalculateExpenses = false;
        if ($credit->business &&
            in_array($credit->business->name, ['SEFIL_1', 'SEFIL_2']) &&
            !in_array($credit->collection_state, ['Cancelado', 'CANCELADO','Convenio de pago','CONVENIO DE PAGO'])) {
            $shouldCalculateExpenses = true;
        }

        if ($shouldCalculateExpenses) {
            $currentExpenses = floatval($credit->management_collection_expenses ?? 0);
            $calculatedExpenses = $this->utilService->calculateManagementCollectionExpenses(
                $credit->total_amount ?? 0,
                $credit->days_past_due ?? 0
            );
            $credit->management_collection_expenses = $currentExpenses + $calculatedExpenses;
            $credit->total_amount = floatval($credit->total_amount ?? 0) + $calculatedExpenses;
        } else {
            // Para empresas que no son SEFIL_1 o SEFIL_2, o créditos Cancelados/Convenio de pago, establecer gastos de cobranza en 0
            $credit->management_collection_expenses = 0;
        }

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
            return ResponseBase::error(
                'Error al actualizar el crédito',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
