<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);

            $user = User::where('username', $validated['username'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return ResponseBase::unauthorized('Credenciales incorrectas');
            }

            // Revocar tokens anteriores ANTES de crear el nuevo
            $user->tokens()->delete();

            // Crear nuevo token DESPUÉS de eliminar los anteriores
            $token = $user->createToken('login', ['all']);

            return ResponseBase::success(
                [
                    'token' => $token->plainTextToken,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                    ]
                ],
                'Inicio de sesión exitoso'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error during login', [
                'message' => $e->getMessage(),
                'username' => $request->input('username')
            ]);

            return ResponseBase::error(
                'Error al iniciar sesión',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return ResponseBase::success(
                null,
                'Sesión cerrada exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error during logout', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return ResponseBase::error(
                'Error al cerrar sesión',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}