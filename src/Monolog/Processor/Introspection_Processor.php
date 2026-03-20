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
namespace Monolog\Processor;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Psr\Log\Log_Level;
/**
 * Injects line/file:class/function where the log message came from
 *
 * Warning: This only works if the handler processes the logs directly.
 * If you put the processor on a handler that is behind a FingersCrossedHandler
 * for example, the processor will only be called once the trigger level is reached,
 * and all the log records will have the same file/line/.. data from the call that
 * triggered the FingersCrossedHandler.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Introspection_Processor implements Processor_Interface
{
    protected Level $level;
    /** @var string[] */
    protected array $skip_classes_partials;
    protected const SKIP_FUNCTIONS = ['call_user_func', 'call_user_func_array'];
    protected const SKIP_CLASSES = ['Monolog\\'];
    /**
     * @param string|int|Level $level               The minimum logging level at which this Processor will be triggered
     * @param string[]         $skipClassesPartials
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function __construct(int|string|Level $level = Level::Debug, array $skip_classes_partials = [], protected int $skip_stack_frames_count = 0)
    {
        $this->level = Logger::to_monolog_level($level);
        $this->skip_classes_partials = array_merge(static::SKIP_CLASSES, $skip_classes_partials);
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        // return if the level is not high enough
        if ($record->level->is_lower_than($this->level)) {
            return $record;
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // skip first since it's always the current method
        array_shift($trace);
        // the call_user_func call is also skipped
        array_shift($trace);
        $i = 0;
        while ($this->is_trace_class_or_skipped_function($trace, $i)) {
            if (isset($trace[$i]['class'])) {
                foreach ($this->skip_classes_partials as $part) {
                    if (str_contains($trace[$i]['class'], $part)) {
                        $i++;
                        continue 2;
                    }
                }
            } elseif (\in_array($trace[$i]['function'], self::SKIP_FUNCTIONS, true)) {
                $i++;
                continue;
            }
            break;
        }
        $i += $this->skip_stack_frames_count;
        // we should have the call source now
        $record->extra = array_merge($record->extra, ['file' => $trace[$i - 1]['file'] ?? null, 'line' => $trace[$i - 1]['line'] ?? null, 'class' => $trace[$i]['class'] ?? null, 'callType' => $trace[$i]['type'] ?? null, 'function' => $trace[$i]['function'] ?? null]);
        return $record;
    }
    /**
     * @param array<mixed> $trace
     */
    private function is_trace_class_or_skipped_function(array $trace, int $index): bool
    {
        if (!isset($trace[$index])) {
            return false;
        }
        return isset($trace[$index]['class']) || \in_array($trace[$index]['function'], self::SKIP_FUNCTIONS, true);
    }
}