<?php

namespace App\Http\Controllers;

use App\Services\AsteriskService;
use Illuminate\Http\Request;

class CallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
            ((request()->filled('variables') ? $request->variables : "")),
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

        $manager_asterisk->hangup($request->channel);
        return response()->json($manager_asterisk,200);
    }

}
