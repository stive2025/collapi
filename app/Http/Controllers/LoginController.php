<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(){
        $user=User::where('username',request('username'))->first();
        if($user && Hash::check(request('password'),$user->password)){

            $token=$user->createToken('login',['all']);
                
            return response()->json([
                "token"=>$token,
                "user"=>$user
            ],200);
        }

        return response()->json([
            'message'=>'No autorizado'
        ],401);
    }
}
