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
use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Monolog\Resettable_Interface;
use Psr\Log\Log_Level;
/**
 * Simple handler wrapper that filters records based on a list of levels
 *
 * It can be configured with an exact list of levels to allow, or a min/max level.
 *
 * @author Hennadiy Verkh
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Filter_Handler extends Handler implements Processable_Handler_Interface, Resettable_Interface, Formattable_Handler_Interface
{
    use Processable_Handler_Trait;
    /**
     * Minimum level for logs that are passed to handler
     *
     * @var bool[] Map of Level value => true
     * @phpstan-var array<value-of<Level::VALUES>, true>
     */
    protected array $accepted_levels;
    /**
     * @phpstan-param (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface $handler
     *
     * @param Closure|HandlerInterface                             $handler        Handler or factory Closure($record|null, $filterHandler).
     * @param int|string|Level|array<int|string|Level|LogLevel::*> $minLevelOrList A list of levels to accept or a minimum level if maxLevel is provided
     * @param int|string|Level|LogLevel::*                         $maxLevel       Maximum level to accept, only used if $minLevelOrList is not an array
     * @param bool                                                 $bubble         Whether the messages that are handled can bubble up the stack or not
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*|array<value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*> $minLevelOrList
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $maxLevel
     */
    public function __construct(
        /**
         * Handler or factory Closure($record, $this)
         *
         * @phpstan-var (Closure(LogRecord|null, HandlerInterface): HandlerInterface)|HandlerInterface
         */
        protected Closure|Handler_Interface $handler,
        int|string|Level|array $min_level_or_list = Level::Debug,
        int|string|Level $max_level = Level::Emergency,
        protected bool $bubble = true
    )
    {
        $this->set_accepted_levels($min_level_or_list, $max_level);
    }
    /**
     * @phpstan-return list<Level> List of levels
     */
    public function get_accepted_levels(): array
    {
        return array_map(Level::from(...), array_keys($this->accepted_levels));
    }
    /**
     * @param  int|string|Level|LogLevel::*|array<int|string|Level|LogLevel::*> $minLevelOrList A list of levels to accept or a minimum level or level name if maxLevel is provided
     * @param  int|string|Level|LogLevel::*                                     $maxLevel       Maximum level or level name to accept, only used if $minLevelOrList is not an array
     * @return $this
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*|array<value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*> $minLevelOrList
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $maxLevel
     */
    public function set_accepted_levels(int|string|Level|array $min_level_or_list = Level::Debug, int|string|Level $max_level = Level::Emergency): self
    {
        if (\is_array($min_level_or_list)) {
            $accepted_levels = array_map(Logger::to_monolog_level(...), $min_level_or_list);
        } else {
            $min_level_or_list = Logger::to_monolog_level($min_level_or_list);
            $max_level = Logger::to_monolog_level($max_level);
            $accepted_levels = array_values(array_filter(Level::cases(), fn(Level $level): bool => $level->value >= $min_level_or_list->value && $level->value <= $max_level->value));
        }
        $this->accepted_levels = [];
        foreach ($accepted_levels as $level) {
            $this->accepted_levels[$level->value] = true;
        }
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function is_handling(Log_Record $record): bool
    {
        return isset($this->accepted_levels[$record->level->value]);
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        $this->get_handler($record)->handle($record);
        return false === $this->bubble;
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        $filtered = [];
        foreach ($records as $record) {
            if ($this->is_handling($record)) {
                $filtered[] = $record;
            }
        }
        if (\count($filtered) > 0) {
            $this->get_handler($filtered[\count($filtered) - 1])->handle_batch($filtered);
        }
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
    public function reset(): void
    {
        $this->reset_processors();
        if ($this->get_handler() instanceof Resettable_Interface) {
            $this->get_handler()->reset();
        }
    }
}