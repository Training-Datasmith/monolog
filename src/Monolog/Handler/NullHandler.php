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

use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Psr\Log\Log_Level;
/**
 * Blackhole
 *
 * Any record it can handle will be thrown away. This can be used
 * to put on top of an existing stack to override it temporarily.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Null_Handler extends Handler
{
    private readonly Level $level;
    /**
     * @param string|int|Level $level The minimum logging level at which this handler will be triggered
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function __construct(string|int|Level $level = Level::Debug)
    {
        $this->level = Logger::to_monolog_level($level);
    }
    /**
     * @inheritDoc
     */
    public function is_handling(Log_Record $record): bool
    {
        return $record->level->value >= $this->level->value;
    }
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        return $record->level->value >= $this->level->value;
    }
}