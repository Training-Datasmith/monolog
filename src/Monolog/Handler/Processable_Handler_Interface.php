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
use Monolog\Processor\Processor_Interface;
/**
 * Interface to describe loggers that have processors
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface Processable_Handler_Interface
{
    /**
     * Adds a processor in the stack.
     *
     * @phpstan-param ProcessorInterface|(callable(LogRecord): LogRecord) $callback
     *
     * @return HandlerInterface            self
     */
    public function push_processor(callable $callback): Handler_Interface;
    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @phpstan-return ProcessorInterface|(callable(LogRecord): LogRecord) $callback
     *
     * @throws \LogicException             In case the processor stack is empty
     */
    public function pop_processor(): callable;
}