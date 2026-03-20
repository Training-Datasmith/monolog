<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Monolog;

use Closure;
use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
/**
 * Monolog error handler
 *
 * A facility to enable logging of runtime errors, exceptions and fatal errors.
 *
 * Quick setup: <code>ErrorHandler::register($logger);</code>
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Error_Handler
{
    private Closure|null $previous_exception_handler = null;
    /** @var array<class-string, LogLevel::*> an array of class name to LogLevel::* constant mapping */
    private array $uncaught_exception_level_map = [];
    /** @var Closure|true|null */
    private Closure|bool|null $previous_error_handler = null;
    /** @var array<int, LogLevel::*> an array of E_* constant to LogLevel::* constant mapping */
    private array $error_level_map = [];
    private bool $handle_only_reported_errors = true;
    private bool $has_fatal_error_handler = false;
    private string $fatal_level = Log_Level::ALERT;
    /** @var ?array{type: int, message: string, file: string, line: int, trace: mixed} */
    private array|null $last_fatal_data = null;
    private const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    public function __construct(private readonly Logger_Interface $logger)
    {
    }
    /**
     * Registers a new ErrorHandler for a given Logger
     *
     * By default it will handle errors, exceptions and fatal errors
     *
     * @param  array<int, LogLevel::*>|false          $errorLevelMap     an array of E_* constant to LogLevel::* constant mapping, or false to disable error handling
     * @param  array<class-string, LogLevel::*>|false $exceptionLevelMap an array of class name to LogLevel::* constant mapping, or false to disable exception handling
     * @param  LogLevel::*|null|false                 $fatalLevel        a LogLevel::* constant, null to use the default LogLevel::ALERT or false to disable fatal error handling
     * @return static
     */
    public static function register(Logger_Interface $logger, $error_level_map = [], $exception_level_map = [], $fatal_level = null): self
    {
        /** @phpstan-ignore-next-line */
        $handler = new static($logger);
        if ($error_level_map !== false) {
            $handler->register_error_handler($error_level_map);
        }
        if ($exception_level_map !== false) {
            $handler->register_exception_handler($exception_level_map);
        }
        if ($fatal_level !== false) {
            $handler->register_fatal_handler($fatal_level);
        }
        return $handler;
    }
    /**
     * @param  array<class-string, LogLevel::*> $levelMap an array of class name to LogLevel::* constant mapping
     * @return $this
     */
    public function register_exception_handler(array $level_map = [], bool $call_previous = true): self
    {
        $prev = set_exception_handler(function (\Throwable $e): void {
            $this->handle_exception($e);
        });
        $this->uncaught_exception_level_map = $level_map;
        foreach ($this->default_exception_level_map() as $class => $level) {
            if (!isset($this->uncaught_exception_level_map[$class])) {
                $this->uncaught_exception_level_map[$class] = $level;
            }
        }
        if ($call_previous && null !== $prev) {
            $this->previous_exception_handler = $prev(...);
        }
        return $this;
    }
    /**
     * @param  array<int, LogLevel::*> $levelMap an array of E_* constant to LogLevel::* constant mapping
     * @return $this
     */
    public function register_error_handler(array $level_map = [], bool $call_previous = true, int $error_types = -1, bool $handle_only_reported_errors = true): self
    {
        $prev = set_error_handler($this->handle_error(...), $error_types);
        $this->error_level_map = array_replace($this->default_error_level_map(), $level_map);
        if ($call_previous) {
            $this->previous_error_handler = $prev !== null ? $prev(...) : true;
        } else {
            $this->previous_error_handler = null;
        }
        $this->handle_only_reported_errors = $handle_only_reported_errors;
        return $this;
    }
    /**
     * @param  LogLevel::*|null $level              a LogLevel::* constant, null to use the default LogLevel::ALERT
     * @param  int              $reservedMemorySize Amount of KBs to reserve in memory so that it can be freed when handling fatal errors giving Monolog some room in memory to get its job done
     * @return $this
     */
    public function register_fatal_handler($level = null, int $reserved_memory_size = 20): self
    {
        register_shutdown_function($this->handle_fatal_error(...));
        $this->fatal_level = $level ?? Log_Level::ALERT;
        $this->has_fatal_error_handler = true;
        return $this;
    }
    /**
     * @return array<class-string, LogLevel::*>
     */
    protected function default_exception_level_map(): array
    {
        return ['ParseError' => Log_Level::CRITICAL, 'Throwable' => Log_Level::ERROR];
    }
    /**
     * @return array<int, LogLevel::*>
     */
    protected function default_error_level_map(): array
    {
        return [
            E_ERROR => Log_Level::CRITICAL,
            E_WARNING => Log_Level::WARNING,
            E_PARSE => Log_Level::ALERT,
            E_NOTICE => Log_Level::NOTICE,
            E_CORE_ERROR => Log_Level::CRITICAL,
            E_CORE_WARNING => Log_Level::WARNING,
            E_COMPILE_ERROR => Log_Level::ALERT,
            E_COMPILE_WARNING => Log_Level::WARNING,
            E_USER_ERROR => Log_Level::ERROR,
            E_USER_WARNING => Log_Level::WARNING,
            E_USER_NOTICE => Log_Level::NOTICE,
            2048 => Log_Level::NOTICE,
            // E_STRICT
            E_RECOVERABLE_ERROR => Log_Level::ERROR,
            E_DEPRECATED => Log_Level::NOTICE,
            E_USER_DEPRECATED => Log_Level::NOTICE,
        ];
    }
    private function handle_exception(\Throwable $e): never
    {
        $level = Log_Level::ERROR;
        foreach ($this->uncaught_exception_level_map as $class => $candidate) {
            if ($e instanceof $class) {
                $level = $candidate;
                break;
            }
        }
        $this->logger->log($level, sprintf('Uncaught Exception %s: "%s" at %s line %s', Utils::get_class($e), $e->get_message(), $e->get_file(), $e->get_line()), ['exception' => $e]);
        if (null !== $this->previous_exception_handler) {
            ($this->previous_exception_handler)($e);
        }
        if (!headers_sent() && \in_array(strtolower((string) \ini_get('display_errors')), ['0', '', 'false', 'off', 'none', 'no'], true)) {
            http_response_code(500);
        }
        exit(255);
    }
    private function handle_error(int $code, string $message, string $file = '', int $line = 0): bool
    {
        if ($this->handle_only_reported_errors && 0 === (error_reporting() & $code)) {
            return false;
        }
        // fatal error codes are ignored if a fatal error handler is present as well to avoid duplicate log entries
        if (!$this->has_fatal_error_handler || !\in_array($code, self::FATAL_ERRORS, true)) {
            $level = $this->error_level_map[$code] ?? Log_Level::CRITICAL;
            $this->logger->log($level, self::code_to_string($code) . ': ' . $message, ['code' => $code, 'message' => $message, 'file' => $file, 'line' => $line]);
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            // Exclude handleError from trace
            $this->last_fatal_data = ['type' => $code, 'message' => $message, 'file' => $file, 'line' => $line, 'trace' => $trace];
        }
        if ($this->previous_error_handler === true) {
            return false;
        }
        if ($this->previous_error_handler instanceof Closure) {
            return (bool) ($this->previous_error_handler)($code, $message, $file, $line);
        }
        return true;
    }
    /**
     * @private
     */
    public function handle_fatal_error(): void
    {
        if (\is_array($this->last_fatal_data)) {
            $last_error = $this->last_fatal_data;
        } else {
            $last_error = error_get_last();
        }
        if (\is_array($last_error) && \in_array($last_error['type'], self::FATAL_ERRORS, true)) {
            $trace = $last_error['trace'] ?? null;
            $this->logger->log($this->fatal_level, 'Fatal Error (' . self::code_to_string($last_error['type']) . '): ' . $last_error['message'], ['code' => $last_error['type'], 'message' => $last_error['message'], 'file' => $last_error['file'], 'line' => $last_error['line'], 'trace' => $trace]);
            if ($this->logger instanceof Logger) {
                foreach ($this->logger->get_handlers() as $handler) {
                    $handler->close();
                }
            }
        }
    }
    private static function code_to_string(int $code): string
    {
        return match ($code) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            2048 => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'Unknown PHP error',
        };
    }
}