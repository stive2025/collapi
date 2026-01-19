<?php

namespace App\Swagger;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CustomSwaggerLogger implements LoggerInterface
{
    use \Psr\Log\LoggerTrait;

    public function log($level, $message, array $context = []): void
    {
        // Ignore el warning específico sobre PathItem
        if (strpos($message, 'Required @OA\PathItem() not found') !== false) {
            return;
        }

        // Para otros mensajes, usar el logger por defecto de Laravel
        if (in_array($level, [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
            Log::error($message, $context);
        } elseif ($level === LogLevel::WARNING) {
            Log::warning($message, $context);
        } elseif ($level === LogLevel::INFO) {
            Log::info($message, $context);
        } else {
            Log::debug($message, $context);
        }
    }
}
