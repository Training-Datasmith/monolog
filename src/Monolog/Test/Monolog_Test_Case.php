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
namespace Monolog\Test;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Json_Serializable_Date_Time_Immutable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Psr\Log\Log_Level;
/**
 * Lets you easily generate log records and a dummy formatter for testing purposes
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Monolog_Test_Case extends \Php_Unit\Framework\Test_Case
{
    /**
     * @param array<mixed> $context
     * @param array<mixed> $extra
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    protected function get_record(int|string|Level $level = Level::Warning, string|\Stringable $message = 'test', array $context = [], string $channel = 'test', \DateTimeImmutable $datetime = new Json_Serializable_Date_Time_Immutable(true), array $extra = []): Log_Record
    {
        return new Log_Record(message: (string) $message, context: $context, level: Logger::to_monolog_level($level), channel: $channel, datetime: $datetime, extra: $extra);
    }
    /**
     * @phpstan-return list<LogRecord>
     */
    protected function get_multiple_records(): array
    {
        return [$this->get_record(Level::Debug, 'debug message 1'), $this->get_record(Level::Debug, 'debug message 2'), $this->get_record(Level::Info, 'information'), $this->get_record(Level::Warning, 'warning'), $this->get_record(Level::Error, 'error')];
    }
    protected function get_identity_formatter(): Formatter_Interface
    {
        $formatter = $this->create_mock(Formatter_Interface::class);
        $formatter->expects(self::any())->method('format')->will_return_callback(function ($record) {
            return $record->message;
        });
        return $formatter;
    }
}