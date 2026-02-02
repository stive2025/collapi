<?php

namespace App\Services;

use Illuminate\Container\Attributes\Log as AttributesLog;
use Illuminate\Support\Facades\Log;

class SofiaService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    
    public function getConfig()
    {
        $header = "Accept: application/json\r\n" .
            "Authorization: Basic " . base64_encode(env('USER_SOFIA') . ':' . env('PASSWORD_SOFIA')) . "\r\n" .
            "User-Agent: PostmanRuntime/7.36.0";

        $url = 'https://sofiasistema.sisofia.com.ec/services/configuracion?consultaParaDispositivosMoviles=false';

        $options = [
            'http' => [
                'header' => $header,
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            Log::error('Sofia Config Error: ' . json_encode($error));
            return [
                'state' => 400,
                'response' => null,
                'error' => $error
            ];
        }

        Log::info('Sofia Config Response: ' . $result);

        $decoded = json_decode($result);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Sofia Config JSON decode error: ' . json_last_error_msg());
            return [
                'state' => 500,
                'response' => null,
                'error' => 'Invalid JSON response: ' . json_last_error_msg()
            ];
        }

        return $decoded;
    }

    public function facturar($request, $value = null)
    {
        $fechaEmision = date('Y-m-d', time() - 18000);

        $header = "Content-type: application/json\r\n" .
                "Accept: application/json\r\n" .
                "Authorization: Basic " . base64_encode(env('USER_SOFIA') . ':' . env('PASSWORD_SOFIA'));

        $rawValue = $value ?? $request->input('value');
        $rawValue = floatval($rawValue);

        $referencia_banco = "";
        $precio_unitario = $rawValue / 1.15;
        $precio_unitario = ceil($precio_unitario * 100) / 100;
        $valor_total = $rawValue;

        $metodoPago = $request->input('metodo');
        $idBanco = $request->input('idBanco');
        $referencia = $request->input('referencia');

        if ($metodoPago === 'TRANSFERENCIA') {
            $referencia_banco = [
                "metodo" => $metodoPago,
                "valor" => $valor_total,
                "infoTransferencia" => [
                    "cuentaBancaria" => [
                        "id" => $idBanco
                    ],
                    "referencia" => $referencia
                ]
            ];
        } elseif (in_array($metodoPago, ['DEPOSITO', 'TARJETA_DEBITO'])) {
            $referencia_banco = [
                "metodo" => "DEPOSITO",
                "valor" => $valor_total,
                "infoDeposito" => [
                    "cuentaBancaria" => [
                        "id" => $idBanco
                    ],
                    "numero" => $referencia
                ]
            ];
        } else {
            $referencia_banco = [
                "metodo" => "EFECTIVO",
                "valor" => $valor_total
            ];
        }

        $cartera = $request->cartera ?? $request->input('cartera');
        $itemCodigo = ($cartera === "SEFIL_1") ? "G001" : "G002";

        $datos_facturacion = [
            'fechaEmision' => $fechaEmision,
            'fechaEntrega' => $fechaEmision,
            'bodega' => [
                "id" => "37"
            ],
            'receptorComprobante' => [
                'tipoIdentificacion' => 'CEDULA',
                'identificacion' => $request->input('ci'),
                'razonSocial' => $request->input('name'),
                'direccion' => 'LOJA',
                'telefono' => $request->input('telefono'),
                'email' => $request->input('email')
            ],
            "tipoIva" => "IVA_15",
            "tipoGenerador" => "ESCRITORIO",
            "uuid" => "d4a8c457-08c5-4144-bb5b-" . date('Ymdis', time() - 18000),
            'detalleFactura' => [
                'itemDetalle' => [
                    [
                        'item' => [
                            'id' => "15",
                            'codigo' => $itemCodigo,
                            "descripcion" => "GESTION COBRANZA",
                            'servicio' => 'true',
                            'controlSeries' => [
                                "valor" => "False"
                            ]
                        ],
                        "cantidad" => "1.00",
                        "precioUnitario" => $precio_unitario,
                        "descuentoUnitario" => "0.00",
                        "tieneIva" => "true",
                        "uuid" => "d5a9c547-08c5-4144-bb5b-" . date('Ymdis', time() - 18000),
                    ]
                ]
            ],
            'pagoFactura' => [
                "pago" => [
                    [
                        "tipo" => $request->input('formaPago'),
                        "valor" => $valor_total,
                        "plazo" => "30",
                        "unidad" => "DIAS"
                    ]
                ]
            ],
            'referenciaAbonos' => [
                'abonoInicial' => [$referencia_banco]
            ]
        ];

        $url_ws_create_factura = 'https://sofiasistema.sisofia.com.ec/services/contribuyentes/' . env('RUC') . '/facturas';

        $options = [
            "http" => [
                "header" => $header,
                "method" => "POST",
                "content" => json_encode($datos_facturacion),
                "ignore_errors" => true,
                "timeout" => 15
            ],
        ];

        try {
            $context = stream_context_create($options);
            $resultado = @file_get_contents($url_ws_create_factura, false, $context);

            if ($resultado === false) {
                $error = error_get_last();

                return [
                    "state" => 400,
                    "response" => null,
                    "data" => $datos_facturacion,
                    "error" => $error
                ];
            }

            $decoded = json_decode($resultado);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    "state" => 500,
                    "response" => null,
                    "data" => $datos_facturacion,
                    "error" => 'Invalid JSON response: ' . json_last_error_msg()
                ];
            }

            return [
                "state" => 200,
                "response" => $decoded,
                "data" => $datos_facturacion
            ];
        } catch (\Throwable $th) {
            return [
                "state" => 500,
                "response" => null,
                "data" => $datos_facturacion,
                "error" => $th->getMessage()
            ];
        }
    }
}