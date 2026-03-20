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
namespace Monolog\Formatter;

use Monolog\Log_Record;
/**
 * Encodes message information into JSON in a format compatible with Logmatic.
 *
 * @author Julien Breux <julien.breux@gmail.com>
 */
class Logmatic_Formatter extends Json_Formatter
{
    protected const MARKERS = ['sourcecode', 'php'];
    protected string $hostname = '';
    protected string $app_name = '';
    /**
     * @return $this
     */
    public function set_hostname(string $hostname): self
    {
        $this->hostname = $hostname;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_app_name(string $app_name): self
    {
        $this->app_name = $app_name;
        return $this;
    }
    /**
     * Appends the 'hostname' and 'appname' parameter for indexing by Logmatic.
     *
     * @see http://doc.logmatic.io/docs/basics-to-send-data
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    public function normalize_record(Log_Record $record): array
    {
        $record = parent::normalize_record($record);
        if ($this->hostname !== '') {
            $record['hostname'] = $this->hostname;
        }
        if ($this->app_name !== '') {
            $record['appname'] = $this->app_name;
        }
        $record['@marker'] = static::MARKERS;
        return $record;
    }
}