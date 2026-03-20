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
namespace Monolog\Formatter;

use Monolog\Level;
use Monolog\Log_Record;
/**
 * Formats a log message according to the ChromePHP array format
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Chrome_Php_Formatter implements Formatter_Interface
{
    /**
     * Translates Monolog log levels to Wildfire levels.
     *
     * @return 'log'|'info'|'warn'|'error'
     */
    private function to_wildfire_level(Level $level): string
    {
        return match ($level) {
            Level::Debug => 'log',
            Level::Info => 'info',
            Level::Notice => 'info',
            Level::Warning => 'warn',
            Level::Error => 'error',
            Level::Critical => 'error',
            Level::Alert => 'error',
            Level::Emergency => 'error',
        };
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): array
    {
        // Retrieve the line and file if set and remove them from the formatted extra
        $backtrace = 'unknown';
        if (isset($record->extra['file'], $record->extra['line'])) {
            $backtrace = $record->extra['file'] . ' : ' . $record->extra['line'];
            unset($record->extra['file'], $record->extra['line']);
        }
        $message = ['message' => $record->message];
        if (\count($record->context) > 0) {
            $message['context'] = $record->context;
        }
        if (\count($record->extra) > 0) {
            $message['extra'] = $record->extra;
        }
        if (\count($message) === 1) {
            $message = reset($message);
        }
        return [$record->channel, $message, $backtrace, $this->to_wildfire_level($record->level)];
    }
    /**
     * @inheritDoc
     * @return mixed[]
     */
    public function format_batch(array $records): array
    {
        $formatted = [];
        foreach ($records as $record) {
            $formatted[] = $this->format($record);
        }
        return $formatted;
    }
}