<?php

namespace App\Mail\Transport;

use GuzzleHttp\Client;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

class GraphMailTransport implements TransportInterface
{
    protected ?string $accessToken = null;
    protected string $fromEmail;

    public function __construct()
    {
        $this->fromEmail = env('MAIL_FROM_ADDRESS', 'gestion.cobranza@sefil.com.ec');
    }

    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $guzzle = new Client();
        $url = 'https://login.microsoftonline.com/' . env('MICROSOFT_TENANT_ID') . '/oauth2/v2.0/token';

        $response = $guzzle->post($url, [
            'form_params' => [
                'client_id' => env('MICROSOFT_CLIENT_ID'),
                'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $token = json_decode($response->getBody()->getContents());
        $this->accessToken = $token->access_token;

        return $this->accessToken;
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        $email = MessageConverter::toEmail($message);

        $graphMessage = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody()
                ],
                'toRecipients' => $this->formatRecipients($email->getTo()),
            ],
            'saveToSentItems' => true
        ];

        if ($email->getCc()) {
            $graphMessage['message']['ccRecipients'] = $this->formatRecipients($email->getCc());
        }

        if ($email->getBcc()) {
            $graphMessage['message']['bccRecipients'] = $this->formatRecipients($email->getBcc());
        }

        $guzzle = new Client();
        $guzzle->post('https://graph.microsoft.com/v1.0/users/' . $this->fromEmail . '/sendMail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $graphMessage,
        ]);

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    protected function formatRecipients($recipients): array
    {
        $formatted = [];
        foreach ($recipients as $recipient) {
            $formatted[] = [
                'emailAddress' => [
                    'address' => $recipient->getAddress()
                ]
            ];
        }
        return $formatted;
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
