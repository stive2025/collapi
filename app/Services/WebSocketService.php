<?php

namespace App\Services;

use Exception;
use WebSocket\Client;
use WebSocket\ConnectionException;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Campain;
use App\Traits\UserMetricsTrait;

class WebSocketService
{
    use UserMetricsTrait;

    private string $url;
    private ?Client $client = null;
    private array $options;

    /**
     * Create a new WebSocketService instance.
     *
     * @param string $url WebSocket URL (e.g., wss://check.sefil.com.ec/ws)
     * @param array $options Connection options
     */
    public function __construct(string $url = 'wss://check.sefil.com.ec/ws', array $options = [])
    {
        $this->url = $url;
        $this->options = array_merge([
            'timeout' => 60,
            'headers' => [],
            'fragment_size' => 4096,
            'context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ], $options);
    }

    /**
     * Set the WebSocket URL
     *
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get the current WebSocket URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set connection options
     *
     * @param array $options
     * @return self
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Set custom headers
     *
     * @param array $headers
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->options['headers'] = $headers;
        return $this;
    }

    /**
     * Connect to the WebSocket server
     *
     * @return bool
     * @throws ConnectionException
     */
    public function connect(): bool
    {
        try {
            if ($this->isConnected()) {
                Log::warning('WebSocket already connected');
                return true;
            }

            $this->client = new Client($this->url, $this->options);
            Log::info('WebSocket connected successfully', ['url' => $this->url]);
            return true;
        } catch (Exception $e) {
            Log::error('WebSocket connection failed', [
                'url' => $this->url,
                'error' => $e->getMessage()
            ]);
            throw new ConnectionException('Failed to connect to WebSocket: ' . $e->getMessage());
        }
    }

    /**
     * Send a message through the WebSocket connection
     *
     * @param string|array $message
     * @param string $opcode Message type (text, binary, ping, pong)
     * @return bool
     */
    public function send($message, string $opcode = 'text'): bool
    {
        try {
            if (!$this->isConnected()) {
                Log::warning('Cannot send message: WebSocket not connected');
                $this->connect();
            }

            if (is_array($message)) {
                $message = json_encode($message);
            }

            $this->client->send($message, $opcode);
            Log::debug('WebSocket message sent', ['message' => $message]);
            return true;
        } catch (Exception $e) {
            Log::error('Error sending WebSocket message', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return false;
        }
    }

    /**
     * Receive a message from the WebSocket connection
     *
     * @return string|null
     */
    public function receive(): ?string
    {
        try {
            if (!$this->isConnected()) {
                Log::warning('Cannot receive message: WebSocket not connected');
                return null;
            }

            $message = $this->client->receive();
            Log::debug('WebSocket message received', ['message' => $message]);
            return $message;
        } catch (Exception $e) {
            Log::error('Error receiving WebSocket message', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Receive and decode JSON message
     *
     * @return array|null
     */
    public function receiveJson(): ?array
    {
        $message = $this->receive();

        if ($message === null) {
            return null;
        }

        try {
            $decoded = json_decode($message, true);
            return $decoded;
        } catch (Exception $e) {
            Log::error('Error decoding JSON message', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return null;
        }
    }

    /**
     * Send a ping message
     *
     * @param string $payload
     * @return bool
     */
    public function ping(string $payload = ''): bool
    {
        try {
            if (!$this->isConnected()) {
                return false;
            }

            $this->client->send($payload, 'ping');
            Log::debug('WebSocket ping sent');
            return true;
        } catch (Exception $e) {
            Log::error('Error sending ping', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Close the WebSocket connection
     *
     * @return void
     */
    public function disconnect(): void
    {
        try {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
                Log::info('WebSocket disconnected');
            }
        } catch (Exception $e) {
            Log::error('Error disconnecting WebSocket', ['error' => $e->getMessage()]);
            $this->client = null;
        }
    }

    /**
     * Check if WebSocket is connected
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    /**
     * Get the underlying WebSocket client
     *
     * @return Client|null
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Listen for messages with a callback
     *
     * @param callable $callback Function to call with each received message
     * @param int $timeout Timeout in seconds (0 = infinite)
     * @return void
     */
    public function listen(callable $callback, int $timeout = 0): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $startTime = time();

        while (true) {
            try {
                $message = $this->receive();

                if ($message !== null) {
                    call_user_func($callback, $message, $this);
                }

                // Check timeout
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    Log::info('WebSocket listen timeout reached');
                    break;
                }

                // Small delay to prevent CPU spinning
                usleep(10000); // 10ms
            } catch (Exception $e) {
                Log::error('Error in WebSocket listen loop', [
                    'error' => $e->getMessage()
                ]);

                // Try to reconnect
                try {
                    $this->disconnect();
                    sleep(1);
                    $this->connect();
                } catch (Exception $reconnectError) {
                    Log::error('Failed to reconnect', [
                        'error' => $reconnectError->getMessage()
                    ]);
                    break;
                }
            }
        }
    }

    /**
     * Send request and wait for response
     *
     * @param string|array $message
     * @param int $timeout Timeout in seconds
     * @return string|null
     */
    public function request($message, int $timeout = 5): ?string
    {
        if (!$this->send($message)) {
            return null;
        }

        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $response = $this->receive();

            if ($response !== null) {
                return $response;
            }

            usleep(50000); // 50ms
        }

        Log::warning('WebSocket request timeout', ['timeout' => $timeout]);
        return null;
    }

    /**
     * Send user update notification via WebSocket
     *
     * @param int $userId User ID
     * @param string $userState User state (CONECTADO, EN LLAMADA, FUERA DE LÍNEA)
     * @param int|string|null $campainId Campaign ID or 'ALL'
     * @param bool $autoConnect Auto-connect if not connected
     * @return bool
     */
    public function sendUserUpdate(
        int $userId,
        string $userState,
        $campainId = null,
        bool $autoConnect = true
    ): bool {
        try {
            // Auto-connect if needed
            if (!$this->isConnected() && $autoConnect) {
                $this->connect();
            }

            $user = User::find($userId);

            if (!$user) {
                Log::warning('User not found for WebSocket update', ['user_id' => $userId]);
                return false;
            }

            // Determine campaign info
            $campainName = 'ALL';
            $metrics = [];

            if ($campainId === 'ALL' || $campainId === null) {
                // For login/logout, use ALL
                $campainIdValue = 'ALL';
                $campainName = 'ALL';
                $metrics = [
                    'nro_credits' => 0,
                    'nro_gestions' => 0,
                    'nro_gestions_dia' => 0,
                    'nro_gestions_efec' => 0,
                    'nro_gestions_efec_dia' => 0,
                    'nro_pendientes' => 0,
                    'nro_proceso' => 0,
                    'nro_proceso_dia' => 0,
                    'nro_calls' => 0,
                    'nro_calls_acum' => 0,
                ];
            } else {
                // For management/call events, calculate metrics
                $campain = Campain::find($campainId);
                $campainIdValue = $campainId;
                $campainName = $campain ? $campain->name : 'Unknown';
                $metrics = $this->calculateUserMetrics($userId, $campainId);
            }

            // Calculate time in state
            $timeState = $this->calculateTimeState($user);

            // Build message
            $message = [
                'type' => 'user_update',
                'name' => $user->name,
                'user_id' => $user->id,
                'campain_id' => $campainIdValue,
                'campain_name' => $campainName,
                'user_state' => $userState,
                'time_state' => $timeState,
                'data' => $metrics
            ];

            // Send message
            $sent = $this->send($message);

            if ($sent) {
                Log::info('WebSocket user update sent', [
                    'user_id' => $userId,
                    'user_state' => $userState,
                    'campain_id' => $campainIdValue
                ]);
            }

            return $sent;
        } catch (Exception $e) {
            Log::error('Error sending WebSocket user update', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send user update after management creation
     *
     * @param int $userId
     * @param int $campainId
     * @return bool
     */
    public function sendManagementUpdate(int $userId, int $campainId): bool
    {
        return $this->sendUserUpdate($userId, 'CONECTADO', $campainId);
    }

    /**
     * Send user update after call creation/store
     *
     * @param int $userId
     * @param int $campainId
     * @return bool
     */
    public function sendCallUpdate(int $userId, int $campainId): bool
    {
        return $this->sendUserUpdate($userId, 'CONECTADO', $campainId);
    }

    /**
     * Send user update when call is dialed (user in call)
     *
     * @param int $userId
     * @param int $campainId
     * @return bool
     */
    public function sendDialUpdate(int $userId, int $campainId): bool
    {
        return $this->sendUserUpdate($userId, 'EN LLAMADA', $campainId);
    }

    /**
     * Send user update on login
     *
     * @param int $userId
     * @return bool
     */
    public function sendLoginUpdate(int $userId): bool
    {
        return $this->sendUserUpdate($userId, 'CONECTADO', 'ALL');
    }

    /**
     * Send user update on logout
     *
     * @param int $userId
     * @return bool
     */
    public function sendLogoutUpdate(int $userId): bool
    {
        return $this->sendUserUpdate($userId, 'FUERA DE LÍNEA', 'ALL');
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
