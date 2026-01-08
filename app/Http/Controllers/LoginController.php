<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\User;
use App\Services\WebSocketService;
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
            
            $isAdmin = in_array(strtolower($user->role), ['admin', 'superadmin']);
            if (!$isAdmin) {
                $localTime = now()->subHours(5);
                $currentHour = $localTime->hour;
                
                if ($currentHour < 7 || $currentHour >= 19) {
                    return ResponseBase::error(
                        'Acceso denegado fuera del horario permitido (7:00 AM - 7:00 PM)',
                        [
                            'horario_local' => $localTime->format('H:i:s'),
                            'horario_servidor' => now()->format('H:i:s')
                        ],
                        403
                    );
                }
            }
            
            $user->tokens()->delete();
            $user->update(['status' => 'CONECTADO','updated_at' => now()]);
            $token = $user->createToken('login', ['all']);

            try {
                $ws = new WebSocketService();
                $ws->sendLoginUpdate($user->id);
            } catch (\Exception $wsError) {
                Log::error('WebSocket notification failed on login', [
                    'error' => $wsError->getMessage(),
                    'user_id' => $user->id
                ]);
            }

            return ResponseBase::success(
                [
                    'token' => $token->plainTextToken,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                        'extension' => $user->extension,
                        'role' => $user->role,
                        'phone' => $user->phone,
                        'permission' => json_decode($user->permission)
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
            $user = $request->user();

            if (!$user) {
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $user->update(['status' => 'FUERA DE LÍNEA']);

            // Enviar notificación WebSocket - Logout
            try {
                $ws = new WebSocketService();
                $ws->sendLogoutUpdate($user->id);
            } catch (\Exception $wsError) {
                Log::error('WebSocket notification failed on logout', [
                    'error' => $wsError->getMessage(),
                    'user_id' => $user->id
                ]);
            }

            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

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