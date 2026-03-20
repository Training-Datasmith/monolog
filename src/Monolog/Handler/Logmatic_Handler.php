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
use Monolog\Formatter\Logmatic_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * @author Julien Breux <julien.breux@gmail.com>
 */
class Logmatic_Handler extends Socket_Handler
{
    /**
     * @param string $logToken Log token supplied by Logmatic.
     * @param string $hostname Host name supplied by Logmatic.
     * @param string $appName  Application name supplied by Logmatic.
     * @param bool   $useSSL   Whether or not SSL encryption should be used.
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(private readonly string $log_token, private readonly string $hostname = '', private readonly string $app_name = '', bool $use_ssl = true, $level = Level::Debug, bool $bubble = true, bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if ($use_ssl && !\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP extension is required to use SSL encrypted connection for LogmaticHandler');
        }
        $endpoint = $use_ssl ? 'ssl://api.logmatic.io:10515' : 'api.logmatic.io:10514';
        $endpoint .= '/v1/';
        parent::__construct($endpoint, $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
    }
    /**
     * @inheritDoc
     */
    protected function generate_data_stream(Log_Record $record): string
    {
        return $this->log_token . ' ' . $record->formatted;
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        $formatter = new Logmatic_Formatter();
        if ($this->hostname !== '') {
            $formatter->set_hostname($this->hostname);
        }
        if ($this->app_name !== '') {
            $formatter->set_app_name($this->app_name);
        }
        return $formatter;
    }
}