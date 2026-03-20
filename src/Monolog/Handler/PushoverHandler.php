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
use Monolog\Utils;
use Psr\Log\Log_Level;
/**
 * Sends notifications through the pushover api to mobile phones
 *
 * @author Sebastian Göttschkes <sebastian.goettschkes@googlemail.com>
 * @see    https://www.pushover.net/api
 */
class Pushover_Handler extends Socket_Handler
{
    /** @var array<int|string> */
    private readonly array $users;
    private readonly string $title;
    private string|int|null $user = null;
    private Level $high_priority_level;
    private Level $emergency_level;
    private bool $use_formatted_message = false;
    /**
     * All parameters that can be sent to Pushover
     * @see https://pushover.net/api
     * @var array<string, bool>
     */
    private array $parameter_names = ['token' => true, 'user' => true, 'message' => true, 'device' => true, 'title' => true, 'url' => true, 'url_title' => true, 'priority' => true, 'timestamp' => true, 'sound' => true, 'retry' => true, 'expire' => true, 'callback' => true];
    /**
     * Sounds the api supports by default
     * @see https://pushover.net/api#sounds
     * @var string[]
     */
    private array $sounds = ['pushover', 'bike', 'bugle', 'cashregister', 'classical', 'cosmic', 'falling', 'gamelan', 'incoming', 'intermission', 'magic', 'mechanical', 'pianobar', 'siren', 'spacealarm', 'tugboat', 'alien', 'climb', 'persistent', 'echo', 'updown', 'none'];
    /**
     * @param string       $token  Pushover api token
     * @param string|array $users  Pushover user id or array of ids the message will be sent to
     * @param string|null  $title  Title sent to the Pushover API
     * @param bool         $useSSL Whether to connect via SSL. Required when pushing messages to users that are not
     *                             the pushover.net app owner. OpenSSL is required for this option.
     * @param int          $retry  The retry parameter specifies how often (in seconds) the Pushover servers will
     *                             send the same notification to the user.
     * @param int          $expire The expire parameter specifies how many seconds your notification will continue
     *                             to be retried for (every retry seconds).
     *
     * @param int|string|Level|LogLevel::* $highPriorityLevel The minimum logging level at which this handler will start
     *                                                        sending "high priority" requests to the Pushover API
     * @param int|string|Level|LogLevel::* $emergencyLevel    The minimum logging level at which this handler will start
     *                                                        sending "emergency" requests to the Pushover API
     *
     *
     * @phpstan-param string|array<int|string>    $users
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $highPriorityLevel
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $emergencyLevel
     */
    public function __construct(private readonly string $token, $users, ?string $title = null, int|string|Level $level = Level::Critical, bool $bubble = true, bool $use_ssl = true, int|string|Level $high_priority_level = Level::Critical, int|string|Level $emergency_level = Level::Emergency, private readonly int $retry = 30, private readonly int $expire = 25200, bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        $connection_string = $use_ssl ? 'ssl://api.pushover.net:443' : 'api.pushover.net:80';
        parent::__construct($connection_string, $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
        $this->users = (array) $users;
        $this->title = $title ?? (string) gethostname();
        $this->high_priority_level = Logger::to_monolog_level($high_priority_level);
        $this->emergency_level = Logger::to_monolog_level($emergency_level);
    }
    protected function generate_data_stream(Log_Record $record): string
    {
        $content = $this->build_content($record);
        return $this->build_header($content) . $content;
    }
    private function build_content(Log_Record $record): string
    {
        // Pushover has a limit of 512 characters on title and message combined.
        $max_message_length = 512 - \strlen($this->title);
        $message = $this->use_formatted_message ? $record->formatted : $record->message;
        $message = Utils::substr($message, 0, $max_message_length);
        $timestamp = $record->datetime->get_timestamp();
        $data_array = ['token' => $this->token, 'user' => $this->user, 'message' => $message, 'title' => $this->title, 'timestamp' => $timestamp];
        if ($record->level->value >= $this->emergency_level->value) {
            $data_array['priority'] = 2;
            $data_array['retry'] = $this->retry;
            $data_array['expire'] = $this->expire;
        } elseif ($record->level->value >= $this->high_priority_level->value) {
            $data_array['priority'] = 1;
        }
        // First determine the available parameters
        $context = array_intersect_key($record->context, $this->parameter_names);
        $extra = array_intersect_key($record->extra, $this->parameter_names);
        // Least important info should be merged with subsequent info
        $data_array = array_merge($extra, $context, $data_array);
        // Only pass sounds that are supported by the API
        if (isset($data_array['sound']) && !\in_array($data_array['sound'], $this->sounds, true)) {
            unset($data_array['sound']);
        }
        return http_build_query($data_array);
    }
    private function build_header(string $content): string
    {
        $header = "POST /1/messages.json HTTP/1.1\r\n";
        $header .= "Host: api.pushover.net\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= 'Content-Length: ' . \strlen($content) . "\r\n";
        return $header . "\r\n";
    }
    protected function write(Log_Record $record): void
    {
        foreach ($this->users as $user) {
            $this->user = $user;
            parent::write($record);
            $this->close_socket();
        }
        $this->user = null;
    }
    /**
     * @param  int|string|Level|LogLevel::* $level
     * @return $this
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function set_high_priority_level(int|string|Level $level): self
    {
        $this->high_priority_level = Logger::to_monolog_level($level);
        return $this;
    }
    /**
     * @param  int|string|Level|LogLevel::* $level
     * @return $this
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function set_emergency_level(int|string|Level $level): self
    {
        $this->emergency_level = Logger::to_monolog_level($level);
        return $this;
    }
    /**
     * Use the formatted message?
     *
     * @return $this
     */
    public function use_formatted_message(bool $use_formatted_message): self
    {
        $this->use_formatted_message = $use_formatted_message;
        return $this;
    }
}