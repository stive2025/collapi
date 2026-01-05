<?php

namespace App\Http\Controllers;

use App\Models\Campain;
use App\Models\Credit;
use App\Models\CollectionCredit;
use App\Http\Responses\ResponseBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectionCreditController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = CollectionCredit::query();

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->credit_id);
            }

            if ($request->filled('campain_id')) {
                $query->where('campain_id', $request->campain_id);
            }

            if ($request->filled('created_at')) {
                $query->whereDate('created_at', $request->created_at);
            }

            if ($request->filled('created_at_from')) {
                $query->whereDate('created_at', '>=', $request->created_at_from);
            }

            if ($request->filled('created_at_to')) {
                $query->whereDate('created_at', '<=', $request->created_at_to);
            }

            if ($request->filled('group')) {
                $groupBy = $request->group;

                $allowedGroups = ['credit_id', 'campain_id', 'user_id'];

                if (in_array($groupBy, $allowedGroups)) {
                    $query->select(
                        $groupBy,
                        DB::raw('COUNT(*) as total_records'),
                        DB::raw('SUM(total_amount) as total_amount_sum'),
                        DB::raw('SUM(capital) as total_capital'),
                        DB::raw('SUM(interest) as total_interest'),
                        DB::raw('SUM(mora) as total_mora'),
                        DB::raw('SUM(safe) as total_safe'),
                        DB::raw('SUM(management_collection_expenses) as total_management_collection_expenses'),
                        DB::raw('SUM(collection_expenses) as total_collection_expenses'),
                        DB::raw('SUM(legal_expenses) as total_legal_expenses'),
                        DB::raw('SUM(other_values) as total_other_values'),
                        DB::raw('AVG(days_past_due) as avg_days_past_due'),
                        DB::raw('MAX(created_at) as last_created_at'),
                        DB::raw('MIN(created_at) as first_created_at')
                    )
                    ->groupBy($groupBy)
                    ->orderBy('total_records', 'desc');

                    $collectionCredits = $query->paginate($request->input('per_page', 15));

                    return ResponseBase::success(
                        $collectionCredits,
                        'Collection credits agrupados correctamente'
                    );
                } else {
                    return ResponseBase::error(
                        'Parámetro group inválido',
                        ['allowed_values' => $allowedGroups],
                        400
                    );
                }
            }

            $query->orderBy('created_at', 'desc');

            $collectionCredits = $query->paginate($request->input('per_page', 15));

            return ResponseBase::success(
                $collectionCredits,
                'Collection credits obtenidos correctamente'
            );

        } catch (\Exception $e) {
            Log::error('Error fetching collection credits', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener collection credits',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Guardar imagen de créditos activos para todas las campañas activas
     */
    public function saveCurrentlyCampain(Request $request)
    {
        try {
            $now = now();

            $activeCampains = Campain::where('state', 'ACTIVE')
                ->where('begin_time', '<=', $now)
                ->where('end_time', '>=', $now)
                ->get();

            if ($activeCampains->isEmpty()) {
                return ResponseBase::error(
                    'No hay campañas activas en el rango de fechas actual',
                    [],
                    404
                );
            }

            $activeCredits = Credit::where('sync_status', 'ACTIVE')->get();

            if ($activeCredits->isEmpty()) {
                return ResponseBase::error(
                    'No hay créditos activos',
                    [],
                    404
                );
            }

            $totalSaved = 0;
            $skippedCampains = [];
            $processedCampains = 0;
            $now = now();
            $today = $now->format('Y-m-d');

            DB::transaction(function () use ($activeCampains, $activeCredits, &$totalSaved, &$skippedCampains, &$processedCampains, $now, $today) {
                foreach ($activeCampains as $campain) {
                    $existsToday = CollectionCredit::where('campain_id', $campain->id)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if ($existsToday) {
                        $skippedCampains[] = [
                            'campain_id' => $campain->id,
                            'campain_name' => $campain->name,
                            'reason' => 'Ya existe una imagen para esta campaña en la fecha de hoy'
                        ];
                        continue;
                    }

                    $batchData = $activeCredits->map(function ($credit) use ($campain, $now) {
                        return [
                            'collection_state' => $credit->collection_state,
                            'days_past_due' => $credit->days_past_due,
                            'paid_fees' => $credit->paid_fees,
                            'pending_fees' => $credit->pending_fees,
                            'total_amount' => $credit->total_amount,
                            'capital' => $credit->capital,
                            'interest' => $credit->interest,
                            'mora' => $credit->mora,
                            'safe' => $credit->safe,
                            'management_collection_expenses' => $credit->management_collection_expenses,
                            'collection_expenses' => $credit->collection_expenses,
                            'legal_expenses' => $credit->legal_expenses,
                            'other_values' => $credit->other_values,
                            'credit_id' => $credit->id,
                            'campain_id' => $campain->id,
                            'user_id' => $credit->user_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->toArray();
                    $chunks = array_chunk($batchData, 500);
                    foreach ($chunks as $chunk) {
                        CollectionCredit::insert($chunk);
                        $totalSaved += count($chunk);
                    }

                    $processedCampains++;
                }
            });

            return ResponseBase::success(
                [
                    'campains_processed' => $processedCampains,
                    'campains_skipped' => count($skippedCampains),
                    'skipped_details' => $skippedCampains,
                    'credits_saved' => $totalSaved
                ],
                $totalSaved > 0
                    ? 'Imagen de créditos guardada exitosamente'
                    : 'No se guardaron registros, todas las campañas ya tienen imagen del día de hoy'
            );

        } catch (\Exception $e) {
            Log::error('Error saving currently campain credits', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al guardar la imagen de créditos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
