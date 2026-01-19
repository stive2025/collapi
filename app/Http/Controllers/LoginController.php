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
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Iniciar sesión",
     *     description="Autentica un usuario y retorna un token de acceso",
     *     operationId="loginUser",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "password"},
     *             @OA\Property(property="username", type="string", example="admin"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inicio de sesión exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inicio de sesión exitoso"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="1|abcd1234efgh5678..."),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="admin"),
     *                     @OA\Property(property="name", type="string", example="Administrador"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="extension", type="string", example="100"),
     *                     @OA\Property(property="role", type="string", example="admin"),
     *                     @OA\Property(property="phone", type="string", example="0999999999"),
     *                     @OA\Property(property="permission", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales incorrectas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Credenciales incorrectas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al iniciar sesión"
     *     )
     * )
     */
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
            // if (!$isAdmin) {
            //     $localTime = now()->subHours(5);
            //     $currentHour = $localTime->hour;
                
            //     if ($currentHour < 7 || $currentHour >= 19) {
            //         return ResponseBase::error(
            //             'Acceso denegado fuera del horario permitido (7:00 AM - 7:00 PM)',
            //             [
            //                 'horario_local' => $localTime->format('H:i:s'),
            //                 'horario_servidor' => now()->format('H:i:s')
            //             ],
            //             403
            //         );
            //     }
            // }
            
            $user->tokens()->delete();
            $user->update(['status' => 'CONECTADO','updated_at' => now()]);
            $token = $user->createToken('login', ['all']);

            try {
                $ws = new WebSocketService();
                $ws->sendLoginUpdate($user->id);
            } catch (\Exception $wsError) {
                Log::error('Error enviando notificación WebSocket en login', [
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
            Log::error('Error durante el inicio de sesión', [
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

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión",
     *     description="Cierra la sesión del usuario actual y elimina su token de acceso",
     *     operationId="logoutUser",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sesión cerrada exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al cerrar sesión"
     *     )
     * )
     */
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
                Log::error('Error enviando notificación WebSocket en logout', [
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
            Log::error('Error durante el cierre de sesión', [
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