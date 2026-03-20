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
use Monolog\Log_Record;
use Monolog\Resettable_Interface;
/**
 * Forwards records to multiple handlers
 *
 * @author Lenar Lõhmus <lenar@city.ee>
 */
class Group_Handler extends Handler implements Processable_Handler_Interface, Resettable_Interface
{
    use Processable_Handler_Trait;
    /** @var HandlerInterface[] */
    protected array $handlers;
    /**
     * @param HandlerInterface[] $handlers Array of Handlers.
     * @param bool               $bubble   Whether the messages that are handled can bubble up the stack or not
     *
     * @throws \InvalidArgumentException if an unsupported handler is set
     */
    public function __construct(array $handlers, protected bool $bubble = true)
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof Handler_Interface) {
                throw new \InvalidArgumentException('The first argument of the GroupHandler must be an array of HandlerInterface instances.');
            }
        }
        $this->handlers = $handlers;
    }
    /**
     * @inheritDoc
     */
    public function is_handling(Log_Record $record): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->is_handling($record)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        foreach ($this->handlers as $handler) {
            $handler->handle(clone $record);
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
            $handler->handle_batch(array_map(fn(\Monolog\Log_Record $record): \Monolog\Log_Record => clone $record, $records));
        }
    }
    public function reset(): void
    {
        $this->reset_processors();
        foreach ($this->handlers as $handler) {
            if ($handler instanceof Resettable_Interface) {
                $handler->reset();
            }
        }
    }
    public function close(): void
    {
        parent::close();
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof Formattable_Handler_Interface) {
                $handler->set_formatter($formatter);
            }
        }
        return $this;
    }
}