<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagementRequest;
use App\Http\Resources\CollectionCallResource;
use App\Http\Resources\ManagementResource;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionCall;
use App\Models\Management;
use Illuminate\Http\Request;

class ManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        
        $query = Management::query();

        if ($request->filled('state')) {
            $query->where('state', $request->query('state'));
        }

        if ($request->filled('substate')) {
            $query->where('substate', $request->query('substate'));
        }

        if ($request->filled('observation')) {
            $query->where('observation', 'LIKE', '%' . $request->query('observation') . '%');
        }

        if ($request->filled('promise_date')) {
            $query->whereDate('promise_date', $request->query('promise_date'));
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->query('created_by'));
        }

        if ($request->filled('days_past_due')) {
            $query->where('days_past_due', $request->query('days_past_due'));
        }

        if ($request->filled('paid_fees')) {
            $query->where('paid_fees', $request->query('paid_fees'));
        }

        if ($request->filled('pending_fees')) {
            $query->where('pending_fees', $request->query('pending_fees'));
        }

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

        if ($request->filled('credit_id')) {
            $query->where('credit_id', $request->query('credit_id'));
        }
        
        if ($request->filled('campain_id')) {
            $query->where('campain_id', $request->query('campain_id'));
        }

        $managements = $query->with([
            'client',
            'credit.clients' => function ($query) {
                $query->select('clients.id', 'clients.name');
            },
            'campain',
            'creator'
        ])->paginate($perPage);

        return ResponseBase::success(
            ManagementResource::collection($managements)->response()->getData(),
            'Gestiones obtenidas correctamente'
        );
    }

    public function indexCallsByManagementID(Request $request, int $management_id)
    {
        $management = Management::where('id', $management_id)->first();

        if (!$management) {
            return ResponseBase::error(
                'Gestión no encontrada',
                [],
                404
            );
        }

        $calls_collection = $management->call_collection;
        $call_ids = json_decode($calls_collection, true);

        if (empty($call_ids) || !is_array($call_ids)) {
            return ResponseBase::success(
                [],
                'No hay llamadas asociadas a esta gestión'
            );
        }

        $calls = CollectionCall::whereIn('id', $call_ids)
            ->with(['contact.client', 'creator'])
            ->get();

        return ResponseBase::success(
            CollectionCallResource::collection($calls),
            'Llamadas obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagementRequest $request)
    {
        try {
            $management = Management::create($request->validated());

            $credit = $management->credit;

            if ($credit) {
                $credit->management_status = $management->substate;
                $credit->management_tray = "GESTIONADO";
                $credit->management_promise = $management->promise_date;
                $credit->save();
            }

            return ResponseBase::success(
                new ManagementResource($management),
                'Gestión creada correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la gestión',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Management $management)
    {
        $management->load([
            'client',
            'credit.clients' => function ($query) {
                $query->select('clients.id', 'clients.name');
            },
            'campain',
            'creator'
        ]);
        
        return ResponseBase::success(
            new ManagementResource($management),
            'Gestión obtenida correctamente'
        );
    }
}