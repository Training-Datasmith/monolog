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
namespace Monolog\Formatter;

use Gelf\Message;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Serializes a log message to GELF
 * @see http://docs.graylog.org/en/latest/pages/gelf.html
 *
 * @author Matt Lehner <mlehner@gmail.com>
 */
class Gelf_Message_Formatter extends Normalizer_Formatter
{
    protected const DEFAULT_MAX_LENGTH = 32766;
    /**
     * @var string the name of the system for the Gelf log message
     */
    protected string $system_name;
    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected string $extra_prefix;
    /**
     * @var int max length per field
     */
    protected int $max_length;
    /**
     * Translates Monolog log levels to Graylog2 log priorities.
     */
    private function get_graylog2priority(Level $level): int
    {
        return match ($level) {
            Level::Debug => 7,
            Level::Info => 6,
            Level::Notice => 5,
            Level::Warning => 4,
            Level::Error => 3,
            Level::Critical => 2,
            Level::Alert => 1,
            Level::Emergency => 0,
        };
    }
    /**
     * @throws \RuntimeException
     */
    public function __construct(
        ?string $system_name = null,
        ?string $extra_prefix = null,
        /**
         * @var string a prefix for 'context' fields from the Monolog record (optional)
         */
        protected string $context_prefix = 'ctxt_',
        ?int $max_length = null
    )
    {
        if (!class_exists(Message::class)) {
            throw new \RuntimeException('Composer package graylog2/gelf-php is required to use Monolog\'s GelfMessageFormatter');
        }
        parent::__construct('U.u');
        $this->system_name = null === $system_name || $system_name === '' ? (string) gethostname() : $system_name;
        $this->extra_prefix = $extra_prefix ?? '';
        $this->max_length = $max_length ?? self::DEFAULT_MAX_LENGTH;
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): Message
    {
        $context = $extra = [];
        if (isset($record->context)) {
            /** @var array<array<mixed>|bool|float|int|string|null> $context */
            $context = parent::normalize($record->context);
        }
        if (isset($record->extra)) {
            /** @var array<array<mixed>|bool|float|int|string|null> $extra */
            $extra = parent::normalize($record->extra);
        }
        $message = new Message();
        $message->set_timestamp($record->datetime)->set_short_message($record->message)->set_host($this->system_name)->set_level($this->get_graylog2priority($record->level));
        // message length + system name length + 200 for padding / metadata
        $len = 200 + \strlen($record->message) + \strlen($this->system_name);
        if ($len > $this->max_length) {
            $message->set_short_message(Utils::substr($record->message, 0, $this->max_length));
        }
        if (isset($record->channel)) {
            $message->set_additional('facility', $record->channel);
        }
        foreach ($extra as $key => $val) {
            $key = (string) preg_replace('#[^\w.-]#', '-', (string) $key);
            $val = \is_bool($val) ? $val ? 1 : 0 : $val;
            $val = \is_scalar($val) || null === $val ? $val : $this->to_json($val);
            $len = \strlen($this->extra_prefix . $key . $val);
            if ($len > $this->max_length) {
                $message->set_additional($this->extra_prefix . $key, Utils::substr((string) $val, 0, $this->max_length));
                continue;
            }
            $message->set_additional($this->extra_prefix . $key, $val);
        }
        foreach ($context as $key => $val) {
            $key = (string) preg_replace('#[^\w.-]#', '-', (string) $key);
            $val = \is_bool($val) ? $val ? 1 : 0 : $val;
            $val = \is_scalar($val) || null === $val ? $val : $this->to_json($val);
            $len = \strlen($this->context_prefix . $key . $val);
            if ($len > $this->max_length) {
                $message->set_additional($this->context_prefix . $key, Utils::substr((string) $val, 0, $this->max_length));
                continue;
            }
            $message->set_additional($this->context_prefix . $key, $val);
        }
        if (!$message->has_additional('file') && isset($context['exception']['file'])) {
            if (1 === preg_match('/^(.+):([0-9]+)$/', (string) $context['exception']['file'], $matches)) {
                $message->set_additional('file', $matches[1]);
                $message->set_additional('line', $matches[2]);
            }
        }
        return $message;
    }
}