# WebSocket Service - Guía de Uso

Este documento muestra la definición del websocket `WebSocketService` para conectarse a `wss://check.sefil.com.ec/ws`.

## Instalación de Dependencias

La dependencia ya está instalada:

```bash
composer require textalk/websocket
```

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