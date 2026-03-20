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

use Aws\Sqs\Sqs_Client;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Writes to any sqs queue.
 *
 * @author Martijn van Calker <git@amvc.nl>
 */
class Sqs_Handler extends Abstract_Processing_Handler
{
    /** 256 KB in bytes - maximum message size in SQS */
    protected const MAX_MESSAGE_SIZE = 262144;
    /** 100 KB in bytes - head message size for new error log */
    protected const HEAD_MESSAGE_SIZE = 102400;
    private readonly Sqs_Client $client;
    public function __construct(Sqs_Client $sqs_client, private readonly string $queue_url, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->client = $sqs_client;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        if (!isset($record->formatted) || 'string' !== \gettype($record->formatted)) {
            throw new \InvalidArgumentException('SqsHandler accepts only formatted records as a string' . Utils::get_record_message_for_exception($record));
        }
        $message_body = $record->formatted;
        if (\strlen($message_body) >= static::MAX_MESSAGE_SIZE) {
            $message_body = Utils::substr($message_body, 0, static::HEAD_MESSAGE_SIZE);
        }
        $this->client->send_message(['QueueUrl' => $this->queue_url, 'MessageBody' => $message_body]);
    }
}