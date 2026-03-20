<?php

declare(strict_types=1);

namespace Monolog\Tests\Monolog\Security;

use Monolog\Handler\Abstract_Processing_Handler;
use Monolog\Handler\Test_Handler;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Security tests verifying that Monolog's infinite logging-loop detection
 * prevents stack overflows when a handler itself triggers a log call.
 *
 * Without this protection a handler that logs on failure (e.g. to send an
 * alert) would trigger itself recursively until the PHP call-stack exhausted.
 *
 * The detection tracks call depth and:
 * - emits a warning at depth 3
 * - silently drops all further records from depth 5 onward
 */
class Infinite_Loop_Detection_Test extends TestCase
{
    /**
     * A handler that re-logs every record it receives to the same logger
     * simulates the most common scenario: a logging failure handler that
     * itself calls logger methods.
     */
    public function test_recursive_handler_does_not_cause_infinite_loop(): void
    {
        $logger  = new Logger('test');
        $records = [];

        /** @var Logger $logger */
        $selfLoggingHandler = new class($logger, $records) extends Abstract_Processing_Handler {
            public function __construct(
                private Logger $logger,
                private array &$records
            ) {
                parent::__construct(Level::Debug);
            }

            protected function write(Log_Record $record): void
            {
                $this->records[] = $record->message;
                // Re-log to the same logger — triggers the loop
                $this->logger->debug('inner log from handler');
            }
        };

        $logger->push_handler($selfLoggingHandler);

        // This call should NOT run forever; the loop guard must abort it
        $logger->info('outer log');

        // The test passes if we reach this line (no infinite recursion / stack overflow)
        $this->assertNotEmpty($records, 'At least the initial record should be processed');
    }

    /**
     * The loop guard must emit a warning before aborting, so that developers
     * can diagnose the issue.
     */
    public function test_loop_warning_is_emitted_before_abort(): void
    {
        $testHandler = new Test_Handler();
        $logger      = new Logger('test', [$testHandler]);

        // We need a handler that causes recursion; attach it after testHandler
        // so testHandler records everything including the warning
        /** @var Logger $capturedLogger */
        $capturedLogger = $logger;
        $recursiveHandler = new class($capturedLogger) extends Abstract_Processing_Handler {
            public function __construct(private Logger $logger)
            {
                parent::__construct(Level::Debug);
            }

            protected function write(Log_Record $record): void
            {
                $this->logger->debug('re-log from handler');
            }
        };

        $logger->push_handler($recursiveHandler);
        $logger->info('initial message');

        $messages = array_column($testHandler->get_records(), 'message');

        // The loop-detection warning must appear in the recorded output
        $hasWarning = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, 'infinite logging loop')) {
                $hasWarning = true;
                break;
            }
        }

        $this->assertTrue(
            $hasWarning,
            'Expected an "infinite logging loop" warning but none was found in: ' . implode(', ', $messages)
        );
    }

    /**
     * When loop detection is disabled, the developer takes responsibility for
     * preventing recursion. The flag should disable the depth check.
     */
    public function test_loop_detection_can_be_disabled(): void
    {
        $logger      = new Logger('test');
        $callCount   = 0;

        // A handler that increments a counter and stops after 3 calls
        $limitedHandler = new class($callCount) extends Abstract_Processing_Handler {
            public function __construct(private int &$count)
            {
                parent::__construct(Level::Debug);
            }

            protected function write(Log_Record $record): void
            {
                $this->count++;
            }
        };

        $logger->push_handler($limitedHandler);
        $logger->use_logging_loop_detection(false);

        // With detection disabled, depth tracking is off — records should flow normally
        $logger->info('message 1');
        $logger->info('message 2');
        $logger->info('message 3');
        $logger->info('message 4');
        $logger->info('message 5');

        // All 5 messages should be processed (depth 1 each, no nesting here)
        $this->assertSame(5, $callCount, 'All records should be handled when loop detection is off');
    }
}
