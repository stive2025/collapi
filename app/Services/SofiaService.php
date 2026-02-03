<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

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
        $url = 'https://sofiasistema.sisofia.com.ec/services/configuracion?consultaParaDispositivosMoviles=false';

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('USER_SOFIA') . ':' . env('PASSWORD_SOFIA')),
            'User-Agent' => 'PostmanRuntime/7.36.0',
        ];

        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'force_ip_resolve' => 'v4',
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ]);

        // Log para debug en Hostinger
        Log::info('Sofia getConfig - Iniciando petición', [
            'url' => $url,
            'user' => env('USER_SOFIA'),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        ]);

        try {
            $response = $client->request('GET', $url, [
                'headers' => $headers
            ]);

            Log::info('Sofia getConfig - Respuesta recibida', [
                'status' => $response->getStatusCode(),
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($status < 200 || $status >= 300) {
                Log::error('Sofia Config Error: ' . $body);
                return [
                    'state' => $status,
                    'response' => null,
                    'error' => $body
                ];
            }

            Log::info('Sofia Config Response: ' . $body);

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Sofia Config JSON decode error: ' . json_last_error_msg());
                return [
                    'state' => 500,
                    'response' => null,
                    'error' => 'Invalid JSON response: ' . json_last_error_msg()
                ];
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::error('Sofia Config Exception: ' . $e->getMessage());
            return [
                'state' => 500,
                'response' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    public function facturar($request, $value = null)
    {
        $fechaEmision = date('Y-m-d', time() - 18000);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(env('USER_SOFIA') . ':' . env('PASSWORD_SOFIA')),
        ];

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

        $client = new Client([
            'timeout' => 15,
            'http_errors' => false
        ]);

        try {
            $response = $client->request('POST', $url_ws_create_factura, [
                'headers' => $headers,
                'json' => $datos_facturacion
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($status < 200 || $status >= 300) {
                return [
                    "state" => $status,
                    "response" => null,
                    "data" => $datos_facturacion,
                    "error" => $body
                ];
            }

            $decoded = json_decode($body);
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