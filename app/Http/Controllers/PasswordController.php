<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id'
        ]);

        $user = User::find($request->id);
        $code = rand(100000, 999999);

        $user->code = $code;
        $user->save();

        // Determinar el correo de destino según el usuario
        $emailDestination = ($user->username === 'maria_bravo')
            ? env('EMAIL_ADMIN', 'mbravo@sefil.com.ec')
            : env('EMAIL_SYSTEM', 'scesen@sefil.com.ec');

        try {
            Mail::to($emailDestination)->send(new PasswordResetMail($user->name, $code));
            return ResponseBase::success('Código de verificación enviado al correo');
        } catch (\Exception $e) {
            Log::error('Error enviando email: ' . $e->getMessage());
            return ResponseBase::error('Error al enviar el código de verificación', [], 500);
        }
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|numeric'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->code != $request->code) {
            return response()->json([
                'message' => 'Código inválido'
            ], 400);
        }

        if (Carbon::now()->greaterThan($user->code_expires_at)) {
            $user->code = null;
            $user->code_expires_at = null;
            $user->save();
            
            return response()->json([
                'message' => 'El código ha expirado'
            ], 400);
        }

        return response()->json([
            'message' => 'Código verificado correctamente'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|numeric',
            'password' => 'required|min:8|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->code != $request->code) {
            return response()->json([
                'message' => 'Código inválido'
            ], 400);
        }

        if (Carbon::now()->greaterThan($user->code_expires_at)) {
            return response()->json([
                'message' => 'El código ha expirado'
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->code = null;
        $user->code_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente'
        ], 200);
    }
}
