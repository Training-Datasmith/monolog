# Monolog — Architecture

## Purpose

Monolog is a PSR-3 compliant logging library for PHP. It sends log records to files, sockets, inboxes, databases, and various web services. A channel (Logger instance) fans out to a configurable stack of Handlers, each of which can optionally bubble records up to the next handler in the stack.

## Directory Structure

```
src/Monolog/
  Logger.php                  — PSR-3 Logger; entry point for all logging calls
  Level.php                   — PHP 8.1 backed enum for log severity levels
  Log_Record.php              — Immutable value object representing a single log entry
  Date_Time_Immutable.php     — DateTimeImmutable subclass with microsecond support
  Error_Handler.php           — Registers PHP error/exception/shutdown handlers
  Formatter/                  — Convert LogRecord to strings/arrays for output
    Formatter_Interface.php   — Contract: format(LogRecord): string
    Line_Formatter.php        — Printf-style log lines (default)
    Json_Formatter.php        — Newline-delimited JSON
    Normalizer_Formatter.php  — Recursive normalization base class
    …many more target-specific formatters
  Handler/
    Handler_Interface.php     — Contract: handle(LogRecord): bool (false = bubble)
    Abstract_Handler.php      — Shared level-filtering and formatter plumbing
    Stream_Handler.php        — Writes to any PHP stream / file path
    Rotating_File_Handler.php — Daily/hourly rotating log files
    Syslog_Handler.php        — UNIX syslog
    …many more transport-specific handlers
  Processor/                  — Enrich LogRecord::extra before it reaches handlers
    Processor_Interface.php   — Contract: __invoke(LogRecord): LogRecord
```

## Key Design Decisions

### Log Level as Enum (PHP 8.1+)
`Level` is a backed `int` enum (RFC 5424 severity remapped to Monolog integers). Legacy `Logger::DEBUG/INFO/…` integer constants are `@deprecated`; new code uses `Level::Debug`, `Level::Info`, etc.

### Lazy Processor Initialization
Processors run only after at least one handler decides it will handle the record (`is_handling()` returns true). This avoids expensive processing when records will be discarded.

### Bubbling Chain
`handle()` returns `true` to stop propagation, `false` to bubble to the next handler. This allows a `FingersCrossedHandler` to buffer records and flush only on error while a `StreamHandler` continues to capture everything.

### Infinite Loop Detection
`Logger::add_record()` tracks call depth (separately per Fiber) and warns after depth 3, aborting at depth 5. This prevents runaway logging when a handler itself triggers a log call.

### Serialization Safety
`__serialize()` / `__unserialize()` are implemented to safely persist a Logger (e.g., into a session), reconstructing the WeakMap for fiber depth tracking.

## Extension Points

- **Custom Handler**: extend `Abstract_Processing_Handler`, implement `write(LogRecord): void`
- **Custom Formatter**: implement `Formatter_Interface`
- **Custom Processor**: any `callable(LogRecord): LogRecord`; optionally implement `Processor_Interface`
- **Exception Handler**: `Logger::set_exception_handler(Closure)` to catch handler failures

## Dependency Flow

```
Logger::info($msg)
  → add_record(Level::Info, $msg, $context)
       → foreach processors: enrich LogRecord
       → foreach handlers (stack order):
            handler->is_handling(record)?
            handler->handle(clone record)
                → formatter->format(record) → string
                → transport (file / socket / HTTP / …)
            if handle() returns true → stop bubbling
```
