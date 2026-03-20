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

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Line_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Sends logs to Fleep.io using Webhook integrations
 *
 * You'll need a Fleep.io account to use this handler.
 *
 * @see https://fleep.io/integrations/webhooks/ Fleep Webhooks Documentation
 * @author Ando Roots <ando@sqroot.eu>
 */
class Fleep_Hook_Handler extends Socket_Handler
{
    protected const FLEEP_HOST = 'fleep.io';
    protected const FLEEP_HOOK_URI = '/hook/';
    /**
     * Construct a new Fleep.io Handler.
     *
     * For instructions on how to create a new web hook in your conversations
     * see https://fleep.io/integrations/webhooks/
     *
     * @param  string                    $token Webhook token
     * @throws MissingExtensionException if OpenSSL is missing
     */
    public function __construct(protected string $token, $level = Level::Debug, bool $bubble = true, bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if (!\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP extension is required to use the FleepHookHandler');
        }
        $connection_string = 'ssl://' . static::FLEEP_HOST . ':443';
        parent::__construct($connection_string, $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
    }
    /**
     * Returns the default formatter to use with this handler
     *
     * Overloaded to remove empty context and extra arrays from the end of the log message.
     *
     * @return LineFormatter
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Line_Formatter(null, null, true, true);
    }
    /**
     * Handles a log record
     */
    public function write(Log_Record $record): void
    {
        parent::write($record);
        $this->close_socket();
    }
    /**
     * @inheritDoc
     */
    protected function generate_data_stream(Log_Record $record): string
    {
        $content = $this->build_content($record);
        return $this->build_header($content) . $content;
    }
    /**
     * Builds the header of the API Call
     */
    private function build_header(string $content): string
    {
        $header = 'POST ' . static::FLEEP_HOOK_URI . $this->token . " HTTP/1.1\r\n";
        $header .= 'Host: ' . static::FLEEP_HOST . "\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= 'Content-Length: ' . \strlen($content) . "\r\n";
        return $header . "\r\n";
    }
    /**
     * Builds the body of API call
     */
    private function build_content(Log_Record $record): string
    {
        $data_array = ['message' => $record->formatted];
        return http_build_query($data_array);
    }
}