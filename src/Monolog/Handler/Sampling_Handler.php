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
use Monolog\Log_Record;
/**
 * Sampling handler
 *
 * A sampled event stream can be useful for logging high frequency events in
 * a production environment where you only need an idea of what is happening
 * and are not concerned with capturing every occurrence. Since the decision to
 * handle or not handle a particular event is determined randomly, the
 * resulting sampled log is not guaranteed to contain 1/N of the events that
 * occurred in the application, but based on the Law of large numbers, it will
 * tend to be close to this ratio with a large number of attempts.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @author Kunal Mehta <legoktm@gmail.com>
 */
class Sampling_Handler extends Abstract_Handler implements Processable_Handler_Interface, Formattable_Handler_Interface
{
    use Processable_Handler_Trait;
    /**
     * @phpstan-param (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface $handler
     *
     * @param Closure|HandlerInterface $handler Handler or factory Closure($record|null, $samplingHandler).
     * @param int                      $factor  Sample factor (e.g. 10 means every ~10th record is sampled)
     */
    public function __construct(
        /**
         * Handler or factory Closure($record, $this)
         *
         * @phpstan-var (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface
         */
        protected Closure|Handler_Interface $handler,
        protected int $factor
    )
    {
        parent::__construct();
    }
    public function is_handling(Log_Record $record): bool
    {
        return $this->get_handler($record)->is_handling($record);
    }
    public function handle(Log_Record $record): bool
    {
        if ($this->is_handling($record) && mt_rand(1, $this->factor) === 1) {
            if (\count($this->processors) > 0) {
                $record = $this->process_record($record);
            }
            $this->get_handler($record)->handle($record);
        }
        return false === $this->bubble;
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