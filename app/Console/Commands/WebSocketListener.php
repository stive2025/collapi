<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebSocketService;

class WebSocketListener extends Command
{
    protected $signature = 'websocket:listen {--timeout=0 : Timeout in seconds (0 = infinite)}';
    protected $description = 'Listen for WebSocket messages from wss://check.sefil.com.ec/ws';

    public function handle()
    {
        $ws = new WebSocketService();
        $timeout = (int) $this->option('timeout');

        try {
            $this->info('Conectando a WebSocket...');
            $ws->connect();
            $this->info('✓ Conectado exitosamente a ' . $ws->getUrl());

            // Enviar mensaje inicial de autenticación si es necesario
            // Descomenta y modifica según lo que necesite el servidor
            /*
            $ws->send([
                'type' => 'auth',
                'token' => config('services.sefil.token', 'default-token')
            ]);
            */

            $this->newLine();
            $this->info('Escuchando mensajes... (Presiona Ctrl+C para detener)');
            $this->newLine();

            // Escuchar mensajes con callback
            $ws->listen(function ($message, $ws) {
                $this->line('───────────────────────────────────────────────');
                $this->info('[' . now()->format('Y-m-d H:i:s') . '] Mensaje recibido:');

                // Intentar decodificar como JSON
                $data = json_decode($message, true);

                if ($data) {
                    $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->processMessage($data, $ws);
                } else {
                    $this->line($message);
                }

                $this->line('───────────────────────────────────────────────');
                $this->newLine();
            }, $timeout);

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        } finally {
            $ws->disconnect();
            $this->info('Desconectado del WebSocket');
        }

        return 0;
    }

    private function processMessage(array $data, WebSocketService $ws)
    {
        if (!isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'ping':
                // Responder al ping
                $ws->send(['type' => 'pong']);
                $this->comment('→ Pong enviado');
                break;

            case 'notification':
                $this->warn('🔔 Notificación: ' . ($data['message'] ?? 'N/A'));
                // Aquí puedes guardar en base de datos si es necesario
                // \App\Models\Notification::create(['message' => $data['message']]);
                break;

            case 'update':
                $this->info('🔄 Actualización recibida');
                // Procesar actualización
                break;

            case 'auth_success':
                $this->info('✓ Autenticación exitosa');
                break;

            case 'auth_failed':
                $this->error('✗ Autenticación fallida');
                break;

            default:
                $this->comment('❓ Tipo de mensaje: ' . $data['type']);
        }
    }
}
