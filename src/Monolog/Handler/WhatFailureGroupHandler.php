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

use Monolog\Log_Record;
use Throwable;
/**
 * Forwards records to multiple handlers suppressing failures of each handler
 * and continuing through to give every handler a chance to succeed.
 *
 * @author Craig D'Amelio <craig@damelio.ca>
 */
class What_Failure_Group_Handler extends Group_Handler
{
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle(clone $record);
            } catch (Throwable) {
                // What failure?
            }
        }
        return false === $this->bubble;
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        if (\count($this->processors) > 0) {
            $processed = [];
            foreach ($records as $record) {
                $processed[] = $this->process_record($record);
            }
            $records = $processed;
        }
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle_batch(array_map(fn(\Monolog\Log_Record $record): \Monolog\Log_Record => clone $record, $records));
            } catch (Throwable) {
                // What failure?
            }
        }
    }
    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            try {
                $handler->close();
            } catch (\Throwable) {
                // What failure?
            }
        }
    }
}