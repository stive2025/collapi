<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphService
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $senderEmail;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->tenantId = env('MICROSOFT_TENANT_ID');
        $this->clientId = env('MICROSOFT_CLIENT_ID');
        $this->clientSecret = env('MICROSOFT_CLIENT_SECRET');
        $this->senderEmail = env('EMAIL_SEND', 'gestion.cobranza@sefil.com.ec');
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $client = new Client();
            $response = $client->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'] ?? null;

            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('Error obteniendo token de Microsoft Graph: ' . $e->getMessage());
            return null;
        }
    }

    public function sendEmail(string $to, string $subject, string $body): bool
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return false;
        }

        try {
            $client = new Client();
            $response = $client->post("https://graph.microsoft.com/v1.0/users/{$this->senderEmail}/sendMail", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'subject' => $subject,
                        'body' => [
                            'contentType' => 'HTML',
                            'content' => $body,
                        ],
                        'toRecipients' => [
                            [
                                'emailAddress' => [
                                    'address' => $to,
                                ],
                            ],
                        ],
                    ],
                    'saveToSentItems' => true,
                ],
            ]);

            return $response->getStatusCode() === 202;
        } catch (\Exception $e) {
            Log::error('Error enviando email con Microsoft Graph: ' . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetCode(string $to, string $userName, string $code): bool
    {
        $subject = 'Código de recuperación de contraseña';
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Recuperación de Contraseña</h2>
                <p>Hola <strong>{$userName}</strong>,</p>
                <p>Has solicitado restablecer tu contraseña. Tu código de verificación es:</p>
                <div style='background-color: #f4f4f4; padding: 20px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px;'>{$code}</span>
                </div>
                <p>Este código expirará en 15 minutos.</p>
                <p>Si no solicitaste este cambio, ignora este correo.</p>
                <br>
                <p>Saludos,<br>Equipo de Soporte</p>
            </body>
            </html>
        ";

        return $this->sendEmail($to, $subject, $body);
    }
}
