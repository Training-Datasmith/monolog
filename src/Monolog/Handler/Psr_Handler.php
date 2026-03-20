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
use Monolog\Level;
use Monolog\Log_Record;
use Psr\Log\Logger_Interface;
/**
 * Proxies log messages to an existing PSR-3 compliant logger.
 *
 * If a formatter is configured, the formatter's output MUST be a string and the
 * formatted message will be fed to the wrapped PSR logger instead of the original
 * log record's message.
 *
 * @author Michael Moussa <michael.moussa@gmail.com>
 */
class Psr_Handler extends Abstract_Handler implements Formattable_Handler_Interface
{
    protected Formatter_Interface|null $formatter = null;
    /**
     * @param LoggerInterface $logger The underlying PSR-3 compliant logger to which messages will be proxied
     */
    public function __construct(protected Logger_Interface $logger, int|string|Level $level = Level::Debug, bool $bubble = true, private readonly bool $include_extra = false)
    {
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        $message = $this->formatter !== null ? (string) $this->formatter->format($record) : $record->message;
        $context = $this->include_extra ? [...$record->extra, ...$record->context] : $record->context;
        $this->logger->log($record->level->to_psr_log_level(), $message, $context);
        return false === $this->bubble;
    }
    /**
     * Sets the formatter.
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        $this->formatter = $formatter;
        return $this;
    }
    /**
     * Gets the formatter.
     */
    public function get_formatter(): Formatter_Interface
    {
        if ($this->formatter === null) {
            throw new \LogicException('No formatter has been set and this handler does not have a default formatter');
        }
        return $this->formatter;
    }
}