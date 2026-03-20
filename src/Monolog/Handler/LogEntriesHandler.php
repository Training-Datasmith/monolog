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
/**
 * @author Robert Kaufmann III <rok3@rok3.me>
 */
class Log_Entries_Handler extends Socket_Handler
{
    /**
     * @param string $logToken Log token supplied by LogEntries
     * @param bool   $useSSL Whether or not SSL encryption should be used.
     * @param string $host   Custom hostname to send the data to if needed
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(protected string $log_token, bool $use_ssl = true, $level = Level::Debug, bool $bubble = true, string $host = 'data.logentries.com', bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if ($use_ssl && !\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP plugin is required to use SSL encrypted connection for LogEntriesHandler');
        }
        $endpoint = $use_ssl ? 'ssl://' . $host . ':443' : $host . ':80';
        parent::__construct($endpoint, $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
    }
    /**
     * @inheritDoc
     */
    protected function generate_data_stream(Log_Record $record): string
    {
        return $this->log_token . ' ' . $record->formatted;
    }
}