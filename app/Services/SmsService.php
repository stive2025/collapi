<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $urlBase;
    private string $apiUser;
    private string $apiPassword;
    private string $idCbm;

    public function __construct()
    {
        $this->urlBase = env('SMS_URL', 'https://online.anyway.com.ec/sendsms.php');
        $this->apiUser = env('SMS_API_USER', 'f862144507378b17916f3d0ff4586382');
        $this->apiPassword = env('SMS_API_PASSWORD', 'dbf8baba7e8b186f48441a527a9be597');
        $this->idCbm = env('SMS_ID_CBM', '1241');
    }

    /**
     * Envía un SMS utilizando la API de Anyway
     *
     * @param string $phone Número de teléfono destino
     * @param int $messageCode Código del mensaje (43334, 43335, 48392)
     * @param array $data Datos variables del mensaje
     * @return array
     */
    public function sendSms(string $phone, int $messageCode, array $data): array
    {
        try {
            // Preparar los datos variables según el código del mensaje
            $variableData = $this->prepareVariableData($messageCode, $data);

            if ($variableData === null) {
                return [
                    'success' => false,
                    'error' => "Código de mensaje inválido: {$messageCode}",
                    'code' => 400
                ];
            }

            // Preparar el payload para la API
            $payload = [
                "metodo" => "SmsEnvio",
                "id_cbm" => $this->idCbm,
                "id_transaccion" => time(),
                "telefono" => $phone,
                "id_mensaje" => $messageCode,
                "dt_variable" => "1",
                "datos" => $variableData
            ];

            // Preparar headers
            $header = "Content-type: application/json\r\n" .
                     "Accept: application/json\r\n" .
                     "Authorization: Basic " . base64_encode("{$this->apiUser}:{$this->apiPassword}");

            // Preparar opciones de contexto
            $options = [
                "http" => [
                    "header" => $header,
                    "method" => "POST",
                    "content" => json_encode($payload),
                    "ignore_errors" => true,
                    "timeout" => 30,
                ]
            ];

            $context = stream_context_create($options);
            $result = @file_get_contents($this->urlBase, false, $context);

            if ($result === false) {
                $error = error_get_last();
                Log::error('SmsService::sendSms - file_get_contents failed', [
                    'url' => $this->urlBase,
                    'phone' => $phone,
                    'message_code' => $messageCode,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => 'Error al conectar con el servicio de SMS',
                    'code' => 500
                ];
            }

            $response = json_decode($result, true);

            Log::info('SmsService::sendSms - SMS enviado', [
                'phone' => $phone,
                'message_code' => $messageCode,
                'response' => $response
            ]);

            return [
                'success' => true,
                'response' => $response,
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error('SmsService::sendSms - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
                'message_code' => $messageCode
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Prepara los datos variables según el código del mensaje
     *
     * @param int $messageCode
     * @param array $data
     * @return array|null
     */
    private function prepareVariableData(int $messageCode, array $data): ?array
    {
        switch ($messageCode) {
            case 43334:
                // Mensaje de confirmación de pago
                return [
                    "valor" => [
                        strval($data['name'] ?? ''),
                        strval($data['payment_date'] ?? ''),
                        strval($data['total_amount'] ?? '')
                    ]
                ];

            case 43335:
                // Mensaje de recordatorio de mora
                return [
                    "valor" => [
                        strval($data['name'] ?? ''),
                        strval($data['days_past_due'] ?? ''),
                        strval($data['total_amount'] ?? '')
                    ]
                ];

            case 48392:
                // Mensaje de información de contacto
                return [
                    "valor" => [
                        strval($data['name'] ?? ''),
                        strval($data['total_amount'] ?? ''),
                        strval($data['phone_contact'] ?? '')
                    ]
                ];

            default:
                return null;
        }
    }
}
