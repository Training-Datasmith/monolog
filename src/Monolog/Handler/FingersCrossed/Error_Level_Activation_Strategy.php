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
namespace Monolog\Handler\Fingers_Crossed;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Psr\Log\Log_Level;
/**
 * Error level based activation strategy.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Error_Level_Activation_Strategy implements Activation_Strategy_Interface
{
    private readonly Level $action_level;
    /**
     * @param int|string|Level $actionLevel Level or name or value
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $actionLevel
     */
    public function __construct(int|string|Level $action_level)
    {
        $this->action_level = Logger::to_monolog_level($action_level);
    }
    public function is_handler_activated(Log_Record $record): bool
    {
        return $record->level->value >= $this->action_level->value;
    }
}