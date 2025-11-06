<?php

namespace App\Http\Controllers;

use App\Models\CollectionCall;
use App\Services\AsteriskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $calls=CollectionCall::paginate(request('per_page'));
        return response()->json($calls,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'credit_id' => ['required','integer','exists:credits,id'],
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('collection_contacts','id')
                    ->where(fn($q) => $q->where('credit_id', $request->credit_id))
            ],
            'state' => ['required','string','max:50'],
            'duration' => ['nullable','integer','min:0'],
            'media_path' => ['nullable','string','max:255'],
            'channel' => ['required','string','max:100'],
        ]);
        
        $data_call=[
            'state'=>$validated['state'],
            'duration'=>$validated['duration'] ?? null,
            'media_path'=>$validated['media_path'] ?? null,
            'channel'=>$validated['channel'],
            'created_by'=>Auth::id(),
            'collection_contact_id'=>$validated['contact_id'],
            'credit_id'=>$validated['credit_id']
        ];

        try {
            $create_call=CollectionCall::create($data_call);
            return response()->json([
                "state"=>1,
                "data"=>$create_call,
                "message"=>"Llamada guardada correctamente."
            ],200);
        } catch (\Throwable $th) {

            Log::channel('calls')->info($th->getMessage());
            Log::channel('calls')->info(json_encode($data_call));

            return response()->json([
                "state"=>-1,
                "data"=>$data_call,
                "message"=>"Error al guardar la llamada."
            ],200);
        }
    }

    public function store(Request $request)
    {
        $data_call=[
            'state'=>$request->state,
            'duration'=>$request->duration,
            'media_path'=>$request->media_path,
            'channel'=>$request->channel,
            'created_by'=>Auth::user()->id,
            'collection_contact_id'=>$request->contact_id,
            'credit_id'=>$request->credit_id
        ];

        try {
            $create_call=CollectionCall::create($data_call);
            return response()->json([
                "state"=>1,
                "data"=>$create_call,
                "message"=>"Llamada guardada correctamente."
            ],200);
        } catch (\Throwable $th) {

            Log::channel('calls')->info($th->getMessage());
            Log::channel('calls')->info(json_encode($data_call));

            return response()->json([
                "state"=>-1,
                "data"=>$data_call,
                "message"=>"Error al guardar la llamada."
            ],200);
        }
    }

    public function dial(Request $request){
        $manager_asterisk=new AsteriskService(
            "172.20.1.107",
            "call_master",
            "s3f1l_c@11"
        );

        $manager_asterisk->originateCall(
            $request->channel,
            $request->exten,
            $request->context,
            $request->priority,
            ((request()->filled('application') ? $request->application : "")),
            ((request()->filled('data') ? $request->data : "")),
            $request->timeout,
            $request->caller_id,
            ((request()->filled('variables') ? ['FILENAME' => '0978950498_PRUEBA_AUDIO'] : "")),
            ((request()->filled('account') ? $request->account : "")),
            ((request()->filled('async') ? $request->async : "")),
            ((request()->filled('action_id') ? $request->action_id : ""))
        );

        return response()->json($manager_asterisk,200);
    }

    public function hangup(Request $request){
        $manager_asterisk=new AsteriskService(
            "172.20.1.107",
            "call_master",
            "s3f1l_c@11"
        );

        $hangup=$manager_asterisk->hangup($request->channel);
        return response()->json($hangup,200);
    }
}