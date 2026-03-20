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
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * IFTTTHandler uses cURL to trigger IFTTT Maker actions
 *
 * Register a secret key and trigger/event name at https://ifttt.com/maker
 *
 * value1 will be the channel from monolog's Logger constructor,
 * value2 will be the level name (ERROR, WARNING, ..)
 * value3 will be the log record's message
 *
 * @author Nehal Patel <nehal@nehalpatel.me>
 */
class Ifttt_Handler extends Abstract_Processing_Handler
{
    /**
     * @param string $eventName The name of the IFTTT Maker event that should be triggered
     * @param string $secretKey A valid IFTTT secret key
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(private readonly string $event_name, private readonly string $secret_key, int|string|Level $level = Level::Error, bool $bubble = true)
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is needed to use the IFTTTHandler');
        }
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    public function write(Log_Record $record): void
    {
        $post_data = ['value1' => $record->channel, 'value2' => $record['level_name'], 'value3' => $record->message];
        $post_string = Utils::json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://maker.ifttt.com/trigger/' . $this->event_name . '/with/key/' . $this->secret_key);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        Curl\Util::execute($ch);
    }
}