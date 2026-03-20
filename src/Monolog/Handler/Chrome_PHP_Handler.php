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

use Monolog\Formatter\Chrome_Php_Formatter;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Json_Serializable_Date_Time_Immutable;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Handler sending logs to the ChromePHP extension (http://www.chromephp.com/)
 *
 * This also works out of the box with Firefox 43+
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Chrome_Php_Handler extends Abstract_Processing_Handler
{
    use Web_Request_Recognizer_Trait;
    /**
     * Version of the extension
     */
    protected const VERSION = '4.0';
    /**
     * Header name
     */
    protected const HEADER_NAME = 'X-ChromeLogger-Data';
    /**
     * Regular expression to detect supported browsers (matches any Chrome, or Firefox 43+)
     */
    protected const USER_AGENT_REGEX = '{\b(?:Chrome/\d+(?:\.\d+)*|HeadlessChrome|Firefox/(?:4[3-9]|[5-9]\d|\d{3,})(?:\.\d)*)\b}';
    protected static bool $initialized = false;
    /**
     * Tracks whether we sent too much data
     *
     * Chrome limits the headers to 4KB, so when we sent 3KB we stop sending
     */
    protected static bool $overflowed = false;
    /** @var mixed[] */
    protected static array $json = ['version' => self::VERSION, 'columns' => ['label', 'log', 'backtrace', 'type'], 'rows' => []];
    protected static bool $send_headers = true;
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        if (!$this->is_web_request()) {
            return;
        }
        $messages = [];
        foreach ($records as $record) {
            if ($record->level < $this->level) {
                continue;
            }
            $message = $this->process_record($record);
            $messages[] = $message;
        }
        if (\count($messages) > 0) {
            $messages = $this->get_formatter()->format_batch($messages);
            self::$json['rows'] = array_merge(self::$json['rows'], $messages);
            $this->send();
        }
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Chrome_Php_Formatter();
    }
    /**
     * Creates & sends header for a record
     *
     * @see sendHeader()
     * @see send()
     */
    protected function write(Log_Record $record): void
    {
        if (!$this->is_web_request()) {
            return;
        }
        self::$json['rows'][] = $record->formatted;
        $this->send();
    }
    /**
     * Sends the log header
     *
     * @see sendHeader()
     */
    protected function send(): void
    {
        if (self::$overflowed || !self::$send_headers) {
            return;
        }
        if (!self::$initialized) {
            self::$initialized = true;
            self::$send_headers = $this->headers_accepted();
            if (!self::$send_headers) {
                return;
            }
            self::$json['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        $json = Utils::json_encode(self::$json, Utils::DEFAULT_JSON_FLAGS & ~JSON_UNESCAPED_UNICODE, true);
        $data = base64_encode($json);
        if (\strlen($data) > 3 * 1024) {
            self::$overflowed = true;
            $record = new Log_Record(message: 'Incomplete logs, chrome header size limit reached', level: Level::Warning, channel: 'monolog', datetime: new Json_Serializable_Date_Time_Immutable(true));
            self::$json['rows'][\count(self::$json['rows']) - 1] = $this->get_formatter()->format($record);
            $json = Utils::json_encode(self::$json, Utils::DEFAULT_JSON_FLAGS & ~JSON_UNESCAPED_UNICODE, true);
            $data = base64_encode($json);
        }
        if (trim($data) !== '') {
            $this->send_header(static::HEADER_NAME, $data);
        }
    }
    /**
     * Send header string to the client
     */
    protected function send_header(string $header, string $content): void
    {
        if (!headers_sent() && self::$send_headers) {
            header(sprintf('%s: %s', $header, $content));
        }
    }
    /**
     * Verifies if the headers are accepted by the current user agent
     */
    protected function headers_accepted(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        return preg_match(static::USER_AGENT_REGEX, (string) $_SERVER['HTTP_USER_AGENT']) === 1;
    }
}