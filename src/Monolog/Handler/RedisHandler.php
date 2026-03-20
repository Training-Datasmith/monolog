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
use Predis\Client as Predis;
use Redis;
/**
 * Logs to a Redis key using rpush
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $redis = new RedisHandler(new Predis\Client("tcp://localhost:6379"), "logs");
 *   $log->pushHandler($redis);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class Redis_Handler extends Abstract_Processing_Handler
{
    /**
     * @param Predis<Predis>|Redis $redisClient The redis instance
     * @param string $redisKey The key name to push records to
     * @param int                  $capSize Number of entries to limit list size to, 0 = unlimited
     */
    public function __construct(private readonly Predis|Redis $redis_client, private readonly string $redis_key, int|string|Level $level = Level::Debug, bool $bubble = true, protected int $cap_size = 0)
    {
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        if ($this->cap_size > 0) {
            $this->write_capped($record);
        } else {
            $this->redis_client->rpush($this->redis_key, $record->formatted);
        }
    }
    /**
     * Write and cap the collection
     * Writes the record to the redis list and caps its
     */
    protected function write_capped(Log_Record $record): void
    {
        if ($this->redis_client instanceof Redis) {
            $mode = \defined('Redis::MULTI') ? Redis::MULTI : 1;
            $this->redis_client->multi($mode)->r_push($this->redis_key, $record->formatted)->ltrim($this->redis_key, -$this->cap_size, -1)->exec();
        } else {
            $redis_key = $this->redis_key;
            $cap_size = $this->cap_size;
            $this->redis_client->transaction(function ($tx) use ($record, $redis_key, $cap_size): void {
                $tx->rpush($redis_key, $record->formatted);
                $tx->ltrim($redis_key, -$cap_size, -1);
            });
        }
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Line_Formatter();
    }
}