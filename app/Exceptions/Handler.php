<?php
// filepath: c:\xampp\htdocs\collapi\app\Exceptions\Handler.php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Responses\ResponseBase;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Siempre retornar JSON para rutas API
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'code' => -1,
                'message' => 'Token inválido o expirado. Por favor, inicie sesión nuevamente.',
                'result' => null
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Capturar AuthenticationException
        if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        // Capturar errores de token inválido que no lanzan AuthenticationException
        if ($request->is('api/*')) {
            // Token revocado o inexistente
            if ($e instanceof \Laravel\Sanctum\Exceptions\MissingAbilityException) {
                return response()->json([
                    'code' => -1,
                    'message' => 'Permisos insuficientes',
                    'result' => null
                ], 403);
            }

            // Errores generales de autenticación no capturados
            if (str_contains($e->getMessage(), 'Unauthenticated') || 
                str_contains($e->getMessage(), 'authentication')) {
                return response()->json([
                    'code' => -1,
                    'message' => 'Token inválido o expirado. Por favor, inicie sesión nuevamente.',
                    'result' => null
                ], 401);
            }
        }

        return parent::render($request, $e);
    }
}