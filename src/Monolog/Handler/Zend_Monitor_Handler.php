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

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Normalizer_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Handler sending logs to Zend Monitor
 *
 * @author  Christian Bergau <cbergau86@gmail.com>
 * @author  Jason Davis <happydude@jasondavis.net>
 */
class Zend_Monitor_Handler extends Abstract_Processing_Handler
{
    /**
     * @throws MissingExtensionException
     */
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!\function_exists('zend_monitor_custom_event')) {
            throw new Missing_Extension_Exception('You must have Zend Server installed with Zend Monitor enabled in order to use this handler');
        }
        parent::__construct($level, $bubble);
    }
    /**
     * Translates Monolog log levels to ZendMonitor levels.
     */
    protected function to_zend_monitor_level(Level $level): int
    {
        return match ($level) {
            Level::Debug => \ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Info => \ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Notice => \ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Warning => \ZEND_MONITOR_EVENT_SEVERITY_WARNING,
            Level::Error => \ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Critical => \ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Alert => \ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Emergency => \ZEND_MONITOR_EVENT_SEVERITY_ERROR,
        };
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->write_zend_monitor_custom_event($record->level->get_name(), $record->message, $record->formatted, $this->to_zend_monitor_level($record->level));
    }
    /**
     * Write to Zend Monitor Events
     * @param string       $type      Text displayed in "Class Name (custom)" field
     * @param string       $message   Text displayed in "Error String"
     * @param array<mixed> $formatted Displayed in Custom Variables tab
     * @param int          $severity  Set the event severity level (-1,0,1)
     */
    protected function write_zend_monitor_custom_event(string $type, string $message, array $formatted, int $severity): void
    {
        zend_monitor_custom_event($type, $message, $formatted, $severity);
    }
    /**
     * @inheritDoc
     */
    public function get_default_formatter(): Formatter_Interface
    {
        return new Normalizer_Formatter();
    }
}