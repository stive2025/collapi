<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            $users,
            'Usuarios obtenidos correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users,username'],
                'email' => ['nullable', 'email', 'unique:users,email'],
                'extension' => ['nullable', 'string', 'max:50'],
                'permission' => ['nullable', 'json'],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:6'],
                'role' => ['required', 'string', 'max:50'],
                'created_by' => ['nullable', 'integer', 'exists:users,id'],
            ]);

            $validated['password'] = Hash::make($validated['password']);
            
            $user = User::create($validated);

            // No retornar el password en la respuesta
            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario creado exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al crear el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // No mostrar el password
        $user->makeHidden(['password']);
        
        return ResponseBase::success(
            $user,
            'Usuario obtenido correctamente'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->id],
                'email' => ['nullable', 'email', 'unique:users,email,' . $user->id],
                'extension' => ['nullable', 'string', 'max:50'],
                'permission' => ['nullable', 'json'],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['sometimes', 'string', 'min:6'],
                'role' => ['sometimes', 'string', 'max:50'],
            ]);

            // Si se envía password, hashearlo
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);
            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return ResponseBase::error(
                'Error al actualizar el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage (desactivar usuario).
     */
    public function destroy(User $user)
    {
        try {
            // Desactivar usuario en lugar de eliminarlo
            $user->update([
                'permission' => '[]',
                'extension' => ''
            ]);

            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario desactivado exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error deactivating user', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return ResponseBase::error(
                'Error al desactivar el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}