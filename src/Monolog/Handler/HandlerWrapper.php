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
 * This simple wrapper class can be used to extend handlers functionality.
 *
 * Example: A custom filtering that can be applied to any handler.
 *
 * Inherit from this class and override handle() like this:
 *
 *   public function handle(LogRecord $record)
 *   {
 *        if ($record meets certain conditions) {
 *            return false;
 *        }
 *        return $this->handler->handle($record);
 *   }
 *
 * @author Alexey Karapetov <alexey@karapetov.com>
 */
class Handler_Wrapper implements Handler_Interface, Processable_Handler_Interface, Formattable_Handler_Interface, Resettable_Interface
{
    public function __construct(protected Handler_Interface $handler)
    {
    }
    /**
     * @inheritDoc
     */
    public function is_handling(Log_Record $record): bool
    {
        return $this->handler->is_handling($record);
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        return $this->handler->handle($record);
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        $this->handler->handle_batch($records);
    }
    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->handler->close();
    }
    /**
     * @inheritDoc
     */
    public function push_processor(callable $callback): Handler_Interface
    {
        if ($this->handler instanceof Processable_Handler_Interface) {
            $this->handler->push_processor($callback);
            return $this;
        }
        throw new \LogicException('The wrapped handler does not implement ' . Processable_Handler_Interface::class);
    }
    /**
     * @inheritDoc
     */
    public function pop_processor(): callable
    {
        if ($this->handler instanceof Processable_Handler_Interface) {
            return $this->handler->pop_processor();
        }
        throw new \LogicException('The wrapped handler does not implement ' . Processable_Handler_Interface::class);
    }
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        if ($this->handler instanceof Formattable_Handler_Interface) {
            $this->handler->set_formatter($formatter);
            return $this;
        }
        throw new \LogicException('The wrapped handler does not implement ' . Formattable_Handler_Interface::class);
    }
    /**
     * @inheritDoc
     */
    public function get_formatter(): Formatter_Interface
    {
        if ($this->handler instanceof Formattable_Handler_Interface) {
            return $this->handler->get_formatter();
        }
        throw new \LogicException('The wrapped handler does not implement ' . Formattable_Handler_Interface::class);
    }
    public function reset(): void
    {
        if ($this->handler instanceof Resettable_Interface) {
            $this->handler->reset();
        }
    }
}