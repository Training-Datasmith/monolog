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
use Monolog\Resettable_Interface;
/**
 * Helper trait for implementing ProcessableInterface
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
trait Processable_Handler_Trait
{
    /**
     * @var callable[]
     * @phpstan-var array<(callable(LogRecord): LogRecord)|ProcessorInterface>
     */
    protected array $processors = [];
    /**
     * @inheritDoc
     */
    public function push_processor(callable $callback): Handler_Interface
    {
        array_unshift($this->processors, $callback);
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function pop_processor(): callable
    {
        if (\count($this->processors) === 0) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }
        return array_shift($this->processors);
    }
    protected function process_record(Log_Record $record): Log_Record
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }
        return $record;
    }
    protected function reset_processors(): void
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof Resettable_Interface) {
                $processor->reset();
            }
        }
    }
}