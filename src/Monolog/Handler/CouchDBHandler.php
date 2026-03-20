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
use Monolog\Formatter\Json_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * CouchDB handler
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @phpstan-type Options array{
 *     host: string,
 *     port: int,
 *     dbname: string,
 *     username: string|null,
 *     password: string|null
 * }
 * @phpstan-type InputOptions array{
 *     host?: string,
 *     port?: int,
 *     dbname?: string,
 *     username?: string|null,
 *     password?: string|null
 * }
 */
class Couch_Db_Handler extends Abstract_Processing_Handler
{
    /**
     * @var mixed[]
     * @phpstan-var Options
     */
    private array $options;
    /**
     * @param mixed[] $options
     *
     * @phpstan-param InputOptions $options
     */
    public function __construct(array $options = [], int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        $this->options = array_merge(['host' => 'localhost', 'port' => 5984, 'dbname' => 'logger', 'username' => null, 'password' => null], $options);
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $basic_auth = null;
        if (null !== $this->options['username'] && null !== $this->options['password']) {
            $basic_auth = sprintf('%s:%s@', $this->options['username'], $this->options['password']);
        }
        $url = 'http://' . $basic_auth . $this->options['host'] . ':' . $this->options['port'] . '/' . $this->options['dbname'];
        $context = stream_context_create(['http' => ['method' => 'POST', 'content' => $record->formatted, 'ignore_errors' => true, 'max_redirects' => 0, 'header' => 'Content-type: application/json']]);
        if (false === @file_get_contents($url, false, $context)) {
            // Strip credentials from the URL before including it in the exception
            // message to avoid leaking user:pass into logs or error output.
            $safe_url = 'http://' . $this->options['host'] . ':' . $this->options['port'] . '/' . $this->options['dbname'];
            throw new \RuntimeException(sprintf('Could not connect to %s', $safe_url));
        }
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Json_Formatter(Json_Formatter::BATCH_MODE_JSON, false);
    }
}