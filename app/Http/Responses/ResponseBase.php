<?php
// filepath: c:\xampp\htdocs\collapi\app\Http\Responses\ResponseBase.php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ResponseBase
{
    /**
     * Respuesta exitosa
     *
     * @param mixed $data
     * @param string $message
     * @param int $httpCode
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Operación exitosa', int $httpCode = 200): JsonResponse
    {
        return response()->json([
            'code' => 1,
            'message' => $message,
            'result' => $data
        ], $httpCode);
    }

    /**
     * Respuesta de error
     *
     * @param string $message
     * @param mixed $errors
     * @param int $httpCode
     * @return JsonResponse
     */
    public static function error(string $message = 'Ocurrió un error', $errors = null, int $httpCode = 400): JsonResponse
    {
        return response()->json([
            'code' => -1,
            'message' => $message,
            'result' => $errors
        ], $httpCode);
    }

    /**
     * Respuesta de validación fallida
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(array $errors, string $message = 'Errores de validación'): JsonResponse
    {
        return response()->json([
            'code' => -1,
            'message' => $message,
            'result' => $errors
        ], 422);
    }

    /**
     * Respuesta no autorizado
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return response()->json([
            'code' => -1,
            'message' => $message,
            'result' => null
        ], 401);
    }

    /**
     * Respuesta no encontrado
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return response()->json([
            'code' => -1,
            'message' => $message,
            'result' => null
        ], 404);
    }
}