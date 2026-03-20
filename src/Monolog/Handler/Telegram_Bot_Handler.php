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
use RuntimeException;
/**
 * Handler sends logs to Telegram using Telegram Bot API.
 *
 * How to use:
 *  1) Create a Telegram bot with https://telegram.me/BotFather;
 *  2) Create a Telegram channel or a group where logs will be recorded;
 *  3) Add the created bot from step 1 to the created channel/group from step 2.
 *
 * In order to create an instance of TelegramBotHandler use
 *  1. The Telegram bot API key from step 1
 *  2. The channel name with the `@` prefix if you created a public channel (e.g. `@my_public_channel`),
 *     or the channel ID with the `-100` prefix if you created a private channel (e.g. `-1001234567890`),
 *     or the group ID from step 2 (e.g. `-1234567890`).
 *
 * @link https://core.telegram.org/bots/api
 *
 * @author Mazur Alexandr <alexandrmazur96@gmail.com>
 */
class Telegram_Bot_Handler extends Abstract_Processing_Handler
{
    private const BOT_API = 'https://api.telegram.org/bot';
    /**
     * The available values of parseMode according to the Telegram api documentation
     */
    private const AVAILABLE_PARSE_MODES = ['HTML', 'MarkdownV2', 'Markdown'];
    /**
     * The maximum number of characters allowed in a message according to the Telegram api documentation
     */
    private const MAX_MESSAGE_LENGTH = 4096;
    /**
     * The kind of formatting that is used for the message.
     * See available options at https://core.telegram.org/bots/api#formatting-options
     * or in AVAILABLE_PARSE_MODES
     */
    private string|null $parse_mode;
    /**
     * Disables link previews for links in the message.
     */
    private bool|null $disable_web_page_preview;
    /**
     * Sends the message silently. Users will receive a notification with no sound.
     */
    private bool|null $disable_notification;
    /**
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     */
    private bool $split_long_messages;
    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     */
    private bool $delay_between_messages;
    /**
     * Telegram message thread id, unique identifier for the target message thread (topic) of the forum; for forum supergroups only
     * See how to get the `message_thread_id` https://stackoverflow.com/a/75178418
     */
    private int|null $topic;
    /**
     * @param  string                    $apiKey               Telegram bot access token provided by BotFather
     * @param  string                    $channel              Telegram channel name
     * @param  bool                      $splitLongMessages    Split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages
     * @param  bool                      $delayBetweenMessages Adds delay between sending a split message according to Telegram API
     * @param  int                       $topic                Telegram message thread id, unique identifier for the target message thread (topic) of the forum
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(private readonly string $api_key, private readonly string $channel, int|string|\Monolog\Level $level = Level::Debug, bool $bubble = true, ?string $parse_mode = null, ?bool $disable_web_page_preview = null, ?bool $disable_notification = null, bool $split_long_messages = false, bool $delay_between_messages = false, ?int $topic = null)
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is needed to use the TelegramBotHandler');
        }
        parent::__construct($level, $bubble);
        $this->set_parse_mode($parse_mode);
        $this->disable_web_page_preview($disable_web_page_preview);
        $this->disable_notification($disable_notification);
        $this->split_long_messages($split_long_messages);
        $this->delay_between_messages($delay_between_messages);
        $this->set_topic($topic);
    }
    /**
     * @return $this
     */
    public function set_parse_mode(string|null $parse_mode = null): self
    {
        if ($parse_mode !== null && !\in_array($parse_mode, self::AVAILABLE_PARSE_MODES, true)) {
            throw new \InvalidArgumentException('Unknown parseMode, use one of these: ' . implode(', ', self::AVAILABLE_PARSE_MODES) . '.');
        }
        $this->parse_mode = $parse_mode;
        return $this;
    }
    /**
     * @return $this
     */
    public function disable_web_page_preview(bool|null $disable_web_page_preview = null): self
    {
        $this->disable_web_page_preview = $disable_web_page_preview;
        return $this;
    }
    /**
     * @return $this
     */
    public function disable_notification(bool|null $disable_notification = null): self
    {
        $this->disable_notification = $disable_notification;
        return $this;
    }
    /**
     * True - split a message longer than MAX_MESSAGE_LENGTH into parts and send in multiple messages.
     * False - truncates a message that is too long.
     *
     * @return $this
     */
    public function split_long_messages(bool $split_long_messages = false): self
    {
        $this->split_long_messages = $split_long_messages;
        return $this;
    }
    /**
     * Adds 1-second delay between sending a split message (according to Telegram API to avoid 429 Too Many Requests).
     *
     * @return $this
     */
    public function delay_between_messages(bool $delay_between_messages = false): self
    {
        $this->delay_between_messages = $delay_between_messages;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_topic(?int $topic = null): self
    {
        $this->topic = $topic;
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        $messages = [];
        foreach ($records as $record) {
            if (!$this->is_handling($record)) {
                continue;
            }
            if (\count($this->processors) > 0) {
                $record = $this->process_record($record);
            }
            $messages[] = $record;
        }
        if (\count($messages) > 0) {
            $this->send((string) $this->get_formatter()->format_batch($messages));
        }
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->send($record->formatted);
    }
    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
     */
    protected function send(string $message): void
    {
        $messages = $this->handle_message_length($message);
        foreach ($messages as $key => $msg) {
            if ($this->delay_between_messages && $key > 0) {
                sleep(1);
            }
            $this->send_curl($msg);
        }
    }
    protected function send_curl(string $message): void
    {
        if ('' === trim($message)) {
            return;
        }
        $ch = curl_init();
        $url = self::BOT_API . $this->api_key . '/SendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $params = ['text' => $message, 'chat_id' => $this->channel, 'parse_mode' => $this->parse_mode, 'disable_web_page_preview' => $this->disable_web_page_preview, 'disable_notification' => $this->disable_notification];
        if ($this->topic !== null) {
            $params['message_thread_id'] = $this->topic;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $result = Curl\Util::execute($ch);
        if (!\is_string($result)) {
            throw new RuntimeException('Telegram API error. Description: No response');
        }
        $result = json_decode($result, true);
        if ($result['ok'] === false) {
            throw new RuntimeException('Telegram API error. Description: ' . $result['description']);
        }
    }
    /**
     * Handle a message that is too long: truncates or splits into several
     * @return string[]
     */
    private function handle_message_length(string $message): array
    {
        $truncated_marker = ' (…truncated)';
        if (!$this->split_long_messages && \strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return [Utils::substr($message, 0, self::MAX_MESSAGE_LENGTH - \strlen($truncated_marker)) . $truncated_marker];
        }
        return str_split($message, self::MAX_MESSAGE_LENGTH);
    }
}