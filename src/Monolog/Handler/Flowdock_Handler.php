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

use Monolog\Formatter\Flowdock_Formatter;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Sends notifications through the Flowdock push API
 *
 * This must be configured with a FlowdockFormatter instance via setFormatter()
 *
 * Notes:
 * API token - Flowdock API token
 *
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @see https://www.flowdock.com/api/push
 * @deprecated Since 2.9.0 and 3.3.0, Flowdock was shutdown we will thus drop this handler in Monolog 4
 */
class Flowdock_Handler extends Socket_Handler
{
    /**
     * @throws MissingExtensionException if OpenSSL is missing
     */
    public function __construct(protected string $api_token, $level = Level::Debug, bool $bubble = true, bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if (!\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP extension is required to use the FlowdockHandler');
        }
        parent::__construct('ssl://api.flowdock.com:443', $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
    }
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        if (!$formatter instanceof Flowdock_Formatter) {
            throw new \InvalidArgumentException('The FlowdockHandler requires an instance of Monolog\Formatter\FlowdockFormatter to function correctly');
        }
        return parent::set_formatter($formatter);
    }
    /**
     * Gets the default formatter.
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        throw new \InvalidArgumentException('The FlowdockHandler must be configured (via setFormatter) with an instance of Monolog\Formatter\FlowdockFormatter to function correctly');
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
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
     * Builds the body of API call
     */
    private function build_content(Log_Record $record): string
    {
        return Utils::json_encode($record->formatted);
    }
    /**
     * Builds the header of the API Call
     */
    private function build_header(string $content): string
    {
        $header = 'POST /v1/messages/team_inbox/' . $this->api_token . " HTTP/1.1\r\n";
        $header .= "Host: api.flowdock.com\r\n";
        $header .= "Content-Type: application/json\r\n";
        $header .= 'Content-Length: ' . \strlen($content) . "\r\n";
        return $header . "\r\n";
    }
}