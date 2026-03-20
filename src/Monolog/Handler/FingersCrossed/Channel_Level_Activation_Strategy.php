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
 * Channel and Error level based monolog activation strategy. Allows to trigger activation
 * based on level per channel. e.g. trigger activation on level 'ERROR' by default, except
 * for records of the 'sql' channel; those should trigger activation on level 'WARN'.
 *
 * Example:
 *
 * <code>
 *   $activationStrategy = new ChannelLevelActivationStrategy(
 *       Level::Critical,
 *       array(
 *           'request' => Level::Alert,
 *           'sensitive' => Level::Error,
 *       )
 *   );
 *   $handler = new FingersCrossedHandler(new StreamHandler('php://stderr'), $activationStrategy);
 * </code>
 *
 * @author Mike Meessen <netmikey@gmail.com>
 */
class Channel_Level_Activation_Strategy implements Activation_Strategy_Interface
{
    private readonly Level $default_action_level;
    /**
     * @var array<string, Level>
     */
    private array $channel_to_action_level;
    /**
     * @param int|string|Level|LogLevel::*                $defaultActionLevel   The default action level to be used if the record's category doesn't match any
     * @param array<string, int|string|Level|LogLevel::*> $channelToActionLevel An array that maps channel names to action levels.
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $defaultActionLevel
     * @phpstan-param array<string, value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::*> $channelToActionLevel
     */
    public function __construct(int|string|Level $default_action_level, array $channel_to_action_level = [])
    {
        $this->default_action_level = Logger::to_monolog_level($default_action_level);
        $this->channel_to_action_level = array_map(Logger::to_monolog_level(...), $channel_to_action_level);
    }
    public function is_handler_activated(Log_Record $record): bool
    {
        if (isset($this->channel_to_action_level[$record->channel])) {
            return $record->level->value >= $this->channel_to_action_level[$record->channel]->value;
        }
        return $record->level->value >= $this->default_action_level->value;
    }
}