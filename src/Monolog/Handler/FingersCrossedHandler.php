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

use Closure;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Handler\Fingers_Crossed\Activation_Strategy_Interface;
use Monolog\Handler\Fingers_Crossed\Error_Level_Activation_Strategy;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Monolog\Resettable_Interface;
use Psr\Log\Log_Level;
/**
 * Buffers all records until a certain level is reached
 *
 * The advantage of this approach is that you don't get any clutter in your log files.
 * Only requests which actually trigger an error (or whatever your actionLevel is) will be
 * in the logs, but they will contain all records, not only those above the level threshold.
 *
 * You can then have a passthruLevel as well which means that at the end of the request,
 * even if it did not get activated, it will still send through log records of e.g. at least a
 * warning level.
 *
 * You can find the various activation strategies in the
 * Monolog\Handler\FingersCrossed\ namespace.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Fingers_Crossed_Handler extends Handler implements Processable_Handler_Interface, Resettable_Interface, Formattable_Handler_Interface
{
    use Processable_Handler_Trait;
    protected Activation_Strategy_Interface $activation_strategy;
    protected bool $buffering = true;
    /** @var LogRecord[] */
    protected array $buffer = [];
    protected Level|null $passthru_level = null;
    /**
     * @phpstan-param (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface $handler
     *
     * @param Closure|HandlerInterface          $handler            Handler or factory Closure($record|null, $fingersCrossedHandler).
     * @param int|string|Level|LogLevel::*|null $activationStrategy Strategy which determines when this handler takes action, or a level name/value at which the handler is activated
     * @param int                               $bufferSize         How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
     * @param bool                              $bubble             Whether the messages that are handled can bubble up the stack or not
     * @param bool                              $stopBuffering      Whether the handler should stop buffering after being triggered (default true)
     * @param int|string|Level|LogLevel::*|null $passthruLevel      Minimum level to always flush to handler on close, even if strategy not triggered
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*|ActivationStrategyInterface|null $activationStrategy
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*|null $passthruLevel
     */
    public function __construct(
        /**
         * Handler or factory Closure($record, $this)
         *
         * @phpstan-var (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface
         */
        protected Closure|Handler_Interface $handler,
        int|string|Level|Activation_Strategy_Interface|null $activation_strategy = null,
        protected int $buffer_size = 0,
        protected bool $bubble = true,
        protected bool $stop_buffering = true,
        int|string|Level|null $passthru_level = null
    )
    {
        if (null === $activation_strategy) {
            $activation_strategy = new Error_Level_Activation_Strategy(Level::Warning);
        }
        // convert simple int activationStrategy to an object
        if (!$activation_strategy instanceof Activation_Strategy_Interface) {
            $activation_strategy = new Error_Level_Activation_Strategy($activation_strategy);
        }
        $this->activation_strategy = $activation_strategy;
        if ($passthru_level !== null) {
            $this->passthru_level = Logger::to_monolog_level($passthru_level);
        }
    }
    /**
     * @inheritDoc
     */
    public function is_handling(Log_Record $record): bool
    {
        return true;
    }
    /**
     * Manually activate this logger regardless of the activation strategy
     */
    public function activate(): void
    {
        if ($this->stop_buffering) {
            $this->buffering = false;
        }
        $this->get_handler(end($this->buffer) ?: null)->handle_batch($this->buffer);
        $this->buffer = [];
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        if ($this->buffering) {
            $this->buffer[] = $record;
            if ($this->buffer_size > 0 && \count($this->buffer) > $this->buffer_size) {
                array_shift($this->buffer);
            }
            if ($this->activation_strategy->is_handler_activated($record)) {
                $this->activate();
            }
        } else {
            $this->get_handler($record)->handle($record);
        }
        return false === $this->bubble;
    }
    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->flush_buffer();
        $this->get_handler()->close();
    }
    public function reset(): void
    {
        $this->flush_buffer();
        $this->reset_processors();
        if ($this->get_handler() instanceof Resettable_Interface) {
            $this->get_handler()->reset();
        }
    }
    /**
     * Clears the buffer without flushing any messages down to the wrapped handler.
     *
     * It also resets the handler to its initial buffering state.
     */
    public function clear(): void
    {
        $this->buffer = [];
        $this->reset();
    }
    /**
     * Resets the state of the handler. Stops forwarding records to the wrapped handler.
     */
    private function flush_buffer(): void
    {
        if (null !== $this->passthru_level) {
            $passthru_level = $this->passthru_level;
            $this->buffer = array_filter($this->buffer, static fn(\Monolog\Log_Record $record) => $passthru_level->includes($record->level));
            if (\count($this->buffer) > 0) {
                $this->get_handler(end($this->buffer))->handle_batch($this->buffer);
            }
        }
        $this->buffer = [];
        $this->buffering = true;
    }
    /**
     * Return the nested handler
     *
     * If the handler was provided as a factory, this will trigger the handler's instantiation.
     */
    public function get_handler(Log_Record|null $record = null): Handler_Interface
    {
        if (!$this->handler instanceof Handler_Interface) {
            $handler = ($this->handler)($record, $this);
            if (!$handler instanceof Handler_Interface) {
                throw new \RuntimeException('The factory Closure should return a HandlerInterface');
            }
            $this->handler = $handler;
        }
        return $this->handler;
    }
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        $handler = $this->get_handler();
        if ($handler instanceof Formattable_Handler_Interface) {
            $handler->set_formatter($formatter);
            return $this;
        }
        throw new \UnexpectedValueException('The nested handler of type ' . $handler::class . ' does not support formatters.');
    }
    /**
     * @inheritDoc
     */
    public function get_formatter(): Formatter_Interface
    {
        $handler = $this->get_handler();
        if ($handler instanceof Formattable_Handler_Interface) {
            return $handler->get_formatter();
        }
        throw new \UnexpectedValueException('The nested handler of type ' . $handler::class . ' does not support formatters.');
    }
}