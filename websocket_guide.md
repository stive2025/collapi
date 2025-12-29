# WebSocket Service - Guía de Uso

Este documento explica cómo usar el `WebSocketService` para conectarse a `wss://check.sefil.com.ec/ws`.

## Instalación de Dependencias

La dependencia ya está instalada:

```bash
composer require textalk/websocket
```

## Uso Básico

### 1. Ejemplo Simple - Enviar y Recibir Mensajes

```php
<?php

use App\Services\WebSocketService;

$ws = new WebSocketService('wss://check.sefil.com.ec/ws');

// Conectar
$ws->connect();

// Enviar un mensaje de texto
$ws->send('Hola desde Laravel');

// Enviar un mensaje JSON (se convierte automáticamente)
$ws->send([
    'action' => 'subscribe',
    'channel' => 'updates'
]);

// Recibir un mensaje
$message = $ws->receive();
echo "Mensaje recibido: " . $message;

// Recibir y decodificar JSON automáticamente
$data = $ws->receiveJson();
print_r($data);

// Desconectar
$ws->disconnect();
```

### 2. Uso en un Controlador

```php
<?php

namespace App\Http\Controllers;

use App\Services\WebSocketService;
use Illuminate\Http\Request;

class WebSocketController extends Controller
{
    public function checkConnection()
    {
        try {
            $ws = new WebSocketService('wss://check.sefil.com.ec/ws');

            // Conectar
            $ws->connect();

            // Enviar consulta
            $ws->send([
                'action' => 'check_status',
                'timestamp' => now()->toIso8601String()
            ]);

            // Esperar respuesta (máximo 5 segundos)
            $response = $ws->receiveJson();

            // Desconectar
            $ws->disconnect();

            return response()->json([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendNotification(Request $request)
    {
        $ws = new WebSocketService();

        try {
            $ws->connect();

            $ws->send([
                'type' => 'notification',
                'message' => $request->input('message'),
                'user_id' => $request->input('user_id')
            ]);

            $confirmation = $ws->receive();
            $ws->disconnect();

            return response()->json([
                'success' => true,
                'confirmation' => $confirmation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

### 3. Uso en un Comando de Artisan para Escuchar Mensajes

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebSocketService;

class WebSocketListener extends Command
{
    protected $signature = 'websocket:listen {--timeout=0 : Timeout in seconds (0 = infinite)}';
    protected $description = 'Listen for WebSocket messages from Sefil';

    public function handle()
    {
        $ws = new WebSocketService();
        $timeout = (int) $this->option('timeout');

        try {
            $this->info('Conectando a WebSocket...');
            $ws->connect();
            $this->info('Conectado exitosamente a ' . $ws->getUrl());

            // Enviar mensaje inicial de autenticación si es necesario
            $ws->send([
                'type' => 'auth',
                'token' => config('services.sefil.token', 'default-token')
            ]);

            // Escuchar mensajes con callback
            $ws->listen(function ($message, $ws) {
                $this->info('Mensaje recibido: ' . $message);

                // Procesar el mensaje
                $data = json_decode($message, true);

                if ($data) {
                    $this->processMessage($data, $ws);
                }
            }, $timeout);

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        } finally {
            $ws->disconnect();
            $this->info('Desconectado');
        }

        return 0;
    }

    private function processMessage(array $data, WebSocketService $ws)
    {
        if (isset($data['type'])) {
            switch ($data['type']) {
                case 'ping':
                    // Responder al ping
                    $ws->send(['type' => 'pong']);
                    $this->comment('Pong enviado');
                    break;

                case 'notification':
                    $this->info('Notificación: ' . ($data['message'] ?? 'N/A'));
                    // Guardar en base de datos si es necesario
                    break;

                case 'update':
                    $this->info('Actualización recibida');
                    // Procesar actualización
                    break;

                default:
                    $this->comment('Tipo de mensaje desconocido: ' . $data['type']);
            }
        }
    }
}
```

### 4. Uso en un Job

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\WebSocketService;

class SendWebSocketNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected $userId;

    public function __construct(array $message, $userId = null)
    {
        $this->message = $message;
        $this->userId = $userId;
    }

    public function handle()
    {
        $ws = new WebSocketService();

        try {
            $ws->connect();

            $payload = array_merge($this->message, [
                'user_id' => $this->userId,
                'timestamp' => now()->toIso8601String()
            ]);

            $ws->send($payload);

            // Opcionalmente esperar confirmación
            $response = $ws->receive();
            \Log::info('WebSocket notification sent', [
                'payload' => $payload,
                'response' => $response
            ]);

            $ws->disconnect();
        } catch (\Exception $e) {
            \Log::error('Failed to send WebSocket notification', [
                'error' => $e->getMessage(),
                'payload' => $this->message
            ]);
            throw $e;
        }
    }
}
```

### 5. Request-Response Pattern

```php
<?php

use App\Services\WebSocketService;

$ws = new WebSocketService();
$ws->connect();

// Enviar consulta y esperar respuesta (timeout: 5 segundos)
$response = $ws->request([
    'action' => 'get_status',
    'id' => 12345
], 5);

if ($response) {
    $data = json_decode($response, true);
    echo "Estado: " . ($data['status'] ?? 'desconocido');
} else {
    echo "Timeout esperando respuesta";
}

$ws->disconnect();
```

### 6. Mantener Conexión Viva con Ping

```php
<?php

use App\Services\WebSocketService;

$ws = new WebSocketService();
$ws->connect();

// Enviar ping cada 30 segundos para mantener la conexión viva
while (true) {
    $ws->ping('heartbeat');

    // Recibir mensajes durante 30 segundos
    $startTime = time();
    while (time() - $startTime < 30) {
        $message = $ws->receive();
        if ($message) {
            echo "Mensaje: " . $message . "\n";
        }
        usleep(100000); // 100ms
    }
}
```

### 7. Configuración Personalizada

```php
<?php

use App\Services\WebSocketService;

$ws = new WebSocketService('wss://check.sefil.com.ec/ws', [
    'timeout' => 120, // 2 minutos
    'headers' => [
        'Authorization' => 'Bearer token-here',
        'X-Custom-Header' => 'value'
    ],
    'fragment_size' => 8192, // Tamaño de fragmento más grande
]);

// También puedes configurar después de la instanciación
$ws->setHeaders([
    'Authorization' => 'Bearer nuevo-token',
    'User-Agent' => 'Laravel/12.0'
]);

$ws->connect();
```

## Métodos Disponibles

### Conexión

- `connect()`: Conecta al servidor WebSocket (retorna `bool`)
- `disconnect()`: Cierra la conexión
- `isConnected()`: Verifica si está conectado (retorna `bool`)

### Envío de Mensajes

- `send(string|array $message, string $opcode = 'text')`: Envía un mensaje
  - Si es array, se convierte a JSON automáticamente
  - `opcode` puede ser: 'text', 'binary', 'ping', 'pong'
- `ping(string $payload = '')`: Envía un mensaje ping

### Recepción de Mensajes

- `receive()`: Recibe un mensaje (retorna `string|null`)
- `receiveJson()`: Recibe y decodifica JSON (retorna `array|null`)
- `listen(callable $callback, int $timeout = 0)`: Escucha mensajes continuamente
- `request($message, int $timeout = 5)`: Envía mensaje y espera respuesta

### Configuración

- `setUrl(string $url)`: Cambia la URL del WebSocket
- `getUrl()`: Obtiene la URL actual
- `setOptions(array $options)`: Configura opciones de conexión
- `setHeaders(array $headers)`: Configura headers personalizados
- `getClient()`: Obtiene el cliente WebSocket subyacente

## Ejecutar el Comando de Escucha

### Desde la consola:

```bash
# Escuchar indefinidamente
php artisan websocket:listen

# Escuchar por 60 segundos
php artisan websocket:listen --timeout=60
```

### Como proceso en segundo plano (Windows):

```cmd
start /B php artisan websocket:listen > storage\logs\websocket.log 2>&1
```

### Como proceso en segundo plano (Linux/Mac):

```bash
nohup php artisan websocket:listen > storage/logs/websocket.log 2>&1 &
```

### Con Supervisor (Producción en Linux):

Crea `/etc/supervisor/conf.d/websocket.conf`:

```ini
[program:websocket-listener]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan websocket:listen
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/websocket.log
stopwaitsecs=3600
```

Luego ejecuta:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start websocket-listener:*
```

## Configuración en `.env`

Puedes agregar configuración al archivo `.env`:

```env
SEFIL_WEBSOCKET_URL=wss://check.sefil.com.ec/ws
SEFIL_AUTH_TOKEN=tu-token-aqui
SEFIL_TIMEOUT=60
```

Y luego en `config/services.php`:

```php
'sefil' => [
    'websocket_url' => env('SEFIL_WEBSOCKET_URL', 'wss://check.sefil.com.ec/ws'),
    'token' => env('SEFIL_AUTH_TOKEN'),
    'timeout' => env('SEFIL_TIMEOUT', 60),
],
```

Uso:

```php
$ws = new WebSocketService(config('services.sefil.websocket_url'));
```

## Ejemplo Completo de Integración

```php
<?php

namespace App\Services;

use App\Models\SefilNotification;

class SefilIntegrationService
{
    protected WebSocketService $ws;

    public function __construct()
    {
        $this->ws = new WebSocketService(
            config('services.sefil.websocket_url')
        );
    }

    public function checkStatus($documentId)
    {
        try {
            $this->ws->connect();

            // Autenticar
            $this->authenticate();

            // Consultar estado
            $this->ws->send([
                'action' => 'check_document',
                'document_id' => $documentId
            ]);

            // Esperar respuesta
            $response = $this->ws->receiveJson();

            $this->ws->disconnect();

            return $response;
        } catch (\Exception $e) {
            \Log::error('Sefil check status failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function subscribeToNotifications(callable $handler)
    {
        $this->ws->connect();
        $this->authenticate();

        $this->ws->send(['action' => 'subscribe', 'channel' => 'notifications']);

        $this->ws->listen(function ($message) use ($handler) {
            $data = json_decode($message, true);

            if ($data && isset($data['type']) && $data['type'] === 'notification') {
                // Guardar en base de datos
                SefilNotification::create([
                    'message' => $data['message'] ?? null,
                    'data' => $data,
                    'received_at' => now()
                ]);

                // Llamar al handler personalizado
                $handler($data);
            }
        });
    }

    protected function authenticate()
    {
        $this->ws->send([
            'type' => 'auth',
            'token' => config('services.sefil.token')
        ]);

        $response = $this->ws->receiveJson();

        if (!isset($response['success']) || !$response['success']) {
            throw new \Exception('Autenticación fallida');
        }
    }
}
```

## Notas Importantes

1. **Conexión Bloqueante**: El método `listen()` es bloqueante, ejecutalo en comandos de consola o workers
2. **Reconexión Automática**: El método `listen()` incluye reconexión automática en caso de error
3. **SSL**: La configuración por defecto desactiva la verificación SSL (útil para desarrollo)
4. **Timeouts**: Configura timeouts apropiados según tu caso de uso
5. **Logging**: Todos los eventos se registran en el log de Laravel

## Troubleshooting

### Error: "Failed to connect to WebSocket"
- Verifica que el servidor esté activo: `wss://check.sefil.com.ec/ws`
- Comprueba tu conexión a internet
- Revisa el firewall

### Error: "SSL certificate problem"
Para producción, habilita la verificación SSL:

```php
$ws = new WebSocketService('wss://check.sefil.com.ec/ws', [
    'context' => stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ])
]);
```

### Conexión se cierra inesperadamente
- Implementa heartbeat con `ping()` cada 30-60 segundos
- Verifica timeouts del servidor
- Usa el método `listen()` que incluye reconexión automática

### Alto consumo de CPU
El método `listen()` incluye delays (`usleep`) para evitar esto. Si usas loops personalizados, incluye delays.
