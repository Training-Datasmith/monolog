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
 * Inspired on LogEntriesHandler.
 *
 * @author Robert Kaufmann III <rok3@rok3.me>
 * @author Gabriel Machado <gabriel.ms1@hotmail.com>
 */
class Insight_Ops_Handler extends Socket_Handler
{
    /**
     * @param string $logToken Log token supplied by InsightOps
     * @param string $region Region where InsightOps account is hosted. Could be 'us' or 'eu'.
     * @param bool   $useSSL Whether or not SSL encryption should be used
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(protected string $log_token, string $region = 'us', bool $use_ssl = true, $level = Level::Debug, bool $bubble = true, bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if ($use_ssl && !\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP plugin is required to use SSL encrypted connection for InsightOpsHandler');
        }
        $endpoint = $use_ssl ? 'ssl://' . $region . '.data.logs.insight.rapid7.com:443' : $region . '.data.logs.insight.rapid7.com:80';
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