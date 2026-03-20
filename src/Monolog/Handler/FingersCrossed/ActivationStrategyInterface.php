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

use Monolog\Log_Record;
/**
 * Interface for activation strategies for the FingersCrossedHandler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface Activation_Strategy_Interface
{
    /**
     * Returns whether the given record activates the handler.
     */
    public function is_handler_activated(Log_Record $record): bool;
}