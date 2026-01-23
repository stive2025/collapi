<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Campain;
use App\Models\Credit;
use App\Services\UtilService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampainController extends Controller
{
    private UtilService $utilService;

    public function __construct(UtilService $utilService)
    {
        $this->utilService = $utilService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = Campain::query();

        if ($request->filled('state')) {
            $query->where('state', $request->query('state'));
        }
        
        $orderBy = $request->query('order_by', 'created_at');
        $orderDir = $request->query('order_dir', 'desc');
        $campains = $query->orderBy($orderBy, $orderDir)->paginate($perPage);
        
        return ResponseBase::success(
            $campains,
            'Campañas obtenidas correctamente'
        );
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string'],
                'state' => ['nullable', 'string'],
                'type' => ['required', 'string'],
                'begin_time' => ['required', 'date'],
                'end_time' => ['required', 'date'],
                'agents' => ['nullable', 'string'],
                'business_id' => ['required', 'integer', 'exists:businesses,id'],
            ]);
            
            if (!isset($validated['state'])) {
                $validated['state'] = 'ACTIVE';
            }

            if ($validated['state'] === 'ACTIVE') {
                Campain::where('business_id', $validated['business_id'])
                    ->where('state', 'ACTIVE')
                    ->update(['state' => 'INACTIVE']);
            }

            $campain = Campain::create($validated);

            return ResponseBase::success(
                $campain,
                'Campaña creada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $campain = Campain::select('campains.*', 'businesses.name as business_name')
            ->leftJoin('businesses', 'campains.business_id', '=', 'businesses.id')
            ->where('campains.id', $id)
            ->first();

        if (!$campain) {
            return ResponseBase::notFound('Campaña no encontrada');
        }

        $totalCredits = Credit::where('business_id', $campain->business_id)
            ->where('sync_status', 'ACTIVE')
            ->count();

        $campain->total_credits = $totalCredits;

        return ResponseBase::success($campain, 'Campaña obtenida correctamente');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $campain = Campain::find($id);

        if (!$campain) {
            return ResponseBase::notFound('Campaña no encontrada');
        }
        
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string'],
                'state' => ['nullable', 'string'],
                'type' => ['sometimes', 'string'],
                'begin_time' => ['sometimes', 'date'],
                'end_time' => ['sometimes', 'date'],
                'agents' => ['nullable', 'string'],
                'business_id' => ['sometimes', 'integer', 'exists:businesses,id'],
            ]);

            $campain->update($validated);

            return ResponseBase::success(
                $campain,
                'Campaña actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al actualizar la campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
    /**
     * Transfer credits associated with the campaign to different users based on filters.
     */
    public function transfer(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'days_past_due_min' => ['nullable', 'integer'],
                'days_past_due_max' => ['nullable', 'integer'],
                'total_fees_min' => ['nullable', 'integer'],
                'total_fees_max' => ['nullable', 'integer'],
                'pending_fees_min' => ['nullable', 'integer'],
                'pending_fees_max' => ['nullable', 'integer'],
                'total_amount_min' => ['nullable', 'numeric'],
                'total_amount_max' => ['nullable', 'numeric'],
                'user_origin' => ['nullable', 'array'],
                'user_origin.*' => ['integer', 'exists:users,id'],
                'user_dstn' => ['required', 'array', 'min:1'],
                'user_dstn.*' => ['integer', 'exists:users,id'],
                'agency' => ['nullable'],
                'status_management' => ['nullable', 'array'],
                'status_management.*' => ['string'],
                'collection_state' => ['nullable', 'array'],
                'collection_state.*' => ['string'],
                'management_tray' => ['nullable', 'array'],
                'management_tray.*' => ['string'],
                'sync_ids' => ['nullable'],
                'sync_status' => ['nullable', 'string'],
            ]);

            if (isset($validated['sync_ids'])) {
                if (is_string($validated['sync_ids'])) {
                    $decoded = json_decode($validated['sync_ids'], true);
                    $validated['sync_ids'] = is_array($decoded) ? $decoded : [$validated['sync_ids']];
                } elseif (!is_array($validated['sync_ids'])) {
                    $validated['sync_ids'] = [$validated['sync_ids']];
                }
            }

            if (isset($validated['agency'])) {
                if (is_string($validated['agency'])) {
                    $decoded = json_decode($validated['agency'], true);
                    $validated['agency'] = is_array($decoded) ? $decoded : [$validated['agency']];
                } elseif (!is_array($validated['agency'])) {
                    $validated['agency'] = [$validated['agency']];
                }
            }

            $campain = Campain::select('id', 'name', 'business_id')->find($id);

            if (!$campain) {
                return ResponseBase::notFound('Campaña no encontrada');
            }

            $query = Credit::select('id')
                ->where('business_id', $campain->business_id);

            $this->applyRangeFilter($query, 'days_past_due', $validated);
            $this->applyRangeFilter($query, 'total_fees', $validated);
            $this->applyRangeFilter($query, 'pending_fees', $validated);
            $this->applyRangeFilter($query, 'total_amount', $validated);

            $this->applyInFilter($query, 'user_id', $validated, 'user_origin');
            $this->applyInFilter($query, 'agency', $validated, 'agency');
            $this->applyInFilter($query, 'management_status', $validated, 'status_management');
            $this->applyInFilter($query, 'collection_state', $validated, 'collection_state');
            $this->applyInFilter($query, 'management_tray', $validated, 'management_tray');
            $this->applyInFilter($query, 'sync_id', $validated, 'sync_ids');

            if (isset($validated['sync_status'])) {
                $query->where('sync_status', $validated['sync_status']);
            }

            $creditIds = $query->pluck('id')->toArray();

            if (empty($creditIds)) {

                return ResponseBase::success([
                    'transferred' => 0,
                    'message' => 'No se encontraron créditos que cumplan los filtros',
                    'debug' => [
                        'campain_id' => $campain->id,
                        'business_id' => $campain->business_id,
                        'filters_applied' => $validated
                    ]
                ], 'Transferencia completada');
            }

            $userDstn = $validated['user_dstn'];
            $totalCredits = count($creditIds);
            $totalUsers = count($userDstn);
            $updatedCount = 0;

            DB::transaction(function () use ($creditIds, $userDstn, $totalCredits, $totalUsers, &$updatedCount) {
                $creditsPerUser = floor($totalCredits / $totalUsers);
                $remainder = $totalCredits % $totalUsers;
                $offset = 0;

                foreach ($userDstn as $index => $userId) {
                    $assignCount = $creditsPerUser + ($index < $remainder ? 1 : 0);
                    
                    if ($assignCount > 0) {
                        $batch = array_slice($creditIds, $offset, $assignCount);
                        Credit::whereIn('id', $batch)->update([
                            'user_id' => $userId,
                            'updated_at' => now()
                        ]);
                        $updatedCount += count($batch);
                        $offset += $assignCount;
                    }
                }
            });

            $data_transfered = [
                'transfered_at'=>date('Y-m-d H:i:s',time() - 18000),
                'transfered_by'=> Auth::user()->id,
                'campain_id' => $campain->id,
                'business_id' => $campain->business_id,
                'filters_applied' => $validated
            ];

            //Log::info('Créditos transferidos', $data_transfered);
            
            return ResponseBase::success([
                'campain_id' => $campain->id,
                'campain_name' => $campain->name,
                'business_id' => $campain->business_id,
                'credits_transferred' => $updatedCount,
                'destination_users' => $userDstn,
                'credits_per_user' => $this->calculateDistribution($totalCredits, count($userDstn))
            ], 'Transferencia completada exitosamente');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al transferir créditos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    private function applyRangeFilter($query, string $field, array $validated): void
    {
        if (isset($validated["{$field}_min"])) {
            $query->where($field, '>=', $validated["{$field}_min"]);
        }
        if (isset($validated["{$field}_max"])) {
            $query->where($field, '<=', $validated["{$field}_max"]);
        }
    }

    private function applyInFilter($query, string $dbField, array $validated, string $inputKey): void
    {
        if (isset($validated[$inputKey]) && !empty($validated[$inputKey])) {
            $query->whereIn($dbField, $validated[$inputKey]);
        }
    }

    private function calculateDistribution(int $total, int $users): array
    {
        $base = floor($total / $users);
        $remainder = $total % $users;
        
        $distribution = [];
        for ($i = 0; $i < $users; $i++) {
            $distribution[] = $base + ($i < $remainder ? 1 : 0);
        }
        
        return $distribution;
    }

    /**
     * Associate managements to payments for a specific date
     */
    public function associateManagements(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => ['required', 'date_format:Y-m-d']
            ]);

            $this->utilService->associateManagementsToPayment($validated['date']);

            // Obtener los pagos procesados de esa fecha
            $payments = \App\Models\CollectionPayment::whereDate('payment_date', $validated['date'])
                ->with(['credit:id,sync_id'])
                ->get();

            $summary = [
                'date' => $validated['date'],
                'total_payments' => $payments->count(),
                'with_management' => $payments->where('with_management', 'SI')->count(),
                'without_management' => $payments->where('with_management', 'NO')->count(),
                'post_management' => $payments->where('post_management', 'SI')->count(),
                'payments' => $payments->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'credit_sync_id' => $payment->credit->sync_id ?? null,
                        'payment_value' => $payment->payment_value,
                        'with_management' => $payment->with_management,
                        'management_auto' => $payment->management_auto,
                        'management_prev' => $payment->management_prev,
                        'days_past_due_auto' => $payment->days_past_due_auto,
                        'days_past_due_prev' => $payment->days_past_due_prev,
                        'post_management' => $payment->post_management
                    ];
                })
            ];

            return ResponseBase::success(
                $summary,
                'Asociación de gestiones a pagos completada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al asociar gestiones a pagos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

}