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
use Monolog\Formatter\Html_Formatter;
use Monolog\Log_Record;
/**
 * Base class for all mail handlers
 *
 * @author Gyula Sallai
 */
abstract class Mail_Handler extends Abstract_Processing_Handler
{
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        $messages = [];
        foreach ($records as $record) {
            if ($record->level->is_lower_than($this->level)) {
                continue;
            }
            $message = $this->process_record($record);
            $messages[] = $message;
        }
        if (\count($messages) > 0) {
            $this->send((string) $this->get_formatter()->format_batch($messages), $messages);
        }
    }
    /**
     * Send a mail with the given content
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     *
     * @phpstan-param non-empty-array<LogRecord> $records
     */
    abstract protected function send(string $content, array $records): void;
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->send((string) $record->formatted, [$record]);
    }
    /**
     * @phpstan-param non-empty-array<LogRecord> $records
     */
    protected function get_highest_record(array $records): Log_Record
    {
        $highest_record = null;
        foreach ($records as $record) {
            if ($highest_record === null || $record->level->is_higher_than($highest_record->level)) {
                $highest_record = $record;
            }
        }
        return $highest_record;
    }
    protected function is_html_body(string $body): bool
    {
        return ($body[0] ?? null) === '<';
    }
    /**
     * Gets the default formatter.
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Html_Formatter();
    }
}