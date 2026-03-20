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

use Amqp_Exchange;
use Gelf\Message as GelfMessage;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Json_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
use Php_Amqp_Lib\Channel\Amqp_Channel;
use Php_Amqp_Lib\Message\Amqp_Message;
class Amqp_Handler extends Abstract_Processing_Handler
{
    protected Amqp_Exchange|Amqp_Channel $exchange;
    /** @var array<string, mixed> */
    private array $extra_attributes = [];
    protected string $exchange_name;
    /**
     * @param AMQPExchange|AMQPChannel $exchange     AMQPExchange (php AMQP ext) or PHP AMQP lib channel, ready for use
     * @param string|null              $exchangeName Optional exchange name, for AMQPChannel (PhpAmqpLib) only
     */
    public function __construct(Amqp_Exchange|Amqp_Channel $exchange, ?string $exchange_name = null, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if ($exchange instanceof Amqp_Channel) {
            $this->exchange_name = (string) $exchange_name;
        } elseif ($exchange_name !== null) {
            @trigger_error('The $exchangeName parameter can only be passed when using PhpAmqpLib, if using an AMQPExchange instance configure it beforehand', E_USER_DEPRECATED);
        }
        $this->exchange = $exchange;
        parent::__construct($level, $bubble);
    }
    /**
     * @return array<string, mixed>
     */
    public function get_extra_attributes(): array
    {
        return $this->extra_attributes;
    }
    /**
     * Configure extra attributes to pass to the AMQPExchange (if you are using the amqp extension)
     *
     * @param  array<string, mixed> $extraAttributes One of content_type, content_encoding,
     *                                               message_id, user_id, app_id, delivery_mode,
     *                                               priority, timestamp, expiration, type
     *                                               or reply_to, headers.
     * @return $this
     */
    public function set_extra_attributes(array $extra_attributes): self
    {
        $this->extra_attributes = $extra_attributes;
        return $this;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $data = $record->formatted;
        $routing_key = $this->get_routing_key($record);
        if ($data instanceof Gelf_Message) {
            $data = json_encode($data->to_array());
        }
        if ($this->exchange instanceof Amqp_Exchange) {
            $attributes = ['delivery_mode' => 2, 'content_type' => 'application/json'];
            if (\count($this->extra_attributes) > 0) {
                $attributes = array_merge($attributes, $this->extra_attributes);
            }
            $this->exchange->publish($data, $routing_key, 0, $attributes);
        } else {
            $this->exchange->basic_publish($this->create_amqp_message($data), $this->exchange_name, $routing_key);
        }
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        if ($this->exchange instanceof Amqp_Exchange) {
            parent::handle_batch($records);
            return;
        }
        foreach ($records as $record) {
            if (!$this->is_handling($record)) {
                continue;
            }
            $record = $this->process_record($record);
            $data = $this->get_formatter()->format($record);
            if ($data instanceof Gelf_Message) {
                $data = json_encode($data->to_array());
            }
            $this->exchange->batch_basic_publish($this->create_amqp_message($data), $this->exchange_name, $this->get_routing_key($record));
        }
        $this->exchange->publish_batch();
    }
    /**
     * Gets the routing key for the AMQP exchange
     */
    protected function get_routing_key(Log_Record $record): string
    {
        $routing_key = sprintf('%s.%s', $record->level->name, $record->channel);
        return strtolower($routing_key);
    }
    private function create_amqp_message(string $data): Amqp_Message
    {
        $attributes = ['delivery_mode' => 2, 'content_type' => 'application/json'];
        if (\count($this->extra_attributes) > 0) {
            $attributes = array_merge($attributes, $this->extra_attributes);
        }
        return new Amqp_Message($data, $attributes);
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Json_Formatter(Json_Formatter::BATCH_MODE_JSON, false);
    }
}