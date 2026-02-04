<?php

namespace App\Http\Controllers;
use App\Exports\CondonacionExport;
use App\Exports\AccountingExport;
use App\Exports\CampainExport;
use App\Exports\CampainAssignExport;
use App\Exports\DireccionesExport;
use App\Exports\PaymentsConsolidatedExport;
use App\Http\Responses\ResponseBase;
use App\Models\Business;
use App\Models\Campain;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportCondonations(Request $request)
    {
        try {
            // No requiere validación estricta, los filtros se aplican en el export
            $fileName = 'HistoricoCondonaciones-' . date('Ymd_His') . '.xlsx';
            return Excel::download(new CondonacionExport(), $fileName);
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar historial de condonaciones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function exportCampain(Request $request)
    {
        try {
            $validated = $request->validate([
                'campain_id' => ['required', 'integer', 'exists:campains,id']
            ]);

            $campainId = $validated['campain_id'];

            $campain = Campain::findOrFail($campainId);
            $campainName = strtoupper($campain->name);

            $monthsInSpanish = [
                1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
            ];

            $day = date('d');
            $monthNumber = (int) date('m');
            $monthName = $monthsInSpanish[$monthNumber];

            $fileName = $campainName . '-dia_' . $day . '-' . $monthName . '.xlsx';

            return Excel::download(new CampainExport($campainId), $fileName);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function exportAccounting(Request $request)
    {
        try {
            $validated = $request->validate([
                'business_ids' => ['required', 'array'],
                'business_ids.*' => ['integer', 'exists:businesses,id'],
                'group' => ['required', 'string', 'in:true,false'],
                'start_date' => ['required_without:month_name', 'date'],
                'end_date' => ['required_without:month_name', 'date'],
                'month_name' => ['required_without_all:start_date,end_date', 'string']
            ]);

            $businessIds = $validated['business_ids'];
            $group = $validated['group'];
            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;
            $monthName = $validated['month_name'] ?? null;

            $businesses = Business::whereIn('id', $businessIds)->get();
            $businessName = $businesses->pluck('name')->map(fn($n) => strtoupper($n))->implode('-');

            $user = $request->user();
            $userName = $user ? $user->name : 'Sistema';

            $monthsInSpanish = [
                1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
            ];

            $day = date('d');
            $monthNumber = (int) date('m');
            $monthNameCurrent = $monthsInSpanish[$monthNumber];

            $fileName = 'Contabilidad-dia_' . $day . '-' . $monthNameCurrent . '-' . $businessName . '.xlsx';

            return Excel::download(
                new AccountingExport($businessIds, $group, $startDate, $endDate, $monthName, $businessName, $userName),
                $fileName
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar contabilidad',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function exportCampainAssign(Request $request)
    {
        try {
            $validated = $request->validate([
                'business_id' => ['required', 'integer', 'exists:businesses,id']
            ]);

            $businessId = $validated['business_id'];

            $business = Business::findOrFail($businessId);
            $businessName = strtoupper($business->name);

            $user = $request->user();
            $userName = $user ? $user->name : 'Sistema';

            $monthsInSpanish = [
                1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
            ];

            $monthNumber = (int) date('m');
            $monthName = $monthsInSpanish[$monthNumber];

            $fileName = 'AsignacionCampaña-' . $monthName . '-' . $businessName . '.xlsx';

            return Excel::download(
                new CampainAssignExport($businessId, $businessName, $userName),
                $fileName
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar asignación de campaña',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function exportDirecciones(Request $request)
    {
        try {
            $validated = $request->validate([
                'business_id' => ['required', 'integer', 'exists:businesses,id'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'agencies' => ['required', 'string']
            ]);

            $businessId = $validated['business_id'];
            $userId = $validated['user_id'];
            $agencies = $validated['agencies'];

            $business = Business::findOrFail($businessId);
            $businessName = $business->name;

            $user = \App\Models\User::find($userId);
            $userName = $user ? $user->name : 'Sistema';

            $monthsInSpanish = [
                1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
            ];

            $day = date('d');
            $monthNumber = (int) date('m');
            $monthName = $monthsInSpanish[$monthNumber];

            $fileName = 'Direcciones-dia_' . $day . '-' . $monthName . '-' . strtoupper($businessName) . '.xlsx';

            return Excel::download(
                new DireccionesExport($businessId, $userId, $agencies, $businessName, $userName),
                $fileName
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar direcciones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function exportPaymentsConsolidated(Request $request)
    {
        try {
            $validated = $request->validate([
                'campain_id' => ['required', 'integer', 'exists:campains,id']
            ]);

            $campainId = $validated['campain_id'];

            $campain = Campain::findOrFail($campainId);
            $campainName = strtoupper($campain->name);

            $user = $request->user();
            $userName = $user ? $user->name : 'Sistema';

            $monthsInSpanish = [
                1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
            ];

            $day = date('d');
            $monthNumber = (int) date('m');
            $monthName = $monthsInSpanish[$monthNumber];

            $fileName = 'PagosConsolidado-dia_' . $day . '-' . $monthName . '-' . $campainName . '.xlsx';

            return Excel::download(
                new PaymentsConsolidatedExport($campainId, $userName),
                $fileName
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al exportar pagos consolidados',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}