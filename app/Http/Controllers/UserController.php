<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users=User::paginate(10);
        return response()->json($users,200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data=[
            'name'=>$request->name,
            'username'=>$request->username,
            'extension'=>$request->extension,
            'permission'=>$request->permission,
            'password'=>Hash::make($request->password),
            'role'=>$request->role,
            'created_by'=>$request->created_by
        ];

        $user_create=User::create($data);
        return response()->json($user_create,200);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return response()->json($user,200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $inactive_user=$user->update([
            "permission"=>"[]",
            "extension"=>""
        ]);
        
        return response()->json($inactive_user,200);
    }
}