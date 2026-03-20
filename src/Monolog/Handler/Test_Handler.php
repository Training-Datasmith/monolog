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
namespace Monolog\Handler;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use No_Discard;
use Psr\Log\Log_Level;
/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @method bool hasEmergency(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasAlert(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasCritical(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasError(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasWarning(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasNotice(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasInfo(array{message: string, context?: mixed[]}|string $recordAssertions)
 * @method bool hasDebug(array{message: string, context?: mixed[]}|string $recordAssertions)
 *
 * @method bool hasEmergencyRecords()
 * @method bool hasAlertRecords()
 * @method bool hasCriticalRecords()
 * @method bool hasErrorRecords()
 * @method bool hasWarningRecords()
 * @method bool hasNoticeRecords()
 * @method bool hasInfoRecords()
 * @method bool hasDebugRecords()
 *
 * @method bool hasEmergencyThatContains(string $message)
 * @method bool hasAlertThatContains(string $message)
 * @method bool hasCriticalThatContains(string $message)
 * @method bool hasErrorThatContains(string $message)
 * @method bool hasWarningThatContains(string $message)
 * @method bool hasNoticeThatContains(string $message)
 * @method bool hasInfoThatContains(string $message)
 * @method bool hasDebugThatContains(string $message)
 *
 * @method bool hasEmergencyThatMatches(string $regex)
 * @method bool hasAlertThatMatches(string $regex)
 * @method bool hasCriticalThatMatches(string $regex)
 * @method bool hasErrorThatMatches(string $regex)
 * @method bool hasWarningThatMatches(string $regex)
 * @method bool hasNoticeThatMatches(string $regex)
 * @method bool hasInfoThatMatches(string $regex)
 * @method bool hasDebugThatMatches(string $regex)
 *
 * @method bool hasEmergencyThatPasses(callable $predicate)
 * @method bool hasAlertThatPasses(callable $predicate)
 * @method bool hasCriticalThatPasses(callable $predicate)
 * @method bool hasErrorThatPasses(callable $predicate)
 * @method bool hasWarningThatPasses(callable $predicate)
 * @method bool hasNoticeThatPasses(callable $predicate)
 * @method bool hasInfoThatPasses(callable $predicate)
 * @method bool hasDebugThatPasses(callable $predicate)
 */
class Test_Handler extends Abstract_Processing_Handler
{
    /** @var LogRecord[] */
    protected array $records = [];
    /** @phpstan-var array<value-of<Level::VALUES>, LogRecord[]> */
    protected array $records_by_level = [];
    private bool $skip_reset = false;
    /**
     * @return array<LogRecord>
     */
    #[No_Discard]
    public function get_records(): array
    {
        return $this->records;
    }
    public function clear(): void
    {
        $this->records = [];
        $this->records_by_level = [];
    }
    public function reset(): void
    {
        if (!$this->skip_reset) {
            $this->clear();
        }
    }
    public function set_skip_reset(bool $skip_reset): void
    {
        $this->skip_reset = $skip_reset;
    }
    /**
     * @param int|string|Level|LogLevel::* $level Logging level value or name
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    #[No_Discard]
    public function has_records(int|string|Level $level): bool
    {
        return isset($this->records_by_level[Logger::to_monolog_level($level)->value]);
    }
    /**
     * @param string|array $recordAssertions Either a message string or an array containing message and optionally context keys that will be checked against all records
     *
     * @phpstan-param array{message: string, context?: mixed[]}|string $recordAssertions
     */
    #[No_Discard]
    public function has_record(string|array $record_assertions, Level $level): bool
    {
        if (\is_string($record_assertions)) {
            $record_assertions = ['message' => $record_assertions];
        }
        return $this->has_record_that_passes(function (Log_Record $rec) use ($record_assertions): bool {
            if ($rec->message !== $record_assertions['message']) {
                return false;
            }
            if (isset($record_assertions['context']) && $rec->context !== $record_assertions['context']) {
                return false;
            }
            return true;
        }, $level);
    }
    #[No_Discard]
    public function has_record_that_contains(string $message, Level $level): bool
    {
        return $this->has_record_that_passes(fn(Log_Record $rec): bool => str_contains($rec->message, $message), $level);
    }
    #[No_Discard]
    public function has_record_that_matches(string $regex, Level $level): bool
    {
        return $this->has_record_that_passes(fn(Log_Record $rec): bool => preg_match($regex, $rec->message) > 0, $level);
    }
    /**
     * @phpstan-param callable(LogRecord, int): mixed $predicate
     */
    #[No_Discard]
    public function has_record_that_passes(callable $predicate, Level $level): bool
    {
        $level = Logger::to_monolog_level($level);
        if (!isset($this->records_by_level[$level->value])) {
            return false;
        }
        foreach ($this->records_by_level[$level->value] as $i => $rec) {
            if ((bool) $predicate($rec, $i)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->records_by_level[$record->level->value][] = $record;
        $this->records[] = $record;
    }
    /**
     * @param mixed[] $args
     */
    #[No_Discard]
    public function __call(string $method, array $args): bool
    {
        if ((bool) preg_match('/(.*)(Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)(.*)/', $method, $matches)) {
            $generic_method = $matches[1] . ('Records' !== $matches[3] ? 'Record' : '') . $matches[3];
            $level = \constant(Level::class . '::' . $matches[2]);
            $callback = [$this, $generic_method];
            if (\is_callable($callback)) {
                $args[] = $level;
                return \call_user_func_array($callback, $args);
            }
        }
        throw new \BadMethodCallException('Call to undefined method ' . static::class . '::' . $method . '()');
    }
}