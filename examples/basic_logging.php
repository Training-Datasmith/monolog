<?php

declare(strict_types=1);

/**
 * Monolog — Basic Logging Examples
 *
 * Demonstrates channel setup, log levels, handlers, and processors.
 * Run: php examples/basic_logging.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\Stream_Handler;
use Monolog\Handler\Rotating_File_Handler;
use Monolog\Formatter\Line_Formatter;
use Monolog\Formatter\Json_Formatter;
use Monolog\Processor\Web_Processor;
use Monolog\Processor\Uid_Processor;

// ── 1. Minimal setup: log to STDOUT ─────────────────────────────────────────

$log = new Logger('app');
$log->push_handler(new Stream_Handler('php://stdout', Level::Debug));

$log->info('Application started', ['version' => '1.0.0']);
$log->debug('Debug detail', ['user_id' => 42]);
$log->warning('Low disk space', ['free_mb' => 512]);
$log->error('Failed to save record', ['table' => 'orders', 'id' => 99]);

// ── 2. Custom line format ────────────────────────────────────────────────────

$formatter = new Line_Formatter(
    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
    'Y-m-d H:i:s.u',
    true,   // allow inline line breaks
    true    // ignore empty context/extra
);

$handler = new Stream_Handler('php://stdout', Level::Info);
$handler->set_formatter($formatter);

$log2 = new Logger('web');
$log2->push_handler($handler);

$log2->info('Request received', ['method' => 'GET', 'path' => '/api/users']);

// ── 3. Multiple handlers with different minimum levels ───────────────────────

$appLog = new Logger('api');
// Everything DEBUG and above → stdout
$appLog->push_handler(new Stream_Handler('php://stdout', Level::Debug));
// Only ERROR and above → a dedicated error log
$appLog->push_handler(new Stream_Handler('php://stderr', Level::Error));

$appLog->debug('SQL query executed', ['query' => 'SELECT * FROM users LIMIT 10']);
$appLog->info('Cache miss', ['key' => 'user:42']);
$appLog->critical('Database connection lost');

// ── 4. Context enrichment via Processors ─────────────────────────────────────

$enrichedLog = new Logger('enriched');
$enrichedLog->push_handler(new Stream_Handler('php://stdout', Level::Debug));

// Attach a unique request ID to every log record
$enrichedLog->push_processor(new Uid_Processor(32));

// Attach a custom processor (closure)
$enrichedLog->push_processor(function (\Monolog\Log_Record $record) {
    return $record->with(extra: array_merge($record->extra, [
        'app_env' => 'production',
        'region'  => 'eu-west-1',
    ]));
});

$enrichedLog->info('Order placed', ['order_id' => 'ORD-2025-001']);

// ── 5. JSON output for log aggregators ───────────────────────────────────────

$jsonLog = new Logger('json-channel');
$jsonHandler = new Stream_Handler('php://stdout', Level::Info);
$jsonHandler->set_formatter(new Json_Formatter());
$jsonLog->push_handler($jsonHandler);

$jsonLog->info('Payment processed', [
    'amount'   => 9999,
    'currency' => 'USD',
    'gateway'  => 'stripe',
]);
