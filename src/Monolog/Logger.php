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
use DateTimeZone;
use Fiber;
use Monolog\Handler\Handler_Interface;
use Monolog\Processor\Processor_Interface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
use Stringable;
use Throwable;
use WeakMap;
/**
 * Monolog log channel
 *
 * It contains a stack of Handlers and a stack of Processors,
 * and uses them to store records that are added to it.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @final
 */
class Logger implements Logger_Interface, Resettable_Interface
{
    /**
     * Detailed debug information
     *
     * @deprecated Use \Monolog\Level::Debug
     */
    public const DEBUG = 100;
    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     *
     * @deprecated Use \Monolog\Level::Info
     */
    public const INFO = 200;
    /**
     * Uncommon events
     *
     * @deprecated Use \Monolog\Level::Notice
     */
    public const NOTICE = 250;
    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     *
     * @deprecated Use \Monolog\Level::Warning
     */
    public const WARNING = 300;
    /**
     * Runtime errors
     *
     * @deprecated Use \Monolog\Level::Error
     */
    public const ERROR = 400;
    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @deprecated Use \Monolog\Level::Critical
     */
    public const CRITICAL = 500;
    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     *
     * @deprecated Use \Monolog\Level::Alert
     */
    public const ALERT = 550;
    /**
     * Urgent alert.
     *
     * @deprecated Use \Monolog\Level::Emergency
     */
    public const EMERGENCY = 600;
    /**
     * Monolog API version
     *
     * This is only bumped when API breaks are done and should
     * follow the major version of the library
     */
    public const API = 3;
    /**
     * Mapping between levels numbers defined in RFC 5424 and Monolog ones
     *
     * @phpstan-var array<int, Level> $rfc_5424_levels
     */
    private const RFC_5424_LEVELS = [7 => Level::Debug, 6 => Level::Info, 5 => Level::Notice, 4 => Level::Warning, 3 => Level::Error, 2 => Level::Critical, 1 => Level::Alert, 0 => Level::Emergency];
    /**
     * The handler stack
     *
     * @var list<HandlerInterface>
     */
    protected array $handlers;
    protected bool $microsecond_timestamps = true;
    protected DateTimeZone $timezone;
    protected Closure|null $exception_handler = null;
    /**
     * Keeps track of depth to prevent infinite logging loops
     */
    private int $log_depth = 0;
    /**
     * @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, int> Keeps track of depth inside fibers to prevent infinite logging loops
     */
    private WeakMap $fiber_log_depth;
    /**
     * Whether to detect infinite logging loops
     * This can be disabled via {@see useLoggingLoopDetection} if you have async handlers that do not play well with this
     */
    private bool $detect_cycles = true;
    /**
     * @param string             $name       The logging channel, a simple descriptive name that is attached to all log records
     * @param list<HandlerInterface> $handlers   Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]         $processors Optional array of processors
     * @param DateTimeZone|null  $timezone   Optional timezone, if not provided date_default_timezone_get() will be used
     *
     * @phpstan-param array<(callable(LogRecord): LogRecord)|ProcessorInterface> $processors
     */
    public function __construct(
        protected string $name,
        array $handlers = [],
        /**
         * Processors that will process all log records
         *
         * To process records of a single handler instead, add the processor on that specific handler
         */
        protected array $processors = [],
        DateTimeZone|null $timezone = null
    )
    {
        $this->set_handlers($handlers);
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->fiber_log_depth = new \WeakMap();
    }
    /**
     * Returns the channel name assigned to this logger.
     *
     * The channel name identifies this logger in the output of every handler,
     * making it easy to distinguish log records from different parts of an
     * application (e.g. 'app', 'security', 'db').
     *
     * @return string The channel name (as set in the constructor or via with_name()).
     * @since  1.0
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Returns a new Logger instance that shares all handlers and processors of
     * this instance but uses a different channel name.
     *
     * Useful for creating sub-loggers for different subsystems without
     * duplicating handler configuration.
     *
     * @param  string $name The new channel name for the cloned logger.
     * @return static A new Logger instance with the same handlers/processors.
     * @since  1.6
     */
    public function with_name(string $name): self
    {
        $new = clone $this;
        $new->name = $name;
        return $new;
    }
    /**
     * Pushes a handler onto the top of the handler stack.
     *
     * Handlers are processed in LIFO order: the last handler pushed is the
     * first one called. The handler's bubbling setting controls whether
     * subsequent handlers also receive the record.
     *
     * @param  Handler_Interface $handler The handler to add to the top of the stack.
     * @return $this Fluent interface.
     * @since  1.0
     * @see    pop_handler()  To remove the top handler.
     * @see    set_handlers() To replace all handlers at once.
     */
    public function push_handler(Handler_Interface $handler): self
    {
        array_unshift($this->handlers, $handler);
        return $this;
    }
    /**
     * Pops a handler from the stack
     *
     * @throws \LogicException If empty handler stack
     */
    public function pop_handler(): Handler_Interface
    {
        if (0 === \count($this->handlers)) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }
        return array_shift($this->handlers);
    }
    /**
     * Set handlers, replacing all existing ones.
     *
     * If a map is passed, keys will be ignored.
     *
     * @param  list<HandlerInterface> $handlers
     * @return $this
     */
    public function set_handlers(array $handlers): self
    {
        $this->handlers = [];
        foreach (array_reverse($handlers) as $handler) {
            $this->push_handler($handler);
        }
        return $this;
    }
    /**
     * @return list<HandlerInterface>
     */
    public function get_handlers(): array
    {
        return $this->handlers;
    }
    /**
     * Adds a processor on to the stack.
     *
     * @phpstan-param ProcessorInterface|(callable(LogRecord): LogRecord) $callback
     * @return $this
     */
    public function push_processor(Processor_Interface|callable $callback): self
    {
        array_unshift($this->processors, $callback);
        return $this;
    }
    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @phpstan-return ProcessorInterface|(callable(LogRecord): LogRecord)
     * @throws \LogicException If empty processor stack
     */
    public function pop_processor(): callable
    {
        if (0 === \count($this->processors)) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }
        return array_shift($this->processors);
    }
    /**
     * @return callable[]
     * @phpstan-return array<ProcessorInterface|(callable(LogRecord): LogRecord)>
     */
    public function get_processors(): array
    {
        return $this->processors;
    }
    /**
     * Controls whether log record timestamps include microsecond precision.
     *
     * As of PHP 7.1, microseconds are always captured by the engine with no
     * performance overhead. Monolog 2+ enables microseconds by default.
     * Call with `false` to suppress microseconds from output (e.g. for log
     * aggregators that do not handle sub-second precision).
     *
     * @param  bool $micro True to include microseconds (default); false to truncate to seconds.
     * @return $this Fluent interface.
     * @since  2.0
     */
    public function use_microsecond_timestamps(bool $micro): self
    {
        $this->microsecond_timestamps = $micro;
        return $this;
    }
    /**
     * Enables or disables infinite logging-loop detection.
     *
     * When enabled (default), Monolog tracks how deeply `add_record` is
     * nested. A warning is emitted at depth 3 and all further records are
     * silently dropped at depth 5. This prevents stack overflows when a
     * handler itself triggers a log call.
     *
     * Disable only when you have async handlers that intentionally call back
     * into the logger from a different execution context (e.g. Fiber or
     * ReactPHP event loop) and the depth counter creates false positives.
     *
     * @param  bool $detect_cycles True to enable loop detection (default); false to disable.
     * @return $this Fluent interface.
     * @since  2.6
     */
    public function use_logging_loop_detection(bool $detect_cycles): self
    {
        $this->detect_cycles = $detect_cycles;
        return $this;
    }
    /**
     * Creates a log record and dispatches it through the handler stack.
     *
     * This is the core method invoked by all convenience methods (debug, info,
     * warning, etc.). Processors are applied lazily: only after at least one
     * handler agrees to handle the record (is_handling() returns true).
     *
     * Infinite loop protection: if add_record is called recursively (e.g. from
     * inside a handler), a warning is emitted at depth 3 and the record is
     * silently dropped at depth 5. This prevents stack overflows.
     *
     * @param  int|Level                              $level    The log severity. May be:
     *                                                           - A `Level` enum case (preferred)
     *                                                           - A Monolog integer constant (e.g. 200 for INFO)
     *                                                           - An RFC 5424 integer (0–7)
     * @param  string                                 $message  The human-readable log message.
     * @param  array<string, mixed>                   $context  Contextual data to attach to the record.
     *                                                           All values must be serialisable by the formatter.
     * @param  ?Json_Serializable_Date_Time_Immutable $datetime Custom timestamp for the record; uses now() when null.
     *                                                           Pass a past/future datetime to backfill log entries.
     *
     * @return bool True when at least one handler accepted the record; false when all handlers ignored it.
     *
     * @phpstan-param value-of<Level::VALUES>|Level $level
     * @complexity O(h * p) where h = handler count and p = processor count.
     * @since 1.0
     */
    public function add_record(int|Level $level, string $message, array $context = [], Json_Serializable_Date_Time_Immutable|null $datetime = null): bool
    {
        if (\is_int($level) && isset(self::RFC_5424_LEVELS[$level])) {
            $level = self::RFC_5424_LEVELS[$level];
        }
        if ($this->detect_cycles) {
            if (null !== $fiber = Fiber::get_current()) {
                $log_depth = $this->fiber_log_depth[$fiber] = ($this->fiber_log_depth[$fiber] ?? 0) + 1;
            } else {
                $log_depth = ++$this->log_depth;
            }
        } else {
            $log_depth = 0;
        }
        if ($log_depth === 3) {
            $this->warning('A possible infinite logging loop was detected and aborted. It appears some of your handler code is triggering logging, see the previous log record for a hint as to what may be the cause.');
            return false;
        }
        if ($log_depth >= 5) {
            // log depth 4 is let through, so we can log the warning above
            return false;
        }
        try {
            $record_initialized = \count($this->processors) === 0;
            $record = new Log_Record(datetime: $datetime ?? new Json_Serializable_Date_Time_Immutable($this->microsecond_timestamps, $this->timezone), channel: $this->name, level: self::to_monolog_level($level), message: $message, context: $context, extra: []);
            $handled = false;
            foreach ($this->handlers as $handler) {
                if (false === $record_initialized) {
                    // skip initializing the record as long as no handler is going to handle it
                    if (!$handler->is_handling($record)) {
                        continue;
                    }
                    try {
                        foreach ($this->processors as $processor) {
                            $record = $processor($record);
                        }
                        $record_initialized = true;
                    } catch (Throwable $e) {
                        $this->handle_exception($e, $record);
                        return true;
                    }
                }
                // once the record is initialized, send it to all handlers as long as the bubbling chain is not interrupted
                try {
                    $handled = true;
                    if (true === $handler->handle(clone $record)) {
                        break;
                    }
                } catch (Throwable $e) {
                    $this->handle_exception($e, $record);
                    return true;
                }
            }
            return $handled;
        } finally {
            if ($this->detect_cycles) {
                if (isset($fiber)) {
                    $this->fiber_log_depth[$fiber]--;
                } else {
                    $this->log_depth--;
                }
            }
        }
    }
    /**
     * Ends a log cycle and frees all resources used by handlers.
     *
     * Closing a Handler means flushing all buffers and freeing any open resources/handles.
     * Handlers that have been closed should be able to accept log records again and re-open
     * themselves on demand, but this may not always be possible depending on implementation.
     *
     * This is useful at the end of a request and will be called automatically on every handler
     * when they get destructed.
     */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }
    /**
     * Ends a log cycle and resets all handlers and processors to their initial state.
     *
     * Resetting a Handler or a Processor means flushing/cleaning all buffers, resetting internal
     * state, and getting it back to a state in which it can receive log records again.
     *
     * This is useful in case you want to avoid logs leaking between two requests or jobs when you
     * have a long running process like a worker or an application server serving multiple requests
     * in one process.
     */
    public function reset(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof Resettable_Interface) {
                $handler->reset();
            }
        }
        foreach ($this->processors as $processor) {
            if ($processor instanceof Resettable_Interface) {
                $processor->reset();
            }
        }
    }
    /**
     * Gets the name of the logging level as a string.
     *
     * This still returns a string instead of a Level for BC, but new code should not rely on this method.
     *
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     *
     * @phpstan-param  value-of<Level::VALUES>|Level $level
     * @phpstan-return value-of<Level::NAMES>
     *
     * @deprecated Since 3.0, use {@see toMonologLevel} or {@see \Monolog\Level->getName()} instead
     */
    public static function get_level_name(int|Level $level): string
    {
        trigger_error('Logger::get_level_name() is deprecated since Monolog 3.0; use Level->getName() instead.', E_USER_DEPRECATED);
        return self::to_monolog_level($level)->get_name();
    }
    /**
     * Converts PSR-3 levels to Monolog ones if necessary
     *
     * @param  int|string|Level|LogLevel::*      $level Level number (monolog) or name (PSR-3)
     * @throws \Psr\Log\InvalidArgumentException If level is not defined
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public static function to_monolog_level(string|int|Level $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }
        if (\is_string($level)) {
            if (is_numeric($level)) {
                $level_enum = Level::try_from((int) $level);
                if ($level_enum === null) {
                    throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' . implode(', ', Level::NAMES + Level::VALUES));
                }
                return $level_enum;
            }
            // Contains first char of all log levels and avoids using strtoupper() which may have
            // strange results depending on locale (for example, "i" will become "İ" in Turkish locale)
            $upper = strtr(substr($level, 0, 1), 'dinweca', 'DINWECA') . strtolower(substr($level, 1));
            if (\defined(Level::class . '::' . $upper)) {
                return \constant(Level::class . '::' . $upper);
            }
            throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' . implode(', ', Level::NAMES + Level::VALUES));
        }
        $level_enum = Level::try_from($level);
        if ($level_enum === null) {
            throw new InvalidArgumentException('Level "' . var_export($level, true) . '" is not defined, use one of: ' . implode(', ', Level::NAMES + Level::VALUES));
        }
        return $level_enum;
    }
    /**
     * Checks whether the Logger has a handler that listens on the given level
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function is_handling(int|string|Level $level): bool
    {
        $record = new Log_Record(datetime: new Json_Serializable_Date_Time_Immutable($this->microsecond_timestamps, $this->timezone), channel: $this->name, message: '', level: self::to_monolog_level($level));
        foreach ($this->handlers as $handler) {
            if ($handler->is_handling($record)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Set a custom exception handler that will be called if adding a new record fails
     *
     * The Closure will receive an exception object and the record that failed to be logged
     *
     * @return $this
     */
    public function set_exception_handler(Closure|null $callback): self
    {
        $this->exception_handler = $callback;
        return $this;
    }
    public function get_exception_handler(): Closure|null
    {
        return $this->exception_handler;
    }
    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed             $level   The log level (a Monolog, PSR-3 or RFC 5424 level)
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     *
     * @phpstan-param Level|LogLevel::* $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$level instanceof Level) {
            if (!\is_string($level) && !\is_int($level)) {
                throw new \InvalidArgumentException('$level is expected to be a string, int or ' . Level::class . ' instance');
            }
            if (isset(self::RFC_5424_LEVELS[$level])) {
                $level = self::RFC_5424_LEVELS[$level];
            }
            $level = static::to_monolog_level($level);
        }
        $this->add_record($level, (string) $message, $context);
    }
    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Debug, (string) $message, $context);
    }
    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Info, (string) $message, $context);
    }
    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Notice, (string) $message, $context);
    }
    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Warning, (string) $message, $context);
    }
    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Error, (string) $message, $context);
    }
    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Critical, (string) $message, $context);
    }
    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Alert, (string) $message, $context);
    }
    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|Stringable $message The log message
     * @param mixed[]           $context The log context
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->add_record(Level::Emergency, (string) $message, $context);
    }
    /**
     * Sets the timezone to be used for the timestamp of log records.
     *
     * @return $this
     */
    public function set_timezone(DateTimeZone $tz): self
    {
        $this->timezone = $tz;
        return $this;
    }
    /**
     * Returns the timezone to be used for the timestamp of log records.
     */
    public function get_timezone(): DateTimeZone
    {
        return $this->timezone;
    }
    /**
     * Delegates exception management to the custom exception handler,
     * or throws the exception if no custom handler is set.
     */
    protected function handle_exception(Throwable $e, Log_Record $record): void
    {
        if (null === $this->exception_handler) {
            throw $e;
        }
        ($this->exception_handler)($e, $record);
    }
    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return ['name' => $this->name, 'handlers' => $this->handlers, 'processors' => $this->processors, 'microsecondTimestamps' => $this->microsecond_timestamps, 'timezone' => $this->timezone, 'exceptionHandler' => $this->exception_handler, 'logDepth' => $this->log_depth, 'detectCycles' => $this->detect_cycles];
    }
    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach (['name', 'handlers', 'processors', 'microsecondTimestamps', 'timezone', 'exceptionHandler', 'logDepth', 'detectCycles'] as $property) {
            if (isset($data[$property])) {
                $this->{$property} = $data[$property];
            }
        }
        $this->fiber_log_depth = new \WeakMap();
    }
}