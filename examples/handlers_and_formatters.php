<?php

declare(strict_types=1);

/**
 * Monolog — Handlers and Formatters
 *
 * Demonstrates rotating file handler, FingersCrossedHandler, and custom
 * exception handling.
 * Run: php examples/handlers_and_formatters.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\Stream_Handler;
use Monolog\Handler\Fingers_Crossed_Handler;
use Monolog\Handler\Group_Handler;
use Monolog\Handler\Null_Handler;

// ── 1. FingersCrossedHandler: buffer until error, then flush all ─────────────

$bufferedHandler = new Fingers_Crossed_Handler(
    new Stream_Handler('php://stdout', Level::Debug),
    Level::Error  // activation level
);

$log = new Logger('fc');
$log->push_handler($bufferedHandler);

// These are buffered and NOT written yet (no error has occurred)
$log->debug('Step 1 completed');
$log->info('Step 2 completed');

// This triggers the flush: all buffered records + this one are written
$log->error('Step 3 FAILED — flushing buffered records');

// ── 2. GroupHandler: fan-out to multiple handlers ────────────────────────────

$stdout = new Stream_Handler('php://stdout', Level::Warning);
$stderr = new Stream_Handler('php://stderr', Level::Error);

$group = new Group_Handler([$stdout, $stderr]);

$fanLog = new Logger('fan');
$fanLog->push_handler($group);

$fanLog->warning('Resource limit approaching');   // → stdout only
$fanLog->error('Unhandled exception caught');     // → both

// ── 3. Custom exception handler — prevent logging failures from crashing app ─

$log3 = new Logger('safe');
$log3->push_handler(new Stream_Handler('php://stdout', Level::Debug));

$log3->set_exception_handler(function (\Throwable $e, \Monolog\Log_Record $record): void {
    // Silently ignore logging failures in production, or write to a fallback
    file_put_contents('php://stderr', 'Logging failed: ' . $e->getMessage() . "\n");
});

$log3->info('This will log normally');

// ── 4. Null handler for testing (discards everything) ────────────────────────

$testLog = new Logger('test');
$testLog->push_handler(new Null_Handler());

$testLog->critical('This goes nowhere during tests');
echo "All examples completed.\n";
