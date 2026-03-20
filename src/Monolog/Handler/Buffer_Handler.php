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
use Monolog\Resettable_Interface;
/**
 * Buffers all records until closing the handler and then pass them as batch.
 *
 * This is useful for a MailHandler to send only one mail per request instead of
 * sending one per log message.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Buffer_Handler extends Abstract_Handler implements Processable_Handler_Interface, Formattable_Handler_Interface
{
    use Processable_Handler_Trait;
    protected int $buffer_size = 0;
    /** @var LogRecord[] */
    protected array $buffer = [];
    protected bool $initialized = false;
    /**
     * @param HandlerInterface $handler         Handler.
     * @param int              $bufferLimit     How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param bool             $flushOnOverflow If true, the buffer is flushed when the max size has been reached, by default oldest entries are discarded
     */
    public function __construct(protected Handler_Interface $handler, protected int $buffer_limit = 0, int|string|Level $level = Level::Debug, bool $bubble = true, protected bool $flush_on_overflow = false)
    {
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if ($record->level->is_lower_than($this->level)) {
            return false;
        }
        if (!$this->initialized) {
            // __destructor() doesn't get called on Fatal errors
            register_shutdown_function([$this, 'close']);
            $this->initialized = true;
        }
        if ($this->buffer_limit > 0 && $this->buffer_size === $this->buffer_limit) {
            if ($this->flush_on_overflow) {
                $this->flush();
            } else {
                array_shift($this->buffer);
                $this->buffer_size--;
            }
        }
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        $this->buffer[] = $record;
        $this->buffer_size++;
        return false === $this->bubble;
    }
    public function flush(): void
    {
        if ($this->buffer_size === 0) {
            return;
        }
        $this->handler->handle_batch($this->buffer);
        $this->clear();
    }
    public function __destruct()
    {
        // suppress the parent behavior since we already have register_shutdown_function()
        // to call close(), and the reference contained there will prevent this from being
        // GC'd until the end of the request
    }
    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->flush();
        $this->handler->close();
    }
    /**
     * Clears the buffer without flushing any messages down to the wrapped handler.
     */
    public function clear(): void
    {
        $this->buffer_size = 0;
        $this->buffer = [];
    }
    public function reset(): void
    {
        $this->flush();
        parent::reset();
        $this->reset_processors();
        if ($this->handler instanceof Resettable_Interface) {
            $this->handler->reset();
        }
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
        throw new \UnexpectedValueException('The nested handler of type ' . $this->handler::class . ' does not support formatters.');
    }
    /**
     * @inheritDoc
     */
    public function get_formatter(): Formatter_Interface
    {
        if ($this->handler instanceof Formattable_Handler_Interface) {
            return $this->handler->get_formatter();
        }
        throw new \UnexpectedValueException('The nested handler of type ' . $this->handler::class . ' does not support formatters.');
    }
    public function set_handler(Handler_Interface $handler): void
    {
        $this->handler = $handler;
    }
}