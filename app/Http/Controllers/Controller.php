<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="CollAPI - API de Cobranzas",
 *     version="1.0.0",
 *     description="API REST para el sistema de gestión de cobranzas",
 *     @OA\Contact(
 *         email="soporte@collapi.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Servidor API"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Ingrese el token de autenticación Bearer"
 * )
 */
abstract class Controller
{
    //
}
